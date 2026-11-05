import { describe, expect, it } from 'vitest'
import {
  eventLogoUrl,
  eventsByMonth,
  eventsOfYear,
  eventYears,
  dateTile,
  formatEventDates,
  isOngoing,
  matchesEventQuery,
  shortEventDates,
  splitByToday,
  todayInParis,
  type PublicEvent,
} from '../../app/utils/events'

const event = (id: number, start: string | null, end: string | null = start, logo: string | null = null): PublicEvent => ({
  id, libelle: `Event ${id}`, place: 'Paris', logo, start, end,
})

const TODAY = '2026-06-15'

describe('isOngoing', () => {
  it('HOME-07: is true from the start date to the end date included', () => {
    expect(isOngoing(event(1, '2026-06-14', '2026-06-16'), TODAY)).toBe(true)
    expect(isOngoing(event(2, '2026-06-15'), TODAY)).toBe(true)
    expect(isOngoing(event(3, '2026-06-16', '2026-06-18'), TODAY)).toBe(false)
    expect(isOngoing(event(4, '2026-06-01', '2026-06-02'), TODAY)).toBe(false)
  })
})

describe('todayInParis', () => {
  it('HOME-06: gives the calendar date in Europe/Paris, not in UTC', () => {
    expect(todayInParis(new Date('2026-06-14T22:30:00Z'))).toBe('2026-06-15')
    expect(todayInParis(new Date('2026-06-14T12:00:00Z'))).toBe('2026-06-14')
  })
})

describe('formatEventDates', () => {
  it('HOME-04: formats a date range in the current language', () => {
    // Spacing around the dash depends on the locale (ICU data), not on our code.
    expect(formatEventDates('2026-06-12', '2026-06-14', 'fr-FR')).toMatch(/^12\s?–\s?14 juin 2026$/)
    expect(formatEventDates('2026-06-12', '2026-06-14', 'en-GB')).toMatch(/^12\s?–\s?14 June 2026$/)
  })

  it('HOME-04: formats a single day, or a missing end date, as one date', () => {
    expect(formatEventDates('2026-06-12', '2026-06-12', 'fr-FR')).toBe('12 juin 2026')
    expect(formatEventDates('2026-06-12', null, 'fr-FR')).toBe('12 juin 2026')
    expect(formatEventDates(null, null, 'fr-FR')).toBe('')
  })
})

describe('event links', () => {
  it('HOME-04: builds the logo URL only when a logo is set', () => {
    expect(eventLogoUrl('logo/cdf.png', 'https://www.kayak-polo.info')).toBe('https://www.kayak-polo.info/img/logo/cdf.png')
    expect(eventLogoUrl(null, 'https://www.kayak-polo.info')).toBeNull()
    expect(eventLogoUrl('', 'https://www.kayak-polo.info')).toBeNull()
  })
})

describe('events by year', () => {
  const events = [
    event(1, '2025-12-30', '2026-01-02'), // straddles two years
    event(2, '2026-06-01'),
    event(3, '2026-03-01', '2026-03-03'),
    event(4, null, null),
  ]

  it('EVL-02: lists the years having events, most recent first, ignoring undated events', () => {
    expect(eventYears(events)).toEqual([2026, 2025])
  })

  it('EVL-02: lists the events of a year, earliest first, including those straddling the year change', () => {
    expect(eventsOfYear(events, 2026).map(e => e.id)).toEqual([1, 3, 2])
    expect(eventsOfYear(events, 2025).map(e => e.id)).toEqual([1])
    expect(eventsOfYear(events, 2024)).toEqual([])
  })
})

describe('dateTile', () => {
  it('CMP-18: days over the short month of the last day', () => {
    expect(dateTile('2026-07-03', '2026-07-05', 'fr')).toEqual({ days: '3–5', month: 'juil.' })
    expect(dateTile('2026-07-04', '2026-07-04', 'fr')).toEqual({ days: '4', month: 'juil.' })
    expect(dateTile('2026-06-30', null, 'en')).toEqual({ days: '30', month: 'Jun' })
    expect(dateTile('2026-06-30', '2026-07-02', 'en')).toEqual({ days: '30–2', month: 'Jul' })
    expect(dateTile(null, null, 'fr')).toBeNull()
  })
})

describe('events list (PAGE_EVENT_GROUP.md § 2.2)', () => {
  const list = [
    event(1, '2026-07-03', '2026-07-05'),
    event(2, '2026-03-14', '2026-03-15'),
    event(3, '2026-11-07', '2026-11-08'),
    event(4, '2026-10-21', '2026-10-23'),
    event(5, '2026-03-28', '2026-03-29'),
  ]

  it('EVL-03: upcoming events first in order (the next one first), then the past ones, the latest first', () => {
    const { upcoming, past } = splitByToday(list, '2026-10-22')
    expect(upcoming.map(item => item.id)).toEqual([4, 3])
    expect(past.map(item => item.id)).toEqual([1, 5, 2])
  })

  it('EVL-03: events grouped by the month of their first day, in the given order', () => {
    expect(eventsByMonth(splitByToday(list, '2026-10-22').past).map(group => [group.month, group.events.map(item => item.id)]))
      .toEqual([['2026-07', [1]], ['2026-03', [5, 2]]])
  })

  it('EVL-04: a query matches the name or the place, whatever the accents and the case', () => {
    const finals = { ...event(9, '2026-07-03'), libelle: 'Finales championnats de France', place: 'Combourg' }
    expect(matchesEventQuery(finals, 'CHAMPIONNAT')).toBe(true)
    expect(matchesEventQuery(finals, 'combourg')).toBe(true)
    expect(matchesEventQuery({ ...finals, place: 'Saint-Grégoire' }, 'gregoire')).toBe(true)
    expect(matchesEventQuery(finals, 'acigne')).toBe(false)
    expect(matchesEventQuery(finals, '  ')).toBe(true)
  })

  it('EVL-03: short dates « 3–5 juil. », across months « 30 juin – 2 juil. »', () => {
    expect(shortEventDates('2026-07-03', '2026-07-05', 'fr')).toBe('3–5 juil.')
    expect(shortEventDates('2026-07-04', '2026-07-04', 'fr')).toBe('4 juil.')
    expect(shortEventDates('2026-06-30', '2026-07-02', 'fr').replace(/\s/g, ' ')).toBe('30 juin – 2 juil.')
  })
})
