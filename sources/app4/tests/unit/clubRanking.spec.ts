// Admin page of the national club ranking (PAGE_CLASSEMENT_NATIONAL_CLUBS.md § 8): pure display rules.
import { describe, expect, it } from 'vitest'
import { alertLinks, articleGroups, groupAlerts, linesCsv, parseCodes, seasonActions, standingsCsv, unresolvedOfficials, type ClubRankingRule, type RankingAlert } from '../../utils/clubRanking'

const rule = (article: string, rank: number, points = 10): ClubRankingRule => ({
  id: rank, code: `${article}.${rank}`, article, rank, label: `Rubrique ${article}.${rank}`, short_label: `R${rank}`, points, type: 'TEAM_OF_CLUB', params: {}, note: '',
})

const alert = (kind: string, blocking = false, context: Record<string, unknown> = {}): RankingAlert => ({ kind, message: kind, rule: null, blocking, context })

describe('columns of the ranking', () => {
  it('groups the rules by article, in the order of the annex (7.4.10 after 7.4.9)', () => {
    const groups = articleGroups([rule('7.4', 10), rule('7.1', 2), rule('7.4', 9), rule('7.1', 1)])
    expect(groups.map(group => group.article)).toEqual(['7.1', '7.4'])
    expect(groups[1]!.rules.map(item => item.code)).toEqual(['7.4.9', '7.4.10'])
  })
})

describe('life cycle of a season (§ 8)', () => {
  it('computes while draft or computed, validates an up-to-date calculation, publishes a validated one', () => {
    expect(seasonActions({ status: 'draft', stale: false, computed_at: null })).toMatchObject({ compute: true, validate: false, publish: false, reopen: false })
    expect(seasonActions({ status: 'computed', stale: false, computed_at: 'x' })).toMatchObject({ compute: true, validate: true })
    expect(seasonActions({ status: 'computed', stale: true, computed_at: 'x' }).validate).toBe(false)
    expect(seasonActions({ status: 'validated', stale: false, computed_at: 'x' })).toMatchObject({ editable: false, compute: false, publish: true, reopen: true })
    expect(seasonActions({ status: 'published', stale: false, computed_at: 'x' })).toMatchObject({ editable: false, publish: false, reopen: true })
  })
})

describe('alerts of a calculation', () => {
  it('shows the blocking alerts first, grouped by kind', () => {
    const groups = groupAlerts([alert('organiser_unknown'), alert('ranking_not_published', true), alert('organiser_unknown')])
    expect(groups.map(group => [group.kind, group.items.length, group.blocking])).toEqual([['ranking_not_published', 1, true], ['organiser_unknown', 2, false]])
  })

  it('lists the officials to match with a licensee', () => {
    expect(unresolvedOfficials([alert('official_unresolved', false, { name: 'MARTIN Paul', gamedays: [3], candidates: [1, 2] }), alert('selection_team')]))
      .toEqual([{ name: 'MARTIN Paul', gamedays: [3], competitions: {}, candidates: [1, 2] }])
  })
})

describe('links of an alert', () => {
  it('points to the gamedays and to the competition of the alert', () => {
    expect(alertLinks(alert('organiser_unknown', false, { gamedays: [12, 13], gameday_competitions: { 12: 'N1D', 13: 'N1D' } })).map(link => link.to)).toEqual(['/gamedays?competition=N1D&search=12', '/gamedays?competition=N1D&search=13'])
    expect(alertLinks(alert('gameday_without_date', false, { gameday: 7 })).map(link => link.label)).toEqual(['#7'])
    expect(alertLinks(alert('ranking_not_published', true, { category: 'N1D', competition: 'N1D' }))).toEqual([{ label: 'N1D', to: '/competitions?search=N1D' }])
    expect(alertLinks(alert('after_season'))).toEqual([])
  })
})

describe('codes and exports', () => {
  it('reads competition codes separated by commas or spaces', () => {
    expect(parseCodes(' n15*, N15A  cf15;')).toEqual(['N15*', 'N15A', 'CF15'])
  })

  it('exports the ranking with one column per rule and the lines of detail', () => {
    const headers = { rank: 'Rang', club: 'Club', number: 'N°', department: 'Dpt', region: 'Région', total: 'Total' }
    const text = standingsCsv([{ rank: 1, club: '5006', label: 'CANOE CLUB D\'AVRANCHES', department: 'Manche', region: 'Normandie', total: 30, by_rule: { '7.1.1': 20, '7.1.3': 10 } }], [rule('7.1', 3), rule('7.1', 1), rule('7.2', 1)], headers)
    expect(text.split('\n')).toEqual(['Rang;Club;N°;Dpt;Région;7.1.1;7.1.3;7.2.1;Total', '1;CANOE CLUB D\'AVRANCHES;5006;Manche;Normandie;20;10;;30'])
    const detail = linesCsv([{ rule: '7.1.1', club: '5006', points: 20, reason: 'Équipe "Avranches I"; U15', source_type: 'team', source_id: '1:U15', matric: null }], () => 'Avranches', { rule: 'Rubrique', club: 'Club', number: 'N°', points: 'Points', reason: 'Motif' })
    expect(detail.split('\n')[1]).toBe('7.1.1;Avranches;5006;20;"Équipe ""Avranches I""; U15"')
  })
})
