import { describe, expect, it } from 'vitest'
import { clubMarkers, clubView, clubWebsite, type ClubSummary } from '../../app/utils/clubs'
import { decadeAnchors, medalist, seasonAnchor, seasonFinals, teamKey, titleHolder, type History, type HistoryCompetition, type PodiumEntry } from '../../app/utils/history'
import { searchApiPath, searchHits, searchKinds, searchQuery, searchSections, type SearchResults } from '../../app/utils/search'
import { honoursBySeason, medalTally, playerName, selectedRoster, type TeamHonour } from '../../app/utils/teams'

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

  it('TEA-08: medals of final rounds only, by colour', () => {
    const honour = (medal: 1 | 2 | 3 | null, final: boolean): TeamHonour => ({ season: '2999', competition: { code: 'X', display_title: 'X', group: null }, rank: medal ?? 5, medal, final_round: final })
    expect(medalTally([honour(1, true), honour(1, true), honour(2, true), honour(1, false), honour(null, true)])).toEqual({ 1: 2, 2: 1, 3: 0 })
  })

  it('players are written « NOM Prénom »', () => {
    expect(playerName({ last_name: 'ALPHA', first_name: 'Ann' })).toBe('ALPHA Ann')
    expect(playerName({ last_name: 'ALPHA', first_name: null })).toBe('ALPHA')
  })
})

describe('history (PAGE_HISTORY.md § 2 bis)', () => {
  const team = (label: string, number: number | null = null) => ({ number, label, logo: null })
  const podium = (gold: string, silver = 'S', bronze = 'B'): PodiumEntry[] => [
    { rank: 1, team: team(gold, gold.length), medal: 1 },
    { rank: 2, team: team(silver), medal: 2 },
    { rank: 3, team: team(bronze), medal: 3 },
    { rank: 4, team: team('Fourth'), medal: null },
  ]
  const competition = (code: string, entries: PodiumEntry[]): HistoryCompetition => ({ code, display_title: code, soustitre2: null, type: 'CP', podium: entries })
  const history = (...seasons: [string, string][]): History => ({
    group: { code: 'N1H', libelle: 'Nationale 1', libelle_en: null },
    seasons: seasons.map(([season, gold]) => ({
      season,
      competitions: [competition('QUALIF', podium('Q').map(item => ({ ...item, medal: null }))), competition('FINAL', podium(gold))],
    })),
  })

  it('HIS-06: the final of each season is its first competition awarding a gold medal', () => {
    const finals = seasonFinals(history(['2026', 'Avranches'], ['2025', 'Acigné']))
    expect(finals.map(item => [item.season, item.competition.code, medalist(item.competition, 1)?.team.label])).toEqual([
      ['2026', 'FINAL', 'Avranches'],
      ['2025', 'FINAL', 'Acigné'],
    ])
  })

  it('HIS-06: the title holder, its current streak and since when, or its number of titles', () => {
    const streak = titleHolder(seasonFinals(history(['2026', 'Avranches'], ['2025', 'Avranches'], ['2024', 'Avranches'], ['2023', 'Acigné'], ['2019', 'Avranches'])))
    expect(streak).toMatchObject({ season: '2026', streak: 3, since: '2024', titles: 4 })
    expect(streak?.champion.team.label).toBe('Avranches')
    expect(streak?.others.map(item => item.rank)).toEqual([2, 3])
    expect(titleHolder(seasonFinals(history(['2026', 'Acigné'], ['2025', 'Avranches'], ['2019', 'Acigné'])))).toMatchObject({ streak: 1, titles: 2 })
    expect(titleHolder([])).toBeNull()
  })

  it('HIS-07: a team is the same across seasons by its number, else its name', () => {
    expect(teamKey(team('Avranches I', 67))).toBe('67')
    expect(teamKey(team('Avranches I'))).toBe('Avranches I')
  })

  it('HIS-01 / HIS-07: one anchor per decade, on its most recent season', () => {
    expect(decadeAnchors(['2026', '2021', '2020', '2019', '2011', '1999'])).toEqual([
      { decade: '2020', season: '2026' },
      { decade: '2010', season: '2019' },
      { decade: '1990', season: '1999' },
    ])
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
    news: [{ slug: 'finale', title: 'Finale', lead: 'Le chapô.', cover: null, published_at: '2020-06-01T12:00:00+02:00', categories: [] }],
    pages: [{ title: 'Arbitrage', path: '/le-kayak-polo/arbitrage' }],
  }

  it('SRC-05: queries shorter than 2 or longer than 50 characters are not sent', () => {
    expect(searchQuery('  saint   malo ')).toBe('saint malo')
    expect(searchQuery('a')).toBeNull()
    expect(searchQuery('x'.repeat(51))).toBeNull()
    expect(searchQuery(['ab'])).toBeNull()
    expect(searchApiPath('saint malo')).toBe('/search?q=saint+malo')
    expect(searchApiPath('saint malo', 'en')).toBe('/search?q=saint+malo&lang=en')
  })

  it('SRC-03: one section per non-empty category, each hit linking to its site page', () => {
    expect(searchSections(results).map(section => section.category)).toEqual(['competitions', 'events', 'clubs', 'news', 'pages'])
    expect(searchHits(results, 'competitions')[0]).toEqual({ key: 'c-2999-RCH', label: 'Championnat Résultats', detail: 'Poule A', aside: '2999', to: '/competitions/2999/RCH' })
    expect(searchHits(results, 'events')[0]!.to).toBe('/events/77')
    expect(searchHits(results, 'clubs')[0]!.to).toBe('/clubs/C001')
    // CMS-11: news and editorial pages (phase 4).
    expect(searchHits(results, 'news')[0]).toEqual({ key: 'n-finale', label: 'Finale', detail: 'Le chapô.', aside: 'juin 2020', to: '/news/finale' })
    expect(searchHits(results, 'clubs')[0]!.aside).toBe('C001')
    expect(searchHits(results, 'pages')[0]!.to).toBe('/le-kayak-polo/arbitrage')
  })

  it('SRC-07: one filter per non-empty category with its count; the first club is the best match, not repeated below', () => {
    const all = searchKinds(results, null)
    expect(all.counts).toEqual([['competitions', 1], ['events', 1], ['clubs', 1], ['news', 1], ['pages', 1]])
    expect(all.total).toBe(5)
    expect(all.best?.code).toBe('C001')
    expect(all.sections.map(section => section.category)).toEqual(['competitions', 'events', 'news', 'pages'])
    const clubs = searchKinds(results, 'clubs')
    expect(clubs.best).toBeNull()
    expect(clubs.sections.map(section => section.category)).toEqual(['clubs'])
    expect(searchKinds(results, 'teams').sections).toEqual([])
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
