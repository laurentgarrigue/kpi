import { describe, expect, it } from 'vitest'
import {
  calendarApiPath, entriesByDate, gamedayCompetitionPath, gridRange, lookaheadRange, monthGrid, monthRange,
  nextMonthWithEntries, parseFilters, parseMonth, shiftMonth, type CalendarEntry,
} from '../../app/utils/calendar'

function entry(id: number, start: string, end: string, type = 'CHPT'): CalendarEntry {
  return {
    id,
    competition: { season: '2026', code: 'N1H', display_title: 'Nationale 1', type, level: 'NAT', group: 'N1H' },
    name: `J${id}`,
    place: 'Lacville',
    department: '33',
    start,
    end,
    event: null,
  }
}

describe('calendar month and filters (PAGE_CALENDAR.md)', () => {
  it('CAL-01: the month of the query, or the current month when absent or invalid', () => {
    expect(parseMonth('2026-06', '2026-10-09')).toBe('2026-06')
    expect(parseMonth(undefined, '2026-10-09')).toBe('2026-10')
    expect(parseMonth('2026-13', '2026-10-09')).toBe('2026-10')
    expect(parseMonth(['2026-06'], '2026-10-09')).toBe('2026-10')
  })

  it('CAL-04: level and group filters, dropped when invalid', () => {
    expect(parseFilters({ level: 'NAT', group: 'N1H' })).toEqual({ level: 'NAT', group: 'N1H' })
    expect(parseFilters({ level: 'XYZ', group: 'bad group' })).toEqual({})
  })

  it('months shift across years; ranges are calendar days', () => {
    expect(shiftMonth('2026-12', 1)).toBe('2027-01')
    expect(shiftMonth('2026-01', -1)).toBe('2025-12')
    expect(monthRange('2028-02')).toEqual({ start: '2028-02-01', end: '2028-02-29' })
  })

  it('the month grid runs from the Monday before the 1st to the Sunday after the last day', () => {
    // June 2026: Monday 1st → Tuesday 30th.
    expect(gridRange('2026-06')).toEqual({ start: '2026-06-01', end: '2026-07-05' })
    // November 2026: Sunday 1st → Monday 30th.
    expect(gridRange('2026-11')).toEqual({ start: '2026-10-26', end: '2026-12-06' })
  })

  it('api2 path of a period with its filters', () => {
    expect(calendarApiPath({ start: '2026-06-01', end: '2026-07-05' }, { level: 'NAT', group: 'N1H' }))
      .toBe('/calendar?start=2026-06-01&end=2026-07-05&level=NAT&group=N1H')
    expect(lookaheadRange('2026-06')).toEqual({ start: '2026-07-01', end: '2027-06-30' })
  })
})

describe('calendar entries', () => {
  const entries = [entry(1, '2026-05-30', '2026-06-01'), entry(2, '2026-06-12', '2026-06-14'), entry(3, '2026-06-12', '2026-06-12', 'CP'), entry(4, '2026-07-01', '2026-07-01')]

  it('CAL-02: gamedays of the month grouped by start date; one started earlier is listed on the 1st', () => {
    expect(entriesByDate(entries, '2026-06').map(group => [group.date, group.entries.map(item => item.id)])).toEqual([
      ['2026-06-01', [1]],
      ['2026-06-12', [2, 3]],
    ])
  })

  it('CAL-02: the month grid shows a multi-day gameday on each of its days', () => {
    const days = monthGrid(entries, '2026-06').flat()
    const idsOn = (date: string) => days.find(day => day.date === date)!.entries.map(item => item.id)
    expect(days).toHaveLength(35)
    expect(idsOn('2026-06-13')).toEqual([2])
    expect(idsOn('2026-06-12')).toEqual([2, 3])
    expect(days.find(day => day.date === '2026-07-01')).toMatchObject({ inMonth: false, entries: [{ id: 4 }] })
  })

  it('CAL-03: a gameday leads to its competition games, on the gameday for a championship', () => {
    expect(gamedayCompetitionPath(entries[1]!)).toBe('/competitions/2026/N1H/games?gameday=2')
    expect(gamedayCompetitionPath(entries[2]!)).toBe('/competitions/2026/N1H/games')
  })

  it('CAL-05: the next month having gamedays', () => {
    expect(nextMonthWithEntries(entries, '2026-06')).toBe('2026-07')
    expect(nextMonthWithEntries(entries, '2026-07')).toBeNull()
  })
})
