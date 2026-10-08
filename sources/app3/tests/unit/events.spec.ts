import { describe, expect, it } from 'vitest'
import { eventLogoUrl, eventUrl, latestEvents, type PublicEvent } from '../../app/utils/events'

const event = (id: number, logo: string | null = null): PublicEvent => ({
  id, libelle: `Event ${id}`, place: 'Paris', logo, year: 2026,
})

describe('latestEvents', () => {
  it('HOME-03/HOME-06: keeps at most the requested number, in the api2 order', () => {
    const events = [9, 8, 7, 6, 5, 4, 3, 2].map(id => event(id))
    expect(latestEvents(events, 6).map(e => e.id)).toEqual([9, 8, 7, 6, 5, 4])
  })

  it('HOME-06: returns every event when there are fewer than requested', () => {
    expect(latestEvents([event(1)], 6)).toHaveLength(1)
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
