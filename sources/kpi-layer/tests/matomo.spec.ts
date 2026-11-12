import { readdirSync, readFileSync } from 'node:fs'
import { describe, expect, it } from 'vitest'
import {
  analyticsEventOf,
  createMatomoTracker,
  type MatomoCommand,
  matomoInitCommands,
  matomoTarget,
  notFoundTitle,
} from '../app/utils/matomo'

const MATOMO = { url: 'https://matomo.kayak-polo.info/', siteId: 8 }

/** A fake browser: the test moves `href` / `title` like a navigation would, hits are sent on `flush()`. */
function fakeBrowser(start: { href: string, title: string, referrer?: string }) {
  const state = { ...start, referrer: start.referrer ?? '' }
  const pending: (() => void)[] = []
  const commands: MatomoCommand[] = []
  const tracker = createMatomoTracker(command => commands.push(command), {
    href: () => state.href,
    title: () => state.title,
    referrer: () => state.referrer,
    defer: send => pending.push(send),
  })
  return {
    tracker,
    commands,
    go(href: string, title: string) {
      state.href = href
      state.title = title
    },
    flush() {
      pending.splice(0).forEach(send => send())
    },
    hits: () => commands.filter(([name]) => ['trackPageView', 'trackSiteSearch', 'trackEvent'].includes(name)),
  }
}

describe('matomoTarget (ANA-01)', () => {
  it('ANA-01: is off without URL or without site', () => {
    expect(matomoTarget({})).toBeNull()
    expect(matomoTarget({ url: '', siteId: '8' })).toBeNull()
    expect(matomoTarget({ url: 'https://matomo.kayak-polo.info', siteId: '' })).toBeNull()
    expect(matomoTarget({ url: '   ', siteId: ' ' })).toBeNull()
  })

  it('ANA-01: builds the tracker and script URLs, trailing slash or not', () => {
    expect(matomoTarget(MATOMO)).toEqual({
      trackerUrl: 'https://matomo.kayak-polo.info/matomo.php',
      scriptUrl: 'https://matomo.kayak-polo.info/matomo.js',
      siteId: '8',
    })
  })
})

describe('matomoInitCommands (ANA-02, ANA-12)', () => {
  const commands = matomoInitCommands(matomoTarget(MATOMO)!)

  it('ANA-02: disables cookies first, then sets the tracker URL and the site', () => {
    expect(commands.slice(0, 3)).toEqual([
      ['disableCookies'],
      ['setTrackerUrl', 'https://matomo.kayak-polo.info/matomo.php'],
      ['setSiteId', '8'],
    ])
  })

  it('ANA-02: sends no hit', () => {
    expect(commands.some(([name]) => name.startsWith('track'))).toBe(false)
  })

  it('ANA-12: counts .ics files as downloads, and tracks links', () => {
    expect(commands).toContainEqual(['addDownloadExtensions', 'ics'])
    expect(commands).toContainEqual(['enableLinkTracking'])
  })
})

