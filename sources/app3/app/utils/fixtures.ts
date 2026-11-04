import { joinURL } from 'ufo'
import type { PublicEvent } from './events'

/** Fixture of the home page « Coming up » / « Recently » (PAGE_HOME.md § 2, API_PUBLIC_TRANSVERSE.md § 3.7). */
export interface EventFixture extends PublicEvent {
  kind: 'event'
}

/** Gamedays outside any event, of one competition group, on the same dates and place. */
export interface GroupFixture {
  kind: 'group'
  season: string
  code: string
  libelle: string
  libelle_en: string | null
  place: string | null
  department: string | null
  logo: string | null
  start: string | null
  end: string | null
}

export type Fixture = EventFixture | GroupFixture

/** api2 `GET /fixtures/site`: split on api2's « today » (Europe/Paris), sorted, 9 at most each. */
export interface HomeFixtures {
  today: string
  upcoming: Fixture[]
  recent: Fixture[]
}

/** Site page of the fixture: the event page, or the group page. */
export function fixturePath(fixture: Fixture): string {
  return fixture.kind === 'event' ? `/events/${fixture.id}` : `/groups/${fixture.season}/${fixture.code}`
}

/** Event name, or group label in the page language (French label when there is no English one). */
export function fixtureTitle(fixture: Fixture, locale: string): string {
  return fixture.kind === 'group' && locale === 'en' && fixture.libelle_en ? fixture.libelle_en : fixture.libelle
}

/** Place, with the department of a group day when known (« Saint-Omer (62) »). */
export function fixturePlace(fixture: Fixture): string {
  const place = fixture.place ?? ''
  return fixture.kind === 'group' && fixture.department ? `${place} (${fixture.department})` : place
}

export function fixtureKey(fixture: Fixture): string {
  return fixture.kind === 'event'
    ? `event-${fixture.id}`
    : `group-${fixture.season}-${fixture.code}-${fixture.start}-${fixture.place}`
}

/** When the next fixture takes place, relative to today (HOME-11). */
export type RelativeWhen = { kind: 'now' | 'tomorrow' | 'weekend' } | { kind: 'days', days: number }

const DAY_MS = 86_400_000
const WEEK_DAYS = 6
const WEEKEND_DAYS = new Set([0, 6])

/** « En ce moment », « Demain », « Ce week-end » (within 6 days, starting on Saturday or Sunday), « Dans n jours ». */
export function relativeWhen(start: string, end: string | null, today: string): RelativeWhen {
  if (start <= today && today <= (end ?? start)) {
    return { kind: 'now' }
  }
  const days = Math.round((Date.parse(start) - Date.parse(today)) / DAY_MS)
  if (days === 1) {
    return { kind: 'tomorrow' }
  }
  return days <= WEEK_DAYS && WEEKEND_DAYS.has(new Date(start).getUTCDay()) ? { kind: 'weekend' } : { kind: 'days', days }
}

/**
 * Upcoming fixtures as the home page shows them (HOME-12): the next one leads; group days held on its very dates are
 * its programme, not repeated in the rest of the agenda.
 */
export function homeAgenda(upcoming: readonly Fixture[]): { next: Fixture | null, programme: GroupFixture[], after: Fixture[] } {
  const [next, ...rest] = upcoming
  if (!next) {
    return { next: null, programme: [], after: [] }
  }
  const programme = next.kind === 'event'
    ? rest.filter((item): item is GroupFixture => item.kind === 'group' && item.start === next.start && item.end === next.end)
    : []
  return { next, programme, after: rest.filter(item => !programme.includes(item as GroupFixture)) }
}

/** Live view of a fixture in app2. */
export function liveUrl(fixture: Fixture, app2BaseUrl: string): string {
  return fixture.kind === 'event'
    ? joinURL(app2BaseUrl, 'event', String(fixture.id))
    : joinURL(app2BaseUrl, 'group', fixture.season, fixture.code)
}
