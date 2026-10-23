import { withQuery } from 'ufo'
import type { ClubSummary } from './clubs'
import { shortEventDates } from './events'
import { cmsLanguageQuery, type NewsSummary } from './news'
import type { TeamSummary } from './teams'

/** Global search (FEATURE_SEARCH.md, API_PUBLIC_TRANSVERSE.md § 3.6). */

export const SEARCH_MIN_LENGTH = 2
export const SEARCH_MAX_LENGTH = 50
/** Delay before suggestions are requested while typing (SRC-02, TEA-01). */
export const SUGGESTION_DELAY_MS = 300

export interface SearchResults {
  competitions: { season: string, code: string, display_title: string, soustitre2: string | null, group: string | null }[]
  events: { id: number, libelle: string, place: string | null, start: string | null, end: string | null }[]
  teams: TeamSummary[]
  clubs: ClubSummary[]
  /** Phase 4 (FEATURE_CMS.md § 5.1, CMS-11). */
  news: NewsSummary[]
  pages: { title: string, path: string }[]
}

export const SEARCH_CATEGORIES = ['competitions', 'events', 'teams', 'clubs', 'news', 'pages'] as const
export type SearchCategory = typeof SEARCH_CATEGORIES[number]

/** A search result as displayed: label, detail, side information and app3 path (without language prefix). */
export interface SearchHit {
  key: string
  label: string
  detail: string | null
  aside?: string
  to: string
}

/** The trimmed query when it can be searched, otherwise `null` (no request is sent). */
export function searchQuery(value: unknown): string | null {
  if (typeof value !== 'string') {
    return null
  }
  const query = value.trim().replace(/\s+/g, ' ')
  return query.length >= SEARCH_MIN_LENGTH && query.length <= SEARCH_MAX_LENGTH ? query : null
}

/** News and pages are searched in the visitor's language (FEATURE_CMS.md § 5.1). */
export function searchApiPath(query: string, locale = 'fr'): string {
  return withQuery('/search', { q: query, ...cmsLanguageQuery(locale) })
}

const monthYear = (date: string, language: string) => new Intl.DateTimeFormat(language, { month: 'long', year: 'numeric', timeZone: 'UTC' }).format(new Date(date))

/** Results of one category, mapped to their page on the site (SRC-03), with a side information (SRC-07). */
export function searchHits(results: SearchResults, category: SearchCategory, language = 'fr'): SearchHit[] {
  switch (category) {
    case 'competitions':
      return results.competitions.map(item => ({
        key: `c-${item.season}-${item.code}`,
        label: item.display_title,
        detail: item.soustitre2,
        aside: item.season,
        to: `/competitions/${item.season}/${item.code}`,
      }))
    case 'events':
      return results.events.map(item => ({
        key: `e-${item.id}`,
        label: item.libelle,
        detail: item.place,
        ...(item.start ? { aside: `${shortEventDates(item.start, item.end, language)} ${item.start.slice(0, 4)}` } : {}),
        to: `/events/${item.id}`,
      }))
    case 'teams':
      return results.teams.map(item => ({ key: `t-${item.number}`, label: item.label, detail: item.club.label, to: `/teams/${item.number}` }))
    case 'clubs':
      return results.clubs.map(item => ({ key: `k-${item.code}`, label: item.label, detail: item.department.label, aside: item.code, to: `/clubs?club=${item.code}` }))
    case 'news':
      return results.news.map(item => ({
        key: `n-${item.slug}`,
        label: item.title,
        detail: item.lead || null,
        ...(item.published_at ? { aside: monthYear(item.published_at, language) } : {}),
        to: `/news/${item.slug}`,
      }))
    case 'pages':
      return results.pages.map(item => ({ key: `p-${item.path}`, label: item.title, detail: null, to: item.path }))
  }
}

/** Non-empty categories, in display order. */
export function searchSections(results: SearchResults, language = 'fr'): { category: SearchCategory, hits: SearchHit[] }[] {
  return SEARCH_CATEGORIES.map(category => ({ category, hits: searchHits(results, category, language) })).filter(section => section.hits.length > 0)
}

/** The category of the query when it has results, else every category (SRC-07). */
export function searchKind(value: unknown, results: SearchResults): SearchCategory | null {
  return (SEARCH_CATEGORIES as readonly unknown[]).includes(value) && results[value as SearchCategory].length > 0 ? value as SearchCategory : null
}

/**
 * The results page (SRC-07): one filter per non-empty category with its count; without filter, the first club is the
 * best match, put first and not repeated in its section.
 */
export function searchKinds(results: SearchResults, kind: SearchCategory | null, language = 'fr') {
  const sections = searchSections(results, language)
  const best = kind === null ? results.clubs[0] ?? null : null
  return {
    counts: sections.map(section => [section.category, section.hits.length] as [SearchCategory, number]),
    total: sections.reduce((total, section) => total + section.hits.length, 0),
    best,
    sections: sections
      .filter(section => kind === null || section.category === kind)
      .map(section => (best && section.category === 'clubs' ? { ...section, hits: section.hits.slice(1) } : section))
      .filter(section => section.hits.length > 0),
  }
}
