// Matomo audience measurement, cookie-free (FEATURE_ANALYTICS.md). Pure logic: the browser is injected, so every
// rule is unit tested. Copied as is into app2/utils/ and app4/utils/ until they adopt the layer (phase 6); the copies
// are checked identical by kpi-layer/tests/matomo.spec.ts.

export type MatomoCommand = [string, ...unknown[]]

export interface MatomoSettings {
  url?: string
  siteId?: string | number
}

export interface MatomoTarget {
  trackerUrl: string
  scriptUrl: string
  siteId: string
}

/** What the tracker reads from the browser, at the time the hit is sent. */
export interface MatomoPage {
  href: () => string
  title: () => string
  referrer: () => string
  /** Runs after the head of the new page is rendered (its title included). */
  defer: (send: () => void) => void
}

/** A click on an element carrying `data-analytics-category` and `data-analytics-action` (§ 5.5). */
export interface AnalyticsEvent {
  category: string
  action: string
  name?: string
}

export interface MatomoTracker {
  pageView: () => void
  notFound: () => void
  siteSearch: (keyword: string, category: string | null, count: number) => void
  event: (event: AnalyticsEvent) => void
}

/** `.ics` files count as downloads (ANA-12); Matomo already knows pdf, csv, xlsx… */
export const MATOMO_EXTRA_DOWNLOAD_EXTENSIONS = ['ics']

/** Tracking is on if and only if both the URL and the site are set (ANA-01). */
export function matomoTarget(settings: MatomoSettings): MatomoTarget | null {
  const base = (settings.url ?? '').trim().replace(/\/+$/, '')
  const siteId = String(settings.siteId ?? '').trim()
  if (!base || !siteId) {
    return null
  }
  return { trackerUrl: `${base}/matomo.php`, scriptUrl: `${base}/matomo.js`, siteId }
}

/** Sent before any hit (ANA-02): no cookie, then where and for which site. */
export function matomoInitCommands(target: MatomoTarget): MatomoCommand[] {
  return [
    ['disableCookies'],
    ['setTrackerUrl', target.trackerUrl],
    ['setSiteId', target.siteId],
    ['addDownloadExtensions', MATOMO_EXTRA_DOWNLOAD_EXTENSIONS.join('|')],
    ['enableLinkTracking'],
    ['enableHeartBeatTimer'],
  ]
}

/** Matomo's convention for the « Pages not found » report (ANA-10). */
export function notFoundTitle(href: string, referrer: string): string {
  const { pathname, search } = new URL(href)
  return `404/URL = ${encodeURIComponent(pathname + search)}/From = ${encodeURIComponent(referrer)}`
}

/** Same page = same origin and path: a query or hash change (tab, filter) is not a new page (ANA-08). */
function pageKey(href: string): string {
  const { origin, pathname } = new URL(href)
  return origin + pathname
}

/** The event declared by `data-analytics-*` attributes, or null when they are incomplete. */
export function analyticsEventOf(dataset: Record<string, string | undefined>): AnalyticsEvent | null {
  const { analyticsCategory: category, analyticsAction: action, analyticsName: name } = dataset
  if (!category || !action) {
    return null
  }
  return name ? { category, action, name } : { category, action }
}

export function createMatomoTracker(push: (command: MatomoCommand) => void, page: MatomoPage): MatomoTracker {
  let previousHref: string | null = null
  let previousPage: string | null = null

  // Internal navigation: the referrer is the previous page (ANA-07); the first one keeps document.referrer.
  function locate(href: string) {
    if (previousHref !== null) {
      push(['setReferrerUrl', previousHref])
    }
    push(['setCustomUrl', href])
    previousHref = href
    previousPage = pageKey(href)
  }

  return {
    pageView: () => page.defer(() => {
      const href = page.href()
      if (pageKey(href) === previousPage) {
        return
      }
      locate(href)
      push(['setDocumentTitle', page.title()])
      push(['trackPageView'])
    }),
    notFound: () => page.defer(() => {
      const href = page.href()
      const from = previousHref ?? page.referrer()
      locate(href)
      push(['setDocumentTitle', notFoundTitle(href, from)])
      push(['trackPageView'])
    }),
    siteSearch: (keyword, category, count) => page.defer(() => {
      locate(page.href())
      push(['setDocumentTitle', page.title()])
      push(['trackSiteSearch', keyword, category ?? false, count])
    }),
    event: ({ category, action, name }) => {
      push(name ? ['trackEvent', category, action, name] : ['trackEvent', category, action])
    },
  }
}
