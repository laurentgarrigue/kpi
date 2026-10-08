import { joinURL } from 'ufo'

/** Default head of every page: language, canonical/hreflang, title template, robots (SITE_LAYOUT.md § 5). */
export function useSiteSeo() {
  const { t } = useI18n()
  const config = useRuntimeConfig().public
  const localeHead = useLocaleHead({ seo: true })
  const siteName = t('site.name')

  useHead(() => ({
    htmlAttrs: localeHead.value.htmlAttrs,
    link: localeHead.value.link,
    meta: localeHead.value.meta,
    titleTemplate: (title?: string) => (title ? `${title} — ${siteName}` : siteName),
  }))

  useSeoMeta({
    description: () => t('site.description'),
    ogSiteName: siteName,
    ogType: 'website',
    ogImage: joinURL(config.i18n.baseUrl, 'img/brand/cna-kayak-polo.png'),
    robots: robotsDirective(config.beta) ?? undefined,
  })
}
