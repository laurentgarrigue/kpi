import type { PageLinkContext } from '~/utils/page-links'

/** Context of the links from results pages to the legacy site, app2 and the PDFs. */
export function usePageLinkContext(): ComputedRef<PageLinkContext> {
  const { locale } = useI18n()
  const { legacyBaseUrl, app2BaseUrl } = useRuntimeConfig().public
  return computed(() => ({ locale: locale.value, legacyBaseUrl, app2BaseUrl }))
}
