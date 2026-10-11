import { describe, expect, it } from 'vitest'
import { isPurgeAuthorized, purgedKeys, purgedPaths } from '../../server/utils/cache-purge'
import { oldPathCandidate } from '../../server/utils/old-paths'
import { sitemapXml } from '../../server/utils/sitemap'

const key = (pathname: string) => `cache:nitro:routes:_:${pathname}.Zx81Ab.json`

describe('cache purge (FEATURE_CMS.md § 5.4)', () => {
  it('CMS-07: a path purges its languages and its payloads', () => {
    expect(purgedPaths('/news')).toEqual(['/news', '/news/_payload.json', '/en/news', '/en/news/_payload.json'])
    expect(purgedPaths('/')).toEqual(['/', '/_payload.json', '/en', '/en/_payload.json'])
  })

  it('CMS-07: removes the cached pages of the paths, all their query strings, nothing else', () => {
    const keys = [key('index'), key('news'), key('news_payloadjson'), key('ennews'), key('newsfinaleresult'), key('calendar'), 'cache:nitro:handlers:_:news.a.json']
    expect(purgedKeys(['/news'], keys)).toEqual([key('news'), key('news_payloadjson'), key('ennews')])
    expect(purgedKeys(['/', '/news/finale-resultats'], keys)).toEqual([key('index'), key('newsfinaleresult')])
    expect(purgedKeys(['/*'], keys)).toHaveLength(6)
  })

  it('CMS-07: only the shared secret is accepted', () => {
    expect(isPurgeAuthorized('Bearer s3cret', 's3cret')).toBe(true)
    expect(isPurgeAuthorized('Bearer s3cres', 's3cret')).toBe(false)
    expect(isPurgeAuthorized(undefined, 's3cret')).toBe(false)
    expect(isPurgeAuthorized('Bearer ', '')).toBe(false)
  })
})

describe('old WordPress paths (FEATURE_CMS.md § 8)', () => {
  it('CMS-09: only permalink shapes are looked up', () => {
    expect(oldPathCandidate('/', { p: '123' })).toBe('/?p=123')
    expect(oldPathCandidate('/', { page_id: '45' })).toBe('/?page_id=45')
    expect(oldPathCandidate('/', { p: 'x' })).toBeNull()
    expect(oldPathCandidate('/2020/06/finale/', {})).toBe('/2020/06/finale/')
    expect(oldPathCandidate('/news', {})).toBeNull()
    expect(oldPathCandidate('/_nuxt/', {})).toBeNull()
  })
})

describe('sitemap (PAGE_CONTENT.md § 5)', () => {
  it('CNT-07: lists the application pages and the published content, in both languages', () => {
    const xml = sitemapXml('https://beta.kpi.localhost/', [{ path: '/news/finale-resultats', updated_at: '2020-06-01T12:00:00+02:00' }])
    expect(xml).toContain('<url><loc>https://beta.kpi.localhost/</loc></url>')
    expect(xml).toContain('<url><loc>https://beta.kpi.localhost/en</loc></url>')
    expect(xml).toContain('<url><loc>https://beta.kpi.localhost/en/news/finale-resultats</loc><lastmod>2020-06-01T12:00:00+02:00</lastmod></url>')
    expect(xml.match(/<url>/g)).toHaveLength(18)
  })
})
