import { describe, expect, it } from 'vitest'
import { rankingMark } from '../../app/utils/results/ranking'
import type { RankingRow } from '../../app/utils/results/types'

const row = (rank: number, medal: RankingRow['medal'] = null): RankingRow => ({
  rank, team: { id: rank, number: rank, label: `T${rank}` }, points: 0, played: 0, medal,
})

describe('rankingMark', () => {
  it('CPL-10/CMP-10: a medal replaces the qualified mark', () => {
    expect(rankingMark(row(1, 1), 0, 8, 2, 1)).toEqual({ kind: 'medal', medal: 1 })
  })

  it('CPL-06/CMP-10: the first `qualified` and the last `eliminated` positions are marked', () => {
    expect(rankingMark(row(2), 1, 8, 2, 1)).toEqual({ kind: 'qualified' })
    expect(rankingMark(row(3), 2, 8, 2, 1)).toBeNull()
    expect(rankingMark(row(8), 7, 8, 2, 1)).toEqual({ kind: 'eliminated' })
  })
})
