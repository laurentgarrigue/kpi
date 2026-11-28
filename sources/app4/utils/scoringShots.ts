import type { Period, ScoringPlayer, ScoringShot, TeamSide } from '~/types/scoring'
import type { LastPossession } from './scoringSides'

/**
 * Shots and saves in two keys (PAGE_SCORING.md §0.19). Pure: the team comes from the possession declared
 * by the shot clock, the optional number designates the shooter (SHOT) or the goalkeeper (SAVE).
 */

/** Digits accepted after T / A (shirt numbers). */
export const SHOT_NUMBER_MAX_DIGITS = 2
/** How long the « n° ? » prompt waits for a digit, restarted by each digit (T5). */
export const SHOT_NUMBER_WINDOW_MS = 3000

const other = (team: TeamSide): TeamSide => (team === 'A' ? 'B' : 'A')

/** Team in possession: the one given the ball by the last shot-clock start of this period (T4). */
export function possessionTeam(last: LastPossession | null, period: Period): TeamSide | null {
  return last !== null && last.period === period ? last.team : null
}

/** A shot belongs to the attack, a save to the defence (the goalkeeper's team); '' when unknown. */
export function shotTeam(kind: ScoringShot['kind'], possession: TeamSide | null): TeamSide | '' {
  if (possession === null) return ''
  return kind === 'SHOT' ? possession : other(possession)
}

/** Digits typed after the key: the number so far, or null when the key is not a digit. */
export function appendShotDigit(digits: string, key: string): string | null {
  if (!/^\d$/.test(key)) return null
  return digits.length >= SHOT_NUMBER_MAX_DIGITS ? digits : digits + key
}

/** Player of the roster wearing this number (the coach, numbered 0, is never a shooter). */
export function playerByNumber(players: readonly ScoringPlayer[], number: number): ScoringPlayer | null {
  return players.find(p => p.capitaine !== 'E' && p.numero === number) ?? null
}

export interface ShotTotals {
  A: { shots: number, saves: number }
  B: { shots: number, saves: number }
  /** Facts whose possession was unknown. */
  unknown: number
}

/** Totals of the shot log, per team (T6). */
export function shotTotals(shots: readonly ScoringShot[]): ShotTotals {
  const totals: ShotTotals = { A: { shots: 0, saves: 0 }, B: { shots: 0, saves: 0 }, unknown: 0 }
  for (const s of shots) {
    if (s.team === '') totals.unknown++
    else if (s.kind === 'SHOT') totals[s.team].shots++
    else totals[s.team].saves++
  }
  return totals
}
