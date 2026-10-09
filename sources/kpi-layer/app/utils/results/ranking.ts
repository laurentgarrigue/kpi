import type { RankingRow } from './types'

export type RankingMark = { kind: 'medal', medal: 1 | 2 | 3 } | { kind: 'qualified' } | { kind: 'eliminated' } | null

/**
 * Mark of a ranking line (kpclassement.tpl): the medal computed by api2 first, otherwise « qualified » for the
 * first `qualified` positions and « eliminated » for the last `eliminated` ones.
 */
export function rankingMark(row: RankingRow, index: number, total: number, qualified: number, eliminated: number): RankingMark {
  if (row.medal) {
    return { kind: 'medal', medal: row.medal }
  }
  if (index < qualified) {
    return { kind: 'qualified' }
  }
  if (index >= total - eliminated) {
    return { kind: 'eliminated' }
  }
  return null
}
