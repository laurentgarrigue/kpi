import { describe, expect, it } from 'vitest'
import {
  eventLogoUrl,
  eventUrl,
  formatEventDates,
  isOngoing,
  recentEvents,
  todayInParis,
  upcomingEvents,
  type PublicEvent,
} from '../../app/utils/events'

const event = (id: number, start: string | null, end: string | null = start, logo: string | null = null): PublicEvent => ({
  id, libelle: `Event ${id}`, place: 'Paris', logo, start, end,
})

const TODAY = '2026-06-15'

describe('upcomingEvents', () => {
  it('HOME-07: keeps ongoing and future events, nearest first, at most the requested number', () => {
    const events = [
      event(1, '2026-09-01'),
      event(2, '2026-06-14', '2026-06-16'), // ongoing
      event(3, '2026-06-01', '2026-06-02'), // past
      event(4, '2026-07-01'),
      event(5, '2026-06-15'), // today
    ]
    expect(upcomingEvents(events, TODAY, 6).map(e => e.id)).toEqual([2, 5, 4, 1])
    expect(upcomingEvents(events, TODAY, 2).map(e => e.id)).toEqual([2, 5])
  })

  it('HOME-06: uses the start date when the end date is missing, ignores undated events', () => {
    const events = [event(1, '2026-07-01', null), event(2, null, null)]
    expect(upcomingEvents(events, TODAY, 6).map(e => e.id)).toEqual([1])
  })
})

describe('recentEvents', () => {
  it('HOME-03: keeps finished events, most recent first, at most the requested number', () => {
    const events = [
      event(1, '2026-01-10'),
      event(2, '2026-06-14', '2026-06-16'), // ongoing: not recent
      event(3, '2026-06-01', '2026-06-02'),
      event(4, '2026-03-01', '2026-03-03'),
      event(5, '2026-07-01'),
    ]
    expect(recentEvents(events, TODAY, 6).map(e => e.id)).toEqual([3, 4, 1])
    expect(recentEvents(events, TODAY, 1).map(e => e.id)).toEqual([3])
  })

  it('HOME-06: ignores undated events', () => {
    expect(recentEvents([event(1, null, null)], TODAY, 6)).toEqual([])
  })
})

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
  it('HOME-04: links an event to its app2 page', () => {
    expect(eventUrl(42, 'https://app.kayak-polo.info/')).toBe('https://app.kayak-polo.info/event/42')
  })

  it('HOME-04: builds the logo URL only when a logo is set', () => {
    expect(eventLogoUrl('logo/cdf.png', 'https://www.kayak-polo.info')).toBe('https://www.kayak-polo.info/img/logo/cdf.png')
    expect(eventLogoUrl(null, 'https://www.kayak-polo.info')).toBeNull()
    expect(eventLogoUrl('', 'https://www.kayak-polo.info')).toBeNull()
  })
})
