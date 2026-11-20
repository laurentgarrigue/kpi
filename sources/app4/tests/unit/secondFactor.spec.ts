import { describe, expect, it } from 'vitest'
import {
  accountOf,
  graceDaysLeft,
  isCompleteCode,
  normalizeCode,
  parseSecondFactorFragment,
  recoveryCodesText
} from '../../utils/secondFactor'

describe('second factor (AUTH_HARDENING.md § 5)', () => {
  it('AUTH-2FA-12: reads a Google challenge from the URL fragment', () => {
    expect(parseSecondFactorFragment('#challenge=abc.def&method=email&maskedEmail=a***%40example.test')).toEqual({
      mode: 'challenge',
      token: 'abc.def',
      method: 'email',
      maskedEmail: 'a***@example.test',
      totpAvailable: true,
      codeSent: true
    })
  })

  it('AUTH-2FA-12: reads a Google enrollment from the URL fragment', () => {
    expect(parseSecondFactorFragment('#enroll=tok&totpAvailable=0&maskedEmail=')).toEqual({
      mode: 'enroll',
      token: 'tok',
      method: null,
      maskedEmail: null,
      totpAvailable: false,
      codeSent: false
    })
  })

  it('ignores an empty or unrelated fragment', () => {
    expect(parseSecondFactorFragment('')).toBeNull()
    expect(parseSecondFactorFragment('#foo=bar')).toBeNull()
  })

  it('AUTH-2FA-09: a typed code loses its spaces', () => {
    expect(normalizeCode(' 123 456 ')).toBe('123456')
    expect(normalizeCode('abcde-fghij ')).toBe('abcde-fghij')
  })

  it('accepts a 6-digit code or a recovery code, nothing else', () => {
    expect(isCompleteCode('123456')).toBe(true)
    expect(isCompleteCode('123 456')).toBe(true)
    expect(isCompleteCode('abcde-fghij')).toBe(true)
    expect(isCompleteCode('12345')).toBe(false)
    expect(isCompleteCode('abc')).toBe(false)
  })

  it('AUTH-2FA-02: counts the grace days left, never below zero', () => {
    const now = new Date('2027-02-10T10:00:00Z')
    expect(graceDaysLeft('2027-02-17T10:00:00+00:00', now)).toBe(7)
    expect(graceDaysLeft('2027-02-10T22:00:00+00:00', now)).toBe(1)
    expect(graceDaysLeft('2027-02-09T10:00:00+00:00', now)).toBe(0)
    expect(graceDaysLeft(null, now)).toBeNull()
  })

  it('AUTH-2FA-06: recovery codes are saved one per line, with a heading', () => {
    expect(recoveryCodesText(['aaaaa-bbbbb', 'ccccc-ddddd'], 'KPI — TADMIN')).toBe('KPI — TADMIN\n\naaaaa-bbbbb\nccccc-ddddd\n')
  })

  it('reads the account code of a sign-in token, for the recovery codes file', () => {
    expect(accountOf(`challenge.${btoa('TADMIN')}.1800000000.sig`)).toBe('TADMIN')
    expect(accountOf('garbage')).toBe('')
  })
})
