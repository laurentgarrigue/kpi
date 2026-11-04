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

describe('home page (PAGE_HOME.md § 2 bis)', () => {
  it('HOME-01: has a single small translated h1, not limited to France', async () => {
    const wrapper = await mountHome(async () => fixtures)
    expect(wrapper.findAll('h1')).toHaveLength(1)
    expect(wrapper.find('h1').text()).toBe('Le kayak-polo, en France et à l\'international')
  })

  it('HOME-11 / LAY-09: an ongoing next fixture opens the page, followed live in app2 in a new tab', async () => {
    const wrapper = await mountHome(async () => fixtures)
    const next = wrapper.find('[data-testid="home-next"]')
    expect(next.attributes('data-live')).toBeDefined()
    expect(next.find('[data-testid="home-next-when"]').text()).toBe('En ce moment')
    expect(next.find('h2').text()).toBe('Event 3')
    const live = next.find('[data-testid="live-cta"]')
    expect(live.attributes('href')).toBe('https://app.kpi.localhost/event/3')
    expect(live.attributes('target')).toBe('_blank')
    expect(live.attributes('rel')).toContain('noopener')
    expect(live.text()).toContain('(nouvel onglet)')
    expect(next.find('[data-testid="home-next-link"]').attributes('href')).toBe('/events/3')
  })

  it('HOME-11: a coming fixture says when, and offers its programme and calendar', async () => {
    const wrapper = await mountHome(async () => ({ ...fixtures, upcoming: fixtures.upcoming.slice(2) }))
    const next = wrapper.find('[data-testid="home-next"]')
    expect(next.attributes('data-live')).toBeUndefined()
    expect(next.find('[data-testid="home-next-when"]').text()).toBe('Dans 16 jours')
    expect(next.find('[data-testid="home-next-link"]').attributes('href')).toBe('/events/2')
    expect(next.find('[data-testid="home-next-ics"]').attributes('href')).toBe('https://kpi.localhost/api2/event/2/calendar.ics')
  })

  it('HOME-12: group days on the very dates of the next event are its programme, not repeated after', async () => {
    const wrapper = await mountHome(async () => ({ ...fixtures, upcoming: [event(5, '2026-06-20', '2026-06-21'), ...fixtures.upcoming.slice(1)] }))
    expect(wrapper.findAll('[data-testid="home-programme"] a').map(link => link.attributes('href'))).toEqual(['/groups/2026/N1H'])
    expect(cardLinks(wrapper.find('[data-testid="upcoming-fixtures"]'))).toEqual(['/events/2', '/events/1'])
  })

  it('HOME-07 / HOME-09: the other upcoming fixtures in api2 order; a group day shows its group and place and leads to the group page', async () => {
    const wrapper = await mountHome(async () => fixtures)
    const after = wrapper.find('[data-testid="upcoming-fixtures"]')
    expect(cardLinks(after)).toEqual(['/groups/2026/N1H', '/events/2', '/events/1'])
    expect(after.find('a').text()).toContain('Groupe N1H')
    expect(after.find('a').text()).toContain('Lacville (33)')
    expect(after.find('a').text()).toMatch(/20\s?–\s?21 juin/)
  })

  it('HOME-03: shows the finished fixtures of api2, most recent first', async () => {
    const wrapper = await mountHome(async () => fixtures)
    expect(cardLinks(wrapper.find('[data-testid="recent-fixtures"]'))).toEqual(['/groups/2026/N2H', '/events/10'])
    expect(api2).toHaveBeenCalledWith('/fixtures/site')
  })

  it('HOME-05: shows a message per empty section, and no next fixture', async () => {
    const wrapper = await mountHome(async () => noFixtures)
    expect(wrapper.find('[data-testid="home-next"]').exists()).toBe(false)
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
})

describe('editorial home (PAGE_CONTENT.md § 4)', () => {
  const alert = (level: 'info' | 'important'): HomeBlock => ({ kind: 'alert', level, text: 'Bassin fermé' })
  const partners: HomeBlock = { kind: 'partners', partners: [{ name: 'KIP Sport', url: 'https://kip.example', logo: null }] }
  const sectionOrder = (wrapper: Awaited<ReturnType<typeof mountHome>>) =>
    wrapper.findAll('section[aria-labelledby^="home-"]').map(section => section.attributes('aria-labelledby'))

  it('HOME-13 / CNT-05: the next fixture, the news (the featured one first) then the agenda; the partners close the page', async () => {
    const wrapper = await mountHome(async () => fixtures, async () => [...fixtureBlocks, partners])
    expect(sectionOrder(wrapper)).toEqual(['home-next', 'home-latest-news', 'home-agenda', 'home-partners'])
    const news = wrapper.findAll('[data-testid="home-latest-news"] [data-testid="news-card"]')
    expect(news.length).toBeGreaterThan(1)
    expect(news[0]!.find('a').attributes('href')).toBe('/news/finale-resultats')
    expect(wrapper.find('[data-testid="home-all-news"]').attributes('href')).toBe('/news')
    const partner = wrapper.find('[data-testid="home-partners"] a')
    expect(partner.attributes('href')).toBe('https://kip.example')
    expect(partner.attributes('target')).toBe('_blank')
  })

  it('CNT-05: an api2 error keeps the fixtures', async () => {
    const wrapper = await mountHome(async () => fixtures, async () => {
      throw new Error('api2 down')
    })
    expect(wrapper.find('[data-testid="home-latest-news"]').exists()).toBe(false)
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
