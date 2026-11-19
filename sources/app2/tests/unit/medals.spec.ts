import { describe, expect, it } from 'vitest'
import {
  byFamily, isTimelineStat, medalExplanation, medalLabel, medalName, playerKey, svgWithUniqueIds, type MedalItem,
} from '../../utils/medals'

const medal = (overrides: Partial<MedalItem> = {}): MedalItem => ({
  code: 'powerplay', type: 'medal', scopes: ['T'], family: 'superiorite', data_level: 'S',
  name_fr: 'Supériorité', name_en: 'Powerplay', explanation_fr: 'Explication', explanation_en: 'Explanation',
  icon: 'powerplay', icon_svg: '<path/>', ...overrides,
})

describe('medal names', () => {
  it('double name in French, the English name alone in English', () => {
    expect(medalName(medal(), 'fr')).toEqual({ main: 'Supériorité', english: 'Powerplay' })
    expect(medalName(medal(), 'en')).toEqual({ main: 'Powerplay', english: null })
    expect(medalLabel(medal(), 'fr')).toBe('Supériorité · Powerplay')
    expect(medalLabel(medal(), 'en')).toBe('Powerplay')
  })

  it('no double name when both languages agree', () => {
    expect(medalName(medal({ name_fr: 'Marathon', name_en: 'Marathon' }), 'fr').english).toBeNull()
  })

  it('explanation follows the language', () => {
    expect(medalExplanation(medal(), 'fr')).toBe('Explication')
    expect(medalExplanation(medal(), 'en')).toBe('Explanation')
  })
})

describe('catalogue helpers', () => {
  it('groups by family in the order of the first element', () => {
    const groups = byFamily([medal({ code: 'a', family: 'elan' }), medal({ code: 'b' }), medal({ code: 'c', family: 'elan' })])
    expect(groups.map(g => g.family)).toEqual(['elan', 'superiorite'])
    expect(groups[0]!.items.map(i => i.code)).toEqual(['a', 'c'])
  })

  it('a timeline statistic carries its games count', () => {
    expect(isTimelineStat({ games_count: 0 })).toBe(true)
    expect(isTimelineStat({})).toBe(false)
  })

  it('makes mask ids unique per drawing', () => {
    expect(svgWithUniqueIds('<mask id="m-goal"/><g mask="url(#m-goal)"/>', '3'))
      .toBe('<mask id="m-goal-3"/><g mask="url(#m-goal-3)"/>')
  })

  it('meets stats and medals on accent- and case-insensitive names', () => {
    expect(playerKey('Éloïse', ' Dupont ')).toBe(playerKey('eloise', 'DUPONT'))
    expect(playerKey(null, undefined)).toBe('|')
  })
})
