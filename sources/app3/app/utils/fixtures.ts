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

export function eventFixture(event: PublicEvent): EventFixture {
  return { ...event, kind: 'event' }
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
