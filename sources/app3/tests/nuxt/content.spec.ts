import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mockNuxtImport } from '@nuxt/test-utils/runtime'
import { fakeApi2 } from '../fixtures/api2'
import { expectNotFound, mountRoute } from './results-setup'

// Editorial pages (PAGE_CONTENT.md § 2), on real api2 responses captured on SQL/fixtures.
const { api2 } = vi.hoisted(() => ({ api2: vi.fn() }))
mockNuxtImport('useApi2', () => () => api2)
const { navigate } = vi.hoisted(() => ({ navigate: vi.fn() }))
mockNuxtImport('navigateTo', () => navigate)
beforeEach(() => {
  api2.mockReset()
  api2.mockImplementation(fakeApi2)
  navigate.mockReset()
  clearNuxtData()
})

describe('editorial pages (PAGE_CONTENT.md § 2)', () => {
  it('CNT-01: a published page in the site template, with its children', async () => {
    const wrapper = await mountRoute('/le-kayak-polo')
    expect(api2).toHaveBeenCalledWith('/pages/le-kayak-polo')
    expect(wrapper.find('h1').text()).toBe('Le kayak-polo')
    expect(wrapper.findAll('[data-testid="page-children"] a').map(link => link.attributes('href'))).toEqual(['/le-kayak-polo/arbitrage'])
  })

  it('CNT-01: an unknown or draft page is the site 404; an application route is never a page', async () => {
    expect(await expectNotFound('/page-brouillon')).toBe(404)
    expect(await expectNotFound('/Not_A_Slug')).toBe(404)
    api2.mockClear()
    await mountRoute('/calendar')
    expect(api2.mock.calls.some(([path]) => String(path).startsWith('/pages/'))).toBe(false)
  })

  it('CNT-02: a child page is served under its parent path, with the parents in the breadcrumb', async () => {
    const wrapper = await mountRoute('/le-kayak-polo/arbitrage')
    expect(navigate).not.toHaveBeenCalled()
    expect(wrapper.find('[data-testid="breadcrumb"]').findAll('a').map(link => link.attributes('href'))).toEqual(['/', '/le-kayak-polo'])
  })

  it('CNT-02: another path redirects (301) to the page path', async () => {
    await mountRoute('/arbitrage')
    expect(navigate).toHaveBeenCalledWith('/le-kayak-polo/arbitrage', { redirectCode: 301 })
  })

  it('CNT-03: the table of contents appears from 3 h2, with working anchors', async () => {
    const wrapper = await mountRoute('/le-kayak-polo')
    const links = wrapper.findAll('[data-testid="page-toc"] a')
    expect(links.map(link => [link.text(), link.attributes('href')])).toEqual([['Histoire', '#histoire'], ['Règles', '#regles'], ['Pratiquer', '#pratiquer']])
    expect(wrapper.find('[data-testid="content-body"] h2#regles').exists()).toBe(true)
    expect(wrapper.find('[data-testid="page-toc"]').attributes('aria-label')).toBe('Sur cette page')

    // CNT-08: the summary follows the reading.
    expect(wrapper.find('[data-testid="page-toc"] a[aria-current]').exists()).toBe(false)

    const child = await mountRoute('/le-kayak-polo/arbitrage')
    expect(child.find('[data-testid="page-toc"]').exists()).toBe(false)
  })
})

describe('reading (PAGE_CONTENT.md § 2 bis)', () => {
  it('CNT-08: « On this page » marks the heading being read', async () => {
    const callbacks: IntersectionObserverCallback[] = []
    vi.stubGlobal('IntersectionObserver', class {
      constructor(callback: IntersectionObserverCallback) {
        callbacks.push(callback)
      }

      observe() {}
      disconnect() {}
    })
    const wrapper = await mountRoute('/le-kayak-polo')
    const heading = wrapper.find('[data-testid="content-body"] h2#regles').element
    callbacks.at(-1)!([{ isIntersecting: true, target: heading } as unknown as IntersectionObserverEntry], {} as IntersectionObserver)
    await nextTick()
    expect(wrapper.find('[data-testid="page-toc"] a[aria-current="location"]').attributes('href')).toBe('#regles')
    vi.unstubAllGlobals()
  })
})
