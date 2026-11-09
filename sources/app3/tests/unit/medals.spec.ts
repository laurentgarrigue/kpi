// Match medals: pure rules of the display (FEATURE_MATCH_MEDALS.md § 7, § 8).
import { describe, expect, it } from 'vitest'
import { api2Fixture } from '../fixtures/api2'
import { byFamily, isTimelineStat, medalExplanation, medalLabel, medalName, svgWithUniqueIds, type MedalCatalog } from '../../app/utils/medals'

const catalog = api2Fixture('/medals/catalog?season=2998').body as MedalCatalog
const item = (code: string) => catalog.items.find(entry => entry.code === code)!

describe('medal names (§ 1.1)', () => {
  it('MED-42: double name in French, the English name alone in English', () => {
    expect(medalName(item('powerplay'), 'fr')).toEqual({ main: 'Supériorité', english: 'Powerplay' })
    expect(medalName(item('powerplay'), 'en')).toEqual({ main: 'Powerplay', english: null })
    expect(medalName({ ...item('opener'), name_fr: 'Marathon', name_en: 'Marathon' }, 'fr')).toEqual({ main: 'Marathon', english: null })
    expect(medalLabel(item('powerplay'), 'fr')).toBe('Supériorité · Powerplay')
    expect(medalLabel(item('powerplay'), 'en')).toBe('Powerplay')
  })

  it('MED-41: the explanation of the language, rendered by api2 with the thresholds of the season', () => {
    expect(medalExplanation(item('hat_trick'), 'fr')).toBe('Marque au moins 3 buts dans le match.')
    expect(medalExplanation(item('hat_trick'), 'en')).toBe('Scores at least 3 goals in the game.')
  })
})

describe('catalogue', () => {
  it('MED-48: elements grouped by family in the order of the catalogue, statistics apart', () => {
    const groups = byFamily(catalog.items.filter(entry => entry.type !== 'stat'))
    expect(groups[0]!.family).toBe('elan')
    expect(groups[0]!.items.map(entry => entry.code)).toEqual(['opener'])
    expect(groups.map(group => group.family)).toContain('arbitrage')
  })

  it('a timeline statistic is recognised by the perimeter counts api2 adds to it', () => {
    expect(isTimelineStat({ kind: 'powerplay_rate', columns: [], rows: [], games_count: 4 })).toBe(true)
    expect(isTimelineStat({ kind: 'scorers', columns: [], rows: [] })).toBe(false)
  })
})

describe('pictograms (§ 7.1)', () => {
  it('the masks of a composed pictogram get ids unique in the page', () => {
    const svg = '<mask id="m-ref_knockout"><rect/></mask><g mask="url(#m-ref_knockout)"></g>'
    expect(svgWithUniqueIds(svg, 'x1')).toBe('<mask id="m-ref_knockout-x1"><rect/></mask><g mask="url(#m-ref_knockout-x1)"></g>')
  })
})
