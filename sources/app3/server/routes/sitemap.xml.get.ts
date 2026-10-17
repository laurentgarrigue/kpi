import { joinURL } from 'ufo'

// Sitemap: application pages + published articles and pages from api2 (PAGE_CONTENT.md § 5, CNT-07). Without api2,
// the application pages are still listed.
export default defineEventHandler(async (event) => {
  const config = useRuntimeConfig(event)
  const content = await $fetch<SitemapEntry[]>(joinURL(config.api2InternalUrl || config.public.api2BaseUrl, 'sitemap/content.xml'))
    .catch(() => [])
  setHeader(event, 'Content-Type', 'application/xml; charset=utf-8')
  setHeader(event, 'Cache-Control', 'public, max-age=3600')
  return sitemapXml(config.public.i18n.baseUrl, content)
})
