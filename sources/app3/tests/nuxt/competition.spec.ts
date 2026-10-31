import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mockNuxtImport } from '@nuxt/test-utils/runtime'
import type { ResultsGame } from '#kpi-layer/utils/results/types'
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
  it('CMP-02 / CMP-18: compact header with title, season, written status, website and live links; no eyebrow', async () => {
    const header = (await mountRoute('/competitions/2999/RCH/games')).find('[data-testid="competition-header"]')
    expect(header.find('h1').text()).toBe('Championnat Résultats')
    expect(header.text()).toContain('2999')
    expect(header.find('[data-testid="competition-kind"]').exists()).toBe(false)
    expect(header.find('[data-testid="type-badge"]').exists()).toBe(false)
    expect(header.find('[data-testid="status-badge"]').text()).toBe('En cours')
    expect(header.find('[data-testid="competition-subtitle"]').text()).toBe('Poule A')
    expect(header.find('[data-testid="competition-visual"]').attributes('src')).toBe('https://kpi.localhost/img/logo/bandeau-rch.png')
    expect(header.find('[data-testid="competition-web"]').attributes('href')).toBe('https://example.test')
    expect(header.find('[data-testid="competition-live"]').attributes('href')).toBe('https://app.kpi.localhost/group/2999/TSTRES')
    expect(header.find('[data-testid="competition-live"]').attributes('target')).toBe('_blank')
    expect(header.find('[data-testid="competition-live"]').attributes('data-live-button')).toBeDefined()
  })

  it('CMP-18: the event of the competition is one clickable card, with its date tile and its number of competitions', async () => {
    const card = (await mountRoute('/competitions/2999/RCP/ranking')).find('[data-testid="main-event"]')
    expect(card.element.tagName).toBe('A')
    expect(card.attributes('href')).toBe('/events/77')
    expect(card.find('[data-testid="event-tile"]').text()).toBe('1–2avr.')
    expect(card.text()).toContain('Tournoi Résultats')
    expect(card.text()).toContain('Les 2 compétitions, tous les matchs par terrain et le direct.')
    expect(card.text()).toContain("Voir l'événement")
  })

  it('CMP-03: siblings of the group keep the current tab', async () => {
    const chips = (await mountRoute('/competitions/2999/RCH/ranking')).findAll('[data-testid="competition-chips"] a')
    expect(chips.map(chip => chip.attributes('href'))).toEqual([
      '/competitions/2999/RCH/ranking', '/competitions/2999/RAT/ranking', '/competitions/2999/RCP/ranking', '/competitions/2999/RMU/ranking',
    ])
    expect(chips[0]!.attributes('aria-current')).toBe('page')
  })

  it('CMP-03: with ?event=, siblings are the event competitions and the parameter is kept', async () => {
    const wrapper = await mountRoute('/competitions/2999/RCP/games?event=77')
    expect(wrapper.findAll('[data-testid="competition-chips"] a').map(chip => chip.attributes('href'))).toEqual([
      '/competitions/2999/RCH/games?event=77', '/competitions/2999/RCP/games?event=77',
    ])
    expect(wrapper.findAll('[data-testid="results-tabs"] a')[1]!.attributes('href')).toBe('/competitions/2999/RCP/games?event=77')
  })

  it('CMP-04: tabs are links, the current one marked', async () => {
    const tabs = (await mountRoute('/competitions/2999/RCH/info')).findAll('[data-testid="results-tabs"] a')
    expect(tabs.map(tab => tab.text())).toEqual(['Infos', 'Matchs', 'Terrains', 'Déroulement', 'Classement', 'Stats'])
    expect(tabs.map(tab => tab.attributes('aria-current'))).toEqual(['page', undefined, undefined, undefined, undefined, undefined])
  })

  it('CMP-16: an event covering every gameday is put forward, with the breadcrumb through it', async () => {
    const wrapper = await mountRoute('/competitions/2999/RCP/ranking')
    expect(wrapper.find('[data-testid="main-event"]').attributes('href')).toBe('/events/77')
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
  it('CMP-05: games grouped by date, the in-progress game flagged with its period', async () => {
    const wrapper = await mountRoute('/competitions/2999/RCH/games')
    const dates = wrapper.findAll('[data-testid="games-of-date"]')
    expect(dates).toHaveLength(2)
    expect(dates[0]!.find('h2').text()).toContain('2 matchs')
    expect(dates[0]!.findAll('[data-game]').map(row => row.attributes('data-game'))).toEqual(['9401', '9402'])
    const live = wrapper.find('[data-game="9404"]')
    expect(live.attributes('data-status')).toBe('ON')
    expect(live.find('[data-testid="game-flag"]').text()).toContain('En cours')
    expect(live.find('[data-testid="game-flag"]').text()).toContain('période 1')
    expect(live.find('[data-testid="score-cell"]').classes()).toContain('bg-live')
    expect(wrapper.find('[data-game="9401"] [data-testid="game-flag"]').exists()).toBe(false)
  })

  it('CMP-19: scores sit in the score column, the losing one dimmed; the winner name is in bold', async () => {
    const wrapper = await mountRoute('/competitions/2999/RCH/games')
    const won = wrapper.find('[data-game="9401"]')
    expect(won.find('[data-testid="score-cell"]').classes()).toContain('bg-spine')
    const [winner, loser] = won.findAll('a[data-kind="team"]')
    expect(winner!.classes()).toContain('font-bold')
    expect(loser!.classes()).not.toContain('font-bold')
    expect(won.find('[data-side="A"]').classes()).not.toContain('opacity-50')
    expect(won.find('[data-side="B"]').classes()).toContain('opacity-50')
    const draw = wrapper.find('[data-game="9402"]')
    expect(draw.findAll('a[data-kind="team"].font-bold, [data-side].opacity-50')).toHaveLength(0)
    expect(wrapper.find('[data-game="9404"]').findAll('a.font-bold')).toHaveLength(0)
    expect(wrapper.find('[data-game="9405"] [data-testid="score-cell"]').text()).toContain('–')
  })

  it('CMP-19: team logos, the pitch always shown', async () => {
    const row = (await mountRoute('/competitions/2999/RCH/games')).find('[data-game="9401"]')
    expect(row.findAll('img[data-testid="team-logo"]').map(img => img.attributes('src'))).toContain('https://kpi.localhost/img/KIP/logo/alpha.png')
    expect(row.find('[data-testid="game-pitch"]').text()).toBe('Terrain 1')
  })

  it('CMP-19 / CMP-20: the final is marked in gold; teams to come are read from the draw, its code shown until played', async () => {
    const wrapper = await mountRoute('/competitions/2999/RCP/games')
    const final = wrapper.find('[data-game="9412"]')
    expect(final.attributes('data-final')).toBeDefined()
    expect(final.text()).toContain('Finale')
    expect(final.text()).not.toContain('F1')
    const toCome = wrapper.find('[data-game="9414"]')
    expect(toCome.findAll('[data-testid="draw-slot"]').map(slot => slot.text())).toEqual(['Vainqueur n° 11', 'Perdant n° 12'])
    expect(toCome.find('[data-testid="draw-code"]').text()).toBe('V11-P12')
    expect(wrapper.find('[data-game="9411"]').attributes('data-final')).toBeUndefined()
  })

  it('CMP-06 / CMP-19: a championship filters by gameday in a GET form, without day shortcuts', async () => {
    const wrapper = await mountRoute('/competitions/2999/RCH/games?gameday=9202')
    expect(wrapper.findAll('[data-game]').map(row => row.attributes('data-game'))).toEqual(['9404', '9405'])
    expect(wrapper.find('[data-testid="games-filters"]').attributes('method')).toBe('get')
    expect(wrapper.find('[data-testid="games-filters"] select[name="gameday"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="games-days"]').exists()).toBe(false)
    const day = await mountRoute('/competitions/2999/RCH/games?day=2999-03-01')
    expect(day.findAll('[data-game]').map(row => row.attributes('data-game'))).toEqual(['9401', '9402'])
  })

  it('CMP-06 / CMP-19: in a cup, days are links keeping the other filters', async () => {
    const wrapper = await mountRoute('/competitions/2999/RCP/games?upcoming=1')
    const days = wrapper.findAll('[data-testid="games-days"] a')
    expect(days.map(day => day.attributes('href'))).toEqual([
      '/competitions/2999/RCP/games?upcoming=1',
      '/competitions/2999/RCP/games?upcoming=1&day=2999-04-01',
      '/competitions/2999/RCP/games?upcoming=1&day=2999-04-02',
    ])
    expect(days[0]!.attributes('aria-current')).toBe('true')
    const day = await mountRoute('/competitions/2999/RCP/games?day=2999-04-02')
    expect(day.findAll('[data-game]').map(row => row.attributes('data-game'))).toEqual(['9412', '9414'])
    expect(day.findAll('[data-testid="games-days"] a')[2]!.attributes('aria-current')).toBe('true')
  })

  it('CMP-06: no game for the criteria', async () => {
    const wrapper = await mountRoute('/competitions/2999/RCH/games?day=2999-12-25')
    expect(wrapper.find('[data-testid="no-filtered-games"]').exists()).toBe(true)
  })

  it('CMP-12 / TEA-04: team links to its site page, game sheets to app2 in a new tab, PDF links of api2 with explicit parameters', async () => {
    const wrapper = await mountRoute('/competitions/2999/RCH/games')
    const row = wrapper.find('[data-game="9401"]')
    expect(row.find('a[data-kind="team"]').attributes('href')).toBe('/teams/101?season=2999&competition=RCH')
    expect(row.find('[data-testid="game-sheet"]').attributes('href')).toBe('https://app.kpi.localhost/game/9401')
    expect(row.find('[data-testid="game-sheet"]').attributes('target')).toBe('_blank')
    expect(row.find('[data-testid="score-sheet-pdf"]').exists(), 'no public scoresheet PDF (PDF_GOTENBERG.md § 7.3.2)').toBe(false)
    expect(wrapper.find('[data-testid="games-pdf"]').attributes('href')).toBe('https://kpi.localhost/api2/pdf/games-list?season=2999&competition=RCH&lang=fr')
  })

  it('referees are shown without what api2 stores between parentheses', async () => {
    const wrapper = await mountRoute('/competitions/2999/RCH/games')
    expect(wrapper.find('[data-game="9401"]').text()).toContain('ARBITRE Un, ARBITRE Deux')
    expect(wrapper.find('[data-game="9401"]').text()).not.toContain('(C001)')
  })
})

