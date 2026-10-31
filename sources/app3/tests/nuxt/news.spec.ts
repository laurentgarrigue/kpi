import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import NavMain from '~/components/nav/NavMain.vue'
import { fakeApi2 } from '../fixtures/api2'
import { expectNotFound, mountRoute } from './results-setup'

// News list and article (PAGE_NEWS.md), on real api2 responses captured on SQL/fixtures.
const { api2 } = vi.hoisted(() => ({ api2: vi.fn() }))
mockNuxtImport('useApi2', () => () => api2)
beforeEach(() => {
  api2.mockReset()
  api2.mockImplementation(fakeApi2)
  clearNuxtData()
})

describe('news list (PAGE_NEWS.md § 2.1)', () => {
  it('NEWS-01 / NEWS-10: lists the published news, newest first, the first one put forward, the others as editorial rows', async () => {
    const wrapper = await mountRoute('/news')
    expect(wrapper.find('h1').text()).toBe('Actualités')
    const cards = wrapper.findAll('[data-testid="news-card"]')
    expect(cards.map(card => card.find('h2 a').attributes('href'))).toEqual(['/news/finale-resultats', '/news/nouveau-reglement'])
    const first = wrapper.find('[data-testid="news-featured"] [data-testid="news-card"]')
    expect(first.find('h2 a').attributes('href')).toBe('/news/finale-resultats')
    expect(first.find('time').attributes('datetime')).toBe('2020-06-01T12:00:00+02:00')
    expect(first.find('time').text()).toBe('1 juin 2020')
    expect(first.find('img').attributes('src')).toBe('https://kpi.localhost/media/content/2999/01/finale-1280.webp?v=afcd5ac1')
    expect(first.find('img').attributes('alt')).toBe('La finale')
    expect(first.find('img').attributes('loading')).toBeUndefined()
    expect(first.text()).toContain('Compétitions nationales')
    const row = wrapper.find('[data-testid="news-list"] [data-testid="news-card"]')
    expect(row.find('h2 a').attributes('href')).toBe('/news/nouveau-reglement')
    expect(row.text()).toContain('Règlements et vie fédérale')
    // A single page: no pagination.
    expect(wrapper.find('[data-testid="news-pager"]').exists()).toBe(false)
  })

  it('NEWS-01: a page after the last one is the site 404', async () => {
    expect(await expectNotFound('/news?page=2')).toBe(404)
    expect(await expectNotFound('/news?page=abc')).toBe(404)
  })

  it('NEWS-02: the category chips filter the list, the current one marked', async () => {
    const wrapper = await mountRoute('/news?category=equipes-de-france')
    expect(api2).toHaveBeenCalledWith('/news?category=equipes-de-france')
    const chips = wrapper.findAll('[data-testid="news-categories"] a')
    expect(chips.map(chip => chip.text())).toEqual(['Toutes', 'Équipes de France', 'Compétitions nationales', 'Compétitions régionales, open et internationales', 'Règlements et vie fédérale'])
    expect(chips[0]!.attributes('href')).toBe('/news')
    expect(chips[1]!.attributes('href')).toBe('/news?category=equipes-de-france')
    expect(chips[1]!.attributes('aria-current')).toBe('page')
    expect(wrapper.findAll('[data-testid="news-card"]')).toHaveLength(1)
  })

  it('NEWS-02: an unknown category is ignored', async () => {
    const wrapper = await mountRoute('/news?category=nope')
    expect(api2).toHaveBeenCalledWith('/news')
    expect(wrapper.findAll('[data-testid="news-card"]')).toHaveLength(2)
    expect(wrapper.find('[data-testid="news-categories"] a[aria-current]').text()).toBe('Toutes')
  })

  it('NEWS-01: pagination links keep the category and work without JavaScript', async () => {
    api2.mockImplementation(async (path: string) => (path.startsWith('/news?')
      ? { ...(await fakeApi2<object>('/news')), page: 2, pages: 3 }
      : fakeApi2(path)))
    const wrapper = await mountRoute('/news?category=equipes-de-france&page=2')
    expect(wrapper.find('[data-testid="news-newer"]').attributes('href')).toBe('/news?category=equipes-de-france')
    expect(wrapper.find('[data-testid="news-older"]').attributes('href')).toBe('/news?category=equipes-de-france&page=3')
  })

  it('NEWS-01: no news → message', async () => {
    api2.mockImplementation(async (path: string) => (path === '/news' ? { items: [], page: 1, pages: 0, total: 0 } : fakeApi2(path)))
    expect((await mountRoute('/news')).find('[data-testid="news-empty"]').text()).toBe('Aucune actualité pour le moment.')
  })

  it('NEWS-08: links to the RSS feed of the site', async () => {
    const wrapper = await mountRoute('/news')
    expect(wrapper.find('[data-testid="news-rss"]').attributes('href')).toBe('/rss.xml')
  })
})

