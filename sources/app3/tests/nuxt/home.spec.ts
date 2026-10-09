import { afterAll, beforeAll, describe, expect, it, vi } from 'vitest'
import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import IndexPage from '~/pages/index.vue'
import type { PublicEvent } from '~/utils/events'

const { api2 } = vi.hoisted(() => ({ api2: vi.fn() }))
mockNuxtImport('useApi2', () => () => api2)

// "Today" is 15 June 2026 in Paris for the whole file.
beforeAll(() => {
  vi.useFakeTimers({ toFake: ['Date'] })
  vi.setSystemTime(new Date('2026-06-15T10:00:00Z'))
})
afterAll(() => {
  vi.useRealTimers()
})

const event = (id: number, start: string, end: string = start, logo: string | null = null): PublicEvent => ({
  id, libelle: `Event ${id}`, place: 'Paris', logo, start, end,
})

// api2 order: start date descending.
const events: PublicEvent[] = [
  event(1, '2026-09-01'),
  event(2, '2026-07-01'),
  event(3, '2026-06-14', '2026-06-16', 'logo/event3.png'), // ongoing
  ...Array.from({ length: 7 }, (_, index) => event(10 + index, `2026-05-${String(20 - index).padStart(2, '0')}`)),
]

async function mountHome(response: () => Promise<unknown>) {
  api2.mockReset()
  api2.mockImplementation(response)
  clearNuxtData('home-events')
  return mountSuspended(IndexPage)
}

const cardIds = (section: ReturnType<Awaited<ReturnType<typeof mountHome>>['find']>) =>
  section.findAll('a').map(link => link.attributes('href')?.split('/').pop())

describe('home page (PAGE_HOME.md)', () => {
  it('HOME-01: has a single translated h1, not limited to France', async () => {
    const wrapper = await mountHome(async () => events)
    expect(wrapper.findAll('h1')).toHaveLength(1)
    expect(wrapper.find('h1').text()).toBe('Le kayak-polo, en France et à l\'international')
  })

  it('HOME-02: the competitions button has the same target as the menu entry', async () => {
    const wrapper = await mountHome(async () => events)
    expect(wrapper.find('[data-testid="competitions-cta"]').attributes('href'))
      .toBe('/competitions')
  })

  it('HOME-07: upcoming events come first, nearest first, ongoing ones flagged', async () => {
    const wrapper = await mountHome(async () => events)
    const sections = wrapper.findAll('section[aria-labelledby]').map(section => section.find('h2').text())
    expect(sections).toEqual(['Prochains événements', 'Événements récents'])

    const upcoming = wrapper.find('[data-testid="upcoming-events"]')
    expect(cardIds(upcoming)).toEqual(['3', '2', '1'])
    expect(upcoming.findAll('[data-testid="ongoing-badge"]')).toHaveLength(1)
    expect(upcoming.find('a').find('[data-testid="ongoing-badge"]').text()).toBe('En cours')
  })

  it('HOME-03: shows at most 6 finished events, most recent first', async () => {
    const wrapper = await mountHome(async () => events)
    expect(cardIds(wrapper.find('[data-testid="recent-events"]'))).toEqual(['10', '11', '12', '13', '14', '15'])
    expect(api2).toHaveBeenCalledWith('/events/all')
  })

  it('HOME-04: cards open the event page of the site, with dates and a logo only when set', async () => {
    const wrapper = await mountHome(async () => events)
    const card = wrapper.find('[data-testid="upcoming-events"] a')
    expect(card.attributes('href')).toBe('/events/3')
    expect(card.attributes('target')).toBeUndefined()
    expect(card.text()).toMatch(/14\s?–\s?16 juin 2026/)
    expect(card.find('img').attributes('src')).toBe('https://kpi.localhost/img/logo/event3.png')
    expect(wrapper.findAll('[data-testid="upcoming-events"] a')[1]?.find('img').exists()).toBe(false)
  })

  it('HOME-05: shows a message per empty section', async () => {
    const wrapper = await mountHome(async () => [])
    expect(wrapper.find('[data-testid="upcoming-events-empty"]').text()).toBe('Aucun événement à venir pour le moment.')
    expect(wrapper.find('[data-testid="recent-events-empty"]').text()).toBe('Aucun événement publié pour le moment.')
  })

  it('HOME-05: shows a single unavailability message when api2 fails', async () => {
    const wrapper = await mountHome(async () => {
      throw new Error('api2 down')
    })
    expect(wrapper.findAll('[data-testid="events-unavailable"]')).toHaveLength(1)
    expect(wrapper.find('[data-testid="upcoming-events"]').exists()).toBe(false)
  })

  it('LAY-09: the live button opens app2 in a new tab', async () => {
    const wrapper = await mountHome(async () => events)
    const live = wrapper.find('[data-testid="live-cta"]')
    expect(live.attributes('target')).toBe('_blank')
    expect(live.attributes('rel')).toContain('noopener')
    expect(live.text()).toContain('(nouvel onglet)')
  })
})
