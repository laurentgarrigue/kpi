import type { AnalyticsEvent } from '../utils/matomo'

/** Hits sent by a page itself (FEATURE_ANALYTICS.md § 5.3–5.5). No-op on the server and when tracking is off. */
export function useMatomo() {
  const tracker = import.meta.client ? useNuxtApp().$matomo : null
  return {
    trackNotFound: () => tracker?.notFound(),
    trackSiteSearch: (keyword: string, category: string | null, count: number) => tracker?.siteSearch(keyword, category, count),
    trackEvent: (event: AnalyticsEvent) => tracker?.event(event),
  }
}
