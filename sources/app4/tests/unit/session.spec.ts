import { describe, expect, it } from 'vitest'
import {
  chooseRefreshRoute,
  mandateToKeep,
  needsRefresh,
  planRefresh,
  readJwtLifetime,
  refreshLeadMs,
  refreshOutcomeOf,
  storedSessionState
} from '../../utils/session'

const base64url = (value: string) => Buffer.from(value).toString('base64').replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '')

/** A JWT-shaped string with the given claims (seconds); the signature is irrelevant client-side. */
const fakeJwt = (claims: Record<string, unknown>) =>
  `${base64url(JSON.stringify({ alg: 'RS256', typ: 'JWT' }))}.${base64url(JSON.stringify(claims))}.signature`

const NOW = 1_800_000_000_000 // ms
const SECOND = 1000
const MINUTE = 60 * SECOND
const HOUR = 60 * MINUTE

/** A token issued `ageMs` ago that lives `ttlMs` in total. */
const token = (ttlMs: number, ageMs = 0) =>
  fakeJwt({ iat: (NOW - ageMs) / SECOND, exp: (NOW - ageMs + ttlMs) / SECOND, username: '42' })

describe('AUTH-SC-01 — the session lifetime is read from the JWT, never assumed', () => {
  it('reads iat and exp in milliseconds', () => {
    expect(readJwtLifetime(token(15 * MINUTE))).toEqual({ issuedAt: NOW, expiresAt: NOW + 15 * MINUTE })
  })

  it('reads a JWT whose payload is not padded base64url', () => {
    const jwt = fakeJwt({ iat: 1_700_000_000, exp: 1_700_086_400, username: 'é-ü?>' })
    expect(readJwtLifetime(jwt)).toEqual({ issuedAt: 1_700_000_000_000, expiresAt: 1_700_086_400_000 })
  })

  it('treats an unreadable token as unknown (hence expired)', () => {
    expect(readJwtLifetime(null)).toBeNull()
    expect(readJwtLifetime('')).toBeNull()
    expect(readJwtLifetime('not-a-jwt')).toBeNull()
    expect(readJwtLifetime('a.b.c')).toBeNull()
    expect(readJwtLifetime(fakeJwt({ username: '42' }))).toBeNull()
    expect(needsRefresh(null, NOW)).toBe(true)
  })

  it('falls back to exp alone when iat is missing', () => {
    expect(readJwtLifetime(fakeJwt({ exp: NOW / SECOND + 600 }))).toEqual({ issuedAt: NOW + 600 * SECOND, expiresAt: NOW + 600 * SECOND })
  })
})

describe('AUTH-SC-02 — proactive refresh threshold, whatever the profile', () => {
  it('refreshes when less than 20 % of the lifetime is left', () => {
    const day = readJwtLifetime(token(24 * HOUR))!
    expect(refreshLeadMs(day)).toBe(4.8 * HOUR)
    expect(needsRefresh(day, NOW + 19 * HOUR)).toBe(false)
    expect(needsRefresh(day, NOW + 19.5 * HOUR)).toBe(true)
  })

  it('never refreshes less than one minute before expiry, even for a short token', () => {
    const short = readJwtLifetime(token(2 * MINUTE))!
    expect(refreshLeadMs(short)).toBe(MINUTE)
    expect(needsRefresh(short, NOW + 30 * SECOND)).toBe(false)
    expect(needsRefresh(short, NOW + 61 * SECOND)).toBe(true)
  })

  it('honours a minimum validity requested by the caller (AUTH-SC-20: 2 h at match load)', () => {
    const hour = readJwtLifetime(token(HOUR))!
    expect(needsRefresh(hour, NOW + 10 * MINUTE)).toBe(false)
    expect(needsRefresh(hour, NOW + 10 * MINUTE, 2 * HOUR)).toBe(true)
  })

  it('an expired token always needs a refresh', () => {
    expect(needsRefresh(readJwtLifetime(token(15 * MINUTE, 20 * MINUTE)), NOW)).toBe(true)
  })
})

describe('AUTH-SC-03 — refresh route', () => {
  it('uses /auth/refresh (no cookie rotation) while the JWT is still valid', () => {
    expect(chooseRefreshRoute(readJwtLifetime(token(24 * HOUR, 20 * HOUR)), NOW)).toBe('refresh')
  })

  it('uses the refresh cookie when the JWT is expired, about to expire or absent', () => {
    expect(chooseRefreshRoute(readJwtLifetime(token(15 * MINUTE, 16 * MINUTE)), NOW)).toBe('refresh-session')
    expect(chooseRefreshRoute(readJwtLifetime(token(15 * MINUTE, 15 * MINUTE - 10 * SECOND)), NOW)).toBe('refresh-session')
    expect(chooseRefreshRoute(null, NOW)).toBe('refresh-session')
  })
})

