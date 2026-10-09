import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mockNuxtImport } from '@nuxt/test-utils/runtime'
import { fakeApi2 } from '../fixtures/api2'
import { expectNotFound, mountRoute } from './results-setup'

const { api2 } = vi.hoisted(() => ({ api2: vi.fn() }))
mockNuxtImport('useApi2', () => () => api2)
beforeEach(() => {
  api2.mockReset()
  api2.mockImplementation(fakeApi2)
  clearNuxtData()
})

describe('competition page frame (PAGE_COMPETITION.md § 2)', () => {
  it('CMP-02: header with title, season, badges, website and live links', async () => {
    const header = (await mountRoute('/competitions/2999/RCH/games')).find('[data-testid="competition-header"]')
    expect(header.find('h1').text()).toBe('Championnat Résultats')
    expect(header.text()).toContain('2999')
    expect(header.find('[data-testid="type-badge"]').text()).toBe('Championnat')
    expect(header.find('[data-testid="status-badge"]').text()).toBe('En cours')
    expect(header.find('[data-testid="competition-visual"]').attributes('src')).toBe('https://kpi.localhost/img/logo/bandeau-rch.png')
    expect(header.find('[data-testid="competition-web"]').attributes('href')).toBe('https://example.test')
    expect(header.find('[data-testid="competition-live"]').attributes('href')).toBe('https://app.kpi.localhost/group/2999/TSTRES')
    expect(header.find('[data-testid="competition-live"]').attributes('target')).toBe('_blank')
  })

  it('CMP-03: siblings of the group keep the current tab', async () => {
    const chips = (await mountRoute('/competitions/2999/RCH/ranking')).findAll('[data-testid="competition-chips"] a')
    expect(chips.map(chip => chip.attributes('href'))).toEqual([
      '/competitions/2999/RCH/ranking', '/competitions/2999/RCP/ranking', '/competitions/2999/RMU/ranking', '/competitions/2999/RAT/ranking',
    ])
    expect(chips[0]!.attributes('aria-current')).toBe('page')
  })

  it('CMP-03: with ?event=, siblings are the event competitions and the parameter is kept', async () => {
    const wrapper = await mountRoute('/competitions/2999/RCP/games?event=77')
    expect(wrapper.findAll('[data-testid="competition-chips"] a').map(chip => chip.attributes('href'))).toEqual([
      '/competitions/2999/RCH/games?event=77', '/competitions/2999/RCP/games?event=77',
    ])
    expect(wrapper.find('[data-testid="results-tabs"] a').attributes('href')).toBe('/competitions/2999/RCP/games?event=77')
  })

  it('CMP-04: tabs are links, the current one marked', async () => {
    const tabs = (await mountRoute('/competitions/2999/RCH/info')).findAll('[data-testid="results-tabs"] a')
    expect(tabs.map(tab => tab.text())).toEqual(['Matchs', 'Terrains', 'Infos', 'Déroulement', 'Classement', 'Stats'])
    expect(tabs.map(tab => tab.attributes('aria-current'))).toEqual([undefined, undefined, 'page', undefined, undefined, undefined])
  })

  it('CMP-16: an event covering every gameday is put forward, with the breadcrumb through it', async () => {
    const wrapper = await mountRoute('/competitions/2999/RCP/ranking')
    expect(wrapper.find('[data-testid="main-event-link"]').attributes('href')).toBe('/events/77')
    expect(wrapper.findAll('[data-testid="breadcrumb"] li').map(item => item.text())).toEqual(['Accueil', 'Tournoi Résultats', 'Phase finale'])
  })

  it('CMP-16: an event covering half of the gamedays is only listed', async () => {
    const wrapper = await mountRoute('/competitions/2999/RCH/ranking')
    expect(wrapper.find('[data-testid="main-event"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="other-events"]').text()).toContain('Tournoi Résultats')
    expect(wrapper.findAll('[data-testid="breadcrumb"] li').map(item => item.text())).toEqual(['Accueil', 'Compétitions et résultats 2999', 'Groupe Résultats', 'Championnat Résultats'])
  })

  it('CMP-14: an unknown or unpublished competition is the site 404', async () => {
    expect(await expectNotFound('/competitions/2999/NOPE/games')).toBe(404)
  })
})

