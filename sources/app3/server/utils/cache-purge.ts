/**
 * Purge of the page cache after a publication (FEATURE_CMS.md § 5.4, CMS-07). Pages are cached by the route rules
 * (nuxt.config.ts) under `cache:nitro:routes:_:{pathname}.{hash}.json`, where `{pathname}` is the path without its
 * non-word characters, cut to 16 characters (nitropack `defineCachedEventHandler`). The prefix therefore matches
 * every query string of a path (`/news?page=2`); paths sharing their first 16 characters are purged together, which
 * is harmless.
 */

export const ROUTE_CACHE_BASE = 'cache:nitro:routes'
/** Purges every page (the editorial menu is on all of them). */
export const PURGE_ALL = '/*'
const PATHNAME_KEY_LENGTH = 16
const LANGUAGE_PREFIXES = ['', '/en']

function pathnameKey(path: string): string {
  return path.replace(/\W/g, '').slice(0, PATHNAME_KEY_LENGTH) || 'index'
}

/** Variants of a page: its languages and its extracted payloads (`_payload.json`). */
export function purgedPaths(path: string): string[] {
  return LANGUAGE_PREFIXES.flatMap((prefix) => {
    const page = `${prefix}${path === '/' ? '' : path}` || '/'
    return [page, `${page === '/' ? '' : page}/_payload.json`]
  })
}

/** Cache keys of the given paths, among the stored keys; `/*` → all of them. */
export function purgedKeys(paths: readonly string[], keys: readonly string[]): string[] {
  if (paths.includes(PURGE_ALL)) {
    return keys.filter(key => key.startsWith(`${ROUTE_CACHE_BASE}:`))
  }
  const pathnames = new Set(paths.flatMap(purgedPaths).map(pathnameKey))
  return keys.filter(key => key.startsWith(`${ROUTE_CACHE_BASE}:`) && pathnames.has(key.split(':').at(-1)?.split('.')[0] ?? ''))
}

/** The request carries the shared secret (`Authorization: Bearer …`), compared in constant time. */
export function isPurgeAuthorized(authorization: string | undefined, token: string): boolean {
  const expected = `Bearer ${token}`
  if (!token || !authorization || authorization.length !== expected.length) {
    return false
  }
  let difference = 0
  for (let index = 0; index < expected.length; index++) {
    difference |= expected.charCodeAt(index) ^ authorization.charCodeAt(index)
  }
  return difference === 0
}
