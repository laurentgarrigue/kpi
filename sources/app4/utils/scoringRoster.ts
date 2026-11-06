import type { ScoringEvent, ScoringEventCode, ScoringPlayer, TeamSide } from '~/types/scoring'

/**
 * Reading the rosters of the scoring console (PAGE_SCORING.md §7.3): who scored or was
 * carded, and which player a typed jersey number designates. Entry aid and presentation —
 * no game rule here, so no api2 mirror (unlike utils/scoringRules).
 */

/** Player id of a team-level fact (no player attributed — score-only mode, empty roster). */
const TEAM_FACT_PLAYER = '0'

/** Coach / staff marker in kp_match_joueur.Capitaine: their jersey number means nothing. */
const STAFF = 'E'

export interface PlayerTally {
  goals: number
  /** Cards in entry order (V/J/R/D) — the last one is the player's current situation. */
  cards: ScoringEventCode[]
}

/** Goals and cards of each player, keyed by licence number (team-level facts left out). */
export function playerTallies(events: ScoringEvent[]): Map<string, PlayerTally> {
  const tallies = new Map<string, PlayerTally>()
  for (const e of events) {
    if (e.player === TEAM_FACT_PLAYER) continue
    const tally = tallies.get(e.player) ?? { goals: 0, cards: [] }
    if (e.code === 'B') tally.goals++
    else tally.cards.push(e.code)
    tallies.set(e.player, tally)
  }
  return tallies
}

/** Score computed from the recorded goals (team-level goals included). */
export function goalCounts(events: ScoringEvent[]): Record<TeamSide, number> {
  const counts: Record<TeamSide, number> = { A: 0, B: 0 }
  for (const e of events) {
    if (e.code === 'B') counts[e.team]++
  }
  return counts
}

/**
 * Players designated by what the operator typed: a jersey number, optionally with the team
 * letter before or after it ("7", "a7", "7B", "b 7"). The number alone may match one player
 * on each side — the caller then asks for the team. Coaches are never matched.
 */
export function findPlayersByNumber(query: string, playersA: ScoringPlayer[], playersB: ScoringPlayer[]): ScoringPlayer[] {
  const normalized = query.trim().toUpperCase()
  const digits = normalized.replace(/\D/g, '')
  if (digits === '') return []
  const number = Number(digits)
  const team = normalized.match(/[AB]/)?.[0] as TeamSide | undefined

  const candidates = team === 'A' ? playersA : team === 'B' ? playersB : [...playersA, ...playersB]
  return candidates.filter(p => p.capitaine !== STAFF && p.numero === number)
}
