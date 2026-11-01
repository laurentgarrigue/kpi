import { describe, expect, it } from 'vitest'
import { athletesListParams, athletesListTotalPages } from '../../utils/athletesList'

const NO_FILTER = { q: '', unknownBirthDate: false, international: false, engagedThisSeason: false }

describe('athletesListParams (CATEGORIE_AGE.md stories 28-30, PAGE_ATHLETES.md § 2.6)', () => {
  it('sends only the page and its size when no filter is set', () => {
    expect(athletesListParams(NO_FILTER, 1, 50)).toEqual({ page: 1, limit: 50 })
  })

  it('combines the unknown birth date, international and engaged this season filters', () => {
    expect(athletesListParams({ ...NO_FILTER, unknownBirthDate: true, international: true, engagedThisSeason: true }, 2, 20))
      .toEqual({ page: 2, limit: 20, unknown_birthdate: 1, international: 1, engaged_this_season: 1 })
  })

  it('narrows by name or licence number once the search has 2 characters', () => {
    expect(athletesListParams({ ...NO_FILTER, q: ' d' }, 1, 50)).toEqual({ page: 1, limit: 50 })
    expect(athletesListParams({ ...NO_FILTER, q: ' du ' }, 1, 50)).toEqual({ page: 1, limit: 50, q: 'du' })
  })
})

describe('athletesListTotalPages', () => {
  it('gives at least one page and rounds up', () => {
    expect(athletesListTotalPages(0, 50)).toBe(1)
    expect(athletesListTotalPages(50, 50)).toBe(1)
    expect(athletesListTotalPages(51, 50)).toBe(2)
  })
})
