import { describe, expect, it } from 'vitest'
import {
  AGGREGATE_TABS,
  COMPETITION_TABS,
  competitionPath,
  eventTabPath,
  selectedCompetition,
  defaultGroup,
  defaultTab,
  groupLabel,
  progressView,
} from '../../app/utils/competitions'
import type { GroupSection } from '../../../kpi-layer/app/utils/results/types'

const sections: GroupSection[] = [
  { section: 1, label: 'Competitions_Internationales', groups: [{ code: 'CM', libelle: 'Championnats du monde', libelle_en: 'World championships' }] },
  { section: 2, label: 'Competitions_Nationales', groups: [{ code: 'N1H', libelle: 'Nationale 1 Hommes', libelle_en: null }, { code: 'N2H', libelle: 'Nationale 2 Hommes', libelle_en: null }] },
]

describe('defaultGroup', () => {
  it('CPL-02: the requested group when it exists for the season', () => {
    expect(defaultGroup(sections, 'N2H')).toBe('N2H')
  })

  it('CPL-02: otherwise the first national group, otherwise the first group', () => {
    expect(defaultGroup(sections, 'UNKNOWN')).toBe('N1H')
    expect(defaultGroup(sections, undefined)).toBe('N1H')
    expect(defaultGroup([sections[0]!], undefined)).toBe('CM')
    expect(defaultGroup([], undefined)).toBeNull()
  })
})

describe('groupLabel', () => {
  it('CPL-03: the English label under /en when set, the French one otherwise', () => {
    expect(groupLabel(sections[0]!.groups[0]!, 'en')).toBe('World championships')
    expect(groupLabel(sections[1]!.groups[0]!, 'en')).toBe('Nationale 1 Hommes')
    expect(groupLabel(sections[0]!.groups[0]!, 'fr')).toBe('Championnats du monde')
  })
})

describe('tabs', () => {
  it('CMP-04: competition tabs, the phases tab merged into progress', () => {
    expect(COMPETITION_TABS).toEqual(['info', 'games', 'pitches', 'progress', 'ranking', 'stats'])
    expect(AGGREGATE_TABS).toEqual(['games', 'pitches'])
  })

  it('CMP-01: ranking for a finished competition, games otherwise', () => {
    expect(defaultTab('END')).toBe('ranking')
    expect(defaultTab('ON')).toBe('games')
    expect(defaultTab('ATT')).toBe('games')
  })

  it('CMP-09: horizontal progress view unless vertical is asked', () => {
    expect(progressView('vertical')).toBe('vertical')
    expect(progressView(undefined)).toBe('horizontal')
    expect(progressView('bogus')).toBe('horizontal')
  })
})

describe('competitionPath', () => {
  it('CMP-03: keeps the tab and the event context', () => {
    expect(competitionPath('2026', 'N1H')).toBe('/competitions/2026/N1H')
    expect(competitionPath('2026', 'N1H', 'ranking')).toBe('/competitions/2026/N1H/ranking')
    expect(competitionPath('2026', 'N1H', 'games', 77)).toBe('/competitions/2026/N1H/games?event=77')
  })
})

describe('event view', () => {
  const competitions = [{ code: 'RCH' }, { code: 'RCP' }]

  it('EVT-03/EVT-07: an event tab, restricted to a competition when given', () => {
    expect(eventTabPath(77, 'games')).toBe('/events/77/games')
    expect(eventTabPath(77, 'ranking', 'RCP')).toBe('/events/77/ranking?competition=RCP')
  })

  it('EVT-06/EVT-07: the requested competition when the event has it; else none, or the first when one is required', () => {
    expect(selectedCompetition(competitions, 'RCP', false)).toEqual({ code: 'RCP' })
    expect(selectedCompetition(competitions, 'NOPE', false)).toBeUndefined()
    expect(selectedCompetition(competitions, undefined, false)).toBeUndefined()
    expect(selectedCompetition(competitions, undefined, true)).toEqual({ code: 'RCH' })
    expect(selectedCompetition(competitions, 'NOPE', true)).toEqual({ code: 'RCH' })
    expect(selectedCompetition([], undefined, true)).toBeUndefined()
  })
})
