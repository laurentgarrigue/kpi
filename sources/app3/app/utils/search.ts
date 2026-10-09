import { withQuery } from 'ufo'
import type { ClubSummary } from './clubs'
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
}

export const SEARCH_CATEGORIES = ['competitions', 'events', 'teams', 'clubs'] as const
export type SearchCategory = typeof SEARCH_CATEGORIES[number]

/** A search result as displayed: label, detail and app3 path (without language prefix). */
export interface SearchHit {
  key: string
  label: string
  detail: string | null
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

export function searchApiPath(query: string): string {
  return withQuery('/search', { q: query })
}

/** Results of one category, mapped to their page on the site (SRC-03). */
export function searchHits(results: SearchResults, category: SearchCategory): SearchHit[] {
  switch (category) {
    case 'competitions':
      return results.competitions.map(item => ({
        key: `c-${item.season}-${item.code}`,
        label: item.display_title,
        detail: [item.soustitre2, item.season].filter(Boolean).join(' · '),
        to: `/competitions/${item.season}/${item.code}`,
      }))
    case 'events':
      return results.events.map(item => ({ key: `e-${item.id}`, label: item.libelle, detail: item.place, to: `/events/${item.id}` }))
    case 'teams':
      return results.teams.map(item => ({ key: `t-${item.number}`, label: item.label, detail: item.club.label, to: `/teams/${item.number}` }))
    case 'clubs':
      return results.clubs.map(item => ({ key: `k-${item.code}`, label: item.label, detail: item.department.label, to: `/clubs/${item.code}` }))
  }
}

/** Non-empty categories, in display order. */
export function searchSections(results: SearchResults): { category: SearchCategory, hits: SearchHit[] }[] {
  return SEARCH_CATEGORIES.map(category => ({ category, hits: searchHits(results, category) })).filter(section => section.hits.length > 0)
}
