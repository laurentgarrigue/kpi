import type { MatomoCommand, MatomoTracker } from '../utils/matomo'

// Matomo, cookie-free (FEATURE_ANALYTICS.md § 5). Off unless `matomoUrl` and `matomoSiteId` are both set (ANA-01).
// Copied as is into app2/plugins/ and app4/plugins/ until they adopt the layer (phase 6, ANA-14).

declare global {
  interface Window {
    _paq?: MatomoCommand[]
  }
}

declare module '#app' {
  interface PageMeta {
    /** 'manual': the page sends its own hit (the search page sends a site search, ANA-11). */
    analytics?: 'manual'
  }
}

export default defineNuxtPlugin((nuxtApp) => {
  const { matomoUrl, matomoSiteId } = useRuntimeConfig().public
  const target = matomoTarget({ url: String(matomoUrl ?? ''), siteId: String(matomoSiteId ?? '') })
  if (!target) {
    return { provide: { matomo: null as MatomoTracker | null } }
  }

  const queue = (window._paq = window._paq ?? [])
  matomoInitCommands(target).forEach(command => queue.push(command))
  const script = document.createElement('script')
  script.async = true
  script.src = target.scriptUrl
  document.head.appendChild(script)

  const tracker = createMatomoTracker(command => queue.push(command), {
    href: () => window.location.href,
    title: () => document.title,
    referrer: () => document.referrer,
    // The head of the new page is rendered by a setTimeout(0) of unhead: send after it, with the right title (ANA-06).
    defer: send => setTimeout(send, 0),
  })

  // One hit per rendered page, after redirects (ANA-03, ANA-04); nested pages finish twice, the tracker deduplicates.
  const router = useRouter()
  nuxtApp.hook('page:finish', () => {
    if (router.currentRoute.value.meta.analytics !== 'manual') {
      tracker.pageView()
    }
  })

  // Clicks on elements declaring an event with data-analytics-* attributes (§ 5.5, ANA-13).
  document.addEventListener('click', (click) => {
    const element = click.target instanceof Element ? click.target.closest<HTMLElement>('[data-analytics-category]') : null
    const event = element ? analyticsEventOf(element.dataset) : null
    if (event) {
      tracker.event(event)
    }
  }, { capture: true })

  return { provide: { matomo: tracker as MatomoTracker | null } }
})
