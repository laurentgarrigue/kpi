import type { MatchType, Period, PeriodDurations, BreakDurations, ScoringEventCode } from '~/types/scoring'

/**
 * Pure scoring game rules — TypeScript mirror of api2 `src/Scoring/ScoringRules.php`
 * (plan lot 1.2 / lot 3). Keep the two files in sync: the PHP side is the reference and
 * is covered by `sources/api2/tests/Scoring/scoring_rules_test.php` (62 assertions).
 *
 * Everything here is deterministic — no store, no network, no clock.
 */

/** True for any overtime period: P1, P2, … P{n} (unbounded, spec §0.6). */
export function isOvertime(period: string): period is `P${number}` {
  return /^P\d+$/.test(period)
}

/** Overtime index (P3 → 3), or null when not an overtime. */
export function overtimeIndex(period: string): number | null {
  return isOvertime(period) ? Number(period.slice(1)) : null
}

/**
 * Next period for the "advance" button (spec §7.1):
 *  - type C (classement): M1 → M2 → null (draw allowed, no overtime);
 *  - type E (élimination): M1 → M2 → P1 → P2 → … unbounded while the score is level,
 *    TB only when the competition enables it — golden goal is the rule (spec §7.5).
 * Returns null when no further period is possible.
 */
export function nextPeriodFor(
  type: MatchType,
  period: Period,
  scoreLevel: boolean,
  shootoutEnabled = false
): Period | null {
  if (period === 'M1') return 'M2'
  if (type !== 'E' || !scoreLevel) return null
  if (period === 'M2') return 'P1'
  if (isOvertime(period)) return `P${(overtimeIndex(period) ?? 0) + 1}`
  if (period === 'TB') return null
  return shootoutEnabled ? 'TB' : null
}

/** Golden goal (spec §7.5): in a type-E match, a goal during any overtime ends the match. */
export function goalEndsMatch(type: MatchType, period: string): boolean {
  return type === 'E' && isOvertime(period)
}

/** Duration in seconds of a period (every P{n} shares periodDurations.P — spec §0.6). */
export function periodDurationOf(period: Period, durations: PeriodDurations): number {
  if (isOvertime(period)) return durations.P
  return durations[period]
}

/**
 * Inter-period break duration in seconds (spec §7.5 — 3'/3'/1'), or null when no break
 * clock applies before the given period (M1, TB).
 */
export function breakDurationBefore(nextPeriod: Period, breaks: BreakDurations): number | null {
  if (nextPeriod === 'M2') return breaks.halftime
  if (nextPeriod === 'P1') return breaks.beforeOvertime
  if (isOvertime(nextPeriod)) return breaks.betweenOvertimes
  return null
}

export type CardProgressionViolation = 'unknown_card' | 'player_already_out' | 'card_not_higher'

const CARD_RANK: Record<string, number> = { V: 1, J: 2, R: 3 }

/** Cards whose sanctioned player never comes back (replaced at penalty end, spec §7.4). */
const NO_RETURN_CARDS: ScoringEventCode[] = ['R', 'D']

/**
 * Card progression rule (spec §7.4, 2027 rules): order V → J → R, a player cannot receive
 * a card identical or lower than his previous one, but a first J or R is legal. The black
 * ejection card (D) is applicable anytime; after an R or a D the player's match is over.
 *
 * Returns true when allowed, else a violation key (i18n-able).
 */
export function validateCardProgression(
  previousCards: ScoringEventCode[],
  newCard: string
): true | CardProgressionViolation {
  if (!(newCard in CARD_RANK) && newCard !== 'D') return 'unknown_card'
  if (previousCards.some(c => NO_RETURN_CARDS.includes(c))) return 'player_already_out'
  if (newCard === 'D') return true

  const maxPrevious = previousCards.reduce((max, c) => Math.max(max, CARD_RANK[c] ?? 0), 0)
  return CARD_RANK[newCard] > maxPrevious ? true : 'card_not_higher'
}

/** Whether the sanctioned player himself returns at the end of the penalty (spec §0.9). */
export function playerReturnsAfterPenalty(cardCode: ScoringEventCode): boolean {
  return !NO_RETURN_CARDS.includes(cardCode)
}

/**
 * §7.4 (correction 2026-07-29): the black ejection card (D) carries NO 2-minute penalty —
 * immediate and definitive exclusion, no replacement until the end of the match (the team
 * finishes short-handed). Only V/J/R start a penalty clock.
 */
export function cardCreatesPenaltyClock(cardCode: string): cardCode is 'V' | 'J' | 'R' {
  return cardCode === 'V' || cardCode === 'J' || cardCode === 'R'
}

/**
 * §7.4 (correction 2026-07-29): early lift on a conceded goal applies to V/J only (the
 * player returns). An R penalty runs its FULL 2 minutes whatever happens — even if one or
 * several goals are conceded — and the replacement is only allowed at its end.
 */
export function penaltyLiftableOnGoal(cardCode: string): boolean {
  return cardCode === 'V' || cardCode === 'J'
}

/** First free penalty slot (1|2) for a team, or null when both are busy (spec §7.4). */
export function freePenaltySlot(busySlots: number[]): 1 | 2 | null {
  if (!busySlots.includes(1)) return 1
  if (!busySlots.includes(2)) return 2
  return null
}

/**
 * Which penalty slot is lifted when the short-handed team concedes a goal: the OLDEST
 * among the LIFTABLE ones only (V/J — spec §7.4, correction 2026-07-29). R penalties are
 * never lifted early; D never has a clock.
 */
export function penaltySlotToLift(
  penalties: Array<{ slot: number, startedAt: string, cardCode: string }>
): number | null {
  const liftable = penalties.filter(p => penaltyLiftableOnGoal(p.cardCode))
  if (liftable.length === 0) return null
  return [...liftable].sort((a, b) => a.startedAt.localeCompare(b.startedAt))[0].slot
}

// ─── Shotclock — 3 commands, 3 states (spec §6.5, decision 2026-07-27) ───

export type ShotclockState = 'IDLE' | 'RUNNING' | 'SUSPENDED'
export type ShotclockCommand = 'start60' | 'start40' | 'stop' | 'gameClockStopped' | 'gameClockStarted'

/**
 * Shotclock transition function. IDLE displays "--"; start60/start40 load AND run (the
 * start IS a reset, independent from the game clock); stop discards the countdown back
 * to IDLE (it is NOT a pause); the only pause is the automatic suspension while the game
 * clock is stopped.
 */
export function shotclockTransition(state: ShotclockState, command: ShotclockCommand): ShotclockState {
  switch (command) {
    case 'start60':
    case 'start40':
      return 'RUNNING'
    case 'stop':
      return 'IDLE'
    case 'gameClockStopped':
      return state === 'RUNNING' ? 'SUSPENDED' : state
    case 'gameClockStarted':
      return state === 'SUSPENDED' ? 'RUNNING' : state
  }
}
