import type { AthletesListFilters } from '~/types/athletes-list'

/** Search terms shorter than this are ignored by GET /admin/athletes. */
export const ATHLETES_LIST_MIN_SEARCH_LENGTH = 2

const FILTER_PARAMS = {
  unknownBirthDate: 'unknown_birthdate',
  international: 'international',
  engagedThisSeason: 'engaged_this_season',
} as const

/** Query parameters of GET /admin/athletes for the filters and the page (PAGE_ATHLETES.md § 6.8). */
export function athletesListParams(filters: AthletesListFilters, page: number, limit: number): Record<string, string | number> {
  const params: Record<string, string | number> = { page, limit }
  const q = filters.q.trim()
  if (q.length >= ATHLETES_LIST_MIN_SEARCH_LENGTH) params.q = q
  for (const [filter, param] of Object.entries(FILTER_PARAMS)) {
    if (filters[filter as keyof typeof FILTER_PARAMS]) params[param] = 1
  }
  return params
}

/** Number of pages of the list, at least one. */
export function athletesListTotalPages(total: number, limit: number): number {
  return Math.max(1, Math.ceil(total / limit))
}
