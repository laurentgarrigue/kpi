import { describe, expect, it, vi } from 'vitest'
import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import IndexPage from '~/pages/index.vue'
import type { EventFixture, GroupFixture, HomeFixtures } from '~/utils/fixtures'
import type { HomeBlock } from '~/utils/home'
import { api2Fixture } from '../fixtures/api2'

const { api2 } = vi.hoisted(() => ({ api2: vi.fn() }))
mockNuxtImport('useApi2', () => () => api2)

const event = (id: number, start: string, end: string = start, logo: string | null = null): EventFixture => ({
  kind: 'event', id, libelle: `Event ${id}`, place: 'Paris', logo, start, end,
})
const group = (code: string, start: string, end: string = start): GroupFixture => ({
  kind: 'group', season: '2026', code, libelle: `Groupe ${code}`, libelle_en: `Group ${code}`,
  place: 'Lacville', department: '33', logo: null, start, end,
})

// api2 `GET /fixtures/site`: already split on its « today » (15 June 2026), sorted and capped (API_PUBLIC_TRANSVERSE.md § 3.7).
const fixtures: HomeFixtures = {
  today: '2026-06-15',
  upcoming: [event(3, '2026-06-14', '2026-06-16', 'logo/event3.png'), group('N1H', '2026-06-20', '2026-06-21'), event(2, '2026-07-01'), event(1, '2026-09-01')],
  recent: [group('N2H', '2026-06-01'), event(10, '2026-05-20')],
}
const noFixtures: HomeFixtures = { today: '2026-06-15', upcoming: [], recent: [] }

// Real `GET /home` captured on SQL/fixtures: « featured » then « latest news ».
const fixtureBlocks = api2Fixture('/home').body as HomeBlock[]

async function mountHome(response: () => Promise<unknown>, blocks: () => Promise<HomeBlock[]> = async () => fixtureBlocks) {
  api2.mockReset()
  api2.mockImplementation((path: string) => (path === '/home' ? blocks() : response()))
  clearNuxtData()
  return mountSuspended(IndexPage)
}

const cardLinks = (section: ReturnType<Awaited<ReturnType<typeof mountHome>>['find']>) =>
  section.findAll('a').map(link => link.attributes('href'))

describe('home page (PAGE_HOME.md)', () => {
  it('HOME-01: has a single translated h1, not limited to France', async () => {
    const wrapper = await mountHome(async () => fixtures)
    expect(wrapper.findAll('h1')).toHaveLength(1)
    expect(wrapper.find('h1').text()).toBe('Le kayak-polo, en France et à l\'international')
  })

  it('HOME-02: the competitions button has the same target as the menu entry', async () => {
    const wrapper = await mountHome(async () => fixtures)
    expect(wrapper.find('[data-testid="competitions-cta"]').attributes('href'))
      .toBe('/competitions')
  })

  it('HOME-07: upcoming fixtures come first, in api2 order, ongoing ones flagged', async () => {
    const wrapper = await mountHome(async () => fixtures)
    const sections = wrapper.findAll('section[aria-labelledby$="fixtures-title"]').map(section => section.find('h2').text())
    expect(sections).toEqual(['À venir', 'Récemment'])

    const upcoming = wrapper.find('[data-testid="upcoming-fixtures"]')
    expect(cardLinks(upcoming)).toEqual(['/events/3', '/groups/2026/N1H', '/events/2', '/events/1'])
    expect(upcoming.findAll('[data-testid="ongoing-badge"]')).toHaveLength(1)
    expect(upcoming.find('a').find('[data-testid="ongoing-badge"]').text()).toBe('En cours')
  })

  it('HOME-03: shows the finished fixtures of api2, most recent first', async () => {
    const wrapper = await mountHome(async () => fixtures)
    expect(cardLinks(wrapper.find('[data-testid="recent-fixtures"]'))).toEqual(['/groups/2026/N2H', '/events/10'])
    expect(api2).toHaveBeenCalledWith('/fixtures/site')
  })

  it('HOME-09: a group day outside any event shows its group, place and dates and leads to the group page', async () => {
    const wrapper = await mountHome(async () => fixtures)
    const card = wrapper.findAll('[data-testid="upcoming-fixtures"] a')[1]!
    expect(card.attributes('href')).toBe('/groups/2026/N1H')
    expect(card.find('[data-testid="fixture-kind"]').text()).toBe('Compétition')
    expect(card.text()).toContain('Groupe N1H')
    expect(card.text()).toMatch(/20\s?–\s?21 juin 2026 · Lacville \(33\)/)
    expect(wrapper.find('[data-testid="upcoming-fixtures"] a').find('[data-testid="fixture-kind"]').text()).toBe('Événement')
  })

  it('HOME-04: cards open the event page of the site, with dates and a logo only when set', async () => {
    const wrapper = await mountHome(async () => fixtures)
    const card = wrapper.find('[data-testid="upcoming-fixtures"] a')
    expect(card.attributes('href')).toBe('/events/3')
    expect(card.attributes('target')).toBeUndefined()
    expect(card.text()).toMatch(/14\s?–\s?16 juin 2026/)
    expect(card.find('img').attributes('src')).toBe('https://kpi.localhost/img/logo/event3.png')
    expect(wrapper.findAll('[data-testid="upcoming-fixtures"] a')[2]?.find('img').exists()).toBe(false)
  })

  it('HOME-05: shows a message per empty section', async () => {
    const wrapper = await mountHome(async () => noFixtures)
    expect(wrapper.find('[data-testid="upcoming-fixtures-empty"]').text()).toBe('Aucun rendez-vous à venir pour le moment.')
    expect(wrapper.find('[data-testid="recent-fixtures-empty"]').text()).toBe('Aucun rendez-vous publié pour le moment.')
  })

  it('HOME-05: shows a single unavailability message when api2 fails', async () => {
    const wrapper = await mountHome(async () => {
      throw new Error('api2 down')
    })
    expect(wrapper.findAll('[data-testid="fixtures-unavailable"]')).toHaveLength(1)
    expect(wrapper.find('[data-testid="upcoming-fixtures"]').exists()).toBe(false)
  })

  it('LAY-09: the live button opens app2 in a new tab', async () => {
    const wrapper = await mountHome(async () => fixtures)
    const live = wrapper.find('[data-testid="live-cta"]')
    expect(live.attributes('target')).toBe('_blank')
    expect(live.attributes('rel')).toContain('noopener')
    expect(live.text()).toContain('(nouvel onglet)')
  })
})

