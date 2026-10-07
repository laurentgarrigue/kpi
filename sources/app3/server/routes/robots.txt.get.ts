// Crawling rules: everything disallowed while in beta (SITE_PLATFORM.md § 4.2, PLT-02).
export default defineEventHandler((event) => {
  const { beta, i18n } = useRuntimeConfig(event).public
  setHeader(event, 'Content-Type', 'text/plain; charset=utf-8')
  return robotsTxt({ beta, siteUrl: i18n.baseUrl })
})
