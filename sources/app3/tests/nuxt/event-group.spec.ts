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

describe('event view (PAGE_EVENT_GROUP.md)', () => {
  it('EVT-02: header with name, place, dates and the app2 link in a new tab', async () => {
    const header = (await mountRoute('/events/77/games')).find('[data-testid="scope-header"]')
    expect(header.find('h1').text()).toBe('Tournoi Résultats')
    expect(header.text()).toContain('Rivecity')
    expect(header.find('img').attributes('src')).toBe('https://kpi.localhost/img/logo/resultats.png')
    expect(header.find('[data-testid="scope-live"]').attributes('href')).toBe('https://app.kpi.localhost/event/77')
  })

  it('EVT-03: one chip per competition, to the competition page in the event context', async () => {
    const chips = (await mountRoute('/events/77/pitches')).findAll('[data-testid="competition-chips"] a')
    expect(chips.map(chip => chip.attributes('href'))).toEqual(['/events/77/pitches', '/competitions/2999/RCH/pitches?event=77', '/competitions/2999/RCP/pitches?event=77'])
    expect(chips[0]!.attributes('aria-current')).toBe('page')
  })

  it('EVT-04/AGG-01: games of every competition, each with its competition', async () => {
    const wrapper = await mountRoute('/events/77/games')
    expect(wrapper.findAll('[data-game]').map(row => row.attributes('data-game'))).toEqual(['9404', '9405', '9411', '9413', '9412', '9414'])
    expect(wrapper.find('[data-game="9411"] a[href="/competitions/2999/RCP/games?event=77"]').exists()).toBe(true)
  })

  it('AGG-02: no gameday filter in the event view', async () => {
    const wrapper = await mountRoute('/events/77/games')
    expect(wrapper.find('select[name="gameday"]').exists()).toBe(false)
  })

  it('AGG-04: games list PDF by event', async () => {
    const wrapper = await mountRoute('/events/77/games')
    expect(wrapper.find('[data-testid="games-pdf"]').attributes('href')).toBe('https://kpi.localhost/PdfListeMatchs.php?idEvenement=77')
  })

  it('EVT-05: an unpublished event is the site 404', async () => {
    expect(await expectNotFound('/events/78/games')).toBe(404)
  })
})

describe('group view (PAGE_EVENT_GROUP.md)', () => {
  it('GRP-02: header with the group label, season and app2 link', async () => {
    const header = (await mountRoute('/groups/2999/TSTRES/games')).find('[data-testid="scope-header"]')
    expect(header.find('h1').text()).toBe('Groupe Résultats')
    expect(header.text()).toContain('2999')
    expect(header.find('[data-testid="scope-live"]').attributes('href')).toBe('https://app.kpi.localhost/group/2999/TSTRES')
  })

  it('GRP-03: one chip per competition, without event parameter', async () => {
    const chips = (await mountRoute('/groups/2999/TSTRES/games')).findAll('[data-testid="competition-chips"] a')
    expect(chips.slice(1).map(chip => chip.attributes('href'))).toEqual([
      '/competitions/2999/RCP/games', '/competitions/2999/RMU/games', '/competitions/2999/RCH/games', '/competitions/2999/RAT/games',
    ])
  })

  it('AGG-04: games list PDF by the competitions of the group', async () => {
    const wrapper = await mountRoute('/groups/2999/TSTRES/games')
    expect(wrapper.find('[data-testid="games-pdf"]').attributes('href')).toBe('https://kpi.localhost/PdfListeMatchs.php?S=2999&Compet=RCP,RMU,RCH,RAT')
  })

  it('GRP-04: an unknown group is the site 404', async () => {
    expect(await expectNotFound('/groups/2999/NOPE/games')).toBe(404)
  })
})