describe('pitches tab (§ 3.2)', () => {
  it('CMP-07: one row per time, one card per game in the column of its pitch', async () => {
    const board = (await mountRoute('/competitions/2999/RCH/pitches?day=2999-04-01')).find('[data-testid="pitch-board"]')
    expect(board.findAll('[data-testid="pitch-heads"] > span').map(cell => cell.text()).filter(Boolean)).toEqual(['Terrain 1', 'Terrain 2'])
    expect(board.findAll('[data-testid="pitch-slot"]').map(slot => slot.find('[data-testid="pitch-time"]').text())).toEqual(['09:00', '09:40'])
    expect(board.find('[data-game="9404"]').attributes('style')).toContain('grid-column: 2')
    expect(board.find('[data-game="9405"]').attributes('style')).toContain('grid-column: 3')
  })

  it('CMP-07: pitches are links keeping the day; one pitch is one column, each card carrying its pitch', async () => {
    const wrapper = await mountRoute('/competitions/2999/RCH/pitches?day=2999-04-01')
    expect(wrapper.findAll('[data-testid="pitch-pitches"] a').map(link => link.attributes('href'))).toEqual([
      '/competitions/2999/RCH/pitches?day=2999-04-01',
      '/competitions/2999/RCH/pitches?day=2999-04-01&pitch=1',
      '/competitions/2999/RCH/pitches?day=2999-04-01&pitch=2',
    ])
    const single = await mountRoute('/competitions/2999/RCH/pitches?day=2999-04-01&pitch=2')
    const board = single.find('[data-testid="pitch-board"]')
    expect(board.attributes('data-single')).toBeDefined()
    expect(board.findAll('[data-game]').map(game => game.attributes('data-game'))).toEqual(['9405'])
    expect(board.find('[data-game="9405"] [data-testid="card-pitch"]').text()).toBe('Terrain 2')
    expect(single.find('[data-testid="pitch-pitches"] a[aria-current]').text()).toBe('Terrain 2')
  })

  it('CMP-07: on small screens, day and pitch are two lists of a GET form', async () => {
    const form = (await mountRoute('/competitions/2999/RCH/pitches?day=2999-04-01')).find('[data-testid="pitch-selects"]')
    expect(form.attributes('method')).toBe('get')
    expect(form.findAll('select').map(select => select.attributes('name'))).toEqual(['day', 'pitch'])
  })
})

