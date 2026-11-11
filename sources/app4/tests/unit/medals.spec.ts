// Admin page of the match medals (FEATURE_MATCH_MEDALS.md § 9.3): pure display rules of the statistics.
import { describe, expect, it } from 'vitest'
import { changedThresholds, thresholdChanges, formatStatValue, formatThreshold, humanizeKey, isRefereeKind, matchesQuery, statRowText, statSubject, thresholdsUsed } from '../../utils/medals'

describe('statistics of the admin page', () => {
  it('names the subject of a row: team, referee, pair, player or game', () => {
    expect(statSubject({ team: { label: 'Medal Oscar' } })).toBe('Medal Oscar')
    expect(statSubject({ referee: { first_name: 'One', last_name: 'REFEREE' } })).toBe('REFEREE One')
    expect(statSubject({ referees: [{ first_name: 'One', last_name: 'REFEREE' }, { first_name: 'Two', last_name: 'REFEREE' }] })).toBe('REFEREE One + REFEREE Two')
    expect(statSubject({ first_name: 'Ana', last_name: 'MEDAL', team: { label: 'Medal Oscar' } })).toBe('MEDAL Ana (Medal Oscar)')
    expect(statSubject({ game: { number: 61, team_a: { label: 'A' }, team_b: { label: 'B' }, score_a: 4, score_b: 3 } })).toBe('n° 61 · A 4-3 B')
    expect(statSubject({ from: 120, to: 240 })).toBe('2–4′')
    expect(statSubject({ from: 1200, to: null })).toBe('1200 s +')
  })

  it('MED-54: a missing indicator (too few games) reads « — », a rate « % », nested counts inline', () => {
    expect(formatStatValue(null, 'decimal')).toBe('—')
    expect(formatStatValue(67, 'percent')).toBe('67 %')
    expect(formatStatValue({ V: 2, J: 1 }, 'integer')).toBe('V 2 · J 1')
    expect(formatStatValue([0, 2, 1], 'histogram')).toBe('0 2 1')
    expect(formatStatValue(2.5, 'decimal')).toBe('2.5')
  })

  it('referee statistics are shown in their own tab', () => {
    expect(isRefereeKind('referee_cards')).toBe(true)
    expect(isRefereeKind('medals_referees')).toBe(true)
    expect(isRefereeKind('powerplay_rate')).toBe(false)
  })
})

describe('search of the admin page (front-end only)', () => {
  it('matches without case or accents, every word of the query', () => {
    expect(matchesQuery('supériorite', 'Supériorité · Powerplay')).toBe(true)
    expect(matchesQuery('POWER sup', 'Supériorité · Powerplay')).toBe(true)
    expect(matchesQuery('triplé', 'Supériorité', 'Powerplay')).toBe(false)
    expect(matchesQuery('', 'anything')).toBe(true)
    expect(matchesQuery('  ', 'anything')).toBe(true)
  })

  it('searches a statistic row on its subject and on its values', () => {
    const row = { team: { label: 'Medal Oscar' }, opened: 3, percent: 67 }
    const columns = [{ key: 'opened', type: 'integer' }, { key: 'percent', type: 'percent' }]
    expect(statRowText(row, columns)).toContain('Medal Oscar')
    expect(matchesQuery('67 %', statRowText(row, columns))).toBe(true)
  })
})

describe('thresholds of the admin page', () => {
  it('lists the current thresholds an element uses, from the variables of its explanation', () => {
    const current = { bounceBackSeconds: 60, comebackDeficit: 3, permanentCards: ['D'] }
    expect(thresholdsUsed('Marque moins de {bounceBackSeconds} secondes après {bounceBackSeconds} ou {comebackDeficit}.', current))
      .toEqual([{ key: 'bounceBackSeconds', value: 60 }, { key: 'comebackDeficit', value: 3 }])
    expect(thresholdsUsed('Le match va en prolongation.', current)).toEqual([])
    expect(thresholdsUsed('{unknown}', current)).toEqual([])
  })

  it('shows what a season froze differently from the current thresholds', () => {
    expect(changedThresholds({ a: 1, b: 2, c: ['D'] }, { a: 1, b: 3, c: ['D'] })).toEqual([{ key: 'b', frozen: 2, current: 3 }])
    expect(changedThresholds({ a: 1 }, { a: 1 })).toEqual([])
  })

  it('reads a threshold key and value', () => {
    expect(humanizeKey('bounceBackSeconds')).toBe('Bounce back seconds')
    expect(formatThreshold(['D', 'R'])).toBe('D, R')
    expect(formatThreshold(60)).toBe('60')
  })
})

describe('editing the default thresholds', () => {
  const current = { bounceBackSeconds: 30, hatTrickGoals: 3, permanentCards: ['D'] }

  it('sends only what differs from the current thresholds', () => {
    expect(thresholdChanges({ bounceBackSeconds: 45, hatTrickGoals: 3, permanentCards: ['D'] }, current)).toEqual({ bounceBackSeconds: 45 })
    expect(thresholdChanges({ bounceBackSeconds: 30, hatTrickGoals: 3, permanentCards: ['D'] }, current)).toEqual({})
  })

  it('reads a typed number, and keeps cards in their order', () => {
    expect(thresholdChanges({ bounceBackSeconds: '45', hatTrickGoals: 3, permanentCards: ['D'] }, current)).toEqual({ bounceBackSeconds: 45 })
    expect(thresholdChanges({ bounceBackSeconds: 30, hatTrickGoals: 3, permanentCards: ['D', 'R'] }, current)).toEqual({ permanentCards: ['R', 'D'] })
    expect(thresholdChanges({ bounceBackSeconds: 30, hatTrickGoals: 3, permanentCards: ['R', 'D'] }, { ...current, permanentCards: ['R', 'D'] })).toEqual({})
  })

  it('leaves an empty field out (the form shows it as invalid)', () => {
    expect(thresholdChanges({ bounceBackSeconds: '', hatTrickGoals: 3, permanentCards: ['D'] }, current)).toEqual({})
  })
})
