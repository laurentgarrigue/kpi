import { joinURL } from 'ufo'

// RSS feed of the news, relayed from api2 under the site's own domain (PAGE_NEWS.md § 2.1.5, NEWS-08): api2 builds
// its item links and its `atom:link` with the public site URL.
export default defineEventHandler(async (event) => {
  const config = useRuntimeConfig(event)
  const lang = getQuery(event).lang === 'en' ? 'en' : null
  const feed = await $fetch<string>(joinURL(config.api2InternalUrl || config.public.api2BaseUrl, 'rss.xml'), {
    query: lang ? { lang } : {},
    responseType: 'text',
  })
  setHeader(event, 'Content-Type', 'application/rss+xml; charset=utf-8')
  setHeader(event, 'Cache-Control', 'public, max-age=300')
  return feed
})