describe('pitch card (§ 3.2)', () => {
  it('CMP-17: category, game number, both teams and the score block, live flagged with its period', async () => {
    const card = (await mountRoute('/competitions/2999/RCH/pitches?day=2999-04-01')).find('[data-testid="pitch-board"] [data-game="9404"]')
    expect(card.find('[data-testid="pitch-category"]').text()).toBe('Poule A')
    expect(card.find('[data-testid="pitch-number"]').text()).toBe('n° 4')
    expect(card.text()).toContain('Equipe Alpha')
    expect(card.text()).toContain('Equipe Delta')
    expect(card.find('[data-testid="game-flag"]').text()).toContain('période 1')
    expect(card.find('[data-testid="score-cell"]').classes()).toContain('bg-live')
  })

  it('CMP-17: a provisional score is flagged; the category of a cup game is followed by its phase', async () => {
    api2.mockImplementation(async (path: string) => (path === '/competition/2999/RCP/games'
      ? (await fakeApi2<ResultsGame[]>(path)).map(item => (item.g_id === 9413 ? { ...item, g_validation: '' } : item))
      : fakeApi2(path)))
    const board = (await mountRoute('/competitions/2999/RCP/pitches?day=2999-04-01')).find('[data-testid="pitch-board"]')
    expect(board.find('[data-game="9413"] [data-testid="game-flag"]').text()).toContain('Provisoire')
    expect(board.find('[data-game="9411"] [data-testid="game-flag"]').exists()).toBe(false)
    expect(board.find('[data-game="9411"] [data-testid="pitch-category"]').text().replace(/\s+/g, ' ')).toBe('Finale Poule A')
    const event = (await mountRoute('/events/77/pitches?day=2999-04-01')).find('[data-testid="pitch-board"]')
    expect(event.find('[data-game="9404"] [data-testid="pitch-category"]').text()).toBe('Poule A')
    expect(event.find('[data-game="9411"] [data-testid="pitch-category"]').text().replace(/\s+/g, ' ')).toBe('Finale Poule A')
  })

  it('CMP-17: the final is edged in gold; the winner of a finished game is in bold; a game to come shows no score', async () => {
    const final = (await mountRoute('/competitions/2999/RCP/pitches?day=2999-04-02')).find('[data-game="9412"]')
    expect(final.attributes('data-final')).toBeDefined()
    const game = (await mountRoute('/competitions/2999/RCP/pitches?day=2999-04-01')).find('[data-testid="pitch-board"] [data-game="9411"]')
    const [winner, loser] = game.findAll('a[data-kind="team"]')
    expect(winner!.classes()).toContain('font-bold')
    expect(loser!.classes()).not.toContain('font-bold')
    const toCome = (await mountRoute('/competitions/2999/RCH/pitches?day=2999-04-01')).find('[data-game="9405"]')
    expect(toCome.find('[data-side]').exists()).toBe(false)
  })
})