describe('article (PAGE_NEWS.md § 2.2)', () => {
  it('NEWS-03: title, date, signature, categories, cover, lead and body', async () => {
    const wrapper = await mountRoute('/news/finale-resultats')
    expect(wrapper.find('h1').text()).toBe('Finale des résultats')
    expect(wrapper.find('[data-testid="breadcrumb"]').text()).toContain('Actualités')
    expect(wrapper.find('[data-testid="news-meta"] time').text()).toBe('1 juin 2020')
    expect(wrapper.find('[data-testid="news-updated"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="news-signature"]').text()).toBe('· Par La CNA Kayak-Polo')
    expect(wrapper.findAll('[data-testid="news-categories"] a').map(link => link.attributes('href'))).toEqual(['/news?category=equipes-de-france', '/news?category=competitions-nationales'])
    const cover = wrapper.find('[data-testid="news-cover"]')
    expect(cover.attributes('srcset')).toContain('finale-1280.webp?v=afcd5ac1 1280w')
    expect(cover.attributes('loading')).toBeUndefined()
    expect(wrapper.find('[data-testid="news-lead"]').text()).toBe('Le chapô.')
    expect(wrapper.find('[data-testid="content-body"]').html()).toContain('Corps <strong>gras</strong>.')
  })

  it('NEWS-03: a draft or unknown article is the site 404', async () => {
    expect(await expectNotFound('/news/brouillon')).toBe(404)
  })

  it('NEWS-04: the related block leads to the site pages of the competitions, events and clubs', async () => {
    const links = (await mountRoute('/news/finale-resultats')).findAll('[data-testid="news-links"] a')
    expect(links.map(link => [link.text(), link.attributes('href')])).toEqual([
      ['Championnat Résultats 2999', '/competitions/2999/RCH'],
      ['Tournoi Résultats', '/events/77'],
      ['Club Alpha Lacville', '/clubs?club=C001'],
    ])
  })

  it('NEWS-07: previous and next articles', async () => {
    const wrapper = await mountRoute('/news/finale-resultats')
    expect(wrapper.find('[data-testid="news-previous"]').attributes('href')).toBe('/news/nouveau-reglement')
    expect(wrapper.find('[data-testid="news-next"]').exists()).toBe(false)
  })

  it('NEWS-05: sharing falls back to plain links of the networks, without third-party script', async () => {
    const share = (await mountRoute('/news/finale-resultats')).find('[data-testid="news-share"]')
    expect(share.findAll('a').map(link => link.attributes('data-share'))).toEqual(['facebook', 'x', 'whatsapp', 'email'])
    expect(share.find('[data-share="facebook"]').attributes('href')).toBe('https://www.facebook.com/sharer/sharer.php?u=https:%2F%2Fbeta.kpi.localhost%2Fnews%2Ffinale-resultats')
    expect(share.find('script').exists()).toBe(false)
  })

  it('NEWS-05: the Web Share API is used when the browser has it', async () => {
    const share = vi.fn().mockResolvedValue(undefined)
    vi.stubGlobal('navigator', { ...navigator, share })
    const wrapper = await mountRoute('/news/finale-resultats')
    await wrapper.find('[data-testid="news-share"] summary').trigger('click')
    expect(share).toHaveBeenCalledWith({ url: 'https://beta.kpi.localhost/news/finale-resultats', title: 'Finale des résultats' })
    vi.unstubAllGlobals()
  })

  it('NEWS-07: images open the viewer, browsed with the arrows and closed with Escape; videos load on click', async () => {
    const body = '<p><img src="/media/content/a.jpg" alt="A"><img src="/media/content/b.jpg" alt="B"></p>'
      + '<iframe src="https://www.youtube-nocookie.com/embed/abcdefgh" title="Finale"></iframe>'
    api2.mockImplementation(async (path: string) => (path === '/news/finale-resultats'
      ? { ...(await fakeApi2<object>(path)), body }
      : fakeApi2(path)))
    const wrapper = await mountRoute('/news/finale-resultats')
    const gallery = wrapper.find('[data-testid="gallery"]')
    expect(gallery.find('img').exists()).toBe(false)

    await wrapper.findAll('a.kpi-zoom')[1]!.trigger('click')
    await flushPromises()
    expect(gallery.find('[data-testid="gallery-image"]').attributes('alt')).toBe('B')
    await gallery.trigger('keydown', { key: 'ArrowRight' })
    expect(gallery.find('[data-testid="gallery-image"]').attributes('alt')).toBe('A')
    await gallery.trigger('keydown', { key: 'Escape' })
    expect(gallery.find('[data-testid="gallery-image"]').exists()).toBe(false)

    const video = wrapper.find('a.kpi-video')
    expect(video.attributes('href')).toBe('https://www.youtube.com/watch?v=abcdefgh')
    expect(wrapper.find('iframe').exists()).toBe(false)
    await video.trigger('click')
    expect(wrapper.find('iframe').attributes('src')).toBe('https://www.youtube-nocookie.com/embed/abcdefgh?autoplay=1')
  })

  it('CMS-12: a preview is fetched by the browser with its token', async () => {
    api2.mockImplementation(async (path: string) => (path === '/news/brouillon?preview=t0k3n'
      ? { ...(await fakeApi2<object>('/news/finale-resultats')), title: 'Brouillon', preview: true }
      : fakeApi2(path)))
    const wrapper = await mountRoute('/news/brouillon?preview=t0k3n')
    await flushPromises()
    expect(api2).toHaveBeenCalledWith('/news/brouillon?preview=t0k3n')
    expect(wrapper.find('[data-testid="news-preview"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="news-share"]').exists()).toBe(false)
  })
})

