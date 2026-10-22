import { describe, expect, it } from 'vitest'
import {
  defaultPitchDay,
  filterGames,
  gameDates,
  gamedayOptions,
  groupGamesByDate,
  hasScore,
  isLive,
  isProvisional,
  displayStatus,
  gamePeriod,
  isUpcoming,
  pitchGrid,
  sortGames,
  winnerSide,
  gamesOfCompetition,
  drawCode,
  drawSlot,
  slotFromLabel,
  isFinal,
} from '../../app/utils/results/games'
import { game } from './fixtures'

describe('sortGames / groupGamesByDate', () => {
  it('CMP-05: sorts by date, time, then pitch number', () => {
    const games = [
      game({ g_id: 1, g_date: '2026-06-15', g_time: '09:00', g_pitch: '1' }),
      game({ g_id: 2, g_date: '2026-06-14', g_time: '10:00', g_pitch: '10' }),
      game({ g_id: 3, g_date: '2026-06-14', g_time: '10:00', g_pitch: '2' }),
      game({ g_id: 4, g_date: '2026-06-14', g_time: '09:20', g_pitch: '3' }),
    ]
    expect(sortGames(games).map(g => g.g_id)).toEqual([4, 3, 2, 1])
  })

  it('CMP-05: groups games by date, in date order', () => {
    const groups = groupGamesByDate([
      game({ g_id: 1, g_date: '2026-06-15' }),
      game({ g_id: 2, g_date: '2026-06-14' }),
      game({ g_id: 3, g_date: '2026-06-14', g_time: '11:00' }),
    ])
    expect(groups.map(group => [group.date, group.games.map(g => g.g_id)])).toEqual([
      ['2026-06-14', [2, 3]],
      ['2026-06-15', [1]],
    ])
  })
})

describe('isUpcoming', () => {
  // 14 June 2026, 10:00 in Paris (UTC+2 in summer)
  const now = new Date('2026-06-14T08:00:00Z')

  it('CMP-06: keeps games starting after now − 35 min, in Paris time', () => {
    expect(isUpcoming(game({ g_date: '2026-06-14', g_time: '09:30' }), now)).toBe(true)
    expect(isUpcoming(game({ g_date: '2026-06-14', g_time: '09:20' }), now)).toBe(false)
    expect(isUpcoming(game({ g_date: '2026-06-15', g_time: '08:00' }), now)).toBe(true)
    expect(isUpcoming(game({ g_date: '2026-06-13', g_time: '23:00' }), now)).toBe(false)
  })

  it('CMP-06: a game without a date is never upcoming', () => {
    expect(isUpcoming(game({ g_date: null }), now)).toBe(false)
  })
})

describe('filterGames', () => {
  const now = new Date('2026-06-14T08:00:00Z')
  const games = [
    game({ g_id: 1, d_id: 7, g_date: '2026-06-13', g_time: '10:00' }),
    game({ g_id: 2, d_id: 7, g_date: '2026-06-14', g_time: '11:00' }),
    game({ g_id: 3, d_id: 8, g_date: '2026-06-14', g_time: '12:00' }),
    game({ g_id: 4, d_id: 8, g_date: '2026-06-15', g_time: '09:00' }),
  ]

  it('CMP-06: combines gameday, day and upcoming filters', () => {
    expect(filterGames(games, {}, now).map(g => g.g_id)).toEqual([1, 2, 3, 4])
    expect(filterGames(games, { gameday: 7 }, now).map(g => g.g_id)).toEqual([1, 2])
    expect(filterGames(games, { day: '2026-06-14' }, now).map(g => g.g_id)).toEqual([2, 3])
    expect(filterGames(games, { upcoming: true }, now).map(g => g.g_id)).toEqual([2, 3, 4])
    expect(filterGames(games, { gameday: 8, day: '2026-06-14', upcoming: true }, now).map(g => g.g_id)).toEqual([3])
  })
})

describe('gameDates / gamedayOptions', () => {
  it('lists the distinct dates and gamedays present, in order', () => {
    const games = [
      game({ d_id: 8, d_label: 'J2', d_place: 'Thury', g_date: '2026-06-15' }),
      game({ d_id: 7, d_label: 'J1', d_place: 'Acigné', g_date: '2026-06-13' }),
      game({ d_id: 7, d_label: 'J1', d_place: 'Acigné', g_date: '2026-06-14' }),
    ]
    expect(gameDates(games)).toEqual(['2026-06-13', '2026-06-14', '2026-06-15'])
    expect(gamedayOptions(games)).toEqual([
      { id: 7, label: 'J1', place: 'Acigné', start: '2026-06-13', end: '2026-06-14' },
      { id: 8, label: 'J2', place: 'Thury', start: '2026-06-15', end: '2026-06-15' },
    ])
  })
})