describe('games tab (§ 3.1)', () => {
  it('CMP-05: games grouped by date, status and provisional score', async () => {
    const wrapper = await mountRoute('/competitions/2999/RCH/games')
    const dates = wrapper.findAll('[data-testid="games-of-date"]')
    expect(dates).toHaveLength(2)
    expect(dates[0]!.findAll('[data-game]').map(row => row.attributes('data-game'))).toEqual(['9401', '9402'])
    const live = wrapper.find('[data-game="9404"]')
    expect(live.find('[data-status]').text()).toContain('En cours')
    expect(live.find('[data-testid="provisional"]').exists()).toBe(true)
    expect(wrapper.find('[data-game="9401"] [data-testid="provisional"]').exists()).toBe(false)
  })

  it('CMP-06: filters by gameday and day, GET form', async () => {
    const wrapper = await mountRoute('/competitions/2999/RCH/games?gameday=9202')
    expect(wrapper.findAll('[data-game]').map(row => row.attributes('data-game'))).toEqual(['9404', '9405'])
    expect(wrapper.find('[data-testid="games-filters"]').attributes('method')).toBe('get')
    const day = await mountRoute('/competitions/2999/RCH/games?day=2999-03-01')
    expect(day.findAll('[data-game]').map(row => row.attributes('data-game'))).toEqual(['9401', '9402'])
  })

  it('CMP-06: no game for the criteria', async () => {
    const wrapper = await mountRoute('/competitions/2999/RCH/games?day=2999-12-25')
    expect(wrapper.find('[data-testid="no-filtered-games"]').exists()).toBe(true)
  })

  it('CMP-12 / TEA-04: team links to its site page, game sheets to app2 in a new tab, PDF links with explicit parameters', async () => {
    const wrapper = await mountRoute('/competitions/2999/RCH/games')
    const row = wrapper.find('[data-game="9401"]')
    expect(row.find('a[data-kind="team"]').attributes('href')).toBe('/teams/101?season=2999&competition=RCH')
    expect(row.find('[data-testid="game-sheet"]').attributes('href')).toBe('https://app.kpi.localhost/game/9401')
    expect(row.find('[data-testid="game-sheet"]').attributes('target')).toBe('_blank')
    expect(row.find('[data-testid="score-sheet-pdf"]').attributes('href')).toBe('https://kpi.localhost/PdfMatchMulti.php?listMatch=9401&lang=fr')
    expect(wrapper.find('[data-testid="games-pdf"]').attributes('href')).toBe('https://kpi.localhost/PdfListeMatchs.php?S=2999&Compet=RCH')
  })

  it('referees are shown without what api2 stores between parentheses', async () => {
    const wrapper = await mountRoute('/competitions/2999/RCH/games')
    expect(wrapper.find('[data-game="9401"]').text()).toContain('ARBITRE Un, ARBITRE Deux')
    expect(wrapper.find('[data-game="9401"]').text()).not.toContain('(C001)')
  })
})

describe('pitches tab (§ 3.2)', () => {
  it('CMP-07: one column per pitch and one row per time for the requested day', async () => {
    const grid = (await mountRoute('/competitions/2999/RCH/pitches?day=2999-04-01')).find('[data-testid="pitch-grid"]')
    expect(grid.findAll('thead th').map(cell => cell.text())).toEqual(['Heure', 'Terrain 1', 'Terrain 2'])
    expect(grid.findAll('tbody tr').map(row => row.find('th').text())).toEqual(['09:00', '09:40'])
  })
})

describe('info tab (§ 3.3)', () => {
  it('CMP-08: gamedays with officials, teams by pool when started', async () => {
    const wrapper = await mountRoute('/competitions/2999/RCH/info')
    const gamedays = wrapper.findAll('[data-testid="gameday"]')
    expect(gamedays).toHaveLength(2)
    expect(gamedays[0]!.text()).toContain('Club Organisateur')
    expect(gamedays[0]!.text()).toContain('CHEF Arb')
    expect(wrapper.find('[data-testid="teams-by-pool"]').text()).toContain('Equipe Delta')
  })

  it('CMP-08: no teams before the competition starts', async () => {
    const wrapper = await mountRoute('/competitions/2999/RAT/info')
    expect(wrapper.find('[data-testid="teams-by-pool"]').exists()).toBe(false)
  })
})