describe('menu (PAGE_NEWS.md § 5, PAGE_CONTENT.md § 3)', () => {
  it('NEWS-08: « News » is an internal entry', async () => {
    const wrapper = await mountSuspended(NavMain, { route: '/news' })
    const news = wrapper.find('[data-testid="main-nav-desktop"] a[href="/news"]')
    expect(news.attributes('data-kind')).toBe('internal')
    expect(news.attributes('aria-current')).toBe('page')
  })

  it('CNT-04: « Le kayak-polo » lists the editorial menu after « Teams and clubs »', async () => {
    const wrapper = await mountSuspended(NavMain, { route: '/' })
    const buttons = wrapper.findAll('[data-testid="main-nav-desktop"] button').map(button => button.text())
    expect(buttons).toEqual(['Compétitions', 'Le kayak-polo'])
    expect(wrapper.find('[data-testid="main-nav-desktop"] a[href="/clubs"]').text()).toBe('Clubs et équipes')
    const links = wrapper.findAll('#nav-group-editorial a')
    expect(links.map(link => [link.text().replace(/\(nouvel onglet\)/, '').trim(), link.attributes('href'), link.attributes('target')])).toEqual([
      ['Le kayak-polo', '/le-kayak-polo', undefined],
      ['Arbitrage', '/le-kayak-polo/arbitrage', undefined],
      ['FFCK', 'https://www.ffck.org', '_blank'],
    ])
  })

  it('CNT-04: an api2 error hides the entry, the rest of the menu is intact', async () => {
    api2.mockImplementation(async (path: string) => (path === '/menu' ? Promise.reject(new Error('down')) : fakeApi2(path)))
    const wrapper = await mountSuspended(NavMain, { route: '/' })
    expect(wrapper.find('#nav-group-editorial').exists()).toBe(false)
    expect(wrapper.findAll('[data-testid="main-nav-desktop"] button')).toHaveLength(1)
  })
})