describe('defaultPitchDay', () => {
  const days = ['2026-06-13', '2026-06-15', '2026-06-20']

  it('CMP-07: today when it has games, otherwise the next day with games, otherwise the last one', () => {
    expect(defaultPitchDay(days, '2026-06-15')).toBe('2026-06-15')
    expect(defaultPitchDay(days, '2026-06-14')).toBe('2026-06-15')
    expect(defaultPitchDay(days, '2026-07-01')).toBe('2026-06-20')
    expect(defaultPitchDay([], '2026-07-01')).toBeNull()
  })
})

describe('pitchGrid', () => {
  it('CMP-07: one column per pitch (numeric order), one row per time', () => {
    const grid = pitchGrid([
      game({ g_id: 1, g_time: '10:00', g_pitch: '2' }),
      game({ g_id: 2, g_time: '09:00', g_pitch: '10' }),
      game({ g_id: 3, g_time: '10:00', g_pitch: '1' }),
    ])
    expect(grid.pitches).toEqual(['1', '2', '10'])
    expect(grid.rows.map(row => [row.time, grid.pitches.map(pitch => row.cells[pitch]?.g_id ?? null)])).toEqual([
      ['09:00', [null, null, 2]],
      ['10:00', [3, 1, null]],
    ])
  })
})

describe('status and period', () => {
  it('CMP-17: a finished game not locked yet is provisional', () => {
    expect(displayStatus(game({ g_status: 'ATT' }))).toBe('ATT')
    expect(displayStatus(game({ g_status: 'ON' }))).toBe('ON')
    expect(displayStatus(game({ g_status: 'END', g_validation: 'O' }))).toBe('END')
    expect(displayStatus(game({ g_status: 'END', g_validation: '' }))).toBe('PROVISIONAL')
  })

  it('CMP-17: periods are regular time (Mx), overtime (Px) or shoot-out (TB)', () => {
    expect(gamePeriod('M2')).toEqual({ kind: 'regular', number: 2 })
    expect(gamePeriod('P1')).toEqual({ kind: 'overtime', number: 1 })
    expect(gamePeriod('TB')).toEqual({ kind: 'shootout', number: null })
    expect(gamePeriod(null)).toBeNull()
    expect(gamePeriod('X9')).toBeNull()
  })
})

describe('scores', () => {
  it('CMP-05: a score is provisional until the game is validated', () => {
    expect(isProvisional(game({ g_status: 'ON', g_score_a: '1', g_score_b: '0' }))).toBe(true)
    expect(isProvisional(game({ g_status: 'END', g_score_a: '1', g_score_b: '0', g_validation: 'O' }))).toBe(false)
    expect(isProvisional(game({ g_status: 'ATT' }))).toBe(false)
    expect(hasScore(game({ g_score_a: '', g_score_b: '' }))).toBe(false)
    expect(hasScore(game({ g_score_a: '?', g_score_b: '?' }))).toBe(false)
  })

  it('CMP-07: the winner of a finished game, even before the officials validate the score', () => {
    expect(winnerSide(game({ g_status: 'END', g_validation: '', g_score_a: '3', g_score_b: '1' }))).toBe('A')
  })

  it('CMP-09: the winner of a finished game, forfeits included', () => {
    expect(winnerSide(game({ g_status: 'END', g_validation: 'O', g_score_a: '3', g_score_b: '1' }))).toBe('A')
    expect(winnerSide(game({ g_status: 'END', g_validation: 'O', g_score_a: 'F', g_score_b: '0' }))).toBe('B')
    expect(winnerSide(game({ g_status: 'END', g_validation: 'O', g_score_a: '2', g_score_b: '2' }))).toBeNull()
    expect(winnerSide(game({ g_status: 'ON', g_score_a: '3', g_score_b: '1' }))).toBeNull()
  })
})

