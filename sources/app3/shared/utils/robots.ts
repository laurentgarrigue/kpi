import { joinURL } from 'ufo'

/** Crawling rules while the site is in beta, then once live (SITE_PLATFORM.md § 4.2). */

const BETA_ROBOTS_DIRECTIVE = 'noindex, nofollow'

/** Content of /robots.txt. */
export function robotsTxt({ beta, siteUrl }: { beta: boolean, siteUrl: string }): string {
  if (beta) {
    return 'User-agent: *\nDisallow: /\n'
  }
  return `User-agent: *\nAllow: /\n\nSitemap: ${joinURL(siteUrl, 'sitemap.xml')}\n`
}

/** Value of the X-Robots-Tag header and robots meta tag, or `null` when indexing is allowed. */
export function robotsDirective(beta: boolean): string | null {
  return beta ? BETA_ROBOTS_DIRECTIVE : null
}