describe('progress tab (§ 3.4)', () => {
  it('CMP-09: horizontal by default, rounds in columns; vertical by decreasing level', async () => {
    const horizontal = await mountRoute('/competitions/2999/RCP/progress')
    expect(horizontal.find('[data-testid="progress-horizontal"]').findAll('section').length).toBe(2)
    expect(horizontal.find('[data-testid="progress-switch"] a[aria-pressed="true"]').text()).toBe('Horizontal')

    const vertical = await mountRoute('/competitions/2999/RCP/progress?view=vertical')
    expect(vertical.find('[data-testid="progress-vertical"]').findAll('h3').map(title => title.text())).toEqual(['Finale', 'Poule A', 'Poule B'])
  })

  it('CMP-09: pools with standings (derived from games when not ranked), knockout games with the winner first', async () => {
    const wrapper = await mountRoute('/competitions/2999/RCP/progress?view=vertical')
    const tables = wrapper.findAll('[data-testid="pool-table"]')
    expect(tables).toHaveLength(2)
    expect(tables[1]!.text()).toContain('Equipe Golf')
    const final = wrapper.find('[data-game="9412"]')
    expect(final.find('[data-winner]').text()).toContain('Equipe Echo')
    expect(wrapper.find('[data-game="9414"]').text()).toContain('(Winner game #11)')
  })
})

describe('ranking tab (§ 3.6)', () => {
  it('CMP-10: full columns, provisional badge, qualified and eliminated marks, PDF', async () => {
    const wrapper = await mountRoute('/competitions/2999/RCH/ranking')
    expect(wrapper.find('[data-testid="ranking-badge"]').text()).toBe('Classement provisoire')
    expect(wrapper.findAll('[data-testid="ranking-row"]')).toHaveLength(4)
    expect(wrapper.find('thead').text()).toContain('Diff')
    expect(wrapper.find('[data-mark="eliminated"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="ranking-pdf"]').attributes('href')).toBe('https://kpi.localhost/PdfCltChpt.php?S=2999&Compet=RCH&lang=fr')
  })

  it('CMP-10: medals and final badge for a finished final round', async () => {
    const wrapper = await mountRoute('/competitions/2999/RCP/ranking')
    expect(wrapper.find('[data-testid="ranking-badge"]').text()).toBe('Classement final')
    expect(wrapper.findAll('[data-medal]')).toHaveLength(3)
    expect(wrapper.find('[data-testid="ranking-pdf"]').attributes('href')).toContain('PdfCltNiveauPhase.php')
  })

  it('CMP-10: MULTI shows rank, team, points and played only', async () => {
    const wrapper = await mountRoute('/competitions/2999/RMU/ranking')
    expect(wrapper.findAll('thead th').map(cell => cell.text()).filter(Boolean)).toEqual(['Qualification ou médaille', 'Clt', 'Équipe', 'Pts', 'J'])
  })

  it('CMP-10: empty state before the first games', async () => {
    const wrapper = await mountRoute('/competitions/2999/RAT/ranking')
    expect(wrapper.find('[data-testid="no-ranking"]').text()).toBe('Le classement sera publié après les premiers matchs.')
  })
})

describe('stats tab (§ 3.7)', () => {
  it('CMP-11: top scorers with shared ranks, kinds selector hidden with one statistic', async () => {
    const wrapper = await mountRoute('/competitions/2999/RCH/stats')
    expect(wrapper.find('[data-testid="stat-kinds"]').exists()).toBe(false)
    const rows = wrapper.findAll('[data-testid="stat-row"]')
    expect(rows.map(row => row.find('td').text())).toEqual(['1', '1', '3'])
    expect(rows[0]!.text()).toContain('ALPHA Ann #7')
    expect(wrapper.find('[data-testid="stats-more"]').exists()).toBe(false)
    expect(api2).toHaveBeenCalledWith('/competition/2999/RCH/stats/scorers?limit=20')
  })

  it('CMP-11: empty state', async () => {
    const wrapper = await mountRoute('/competitions/2999/RCP/stats')
    expect(wrapper.find('[data-testid="no-stats"]').text()).toBe('Aucune statistique disponible.')
  })
})