describe('createMatomoTracker — page views', () => {
  it('ANA-03: the first page produces exactly one page view, even if signalled twice (nested pages)', () => {
    const browser = fakeBrowser({ href: 'https://beta.kayak-polo.info/competitions/2026/N1H', title: 'N1H' })
    browser.tracker.pageView()
    browser.tracker.pageView()
    browser.flush()
    expect(browser.hits()).toEqual([['trackPageView']])
  })

  it('ANA-03: the first page keeps document.referrer (no setReferrerUrl)', () => {
    const browser = fakeBrowser({ href: 'https://app.kayak-polo.info/', title: 'KPI' })
    browser.tracker.pageView()
    browser.flush()
    expect(browser.commands.some(([name]) => name === 'setReferrerUrl')).toBe(false)
  })

  it('ANA-04: a redirect produces one page view, for the final page', () => {
    // app4: /admin2/ is redirected to /admin2/login before anything is rendered: only the final page finishes.
    const browser = fakeBrowser({ href: 'https://www.kayak-polo.info/admin2/', title: 'KPI Admin' })
    browser.go('https://www.kayak-polo.info/admin2/login', 'Connexion')
    browser.tracker.pageView()
    browser.flush()
    expect(browser.hits()).toEqual([['trackPageView']])
    expect(browser.commands).toContainEqual(['setCustomUrl', 'https://www.kayak-polo.info/admin2/login'])
  })

  it('ANA-05: the URL sent is the full location, base prefix included', () => {
    const browser = fakeBrowser({ href: 'https://www.kayak-polo.info/admin2/login', title: 'Connexion' })
    browser.tracker.pageView()
    browser.flush()
    browser.go('https://www.kayak-polo.info/admin2/competitions', 'Compétitions')
    browser.tracker.pageView()
    browser.flush()
    expect(browser.commands).toContainEqual(['setCustomUrl', 'https://www.kayak-polo.info/admin2/competitions'])
  })

  it('ANA-06: the title is read when the hit is sent, after the new page rendered its head', () => {
    const browser = fakeBrowser({ href: 'https://beta.kayak-polo.info/', title: 'Accueil' })
    browser.tracker.pageView()
    browser.flush()
    browser.go('https://beta.kayak-polo.info/calendar', 'Accueil') // title not updated yet when the page finishes
    browser.tracker.pageView()
    browser.go('https://beta.kayak-polo.info/calendar', 'Calendrier') // head rendered
    browser.flush()
    expect(browser.commands.filter(([name]) => name === 'setDocumentTitle').at(-1)).toEqual(['setDocumentTitle', 'Calendrier'])
  })

  it('ANA-07: after an internal navigation the referrer is the previous page', () => {
    const browser = fakeBrowser({ href: 'https://beta.kayak-polo.info/', title: 'Accueil' })
    browser.tracker.pageView()
    browser.flush()
    browser.go('https://beta.kayak-polo.info/news', 'Actualités')
    browser.tracker.pageView()
    browser.flush()
    expect(browser.commands).toContainEqual(['setReferrerUrl', 'https://beta.kayak-polo.info/'])
  })

  it('ANA-08: a query or hash change on the same page is not a new page view', () => {
    const browser = fakeBrowser({ href: 'https://beta.kayak-polo.info/calendar', title: 'Calendrier' })
    browser.tracker.pageView()
    browser.flush()
    browser.go('https://beta.kayak-polo.info/calendar?month=2026-12#top', 'Calendrier')
    browser.tracker.pageView()
    browser.flush()
    expect(browser.hits()).toHaveLength(1)
  })

  it('ANA-08: another path is a new page view', () => {
    const browser = fakeBrowser({ href: 'https://beta.kayak-polo.info/competitions/2026/N1H', title: 'N1H' })
    browser.tracker.pageView()
    browser.flush()
    browser.go('https://beta.kayak-polo.info/competitions/2026/N1H/ranking', 'N1H — Classement')
    browser.tracker.pageView()
    browser.flush()
    expect(browser.hits()).toHaveLength(2)
  })
})

