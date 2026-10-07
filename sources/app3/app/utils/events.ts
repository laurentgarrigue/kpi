import { joinURL } from 'ufo'

/** Public event, as returned by api2 `GET /events/{mode}` (PAGE_HOME.md § 3). */
export interface PublicEvent {
  id: number
  libelle: string
  place: string
  logo: string | null
  year: number
}

/** The first `limit` events: api2 already sorts them from the most recent. */
export function latestEvents(events: readonly PublicEvent[], limit: number): PublicEvent[] {
  return events.slice(0, limit)
}

/** Event page in app2, until app3 has its own (phase 2). */
export function eventUrl(id: number, app2BaseUrl: string): string {
  return joinURL(app2BaseUrl, 'event', String(id))
}

/** Absolute URL of an event logo stored under the legacy `/img/` tree, or `null` without logo. */
export function eventLogoUrl(logo: string | null, legacyBaseUrl: string): string | null {
  return logo ? joinURL(legacyBaseUrl, 'img', logo) : null
}