describe('editorial home (PAGE_CONTENT.md § 4)', () => {
  const alert = (level: 'info' | 'important'): HomeBlock => ({ kind: 'alert', level, text: 'Bassin fermé' })
  const partners: HomeBlock = { kind: 'partners', partners: [{ name: 'KIP Sport', url: 'https://kip.example', logo: null }] }
  const sectionOrder = (wrapper: Awaited<ReturnType<typeof mountHome>>) =>
    wrapper.findAll('section[aria-labelledby]').map(section => section.attributes('aria-labelledby') ?? section.attributes('data-testid'))

  it('CNT-05: the active blocks in the configured order, events blocks before the partners', async () => {
    const wrapper = await mountHome(async () => fixtures, async () => [...fixtureBlocks, partners])
    expect(sectionOrder(wrapper)).toEqual(['home-featured', 'home-latest-news', 'upcoming-fixtures-title', 'recent-fixtures-title', 'home-partners'])
    expect(wrapper.find('[data-testid="home-featured"] a').attributes('href')).toBe('/news/finale-resultats')
    expect(wrapper.findAll('[data-testid="home-latest-news"] [data-testid="news-card"]')).toHaveLength(2)
    expect(wrapper.find('[data-testid="home-all-news"]').attributes('href')).toBe('/news')
    const partner = wrapper.find('[data-testid="home-partners"] a')
    expect(partner.attributes('href')).toBe('https://kip.example')
    expect(partner.attributes('target')).toBe('_blank')
  })

  it('CNT-05: an api2 error keeps the events blocks', async () => {
    const wrapper = await mountHome(async () => fixtures, async () => {
      throw new Error('api2 down')
    })
    expect(wrapper.find('[data-testid="home-featured"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="upcoming-fixtures"]').exists()).toBe(true)
  })

  it('CNT-06: the alert banner comes first, with the ARIA role of its level', async () => {
    const info = await mountHome(async () => fixtures, async () => [...fixtureBlocks, alert('info')])
    const banner = info.find('[data-testid="home-alert"]')
    expect(banner.attributes('role')).toBe('status')
    expect(banner.text()).toBe('Bassin fermé')
    expect(info.element.firstElementChild?.getAttribute('data-testid')).toBe('home-alert')

    const important = await mountHome(async () => fixtures, async () => [alert('important')])
    expect(important.find('[data-testid="home-alert"]').attributes('role')).toBe('alert')
  })
})
