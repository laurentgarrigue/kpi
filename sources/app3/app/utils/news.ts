import { withQuery } from 'ufo'

/** Formats of api2 `/news`, `/news/categories`, `/news/{slug}` and `/home` (FEATURE_CMS.md § 5.1). */

export interface MediaVariant {
  width: number
  url: string
}

export interface MediaView {
  url: string
  mime: string
  alt: string
  width: number | null
  height: number | null
  variants: MediaVariant[]
}

export interface NewsCategory {
  slug: string
  label: string
}

export interface NewsSummary {
  slug: string
  title: string
  lead: string
  cover: MediaView | null
  published_at: string
  categories: NewsCategory[]
}

export interface NewsList {
  items: NewsSummary[]
  page: number
  pages: number
  total: number
}

export interface ArticleLink {
  kind: 'competition' | 'event' | 'club'
  label: string
  path: string
}

export interface NewsArticle extends Omit<NewsSummary, 'published_at'> {
  body: string
  published_at: string | null
  updated_at: string
  signature: string
  links: ArticleLink[]
  seo: { title: string, description: string }
  previous: { slug: string, title: string } | null
  next: { slug: string, title: string } | null
  preview: boolean
}

/** News per page, set by api2 (PAGE_NEWS.md § 2.1). */
export const NEWS_PAGE_SIZE = 12
/** Related news shown on competition, event and club pages (NEWS-09). */
export const RELATED_NEWS_LIMIT = 3
/** Width of the Open Graph image (PAGE_NEWS.md § 4). */
export const SHARE_IMAGE_WIDTH = 1280
const ONE_DAY_MS = 86_400_000
const PARIS_TIME_ZONE = 'Europe/Paris'

/** `?page=` of the news list: 1 when absent, `null` when it is not a positive integer (→ 404). */
export function newsPageNumber(value: unknown): number | null {
  if (value === undefined || value === null || value === '') {
    return 1
  }
  return typeof value === 'string' && /^[1-9]\d{0,5}$/.test(value) ? Number(value) : null
}

/** A page after the last one is out of range (NEWS-01); an empty list still has its first page. */
export function isNewsPageOutOfRange(list: Pick<NewsList, 'page' | 'pages'>): boolean {
  return list.page > Math.max(list.pages, 1)
}

/** The `?category=` filter when it names a known category, otherwise `null`: an unknown filter is ignored (NEWS-02). */
export function newsCategory(value: unknown, categories: readonly NewsCategory[]): string | null {
  return typeof value === 'string' && categories.some(category => category.slug === value) ? value : null
}

/** api2 language parameter: French is the default and needs none (FEATURE_CMS.md § 5.1). */
export function cmsLanguageQuery(locale: string): { lang?: string } {
  return locale === 'fr' ? {} : { lang: locale }
}

export function newsCategoriesApiPath(locale: string): string {
  return withQuery('/news/categories', cmsLanguageQuery(locale))
}

/** RSS feed, relayed by app3 from api2 under the site's own domain (NEWS-08). */
export function rssPath(locale: string): string {
  return withQuery('/rss.xml', cmsLanguageQuery(locale))
}

export function newsListApiPath(page: number, category: string | null, locale: string): string {
  return withQuery('/news', { page: page > 1 ? page : undefined, category: category ?? undefined, ...cmsLanguageQuery(locale) })
}

/** Site path of a page of the list, keeping the category (links usable without JavaScript, NEWS-01). */
export function newsListPath(page: number, category: string | null): string {
  return withQuery('/news', { category: category ?? undefined, page: page > 1 ? page : undefined })
}

/** The 3 latest news linked to a competition (`{season}/{code}`), an event (id) or a club (code) (NEWS-09). */
export function relatedNewsApiPath(kind: ArticleLink['kind'], ref: string, locale: string): string {
  return withQuery('/news', { [kind]: ref, limit: RELATED_NEWS_LIMIT, ...cmsLanguageQuery(locale) })
}

/** Media are served by the legacy host (`/media/content/…`, FEATURE_CMS.md § 5.3). */
export function mediaUrl(url: string, legacyBaseUrl: string): string {
  return url.startsWith('/') ? `${legacyBaseUrl.replace(/\/+$/, '')}${url}` : url
}

/** Smallest variant at least `width` px wide, the largest one otherwise, the original without variants. */
export function mediaVariant(media: MediaView, width: number): string {
  const variants = [...media.variants].sort((a, b) => a.width - b.width)
  return (variants.find(variant => variant.width >= width) ?? variants.at(-1))?.url ?? media.url
}

/** `srcset` of the WebP variants (PAGE_NEWS.md § 2.2.3). */
export function mediaSrcset(media: MediaView, legacyBaseUrl: string): string {
  return media.variants.map(variant => `${mediaUrl(variant.url, legacyBaseUrl)} ${variant.width}w`).join(', ')
}

/** Date of an article in Paris, e.g. « 1 juin 2020 ». */
export function newsDate(iso: string, locale: string): string {
  return new Intl.DateTimeFormat(locale, { day: 'numeric', month: 'long', year: 'numeric', timeZone: PARIS_TIME_ZONE }).format(new Date(iso))
}

/** « updated on … » is shown when the article changed more than a day after its publication (PAGE_NEWS.md § 2.2.2). */
export function wasUpdated(publishedAt: string | null, updatedAt: string): boolean {
  return publishedAt !== null && new Date(updatedAt).getTime() - new Date(publishedAt).getTime() > ONE_DAY_MS
}

export interface ShareLink {
  id: 'facebook' | 'x' | 'whatsapp' | 'email'
  href: string
}

/** Plain share URLs, no third-party script (NEWS-05). */
export function shareLinks(url: string, title: string): ShareLink[] {
  return [
    { id: 'facebook', href: withQuery('https://www.facebook.com/sharer/sharer.php', { u: url }) },
    { id: 'x', href: withQuery('https://x.com/intent/post', { url, text: title }) },
    { id: 'whatsapp', href: withQuery('https://wa.me/', { text: `${title} ${url}` }) },
    { id: 'email', href: `mailto:?subject=${encodeURIComponent(title)}&body=${encodeURIComponent(url)}` },
  ]
}

/** schema.org `NewsArticle` of an article (PAGE_NEWS.md § 4, NEWS-06). */
export function newsArticleJsonLd(article: NewsArticle, url: string, image: string | null, publisher: string): Record<string, unknown> {
  return {
    '@context': 'https://schema.org',
    '@type': 'NewsArticle',
    'headline': article.title,
    'mainEntityOfPage': url,
    ...(image ? { image: [image] } : {}),
    ...(article.published_at ? { datePublished: article.published_at } : {}),
    'dateModified': article.updated_at,
    'author': { '@type': 'Organization', 'name': article.signature },
    'publisher': { '@type': 'Organization', 'name': publisher },
  }
}
