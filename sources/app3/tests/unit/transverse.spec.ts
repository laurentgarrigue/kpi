import { describe, expect, it } from 'vitest'
import { clubMarkers, clubView, clubWebsite, type ClubSummary } from '../../app/utils/clubs'
import { seasonAnchor, splitPodium, type PodiumEntry } from '../../app/utils/history'
import { searchApiPath, searchHits, searchQuery, searchSections, type SearchResults } from '../../app/utils/search'
import { honoursBySeason, playerName, selectedRoster, type TeamHonour } from '../../app/utils/teams'

describe('team page (PAGE_TEAM.md)', () => {
  const seasons = [
    { season: '2999', competitions: [{ code: 'RCH', display_title: 'Championnat' }] },
    { season: '2998', competitions: [{ code: 'RCP', display_title: 'Coupe' }, { code: 'RQL', display_title: 'Qualif' }] },
  ]

  it('TEA-03: the requested roster when the team played it, otherwise the most recent', () => {
    expect(selectedRoster(seasons, { season: '2998', competition: 'RQL' })).toEqual({ season: '2998', competition: 'RQL' })
    expect(selectedRoster(seasons, { roster: '2998|RCP' })).toEqual({ season: '2998', competition: 'RCP' })
    expect(selectedRoster(seasons, { season: '2998', competition: 'RCH' })).toEqual({ season: '2999', competition: 'RCH' })
    expect(selectedRoster(seasons, {})).toEqual({ season: '2999', competition: 'RCH' })
    expect(selectedRoster([], { season: '2999', competition: 'RCH' })).toBeNull()
  })

  it('players are written « NOM Prénom »', () => {
    expect(playerName({ last_name: 'ALPHA', first_name: 'Ann' })).toBe('ALPHA Ann')
    expect(playerName({ last_name: 'ALPHA', first_name: null })).toBe('ALPHA')
  })
})

describe('history (PAGE_HISTORY.md)', () => {
  const entry = (rank: number): PodiumEntry => ({ rank, team: { number: rank, label: `T${rank}`, logo: null }, medal: rank <= 3 ? rank as 1 | 2 | 3 : null })

  it('HIS-03: podium first, other ranked teams folded', () => {
    const { top, others } = splitPodium([1, 2, 2, 4, 5].map(entry))
    expect(top.map(item => item.rank)).toEqual([1, 2, 2])
    expect(others.map(item => item.rank)).toEqual([4, 5])
    expect(seasonAnchor('2019')).toBe('saison-2019')
  })
})

describe('clubs (PAGE_CLUBS.md)', () => {
  const club = (code: string, position: ClubSummary['position']): ClubSummary => ({ code, label: code, department: { code: null, label: null }, logo: null, position })

  it('CLB-02: the map only shows clubs having a position; the list is the default view', () => {
    expect(clubMarkers([club('A', { lat: 44.8, lng: -0.5 }), club('B', null)]).map(item => item.code)).toEqual(['A'])
    expect(clubView('map')).toBe('map')
    expect(clubView('grid')).toBe('list')
  })

  it('CLB-03: websites stored without scheme become absolute', () => {
    expect(clubWebsite('www.club.fr')).toBe('https://www.club.fr')
    expect(clubWebsite('http://club.fr')).toBe('http://club.fr')
    expect(clubWebsite(null)).toBeNull()
  })
})

describe('global search (FEATURE_SEARCH.md)', () => {
  const results: SearchResults = {
    competitions: [{ season: '2999', code: 'RCH', display_title: 'Championnat Résultats', soustitre2: 'Poule A', group: 'TSTRES' }],
    events: [{ id: 77, libelle: 'Tournoi Résultats', place: 'Rivecity', start: null, end: null }],
    teams: [],
    clubs: [{ code: 'C001', label: 'Club Alpha', department: { code: 'CD33', label: 'Gironde' }, logo: null, position: null }],
  }

  it('SRC-05: queries shorter than 2 or longer than 50 characters are not sent', () => {
    expect(searchQuery('  saint   malo ')).toBe('saint malo')
    expect(searchQuery('a')).toBeNull()
    expect(searchQuery('x'.repeat(51))).toBeNull()
    expect(searchQuery(['ab'])).toBeNull()
    expect(searchApiPath('saint malo')).toBe('/search?q=saint+malo')
  })

  it('SRC-03: one section per non-empty category, each hit linking to its site page', () => {
    expect(searchSections(results).map(section => section.category)).toEqual(['competitions', 'events', 'clubs'])
    expect(searchHits(results, 'competitions')[0]).toEqual({ key: 'c-2999-RCH', label: 'Championnat Résultats', detail: 'Poule A · 2999', to: '/competitions/2999/RCH' })
    expect(searchHits(results, 'events')[0]!.to).toBe('/events/77')
    expect(searchHits(results, 'clubs')[0]!.to).toBe('/clubs/C001')
  })
})

describe('honoursBySeason', () => {
  const honour = (season: string, code: string): TeamHonour => ({
    season, competition: { code, display_title: code, group: null }, rank: 1, medal: null, final_round: true,
  })

  it('TEA-07: groups consecutive honours of the same season, keeping the api2 order', () => {
    const groups = honoursBySeason([honour('2026', 'N1H'), honour('2026', 'CFH'), honour('2025', 'N1H')])
    expect(groups.map(group => [group.season, group.honours.map(item => item.competition.code)])).toEqual([
      ['2026', ['N1H', 'CFH']],
      ['2025', ['N1H']],
    ])
    expect(honoursBySeason([])).toEqual([])
  })
})
