import { describe, expect, it } from 'vitest'
import { athleteUpdateErrorKey, birthDateField, submittedBirthDate } from '../../utils/athleteBirthDate'

describe('athlete sheet birth date (CATEGORIE_AGE.md stories 13-15)', () => {
  it('an absent or conventional birth date opens the sheet as « date inconnue »', () => {
    expect(birthDateField(null)).toEqual({ unknown: true, date: '' })
    expect(birthDateField('')).toEqual({ unknown: true, date: '' })
    expect(birthDateField('1901-01-01')).toEqual({ unknown: true, date: '' })
    expect(birthDateField('0000-00-00')).toEqual({ unknown: true, date: '' })
    expect(birthDateField('1990-04-12')).toEqual({ unknown: false, date: '1990-04-12' })
  })

  it('« date inconnue » sends no birth date, which clears a wrong one', () => {
    expect(submittedBirthDate({ unknown: true, date: '1990-04-12' })).toBeNull()
    expect(submittedBirthDate({ unknown: false, date: '1990-04-12' })).toBe('1990-04-12')
  })

  it('a known birth date left empty is not submitted: tick « date inconnue » instead', () => {
    expect(submittedBirthDate({ unknown: false, date: '' })).toBeUndefined()
  })

  it('an implausible birth date refused by api2 gets its explicit message', () => {
    expect(athleteUpdateErrorKey({ status: 400, data: { code: 'IMPLAUSIBLE_BIRTHDATE' } })).toBe('athletes.edit.implausible_birthdate')
    expect(athleteUpdateErrorKey({ status: 403, data: { code: 'FORBIDDEN_FEDERAL' } })).toBe('athletes.edit.forbidden')
    expect(athleteUpdateErrorKey({ status: 400, data: { code: 'VALIDATION_ERROR' } })).toBe('athletes.edit.error')
    expect(athleteUpdateErrorKey(new Error('network'))).toBe('athletes.edit.error')
  })
})