describe('createMatomoTracker — not found, search, events', () => {
  it('ANA-10: a 404 is sent with the Matomo « 404/URL = …/From = … » title', () => {
    const browser = fakeBrowser({ href: 'https://beta.kayak-polo.info/kpresultats.php?Compet=N1H', title: 'Page introuvable', referrer: 'https://www.google.com/' })
    browser.tracker.notFound()
    browser.flush()
    expect(browser.commands).toContainEqual(['setDocumentTitle', '404/URL = %2Fkpresultats.php%3FCompet%3DN1H/From = https%3A%2F%2Fwww.google.com%2F'])
    expect(browser.hits()).toEqual([['trackPageView']])
  })

  it('ANA-10: after an internal navigation the « From » of a 404 is the previous page', () => {
    const browser = fakeBrowser({ href: 'https://beta.kayak-polo.info/', title: 'Accueil' })
    browser.tracker.pageView()
    browser.flush()
    browser.go('https://beta.kayak-polo.info/teams/999999', 'Page introuvable')
    browser.tracker.notFound()
    browser.tracker.pageView() // a late page:finish on the same URL must not add a second hit
    browser.flush()
    expect(browser.commands).toContainEqual(['setDocumentTitle', notFoundTitle('https://beta.kayak-polo.info/teams/999999', 'https://beta.kayak-polo.info/')])
    expect(browser.hits()).toHaveLength(2)
  })

  it('ANA-11: a search sends a site search with keyword, category and count, and no page view', () => {
    const browser = fakeBrowser({ href: 'https://beta.kayak-polo.info/search?q=acigne', title: 'Résultats pour « acigne »' })
    browser.tracker.siteSearch('acigne', null, 3)
    browser.flush()
    browser.go('https://beta.kayak-polo.info/search?q=acigne&kind=clubs', 'Résultats pour « acigne »')
    browser.tracker.siteSearch('acigne', 'clubs', 1)
    browser.flush()
    expect(browser.hits()).toEqual([
      ['trackSiteSearch', 'acigne', false, 3],
      ['trackSiteSearch', 'acigne', 'clubs', 1],
    ])
  })

  it('ANA-13: an event is sent with its category, action and optional name', () => {
    const browser = fakeBrowser({ href: 'https://beta.kayak-polo.info/', title: 'Accueil' })
    browser.tracker.event({ category: 'Direct', action: 'Suivre en direct', name: 'accueil' })
    browser.tracker.event({ category: 'Calendrier', action: 'Copie du lien' })
    expect(browser.hits()).toEqual([
      ['trackEvent', 'Direct', 'Suivre en direct', 'accueil'],
      ['trackEvent', 'Calendrier', 'Copie du lien'],
    ])
  })

  it('ANA-13: reads an event from data-analytics-* attributes, ignores incomplete ones', () => {
    expect(analyticsEventOf({ analyticsCategory: 'Partage', analyticsAction: 'facebook', analyticsName: '/news/finale' }))
      .toEqual({ category: 'Partage', action: 'facebook', name: '/news/finale' })
    expect(analyticsEventOf({ analyticsCategory: 'Langue', analyticsAction: 'en' })).toEqual({ category: 'Langue', action: 'en' })
    expect(analyticsEventOf({ analyticsCategory: 'Partage' })).toBeNull()
    expect(analyticsEventOf({})).toBeNull()
  })
})

describe('app2 and app4 copies (ANA-14)', () => {
  const read = (path: string) => readFileSync(new URL(path, import.meta.url), 'utf8')

  it.each(['../../app2', '../../app4'])('ANA-14: %s uses the same tracker and plugin as the layer', (app) => {
    expect(read(`${app}/utils/matomo.ts`)).toBe(read('../app/utils/matomo.ts'))
    expect(read(`${app}/plugins/matomo.client.ts`)).toBe(read('../app/plugins/matomo.client.ts'))
  })
})

describe('legacy Smarty pages (ANA-15)', () => {
  const sources = new URL('../../', import.meta.url)
  const templates = readdirSync(new URL('smarty/templates/', sources)).filter(file => file.endsWith('.tpl')).map(file => `smarty/templates/${file}`)
  const tracked = [...templates, 'commun/MyTools.php']
    .map(path => ({ path, code: readFileSync(new URL(path, sources), 'utf8') }))
    .filter(({ code }) => code.includes("_paq.push(['trackPageView'])"))

  it('ANA-15: the legacy pages are tracked (guard against an empty scan)', () => {
    expect(tracked.length).toBeGreaterThan(0)
  })

  it.each(tracked.map(({ path }) => path))('ANA-15: %s disables cookies before each page view', (path) => {
    const code = tracked.find(file => file.path === path)!.code
    const views = code.split("_paq.push(['trackPageView'])").slice(0, -1)
    expect(views.every(before => before.includes("_paq.push(['disableCookies'])"))).toBe(true)
    expect(views.every(before => before.lastIndexOf("_paq.push(['disableCookies'])") > before.lastIndexOf('<script'))).toBe(true)
  })
})
