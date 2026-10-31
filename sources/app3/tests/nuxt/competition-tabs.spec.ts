// Info, progress, ranking and stats tabs (PAGE_COMPETITION.md § 3.3 to § 3.7); the frame, games and pitches are in competition.spec.ts.
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mockNuxtImport } from '@nuxt/test-utils/runtime'
import { fakeApi2 } from '../fixtures/api2'
import { mountRoute } from './results-setup'

const { api2 } = vi.hoisted(() => ({ api2: vi.fn() }))
mockNuxtImport('useApi2', () => () => api2)
beforeEach(() => {
  api2.mockReset()
  api2.mockImplementation(fakeApi2)
  clearNuxtData()
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

  it('CMP-23: teams with their logos; officials as a grid of label and name', async () => {
    const wrapper = await mountRoute('/competitions/2999/RCH/info')
    const teams = wrapper.find('[data-testid="teams-by-pool"]')
    expect(teams.findAll('img[data-testid="team-logo"]').map(img => img.attributes('src'))[0]).toBe('https://kpi.localhost/img/KIP/logo/alpha.png')
    const officials = wrapper.findAll('[data-testid="gameday"]')[0]!.findAll('[data-testid="official"]')
    expect(officials.map(official => [official.find('dt').text(), official.find('dd').text()])).toContainEqual(['Chef des arbitres', 'CHEF Arb'])
  })

  it('CMP-23: one set of parameters is shown first, dates in large; a finished competition shows its podium and the agenda block', async () => {
    const { api2Fixture } = await import('../fixtures/api2')
    const info = api2Fixture('/competition/2999/RCP/info').body as { gamedays: { start: string, end: string }[] }
    const [first] = info.gamedays
    api2.mockImplementation(async (path: string) => (path === '/competition/2999/RCP/info'
      ? { ...info, gamedays: info.gamedays.map(gameday => ({ ...gameday, start: first!.start, end: first!.end })) }
      : fakeApi2(path)))
    const wrapper = await mountRoute('/competitions/2999/RCP/info')
    expect(wrapper.find('[data-testid="info-when"]').text()).toContain('1 avril 2999')
    expect(wrapper.find('[data-testid="info-when"]').text()).toContain('Rivecity (64)')
    const podium = wrapper.find('[data-testid="info-podium"]')
    expect(podium.findAll('li [data-kind="team"]').map(item => item.text())).toEqual(['Equipe Echo', 'Equipe Golf', 'Equipe Foxtrot'])
    expect(podium.find('[data-testid="info-ranking-link"]').attributes('href')).toBe('/competitions/2999/RCP/ranking')
    expect(wrapper.find('[data-testid="calendar-subscription"]').exists()).toBe(true)
  })

  it('CMP-23: no podium while the competition is not finished', async () => {
    const wrapper = await mountRoute('/competitions/2999/RCH/info')
    expect(wrapper.find('[data-testid="info-podium"]').exists()).toBe(false)
  })

  it('CMP-08: no teams before the competition starts', async () => {
    const wrapper = await mountRoute('/competitions/2999/RAT/info')
    expect(wrapper.find('[data-testid="teams-by-pool"]').exists()).toBe(false)
  })
})

