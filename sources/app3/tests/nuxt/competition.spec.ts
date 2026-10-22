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
      '/competitions/2999/RCH/ranking', '/competitions/2999/RCP/ranking', '/competitions/2999/RMU/ranking', '/competitions/2999/RAT/ranking',
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

  it('CMP-06 / CMP-19: days are links keeping the other filters; gameday and upcoming in a GET form', async () => {
    const wrapper = await mountRoute('/competitions/2999/RCH/games?gameday=9202')
    expect(wrapper.findAll('[data-game]').map(row => row.attributes('data-game'))).toEqual(['9404', '9405'])
    expect(wrapper.find('[data-testid="games-filters"]').attributes('method')).toBe('get')
    const days = wrapper.findAll('[data-testid="games-days"] a')
    expect(days.map(day => day.attributes('href'))).toEqual([
      '/competitions/2999/RCH/games?gameday=9202',
      '/competitions/2999/RCH/games?gameday=9202&day=2999-03-01',
      '/competitions/2999/RCH/games?gameday=9202&day=2999-04-01',
    ])
    expect(days[0]!.attributes('aria-current')).toBe('true')
    const day = await mountRoute('/competitions/2999/RCH/games?day=2999-03-01')
    expect(day.findAll('[data-game]').map(row => row.attributes('data-game'))).toEqual(['9401', '9402'])
    expect(day.findAll('[data-testid="games-days"] a')[1]!.attributes('aria-current')).toBe('true')
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

describe('pitch game layout (§ 3.2)', () => {
  it('CMP-07: category and status share the top line; each score sits at the right of its team', async () => {
    const game = (await mountRoute('/competitions/2999/RCH/pitches?day=2999-04-01')).find('[data-testid="pitch-grid"] [data-game="9404"]')
    const top = game.find('[data-testid="pitch-top"]')
    expect(top.find('[data-testid="pitch-category"]').text()).toBe('Poule A')
    // CMP-17: the status is a coloured icon whose label stays readable by screen readers and on hover, preceded by
    // the game number; the period is abbreviated (M1 → P1).
    const status = top.find('[data-status]')
    expect(status.attributes('data-status')).toBe('ON')
    expect(status.find('[data-testid="status-icon"]').classes()).toContain('text-kpi-blue-600')
    expect(status.attributes('title')).toBe('En cours · période 1')
    expect(status.find('.sr-only').text()).toBe('En cours')
    expect(status.find('[data-testid="status-period"]').text()).toBe('P1')
    expect(top.find('[data-testid="pitch-number"]').text()).toBe('#4')
    const [teamA, teamB] = game.findAll('[data-testid="pitch-team"]')
    expect(teamA!.text()).toContain('Equipe Alpha')
    expect(teamA!.find('[data-testid="side-score"]').text()).toContain('1')
    expect(teamB!.text()).toContain('Equipe Delta')
    expect(teamB!.find('[data-testid="side-score"]').text()).toContain('0')
    // The score is provisional (game on): italic and announced.
    expect(teamA!.find('[data-testid="side-score"]').classes()).toContain('italic')
  })

  it('CMP-17: finished games are green, provisional ones (not locked) orange with an ellipsis, pending ones grey', async () => {
    api2.mockImplementation(async (path: string) => (path === '/competition/2999/RCP/games'
      ? (await fakeApi2<ResultsGame[]>(path)).map(item => (item.g_id === 9413 ? { ...item, g_validation: '' } : item))
      : fakeApi2(path)))
    const grid = (await mountRoute('/competitions/2999/RCP/pitches?day=2999-04-01')).find('[data-testid="pitch-grid"]')
    const iconColour = (id: number) => grid.find(`[data-game="${id}"] [data-testid="status-icon"]`).classes().find(name => name.startsWith('text-'))
    expect(grid.find('[data-game="9411"] [data-status]').attributes('data-status')).toBe('END')
    expect(iconColour(9411)).toBe('text-kpi-green-600')
    const provisional = grid.find('[data-game="9413"] [data-status]')
    expect(provisional.attributes('data-status')).toBe('PROVISIONAL')
    expect(provisional.attributes('title')).toBe('Provisoire')
    expect(iconColour(9413)).toBe('text-orange-600')
  })

  it('CMP-17: the category of a cup game is followed by its phase', async () => {
    const cup = (await mountRoute('/competitions/2999/RCP/pitches?day=2999-04-01')).find('[data-testid="pitch-grid"] [data-game="9411"]')
    expect(cup.find('[data-testid="pitch-category"]').text()).toBe('Finale - Poule A')
    const championship = (await mountRoute('/events/77/pitches?day=2999-04-01')).find('[data-testid="pitch-grid"] [data-game="9404"]')
    expect(championship.find('[data-testid="pitch-category"]').text()).toBe('Poule A')
    const cupInEvent = (await mountRoute('/events/77/pitches?day=2999-04-01')).find('[data-testid="pitch-grid"] [data-game="9411"]')
    expect(cupInEvent.find('[data-testid="pitch-category"]').text()).toBe('Finale - Poule A')
  })

  it('CMP-07: the winner of a finished game is in bold', async () => {
    const game = (await mountRoute('/competitions/2999/RCP/pitches?day=2999-04-01')).find('[data-testid="pitch-grid"] [data-game="9411"]')
    const [winner, loser] = game.findAll('[data-testid="pitch-team"]')
    expect(winner!.find('a').classes()).toContain('font-bold')
    expect(loser!.find('a').classes()).not.toContain('font-bold')
    const onGame = (await mountRoute('/competitions/2999/RCH/pitches?day=2999-04-01')).find('[data-game="9404"]')
    expect(onGame.findAll('a.font-bold')).toHaveLength(0)
  })

  it('CMP-07: a game to come shows no score', async () => {
    const game = (await mountRoute('/competitions/2999/RCH/pitches?day=2999-04-01')).find('[data-testid="pitch-grid"] [data-game="9405"]')
    expect(game.findAll('[data-testid="side-score"]').every(score => score.text().trim() === '' || score.text().trim() === '–')).toBe(true)
  })

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
