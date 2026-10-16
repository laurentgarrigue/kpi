import { describe, expect, it } from 'vitest'
import { chartRounds, knockoutSides, phasesByLevel, poolStandings } from '../../app/utils/results/charts'
import type { ChartPhase, ChartTeam, CompetitionChart } from '../../app/utils/results/types'
import { game } from './fixtures'

const team = (overrides: Partial<ChartTeam>): ChartTeam => ({
  t_id: 1, t_number: 1, t_label: 'Team', t_logo: null, t_clt: null, t_pts: null, t_pld: null, t_diff: null, ...overrides,
})
const phase = (overrides: Partial<ChartPhase>): ChartPhase => ({
  type: 'C', libelle: 'Poule A', level: 1, t_count: 2, d_id: 1, games: null, ...overrides,
})

const chart: CompetitionChart = {
  type: 'CP', code: 'CF', libelle: 'Coupe', status: 'ON', season: '2026',
  rounds: {
    2: { type: 'E', phases: { '98-Finale': phase({ type: 'E', libelle: 'Finale', level: 2 }) } },
    1: {
      type: 'C',
      phases: {
        '99-Poule A': phase({ libelle: 'Poule A' }),
        '99-Poule B': phase({ libelle: 'Poule B' }),
      },
    },
  },
}

describe('chartRounds / phasesByLevel', () => {
  it('CMP-09: horizontal view, rounds in order, phases in api2 order', () => {
    expect(chartRounds(chart).map(round => [round.round, round.phases.map(p => p.libelle)])).toEqual([
      [1, ['Poule A', 'Poule B']],
      [2, ['Finale']],
    ])
  })

  it('CMP-09: vertical view, phases by decreasing level', () => {
    expect(phasesByLevel(chart).map(p => p.libelle)).toEqual(['Finale', 'Poule A', 'Poule B'])
  })
})

describe('poolStandings', () => {
  it('CMP-09: ranks by rank, points, goal difference once games are played', () => {
    const rows = poolStandings(phase({
      teams: [
        team({ t_id: 1, t_label: 'B', t_clt: 2, t_pts: 300, t_pld: 1 }),
        team({ t_id: 2, t_label: 'A', t_clt: 1, t_pts: 400, t_pld: 1 }),
      ],
    }))
    expect(rows.map(row => ('empty' in row ? null : row.t_label))).toEqual(['A', 'B'])
  })

  it('CMP-09: before any game, keeps the draw order', () => {
    const rows = poolStandings(phase({
      teams: [team({ t_id: 1, t_number: 2, t_label: 'X' }), team({ t_id: 2, t_number: 1, t_label: 'Y' })],
    }))
    expect(rows.map(row => ('empty' in row ? null : row.t_label))).toEqual(['Y', 'X'])
  })

  it('CMP-09: completes with placeholders, then empty slots, up to the pool size', () => {
    const rows = poolStandings(phase({
      t_count: 4,
      teams: [team({ t_id: 1, t_label: 'Real' }), team({ t_id: null, t_label: '(1st Group A)' })],
    }))
    expect(rows).toHaveLength(4)
    expect(rows.map(row => ('empty' in row ? 'empty' : row.t_label))).toEqual(['Real', '(1st Group A)', 'empty', 'empty'])
  })
})

describe('knockoutSides', () => {
  it('CMP-09: lists the winner first and flags it', () => {
    const sides = knockoutSides(game({ g_status: 'END', g_validation: 'O', g_score_a: '1', g_score_b: '3' }))
    expect(sides.map(side => [side.side, side.score, side.winner])).toEqual([['B', '3', true], ['A', '1', false]])
  })

  it('keeps A then B without a winner, with placeholder labels', () => {
    const sides = knockoutSides(game({ t_a_label: null, t_b_label: '(Loser game #12)' }))
    expect(sides.map(side => [side.side, side.label, side.winner])).toEqual([['A', null, false], ['B', '(Loser game #12)', false]])
  })
})
