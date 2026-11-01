import { describe, expect, it } from 'vitest'
import { ageCategoryLabel, hasUnverifiableAgeCategory, isAgeCategoryStatColumn, isUnknownBirthDate } from '../../utils/ageCategory'

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

  it('an absent or conventional birth date is unknown, like in api2', () => {
    expect(isUnknownBirthDate(null)).toBe(true)
    expect(isUnknownBirthDate(undefined)).toBe(true)
    expect(isUnknownBirthDate('')).toBe(true)
    expect(isUnknownBirthDate('1901-01-01')).toBe(true)
    expect(isUnknownBirthDate('0000-00-00')).toBe(true)
    expect(isUnknownBirthDate('1990-04-12')).toBe(false)
  })

  it('the derived age category columns of the stats are shown as an age category, not the declared surclassement', () => {
    expect(isAgeCategoryStatColumn('categorie')).toBe(true)
    expect(isAgeCategoryStatColumn('categorieSaison')).toBe(true)
    expect(isAgeCategoryStatColumn('categorieSurclassement')).toBe(false)
    expect(isAgeCategoryStatColumn('nom')).toBe(false)
  })
})
