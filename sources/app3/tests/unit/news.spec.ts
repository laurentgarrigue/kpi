import { describe, expect, it } from 'vitest'
import {
  isNewsPageOutOfRange,
  mediaSrcset,
  mediaUrl,
  mediaVariant,
  newsArticleJsonLd,
  newsCategoriesApiPath,
  newsCategory,
  newsDate,
  newsListApiPath,
  newsListPath,
  newsPageNumber,
  relatedNewsApiPath,
  rssPath,
  shareLinks,
  wasUpdated,
  type MediaView,
  type NewsArticle,
} from '../../app/utils/news'

const LEGACY = 'https://kpi.localhost'
const categories = [{ slug: 'equipes-de-france', label: 'Équipes de France' }]
const cover: MediaView = {
  url: '/media/content/2999/01/finale.jpg?v=1',
  mime: 'image/jpeg',
  alt: 'La finale',
  width: 1600,
  height: 900,
  variants: [
    { width: 320, url: '/media/content/2999/01/finale-320.webp?v=1' },
    { width: 768, url: '/media/content/2999/01/finale-768.webp?v=1' },
    { width: 1280, url: '/media/content/2999/01/finale-1280.webp?v=1' },
  ],
}

describe('news list (PAGE_NEWS.md § 2.1)', () => {
  it('NEWS-01: the page number is a positive integer, 1 by default; anything else is not found', () => {
    expect(newsPageNumber(undefined)).toBe(1)
    expect(newsPageNumber('3')).toBe(3)
    expect(newsPageNumber('0')).toBeNull()
    expect(newsPageNumber('abc')).toBeNull()
    expect(newsPageNumber(['2'])).toBeNull()
  })

  it('NEWS-01: a page after the last one is out of range, the first page of an empty list is not', () => {
    expect(isNewsPageOutOfRange({ page: 2, pages: 1 })).toBe(true)
    expect(isNewsPageOutOfRange({ page: 1, pages: 0 })).toBe(false)
    expect(isNewsPageOutOfRange({ page: 3, pages: 3 })).toBe(false)
  })

  it('NEWS-02: an unknown category is ignored', () => {
    expect(newsCategory('equipes-de-france', categories)).toBe('equipes-de-france')
    expect(newsCategory('nope', categories)).toBeNull()
    expect(newsCategory(undefined, categories)).toBeNull()
  })

  it('NEWS-01/02: list links keep the category and omit the first page', () => {
    expect(newsListPath(1, null)).toBe('/news')
    expect(newsListPath(2, 'equipes-de-france')).toBe('/news?category=equipes-de-france&page=2')
    expect(newsListApiPath(1, null, 'fr')).toBe('/news')
    expect(newsCategoriesApiPath('en')).toBe('/news/categories?lang=en')
    expect(newsListApiPath(2, 'equipes-de-france', 'en')).toBe('/news?page=2&category=equipes-de-france&lang=en')
  })

  it('NEWS-08: the RSS feed is served by the site, in the visitor language', () => {
    expect(rssPath('fr')).toBe('/rss.xml')
    expect(rssPath('en')).toBe('/rss.xml?lang=en')
  })

  it('NEWS-09: related news are the 3 latest of a competition, event or club', () => {
    expect(relatedNewsApiPath('competition', '2999/RCH', 'fr')).toBe('/news?competition=2999%2FRCH&limit=3')
    expect(relatedNewsApiPath('club', 'C001', 'en')).toBe('/news?club=C001&limit=3&lang=en')
  })
})

describe('article (PAGE_NEWS.md § 2.2)', () => {
  it('NEWS-03: media are served by the legacy host, with their WebP variants', () => {
    expect(mediaUrl(cover.url, `${LEGACY}/`)).toBe(`${LEGACY}/media/content/2999/01/finale.jpg?v=1`)
    expect(mediaUrl('https://example.org/a.jpg', LEGACY)).toBe('https://example.org/a.jpg')
    expect(mediaVariant(cover, 768)).toBe('/media/content/2999/01/finale-768.webp?v=1')
    expect(mediaVariant(cover, 1920)).toBe('/media/content/2999/01/finale-1280.webp?v=1')
    expect(mediaVariant({ ...cover, variants: [] }, 768)).toBe(cover.url)
    expect(mediaSrcset(cover, LEGACY)).toBe(`${LEGACY}/media/content/2999/01/finale-320.webp?v=1 320w, ${LEGACY}/media/content/2999/01/finale-768.webp?v=1 768w, ${LEGACY}/media/content/2999/01/finale-1280.webp?v=1 1280w`)
  })

  it('NEWS-03: dates are shown in Paris; « updated » only after more than a day', () => {
    expect(newsDate('2020-06-01T00:30:00+02:00', 'fr')).toBe('1 juin 2020')
    expect(newsDate('2020-06-01T00:30:00+02:00', 'en')).toBe('June 1, 2020')
    expect(wasUpdated('2020-06-01T12:00:00+02:00', '2020-06-02T11:00:00+02:00')).toBe(false)
    expect(wasUpdated('2020-06-01T12:00:00+02:00', '2020-06-03T12:00:00+02:00')).toBe(true)
    expect(wasUpdated(null, '2020-06-03T12:00:00+02:00')).toBe(false)
  })

  it('NEWS-05: share links are plain URLs of the networks', () => {
    const links = shareLinks('https://beta.kpi.localhost/news/finale', 'Finale & co')
    expect(links.map(link => link.id)).toEqual(['facebook', 'x', 'whatsapp', 'email'])
    expect(links[0]?.href).toBe('https://www.facebook.com/sharer/sharer.php?u=https:%2F%2Fbeta.kpi.localhost%2Fnews%2Ffinale')
    expect(links[3]?.href).toBe('mailto:?subject=Finale%20%26%20co&body=https%3A%2F%2Fbeta.kpi.localhost%2Fnews%2Ffinale')
  })

  it('NEWS-06: NewsArticle data names the signature as author', () => {
    const article = { title: 'Finale', published_at: '2020-06-01T12:00:00+02:00', updated_at: '2020-06-02T12:00:00+02:00', signature: 'La CNA Kayak-Polo' } as NewsArticle
    expect(newsArticleJsonLd(article, 'https://site/news/finale', 'https://img', 'kayak-polo.info')).toEqual({
      '@context': 'https://schema.org',
      '@type': 'NewsArticle',
      'headline': 'Finale',
      'mainEntityOfPage': 'https://site/news/finale',
      'image': ['https://img'],
      'datePublished': '2020-06-01T12:00:00+02:00',
      'dateModified': '2020-06-02T12:00:00+02:00',
      'author': { '@type': 'Organization', 'name': 'La CNA Kayak-Polo' },
      'publisher': { '@type': 'Organization', 'name': 'kayak-polo.info' },
    })
  })
})
