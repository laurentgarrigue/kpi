import type { Period, TeamSide } from '~/types/scoring'
import { overtimeIndex } from './scoringRules'

/**
 * Pitch sides of the scoring console (PAGE_SCORING.md §0.18). "Left" and "right" are seen from
 * the head referee and the scoring table (D1). Pure: the mirror in api2 is ScoringRules::sidesFor,
 * tested on the same truth table (SIDE-18).
 */

/** Which side team A occupies. */
export type Sides = 'A_LEFT' | 'A_RIGHT'
/** Starting side as recorded; null until the table has checked it at kick-off (D2, D15). */
export type SidesStart = Sides | null
/** A half of the pitch, seen from the table. */
export type PitchSide = 'L' | 'R'

export interface SidesSetting {
  start: SidesStart
  /** Periods whose sides were swapped by hand (D4). */
  overrides: Period[]
}

/** Shown while the sides are not checked yet (D2). */
const UNCHECKED_START: Sides = 'A_LEFT'

const flip = (sides: Sides): Sides => (sides === 'A_LEFT' ? 'A_RIGHT' : 'A_LEFT')

/** Periods played before this one: M1 = 0, M2 = 1, P{n} = n + 1; shoot-out = 0 (starting sides). */
function periodsBefore(period: Period): number {
  if (period === 'M2') return 1
  const n = overtimeIndex(period)
  return n !== null ? n + 1 : 0
}

/** Sides of a period: the start, swapped once per period played before it, and by hand (D3, D4). */
export function sidesFor(start: SidesStart, period: Period, overrides: readonly Period[]): Sides {
  let sides = start ?? UNCHECKED_START
  if (periodsBefore(period) % 2 === 1) sides = flip(sides)
  if (overrides.includes(period)) sides = flip(sides)
  return sides
}

export function teamOnSide(sides: Sides, side: PitchSide): TeamSide {
  return (side === 'L') === (sides === 'A_LEFT') ? 'A' : 'B'
}

export function sideOfTeam(sides: Sides, team: TeamSide): PitchSide {
  return (team === 'A') === (sides === 'A_LEFT') ? 'L' : 'R'
}

/**
 * Confirmed manual swap (D4): before the check it moves the starting side; after it, it swaps the
 * current period only, and a second swap of the same period cancels the first.
 */
export function toggleSidesForPeriod(setting: SidesSetting, period: Period): SidesSetting {
  if (setting.start === null) return { start: flip(UNCHECKED_START), overrides: [...setting.overrides] }
  const overrides = setting.overrides.includes(period)
    ? setting.overrides.filter(p => p !== period)
    : [...setting.overrides, period]
  return { start: setting.start, overrides }
}

/** Team given the ball by the last shot-clock start of a period. */
export interface LastPossession {
  team: TeamSide
  period: Period
}

/** Likely side inversion (D11): a goal by the team the last start of this period did NOT give the ball to. */
export function possessionConflict(last: LastPossession | null, goalTeam: TeamSide, period: Period): boolean {
  return last !== null && last.period === period && last.team !== goalTeam
}

/** Sides setting carried by the live state (GET /state); not checked when absent or unknown. */
export function sidesSettingOf(live: { sidesStart?: SidesStart, sidesOverrides?: Period[] }): SidesSetting {
  const start = live.sidesStart === 'A_LEFT' || live.sidesStart === 'A_RIGHT' ? live.sidesStart : null
  return { start, overrides: Array.isArray(live.sidesOverrides) ? [...live.sidesOverrides] : [] }
}

/** Starting side that gives `checked` in `period` (D15: the check may happen after M1). */
export function startForSides(checked: Sides, period: Period, overrides: readonly Period[]): Sides {
  return sidesFor('A_LEFT', period, overrides) === 'A_LEFT' ? checked : flip(checked)
}
