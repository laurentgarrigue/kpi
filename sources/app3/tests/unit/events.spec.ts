import { describe, expect, it } from 'vitest'
import {
  eventLogoUrl,
  eventsOfYear,
  eventYears,
  formatEventDates,
  isOngoing,
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