describe('referees (§ 3.1)', () => {
  it('CMP-05: a missing referee (« -1 ») is not shown', async () => {
    const wrapper = await mountRoute('/competitions/2999/RCH/games')
    const games = await import('../fixtures/api2').then(({ api2Fixture }) => api2Fixture('/competition/2999/RCH/games').body as { r_1?: string, r_2?: string }[])
    expect(games.length).toBeGreaterThan(0)
    api2.mockImplementation(async (path: string) => (path === '/competition/2999/RCH/games'
      ? games.map(game => ({ ...game, r_1: 'CD Loire-Atlantique I F', r_2: '-1' }))
      : fakeApi2(path)))
    clearNuxtData()
    const text = (await mountRoute('/competitions/2999/RCH/games')).find('[data-game="9401"]').text()
    expect(text).toContain('CD Loire-Atlantique I F')
    expect(text).not.toContain('-1')
    expect(wrapper.exists()).toBe(true)
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

  it('CMP-08: the phases of a cup, which share every parameter, are shown once without being listed', async () => {
    const { api2Fixture } = await import('../fixtures/api2')
    const info = api2Fixture('/competition/2999/RCP/info').body as { gamedays: { start: string, end: string }[] }
    const [first] = info.gamedays
    api2.mockImplementation(async (path: string) => (path === '/competition/2999/RCP/info'
      ? { ...info, gamedays: info.gamedays.map(gameday => ({ ...gameday, start: first!.start, end: first!.end })) }
      : fakeApi2(path)))
    const wrapper = await mountRoute('/competitions/2999/RCP/info')
    const cards = wrapper.findAll('[data-testid="gameday"]')
    expect(cards).toHaveLength(1)
    expect(cards[0]!.find('h3').exists()).toBe(false)
    expect(wrapper.find('[data-testid="gameday-ics"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="ics-subscribe"]').exists()).toBe(true)
  })

  it('CMP-08: cup phases whose parameters differ are grouped by parameters, titled by their phases', async () => {
    const cards = (await mountRoute('/competitions/2999/RCP/info')).findAll('[data-testid="gameday"]')
    expect(cards.map(card => card.find('h3').text())).toEqual(['Poule A, Poule B', 'Finale'])
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
    expect(wrapper.find('[data-testid="ranking-pdf"]').attributes('href')).toBe('https://kpi.localhost/api2/pdf/ranking?season=2999&competition=RCH&lang=fr')
  })

  it('CMP-10: medals and final badge for a finished final round', async () => {
    const wrapper = await mountRoute('/competitions/2999/RCP/ranking')
    expect(wrapper.find('[data-testid="ranking-badge"]').text()).toBe('Classement final')
    expect(wrapper.findAll('[data-medal]')).toHaveLength(3)
    expect(wrapper.find('[data-testid="ranking-pdf"]').attributes('href')).toContain('/api2/pdf/ranking-phases?')
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
