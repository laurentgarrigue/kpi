/**
 * Old paths of the WordPress site (permalinks, `?p=`, `?page_id=`) that api2 knows how to redirect (FEATURE_CMS.md
 * § 8, `kp_cms_redirect`). Only these shapes are looked up, so that ordinary pages never wait for api2.
 */
const WORDPRESS_QUERY_KEYS = ['p', 'page_id'] as const

/** Path to look up in api2 `GET /redirect`, or `null` when the request cannot be an old path. */
export function oldPathCandidate(pathname: string, query: Record<string, unknown>): string | null {
  if (pathname === '/') {
    const key = WORDPRESS_QUERY_KEYS.find(name => typeof query[name] === 'string' && /^\d{1,10}$/.test(query[name] as string))
    return key ? `/?${key}=${query[key]}` : null
  }
  // WordPress permalinks end with a slash; the site's own routes never do.
  return pathname.length > 1 && pathname.length <= 255 && pathname.endsWith('/') && !pathname.startsWith('/_') ? pathname : null
}
