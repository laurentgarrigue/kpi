import { joinURL } from 'ufo'

const REDIRECT_CODES = new Set([301, 302, 307, 308])

/** Old WordPress paths → 301 to their new page (FEATURE_CMS.md § 8); unknown ones continue to the site's 404. */
export default defineEventHandler(async (event) => {
  if (event.method !== 'GET' && event.method !== 'HEAD') {
    return
  }
  const url = getRequestURL(event)
  const candidate = oldPathCandidate(url.pathname, getQuery(event))
  if (!candidate) {
    return
  }
  const config = useRuntimeConfig(event)
  const redirect = await $fetch<{ to: string, code: number }>(joinURL(config.api2InternalUrl || config.public.api2BaseUrl, 'redirect'), { query: { path: candidate } })
    .catch(() => null)
  if (redirect?.to?.startsWith('/')) {
    return sendRedirect(event, redirect.to, REDIRECT_CODES.has(redirect.code) ? redirect.code : 301)
  }
})
