import { describe, expect, it } from 'vitest'
import {
  AGGREGATE_TABS,
  COMPETITION_TABS,
  competitionPath,
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
    expect(COMPETITION_TABS).toEqual(['games', 'pitches', 'info', 'progress', 'ranking', 'stats'])
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