describe('AUTH-SC-04 — one refresh per browser, adopting what another tab already obtained', () => {
  const ageing = token(24 * HOUR, 20 * HOUR)
  const fresh = token(24 * HOUR)
  const expired = token(15 * MINUTE, 16 * MINUTE)

  it('does nothing when the current token is fresh enough', () => {
    expect(planRefresh({ current: fresh, stored: fresh, now: NOW })).toEqual({ action: 'use', token: fresh })
  })

  it('adopts the fresher token another tab stored, without a network call', () => {
    expect(planRefresh({ current: ageing, stored: fresh, now: NOW })).toEqual({ action: 'use', token: fresh })
  })

  it('calls /auth/refresh with the best valid token when both are ageing', () => {
    expect(planRefresh({ current: ageing, stored: ageing, now: NOW })).toEqual({ action: 'call', route: 'refresh', token: ageing })
  })

  it('calls the cookie route when no valid token is known', () => {
    expect(planRefresh({ current: expired, stored: null, now: NOW })).toEqual({ action: 'call', route: 'refresh-session', token: null })
  })

  it('never reuses the token the server just rejected, even if its exp looks fine', () => {
    expect(planRefresh({ current: fresh, stored: fresh, rejected: fresh, now: NOW }))
      .toEqual({ action: 'call', route: 'refresh-session', token: null })
  })

  it('uses the token another tab obtained after ours was rejected', () => {
    expect(planRefresh({ current: ageing, stored: fresh, rejected: ageing, now: NOW })).toEqual({ action: 'use', token: fresh })
  })

  it('refreshes ahead of a requested minimum validity', () => {
    const hour = token(HOUR, 10 * MINUTE)
    expect(planRefresh({ current: hour, stored: null, now: NOW })).toEqual({ action: 'use', token: hour })
    expect(planRefresh({ current: hour, stored: null, now: NOW, minValidityMs: 2 * HOUR }))
      .toEqual({ action: 'call', route: 'refresh', token: hour })
  })
})

describe('AUTH-SC-05 — the active mandate survives a cookie refresh', () => {
  const mandates = [{ id: 3, libelle: 'Délégué N1' }, { id: 7, libelle: 'Table de marque' }]

  it('keeps the active mandate when it is still granted', () => {
    expect(mandateToKeep({ id: 7, libelle: 'Table de marque' }, mandates)).toEqual({ id: 7, libelle: 'Table de marque' })
  })

  it('drops a mandate that was withdrawn meanwhile', () => {
    expect(mandateToKeep({ id: 9, libelle: 'Ancien' }, mandates)).toBeNull()
    expect(mandateToKeep(null, mandates)).toBeNull()
  })
})

describe('AUTH-SC-06 — page reload restores the session from the cookie before going to /login', () => {
  it('is authenticated with a usable stored token', () => {
    expect(storedSessionState({ token: token(24 * HOUR), hasUser: true, now: NOW })).toBe('authenticated')
  })

  it('is restorable when the stored token expired but the user is known', () => {
    expect(storedSessionState({ token: token(15 * MINUTE, 16 * MINUTE), hasUser: true, now: NOW })).toBe('restorable')
    expect(storedSessionState({ token: null, hasUser: true, now: NOW })).toBe('restorable')
  })

  it('has nothing to restore without a known user', () => {
    expect(storedSessionState({ token: token(24 * HOUR), hasUser: false, now: NOW })).toBe('none')
    expect(storedSessionState({ token: null, hasUser: false, now: NOW })).toBe('none')
  })
})

describe('AUTH-SC-07/08 — only a refusal from the server logs out', () => {
  it('a 2xx is a renewed session', () => {
    expect(refreshOutcomeOf(200)).toBe('refreshed')
  })

  it('401 and 403 are a refusal', () => {
    expect(refreshOutcomeOf(401)).toBe('denied')
    expect(refreshOutcomeOf(403)).toBe('denied')
  })

  it('a network error, a timeout, a rate limit or a server error leave the session in place', () => {
    expect(refreshOutcomeOf(null)).toBe('unreachable')
    expect(refreshOutcomeOf(429)).toBe('unreachable')
    expect(refreshOutcomeOf(500)).toBe('unreachable')
    expect(refreshOutcomeOf(502)).toBe('unreachable')
    expect(refreshOutcomeOf(503)).toBe('unreachable')
  })
})
