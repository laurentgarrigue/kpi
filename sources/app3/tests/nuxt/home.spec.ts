import { describe, expect, it, vi } from 'vitest'
import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import IndexPage from '~/pages/index.vue'
import type { PublicEvent } from '~/utils/events'

const { api2 } = vi.hoisted(() => ({ api2: vi.fn() }))
mockNuxtImport('useApi2', () => () => api2)

const events: PublicEvent[] = Array.from({ length: 8 }, (_, index) => ({
  id: index + 1,
  libelle: `Event ${index + 1}`,
  place: 'Paris',
  logo: index === 0 ? 'logo/event1.png' : null,
  year: 2026,
}))

async function mountHome(response: () => Promise<unknown>) {
  api2.mockReset()
  api2.mockImplementation(response)
  clearNuxtData('home-recent-events')
  return mountSuspended(IndexPage)
}

describe('home page (PAGE_HOME.md)', () => {
  it('HOME-01: has a single translated h1', async () => {
    const wrapper = await mountHome(async () => events)
    expect(wrapper.findAll('h1')).toHaveLength(1)
    expect(wrapper.find('h1').text()).toBe('Le kayak-polo en France')
  })

  it('HOME-02: the competitions button has the same target as the menu entry', async () => {
    const wrapper = await mountHome(async () => events)
    expect(wrapper.find('[data-testid="competitions-cta"]').attributes('href'))
      .toBe('https://kpi.localhost/kpclassements.php?lang=fr')
  })

  it('HOME-03/HOME-04: shows at most 6 events linking to app2, with a logo only when set', async () => {
    const wrapper = await mountHome(async () => events)
    const cards = wrapper.findAll('[data-testid="events-list"] a')
    expect(cards).toHaveLength(6)
    expect(cards[0]?.attributes('href')).toBe('https://app.kpi.localhost/event/1')
    expect(cards[0]?.find('img').attributes('src')).toBe('https://kpi.localhost/img/logo/event1.png')
    expect(cards[1]?.find('img').exists()).toBe(false)
    expect(api2).toHaveBeenCalledWith('/events/all')
  })

  it('HOME-05: shows a message when there is no event', async () => {
    const wrapper = await mountHome(async () => [])
    expect(wrapper.find('[data-testid="events-empty"]').exists()).toBe(true)
  })

  it('HOME-05: shows an unavailability message when api2 fails', async () => {
    const wrapper = await mountHome(async () => {
      throw new Error('api2 down')
    })
    expect(wrapper.find('[data-testid="events-unavailable"]').exists()).toBe(true)
  })
})
