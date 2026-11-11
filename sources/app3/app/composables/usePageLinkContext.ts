import type { PageLinkContext } from '~/utils/page-links'

const EVENT_PAGE = /^\/(?:en\/)?events\/(\d+)(?:\/|$)/

/** Context of the links from results pages to the legacy site, app2 and the PDFs (legacy or api2). */
export function usePageLinkContext(): ComputedRef<PageLinkContext> {
  const { locale } = useI18n()
  const route = useRoute()
  const { legacyBaseUrl, app2BaseUrl, api2BaseUrl } = useRuntimeConfig().public
  return computed(() => {
    const event = EVENT_PAGE.exec(route.path)?.[1]
    return { locale: locale.value, legacyBaseUrl, app2BaseUrl, api2BaseUrl, ...(event ? { event: Number(event) } : {}) }
  })
}
