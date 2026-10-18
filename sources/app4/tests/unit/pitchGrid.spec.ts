import { describe, expect, it } from 'vitest'
import { comparePitches, normalizeTime, pitchConflictIds, pitchDays, type PitchGame } from '../../utils/pitchGrid'

const game = (id: number, dateMatch: string | null, heureMatch: string | null, terrain: string | null): PitchGame =>
  ({ id, dateMatch, heureMatch, terrain })

describe('pitch grid', () => {
  it('GAM-PIT-01: one grid per date, one column per pitch, one row per time, in order', () => {
    const days = pitchDays([
      game(1, '2026-06-02', '10:00:00', '2'),
      game(2, '2026-06-01', '11:00', '1'),
      game(3, '2026-06-01', '09:00', '10'),
      game(4, '2026-06-01', '09:00', '1'),
    ])
    expect(days.map(d => d.date)).toEqual(['2026-06-01', '2026-06-02'])
    expect(days[0]!.pitches).toEqual(['1', '10'])
    expect(days[0]!.rows.map(r => r.time)).toEqual(['09:00', '11:00'])
    expect(days[0]!.rows[0]!.cells['1']!.map(g => g.id)).toEqual([4])
    expect(days[1]!.rows[0]!.time).toBe('10:00')
  })

  it('GAM-PIT-01: several games on the same slot and pitch share the cell', () => {
    const [day] = pitchDays([game(1, '2026-06-01', '09:00', '1'), game(2, '2026-06-01', '09:00', ' 1 ')])
    expect(day!.rows[0]!.cells['1']!.map(g => g.id)).toEqual([1, 2])
  })

  it('GAM-PIT-01: games without pitch, time or date are kept, shown last', () => {
    const days = pitchDays([game(1, null, null, null), game(2, '2026-06-01', null, 'A'), game(3, '2026-06-01', '09:00', '')])
    expect(days.map(d => d.date)).toEqual(['2026-06-01', ''])
    expect(days[0]!.pitches).toEqual(['A', ''])
    expect(days[0]!.rows.map(r => r.time)).toEqual(['09:00', ''])
  })

  it('sorts numeric pitches numerically, before named ones', () => {
    expect(['B', '', '10', '2', 'A'].sort(comparePitches)).toEqual(['2', '10', 'A', 'B', ''])
  })

  it('normalizes times', () => {
    expect(normalizeTime('9:05:00')).toBe('09:05')
    expect(normalizeTime('')).toBeNull()
    expect(normalizeTime(null)).toBeNull()
  })
})

describe('pitch conflicts', () => {
  it('GAM-PIT-04: two games on the same slot and pitch are both flagged', () => {
    expect(pitchConflictIds([game(1, '2026-06-01', '09:00', '1'), game(2, '2026-06-01', '09:00', '1')])).toEqual(new Set([1, 2]))
  })

  it('GAM-PIT-04: games less than 30 minutes apart on the same pitch are flagged, 30 minutes is fine', () => {
    const ids = pitchConflictIds([
      game(1, '2026-06-01', '09:00', '1'),
      game(2, '2026-06-01', '09:20', '1'),
      game(3, '2026-06-01', '10:00', '1'),
      game(4, '2026-06-01', '10:30', '1'),
    ])
    expect(ids).toEqual(new Set([1, 2]))
  })

  it('GAM-PIT-04: other pitch, other date or missing data never conflict', () => {
    expect(pitchConflictIds([
      game(1, '2026-06-01', '09:00', '1'),
      game(2, '2026-06-01', '09:00', '2'),
      game(3, '2026-06-02', '09:00', '1'),
      game(4, '2026-06-01', '09:00', null),
      game(5, '2026-06-01', null, '1'),
    ]).size).toBe(0)
  })

  it('honours a custom interval', () => {
    expect(pitchConflictIds([game(1, '2026-06-01', '09:00', '1'), game(2, '2026-06-01', '09:40', '1')], 45)).toEqual(new Set([1, 2]))
  })
})
