/** `sitemap.xml` of the site (PAGE_CONTENT.md § 5, CNT-07): the application pages and the published content. */

export interface SitemapEntry {
  path: string
  updated_at?: string
}

/** Application pages listed in the sitemap (their data pages come with the switch-over, phase 5). */
export const APPLICATION_PATHS = ['/', '/news', '/calendar', '/events', '/competitions', '/history', '/teams', '/clubs'] as const
const LANGUAGE_PREFIXES = ['', '/en']

function escapeXml(value: string): string {
  return value.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')
}

export function sitemapXml(siteUrl: string, content: readonly SitemapEntry[]): string {
  const base = siteUrl.replace(/\/+$/, '')
  const entries: SitemapEntry[] = [...APPLICATION_PATHS.map(path => ({ path })), ...content]
  const urls = entries.flatMap(entry => LANGUAGE_PREFIXES.map((prefix) => {
    const location = `${base}${prefix}${entry.path === '/' && prefix ? '' : entry.path}`
    const lastmod = entry.updated_at ? `<lastmod>${escapeXml(entry.updated_at)}</lastmod>` : ''
    return `<url><loc>${escapeXml(location)}</loc>${lastmod}</url>`
  }))
  return `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n${urls.join('\n')}\n</urlset>\n`
}