describe('progress tab (§ 3.4)', () => {
  it('CMP-09: horizontal by default, rounds in columns; vertical by decreasing level', async () => {
    const horizontal = await mountRoute('/competitions/2999/RCP/progress')
    expect(horizontal.findAll('[data-testid="progress-horizontal"] > section').length).toBe(2)
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
    expect(wrapper.find('[data-game="9414"]').text()).toContain('Vainqueur n° 11')
  })

  it('CMP-26: in the vertical view a pool table spreads its columns above its games, and a touched team is followed', async () => {
    const wrapper = await mountRoute('/competitions/2999/RCP/progress?view=vertical')
    const vertical = wrapper.find('[data-testid="progress-vertical"]')
    expect(vertical.find('[data-testid="pool-table"]').attributes('data-spread')).toBeDefined()
    await vertical.find('button[data-team="111"]').trigger('click')
    // Its pool row, its pool game and the final (the vertical view also lists the games of the pools).
    expect(wrapper.findAll('[data-testid="progress-vertical"] [data-followed]').length).toBe(3)
    expect(wrapper.find('[data-testid="progress-vertical"]').attributes('data-following')).toBeDefined()
    expect(wrapper.find('[data-testid="unfollow"]').text()).toBe('Ne plus suivre Equipe Echo')
    await wrapper.find('[data-testid="unfollow"]').trigger('click')
    expect(wrapper.findAll('[data-testid="progress-vertical"] [data-followed]')).toHaveLength(0)
  })

  it('CMP-24: one column per round, titled for screen readers only; the final titled in gold', async () => {
    const wrapper = await mountRoute('/competitions/2999/RCP/progress')
    const rounds = wrapper.findAll('[data-testid="progress-horizontal"] > section')
    expect(rounds.map(round => round.find('h2').text())).toEqual(['Tour 1', 'Tour 2'])
    expect(rounds[0]!.find('h2').classes()).toContain('sr-only')
    expect(rounds[1]!.find('[data-final]').text()).toBe('Finale')
    expect(rounds[0]!.find('[data-final]').exists()).toBe(false)
  })

  it('CMP-24: touching a team highlights its whole path, and can be undone', async () => {
    const wrapper = await mountRoute('/competitions/2999/RCP/progress')
    expect(wrapper.find('[data-testid="unfollow"]').exists()).toBe(false)
    await wrapper.find('[data-testid="progress-horizontal"] button[data-team="111"]').trigger('click')
    const followed = wrapper.findAll('[data-testid="progress-horizontal"] [data-followed]')
    expect(followed.length).toBe(2)
    expect(wrapper.find('[data-testid="progress-horizontal"]').attributes('data-following')).toBeDefined()
    expect(wrapper.find('[data-testid="unfollow"]').text()).toBe('Ne plus suivre Equipe Echo')
    await wrapper.find('[data-testid="unfollow"]').trigger('click')
    expect(wrapper.findAll('[data-testid="progress-horizontal"] [data-followed]')).toHaveLength(0)
  })
})

