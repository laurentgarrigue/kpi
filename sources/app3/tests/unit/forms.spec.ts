import { describe, expect, it } from 'vitest'
import { formDateTime, inputType, isSingleCheckbox, postedValue, type FormField } from '../../app/utils/forms'
import { formSubmissionTarget, submissionBody, submissionFailure } from '../../shared/utils/form-submission'

const field = (key: string, type: FormField['type'], options: FormField['options'] = []): FormField =>
  ({ key, type, label: key, help: '', required: false, options, min: null, max: null })

const fields = [field('days', 'checkbox', [{ value: 'sat', label: 'Samedi' }, { value: 'sun', label: 'Dimanche' }]), field('consent', 'checkbox')]

describe('registration forms (FEATURE_FORMS.md § 3)', () => {
  it('FRM-01: a POST to a form page (any language) is relayed; other paths are not', () => {
    expect(formSubmissionTarget('/forms/tournoi-test')).toEqual({ slug: 'tournoi-test', lang: null })
    expect(formSubmissionTarget('/en/forms/tournoi-test/')).toEqual({ slug: 'tournoi-test', lang: 'en' })
    expect(formSubmissionTarget('/forms/tournoi-test/entries')).toBeNull()
    expect(formSubmissionTarget('/news')).toBeNull()
  })

  it('FRM-01: sends the posted strings, one value per field, lists for checkbox lists, without empty values', () => {
    expect(submissionBody({ email: ' a@b.fr ', days: ['sat', 'sun'], consent: '1', _token: 't', website: '', file: { name: 'x' } }))
      .toEqual({ email: ' a@b.fr ', days: ['sat', 'sun'], consent: '1', _token: 't' })
    expect(submissionBody({ days: ['sun', ''] })).toEqual({ days: 'sun' })
    expect(submissionBody(null)).toEqual({})
  })

  it('FRM-02: a 422 gives the field errors and the values to show again, without the token', () => {
    expect(submissionFailure(422, { error: 'validation_failed', errors: { email: 'invalid' } }, { email: 'x', _token: 't', website: 'w' }))
      .toEqual({ kind: 'invalid', errors: { email: 'invalid' }, values: { email: 'x' } })
  })

  it('FRM-05: other refusals keep their reason; 429 is « too many requests »', () => {
    expect(submissionFailure(409, { error: 'full' }, {})).toEqual({ kind: 'rejected', reason: 'full', values: {} })
    expect(submissionFailure(429, { error: 'too_many_requests' }, {})).toMatchObject({ reason: 'too_many_requests' })
    expect(submissionFailure(undefined, undefined, {})).toMatchObject({ reason: 'unavailable' })
  })

  it('FRM-01: controls by field type', () => {
    expect(inputType('phone')).toBe('tel')
    expect(inputType('select')).toBeNull()
    expect(isSingleCheckbox(fields[1]!)).toBe(true)
    expect(isSingleCheckbox(fields[0]!)).toBe(false)
    expect(postedValue({ days: ['sat'] }, 'days')).toEqual(['sat'])
    expect(postedValue({ email: 'a' }, 'email')).toEqual(['a'])
    expect(postedValue(undefined, 'email')).toEqual([])
  })

  it('FRM-01: opening and closing are the Paris wall-clock times given by api2', () => {
    expect(formDateTime('2026-10-12 18:00:00', 'fr')).toBe('12 octobre 2026 à 18:00')
  })
})
