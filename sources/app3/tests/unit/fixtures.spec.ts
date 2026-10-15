import { describe, expect, it } from 'vitest'
import { eventFixture, fixtureKey, fixturePath, fixturePlace, fixtureTitle, type GroupFixture } from '../../app/utils/fixtures'

const group: GroupFixture = {
  kind: 'group', season: '2026', code: 'N1H', libelle: 'Nationale 1 Hommes', libelle_en: 'National 1 Men',
  place: 'Saint-Omer', department: '62', logo: null, start: '2026-10-17', end: '2026-10-18',
}
const event = eventFixture({ id: 77, libelle: 'Tournoi', place: 'Rivecity', logo: null, start: '2026-10-17', end: '2026-10-18' })

describe('fixtures (PAGE_HOME.md § 2)', () => {
  it('HOME-04 / HOME-09: an event leads to its event page, a group to its group page', () => {
    expect(fixturePath(event)).toBe('/events/77')
    expect(fixturePath(group)).toBe('/groups/2026/N1H')
  })

  it('HOME-09: a group is titled in the page language, falling back to its French label', () => {
    expect(fixtureTitle(group, 'fr')).toBe('Nationale 1 Hommes')
    expect(fixtureTitle(group, 'en')).toBe('National 1 Men')
    expect(fixtureTitle({ ...group, libelle_en: null }, 'en')).toBe('Nationale 1 Hommes')
    expect(fixtureTitle(event, 'en')).toBe('Tournoi')
  })

  it('HOME-09: a group place carries its department when known', () => {
    expect(fixturePlace(group)).toBe('Saint-Omer (62)')
    expect(fixturePlace({ ...group, department: null })).toBe('Saint-Omer')
    expect(fixturePlace(event)).toBe('Rivecity')
  })

  it('keys tell events and group days apart', () => {
    expect(fixtureKey(event)).toBe('event-77')
    expect(fixtureKey(group)).toBe('group-2026-N1H-2026-10-17-Saint-Omer')
  })
})
