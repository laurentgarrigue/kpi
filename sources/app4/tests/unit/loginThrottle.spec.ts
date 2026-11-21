import { describe, expect, it } from 'vitest'
import { loginBlockMessage, loginBlockOf, retryAfterMinutes } from '../../utils/loginThrottle'

describe('login rate limit and lock (AUTH_HARDENING.md § 2)', () => {
  it('AUTH-RL-03: rounds the Retry-After seconds up to the minute', () => {
    expect(retryAfterMinutes('61')).toBe(2)
    expect(retryAfterMinutes('900')).toBe(15)
  })

  it('AUTH-RL-03: waits at least one minute', () => {
    expect(retryAfterMinutes('1')).toBe(1)
    expect(retryAfterMinutes('0')).toBe(1)
  })

  it('AUTH-RL-03: has no duration without a readable Retry-After', () => {
    expect(retryAfterMinutes(null)).toBeNull()
    expect(retryAfterMinutes('')).toBeNull()
    expect(retryAfterMinutes('soon')).toBeNull()
    expect(retryAfterMinutes('-5')).toBeNull()
  })

  it('AUTH-RL-03: a 429 is a rate limit', () => {
    expect(loginBlockOf(429, '809')).toEqual({ reason: 'rate_limited', retryAfterMinutes: 14 })
  })

  it('AUTH-LK-04: a 423 is a locked account', () => {
    expect(loginBlockOf(423, '300')).toEqual({ reason: 'account_locked', retryAfterMinutes: 5 })
    expect(loginBlockOf(423, null)).toEqual({ reason: 'account_locked', retryAfterMinutes: null })
  })

  it('other refusals are not a block', () => {
    expect(loginBlockOf(401, '300')).toBeNull()
    expect(loginBlockOf(500, null)).toBeNull()
  })

  describe('message (login page and second factor page)', () => {
    const t = (key: string, named?: Record<string, unknown>, plural?: number) =>
      named === undefined ? key : `${key}:${String(named.n)}:${String(plural)}`

    it('AUTH-LK-04: gives the minutes left, with the plural', () => {
      expect(loginBlockMessage({ reason: 'account_locked', retryAfterMinutes: 5 }, t)).toBe('login.error_account_locked:5:5')
      expect(loginBlockMessage({ reason: 'rate_limited', retryAfterMinutes: 1 }, t)).toBe('login.error_rate_limited:1:1')
    })

    it('AUTH-RL-03: falls back to "a few minutes" without a duration', () => {
      expect(loginBlockMessage({ reason: 'account_locked', retryAfterMinutes: null }, t)).toBe('login.error_account_locked_unknown')
    })
  })
})
