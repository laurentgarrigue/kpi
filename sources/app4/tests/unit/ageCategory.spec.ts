import { describe, expect, it } from 'vitest'
import { ageCategoryLabel, hasUnverifiableAgeCategory } from '../../utils/ageCategory'

describe('age category display (CATEGORIE_AGE.md stories 7, 8, 21)', () => {
  it('shows « ? » for an unknown birth date (no category from the API)', () => {
    expect(ageCategoryLabel('JUN')).toBe('JUN')
    expect(ageCategoryLabel(null)).toBe('?')
    expect(ageCategoryLabel(undefined)).toBe('?')
  })

  it('appends the gender when given', () => {
    expect(ageCategoryLabel('SEN', 'F')).toBe('SEN-F')
    expect(ageCategoryLabel(null, 'M')).toBe('?-M')
  })

  it('flags the non-blocking unknown birth date warning of a competition with age bounds', () => {
    expect(hasUnverifiableAgeCategory(['Naissance_inconnue'])).toBe(true)
    expect(hasUnverifiableAgeCategory([])).toBe(false)
    expect(hasUnverifiableAgeCategory(undefined)).toBe(false)
  })
})