describe('championship progress (§ 3.4)', () => {
  it('CMP-25: no view switch, one card per gameday with its dates, place, standing and games link', async () => {
    const wrapper = await mountRoute('/competitions/2999/RCH/progress?view=vertical')
    expect(wrapper.find('[data-testid="progress-switch"]').exists()).toBe(false)
    const gamedays = wrapper.findAll('[data-testid="progress-gameday"]')
    expect(gamedays.map(gameday => gameday.find('h3').text())).toEqual(['Journée 1', 'Journée 2'])
    expect(gamedays[0]!.text()).toContain('Lacville')
    expect(gamedays[0]!.findAll('[data-testid="pool-table"] tbody tr')).toHaveLength(4)
    expect(gamedays[1]!.find('[data-testid="pool-table"]').exists()).toBe(false)
    expect(gamedays[1]!.text()).toContain('Classement à venir')
    expect(gamedays[1]!.find('a[data-testid="gameday-games"]').attributes('href')).toBe('/competitions/2999/RCH/games?gameday=9202')
  })

  it('CMP-25: touching a team highlights its row in every gameday, and can be undone', async () => {
    const wrapper = await mountRoute('/competitions/2999/RCH/progress')
    const button = wrapper.find('[data-testid="progress-gameday"] button[data-team]')
    const label = button.text()
    await button.trigger('click')
    expect(wrapper.find('[data-testid="progress-gamedays"]').attributes('data-following')).toBeDefined()
    expect(wrapper.findAll('[data-testid="progress-gamedays"] [data-followed]').map(row => row.text())).toEqual([expect.stringContaining(label)])
    expect(wrapper.find('[data-testid="unfollow"]').text()).toBe(`Ne plus suivre ${label}`)
    await wrapper.find('[data-testid="unfollow"]').trigger('click')
    expect(wrapper.findAll('[data-testid="progress-gamedays"] [data-followed]')).toHaveLength(0)
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

  it('CMP-21: a stepped podium 2–1–3 when the three medals are given, then the full table with a signed difference', async () => {
    const wrapper = await mountRoute('/competitions/2999/RCP/ranking')
    const steps = wrapper.findAll('[data-testid="podium"] [data-podium-rank]')
    expect(steps.map(step => step.attributes('data-podium-rank'))).toEqual(['2', '1', '3'])
    expect(steps[1]!.text()).toContain('Equipe Echo')
    expect(steps[1]!.find('a').attributes('href')).toBe('/teams/111?season=2999&competition=RCP')
    const championship = await mountRoute('/competitions/2999/RCH/ranking')
    expect(championship.find('[data-testid="podium"]').exists()).toBe(false)
    const diffs = championship.findAll('[data-testid="ranking-row"]').map(row => row.findAll('td').at(-1)!.text())
    expect(diffs).toEqual(['+5', '0', '0', '-5'])
  })

  it('CMP-10: MULTI shows rank, team, points and played only', async () => {
    const wrapper = await mountRoute('/competitions/2999/RMU/ranking')
    expect(wrapper.findAll('thead th').map(cell => cell.text()).filter(Boolean)).toEqual(['Qualification ou médaille', 'Clt', 'Équipe', 'Pts', 'J'])
  })

  it('CMP-10: empty state before the first games', async () => {
    const wrapper = await mountRoute('/competitions/2999/RAT/ranking')
    expect(wrapper.find('[data-testid="no-ranking"]').text()).toBe('Le classement sera publié après les premiers matchs.')
  })

  it('CMP-10: a cup type competition in progress explains why its ranking waits for the end and links to its progress', async () => {
    const { api2Fixture } = await import('../fixtures/api2')
    const ranking = api2Fixture('/competition/2999/RCP/ranking').body as object
    api2.mockImplementation(async (path: string) => (path === '/competition/2999/RCP/ranking'
      ? { ...ranking, status: 'ON', rows: [] }
      : fakeApi2(path)))
    const wrapper = await mountRoute('/competitions/2999/RCP/ranking')
    const message = wrapper.find('[data-testid="no-ranking"]')
    expect(message.text()).toContain('Le classement général sera publié à l\'issue de la compétition.')
    expect(message.text()).toContain('finalistes compris, ont encore des matchs à jouer')
    expect(message.find('a').attributes('href')).toBe('/competitions/2999/RCP/progress')
    expect(wrapper.find('[data-testid="ranking-badge"]').exists()).toBe(false)
  })
})

describe('stats tab (§ 3.7)', () => {
  it('CMP-11: top scorers with shared ranks, kinds selector hidden with one statistic', async () => {
    const wrapper = await mountRoute('/competitions/2999/RCH/stats')
    expect(wrapper.find('[data-testid="stat-kinds"]').exists()).toBe(false)
    const rows = wrapper.findAll('[data-testid="stat-row"]')
    expect(rows.map(row => row.find('[data-testid="stat-rank"]').text())).toEqual(['1', '1', '3'])
    expect(rows[0]!.text()).toContain('ALPHA Ann')
    expect(rows[0]!.text()).toContain('n° 7')
    expect(wrapper.find('[data-testid="stats-more"]').exists()).toBe(false)
    expect(api2).toHaveBeenCalledWith('/competition/2999/RCH/stats/scorers?limit=20')
  })

  it('CMP-22: the top scorer put forward, goals as bars on a common scale', async () => {
    const wrapper = await mountRoute('/competitions/2999/RCH/stats')
    const lead = wrapper.find('[data-testid="lead-scorer"]')
    expect(lead.text()).toContain('ALPHA Ann')
    expect(lead.text()).toContain('2')
    expect(wrapper.findAll('[data-testid="stat-bar"]').map(bar => bar.attributes('style'))).toEqual(['width: 100%;', 'width: 100%;', 'width: 50%;'])
  })

  it('CMP-22: filter by team with links; no top scorer block once a team is chosen', async () => {
    const wrapper = await mountRoute('/competitions/2999/RCH/stats')
    const teams = wrapper.findAll('[data-testid="stat-teams"] a')
    expect(teams.map(team => team.text())).toEqual(['Toutes', 'Equipe Alpha', 'Equipe Charlie', 'Equipe Bravo'])
    expect(teams[2]!.attributes('href')).toBe('/competitions/2999/RCH/stats?team=103')
    expect(teams[0]!.attributes('aria-current')).toBe('true')
    const filtered = await mountRoute('/competitions/2999/RCH/stats?team=103')
    expect(filtered.findAll('[data-testid="stat-row"]').map(row => row.text())).toHaveLength(1)
    expect(filtered.find('[data-testid="stat-row"]').text()).toContain('CHARLIE Dan')
    expect(filtered.find('[data-testid="lead-scorer"]').exists()).toBe(false)
  })

  it('CMP-11: empty state', async () => {
    const wrapper = await mountRoute('/competitions/2999/RCP/stats')
    expect(wrapper.find('[data-testid="no-stats"]').text()).toBe('Aucune statistique disponible.')
  })
})
