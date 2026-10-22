import { describe, expect, it } from 'vitest'
import { fixtureKey, fixturePath, fixturePlace, fixtureTitle, homeAgenda, liveUrl, relativeWhen, type EventFixture, type GroupFixture } from '../../app/utils/fixtures'

const group: GroupFixture = {
  kind: 'group', season: '2026', code: 'N1H', libelle: 'Nationale 1 Hommes', libelle_en: 'National 1 Men',
  place: 'Saint-Omer', department: '62', logo: null, start: '2026-10-17', end: '2026-10-18',
}
const event: EventFixture = { kind: 'event', id: 77, libelle: 'Tournoi', place: 'Rivecity', logo: null, start: '2026-10-17', end: '2026-10-18' }

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

describe('next fixture of the home page (PAGE_HOME.md § 2 bis)', () => {
  // Thursday 22 October 2026.
  const today = '2026-10-22'

  it('HOME-11: ongoing, tomorrow, this weekend (within 6 days, starting on Saturday or Sunday), or in n days', () => {
    expect(relativeWhen('2026-10-21', '2026-10-23', today)).toEqual({ kind: 'now' })
    expect(relativeWhen('2026-10-23', '2026-10-24', today)).toEqual({ kind: 'tomorrow' })
    expect(relativeWhen('2026-10-24', '2026-10-25', today)).toEqual({ kind: 'weekend' })
    expect(relativeWhen('2026-10-27', '2026-10-27', today)).toEqual({ kind: 'days', days: 5 })
    expect(relativeWhen('2026-10-31', '2026-11-01', today)).toEqual({ kind: 'days', days: 9 })
  })

  it('HOME-12: the next fixture leads; groups on its very dates are its programme, not repeated after', () => {
    const next = { ...event, start: '2026-10-24', end: '2026-10-25' }
    const sameDates = { ...group, start: '2026-10-24', end: '2026-10-25' }
    const later = { ...group, code: 'N2H', start: '2026-11-07', end: '2026-11-08' }
    const agenda = homeAgenda([next, sameDates, later])
    expect(agenda.next).toBe(next)
    expect(agenda.programme).toEqual([sameDates])
    expect(agenda.after).toEqual([later])
    expect(homeAgenda([sameDates, later])).toEqual({ next: sameDates, programme: [], after: [later] })
    expect(homeAgenda([])).toEqual({ next: null, programme: [], after: [] })
  })

  it('HOME-11: live link of an event or a group in app2', () => {
    expect(liveUrl(event, 'https://app.test')).toBe('https://app.test/event/77')
    expect(liveUrl(group, 'https://app.test')).toBe('https://app.test/group/2026/N1H')
  })
})
