// X-Robots-Tag on every response while in beta (SITE_PLATFORM.md § 4.2, PLT-03).
export default defineEventHandler((event) => {
  const directive = robotsDirective(useRuntimeConfig(event).public.beta)
  if (directive) {
    setHeader(event, 'X-Robots-Tag', directive)
  }
})