describe('isLive', () => {
  const now = new Date('2026-06-14T08:00:00Z') // 10:00 in Paris

  it('CMP-13: true when a game is on, or starts within the hour today', () => {
    expect(isLive([game({ g_status: 'ON' })], now)).toBe(true)
    expect(isLive([game({ g_date: '2026-06-14', g_time: '10:50' })], now)).toBe(true)
    expect(isLive([game({ g_date: '2026-06-14', g_time: '11:30' })], now)).toBe(false)
    expect(isLive([game({ g_date: '2026-06-15', g_time: '10:30' })], now)).toBe(false)
    expect(isLive([game({ g_status: 'END', g_date: '2026-06-14', g_time: '09:00' })], now)).toBe(false)
  })
})

describe('gamesOfCompetition', () => {
  it('EVT-06: keeps the games of one competition, all of them without a code', () => {
    const games = [game({ g_id: 1, c_code: 'N1H' }), game({ g_id: 2, c_code: 'N1F' })]
    expect(gamesOfCompetition(games, 'N1F').map(g => g.g_id)).toEqual([2])
    expect(gamesOfCompetition(games, undefined)).toEqual(games)
  })
})

describe('drawSlot / isFinal', () => {
  it('CMP-20: one code per side, A first, as api2 reads them (« 1er poule E », « 1er poule F »)', () => {
    const upcoming = game({ g_status: 'ATT', g_code: '[1E-1F]', t_a_label: null, t_b_label: null })
    expect(drawSlot(upcoming, 'A')).toEqual({ kind: 'pool', rank: 1, pool: 'E' })
    expect(drawSlot(upcoming, 'B')).toEqual({ kind: 'pool', rank: 1, pool: 'F' })
  })

  it('CMP-20: winners, losers and teams of the draw, from the code or a waiting label', () => {
    const coded = game({ g_status: 'ATT', g_code: '[V11-P12]', t_a_label: null, t_b_label: null })
    expect(drawSlot(coded, 'A')).toEqual({ kind: 'winner', game: 11 })
    expect(drawSlot(coded, 'B')).toEqual({ kind: 'loser', game: 12 })
    expect(drawSlot(game({ g_status: 'ATT', g_code: '[T7/T2]', t_a_label: null }), 'A')).toEqual({ kind: 'team', game: 7 })
    expect(drawSlot(game({ g_status: 'ATT', t_a_label: '(Winner game #236)' }), 'A')).toEqual({ kind: 'winner', game: 236 })
    expect(drawSlot(game({ g_status: 'ATT', t_b_label: '(Loser game #12)' }), 'B')).toEqual({ kind: 'loser', game: 12 })
    expect(drawSlot(game({ g_status: 'ATT', t_b_label: '(2nd Group B)' }), 'B')).toEqual({ kind: 'pool', rank: 2, pool: 'B' })
  })

  it('CMP-20: keeps an unknown waiting label as text, without its brackets', () => {
    expect(drawSlot(game({ g_status: 'ATT', t_a_label: '(2ème poule haute)' }), 'A')).toEqual({ kind: 'text', text: '2ème poule haute' })
  })

  it('CMP-20: reads a waiting label on its own (pool placeholders of the progress tab)', () => {
    expect(slotFromLabel('(1st Group A)')).toEqual({ kind: 'pool', rank: 1, pool: 'A' })
    expect(slotFromLabel('Équipe 3')).toEqual({ kind: 'text', text: 'Équipe 3' })
  })

  it('CMP-20: no slot for an assigned team, or once the game is played', () => {
    expect(drawSlot(game({ g_status: 'ATT', g_code: '[1A-2B]', t_a_label: 'Avranches I', t_a_number: 67 }), 'A')).toBeNull()
    expect(drawSlot(game({ g_status: 'END', g_code: '[1A-2B]', t_a_label: null }), 'A')).toBeNull()
  })

  it('CMP-20: the draw code is shown only for a game still to play', () => {
    expect(drawCode(game({ g_status: 'ATT', g_code: '[1A-1C]' }))).toBe('1A-1C')
    expect(drawCode(game({ g_status: 'END', g_code: '[1A-1C]' }))).toBeNull()
  })

  it('CMP-19: a final is recognised from its phase, in French or English', () => {
    expect(isFinal(game({ d_phase: 'Finale' }))).toBe(true)
    expect(isFinal(game({ d_phase: 'final' }))).toBe(true)
    expect(isFinal(game({ d_phase: 'Petite finale' }))).toBe(false)
    expect(isFinal(game({ d_phase: '3ème place' }))).toBe(false)
  })
})
