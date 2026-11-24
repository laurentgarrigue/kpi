/**
 * Session continuity rules of app4 (AUTH_HARDENING.md § 3ter, lot A): pure functions, tested without Nuxt.
 * The orchestration (lock, network, store) lives in composables/useAuth.ts.
 */

/** Lifetime of an access JWT, in milliseconds since the epoch. */
export interface JwtLifetime {
  issuedAt: number
  expiresAt: number
}

export type RefreshRoute = 'refresh' | 'refresh-session'

/** `refreshed`: new token in hand; `denied`: the server refused (log out); `unreachable`: retry later, keep the session. */
export type RefreshOutcome = 'refreshed' | 'denied' | 'unreachable'

export type StoredSessionState = 'authenticated' | 'restorable' | 'none'

export type RefreshPlan =
  | { action: 'use', token: string }
  | { action: 'call', route: RefreshRoute, token: string | null }

/** Refresh when less than this share of the token lifetime is left (AUTH-SC-02). */
export const REFRESH_LEAD_RATIO = 0.2
/** ...but never later than this before expiry. */
export const MIN_REFRESH_LEAD_MS = 60_000
/** A token closer than this to its expiry is not sent any more: it could expire on the way (AUTH-SC-03). */
export const USABLE_TOKEN_MARGIN_MS = 30_000

const MS_PER_SECOND = 1000

const decodeBase64Url = (segment: string): string => {
  const base64 = segment.replace(/-/g, '+').replace(/_/g, '/').padEnd(Math.ceil(segment.length / 4) * 4, '=')
  const bytes = Uint8Array.from(atob(base64), char => char.charCodeAt(0))
  return new TextDecoder().decode(bytes)
}

/** AUTH-SC-01: reads `iat`/`exp` of a JWT; null when the token is absent or unreadable. */
export function readJwtLifetime(token: string | null | undefined): JwtLifetime | null {
  const payload = token?.split('.')[1]
  if (!payload) return null
  try {
    const claims = JSON.parse(decodeBase64Url(payload)) as { iat?: unknown, exp?: unknown }
    if (typeof claims.exp !== 'number') return null
    const expiresAt = claims.exp * MS_PER_SECOND
    const issuedAt = typeof claims.iat === 'number' ? claims.iat * MS_PER_SECOND : expiresAt
    return { issuedAt, expiresAt }
  } catch {
    return null
  }
}

/** AUTH-SC-02: how long before expiry the token is renewed. */
export function refreshLeadMs(lifetime: JwtLifetime): number {
  return Math.max(MIN_REFRESH_LEAD_MS, (lifetime.expiresAt - lifetime.issuedAt) * REFRESH_LEAD_RATIO)
}

/** AUTH-SC-02 / AUTH-SC-20: true when the token must be renewed now (unknown token included). */
export function needsRefresh(lifetime: JwtLifetime | null, now: number, minValidityMs = 0): boolean {
  if (!lifetime) return true
  return lifetime.expiresAt - now < Math.max(refreshLeadMs(lifetime), minValidityMs)
}

const isUsable = (lifetime: JwtLifetime | null, now: number): lifetime is JwtLifetime =>
  lifetime !== null && lifetime.expiresAt - now > USABLE_TOKEN_MARGIN_MS

/** AUTH-SC-03: a valid JWT renews itself without rotating the refresh cookie; otherwise the cookie is used. */
export function chooseRefreshRoute(lifetime: JwtLifetime | null, now: number): RefreshRoute {
  return isUsable(lifetime, now) ? 'refresh' : 'refresh-session'
}

/**
 * AUTH-SC-04: decides, once the refresh lock is held, between using a token already in hand (ours or one another
 * tab stored) and calling the server. A token the server just rejected is never reused.
 */
export function planRefresh(input: {
  current: string | null
  stored: string | null
  rejected?: string | null
  now: number
  minValidityMs?: number
}): RefreshPlan {
  const { current, stored, rejected, now, minValidityMs = 0 } = input
  const candidates = [current, stored]
    .filter((token): token is string => !!token && token !== rejected)
    .map(token => ({ token, lifetime: readJwtLifetime(token) }))
    .filter(candidate => isUsable(candidate.lifetime, now))
    .sort((a, b) => b.lifetime!.expiresAt - a.lifetime!.expiresAt)
  const best = candidates[0]

  if (best && !needsRefresh(best.lifetime, now, minValidityMs)) {
    return { action: 'use', token: best.token }
  }
  return {
    action: 'call',
    route: chooseRefreshRoute(best?.lifetime ?? null, now),
    token: best?.token ?? null
  }
}

/** AUTH-SC-07/08: only an explicit refusal ends the session; anything else is transient. `null` = no response. */
export function refreshOutcomeOf(status: number | null): RefreshOutcome {
  if (status !== null && status >= 200 && status < 300) return 'refreshed'
  if (status === 401 || status === 403) return 'denied'
  return 'unreachable'
}

/** AUTH-SC-05: the active mandate is kept across a refresh while it is still granted. */
export function mandateToKeep<M extends { id: number }>(active: M | null, mandates: ReadonlyArray<{ id: number }>): M | null {
  if (!active) return null
  return mandates.some(mandate => mandate.id === active.id) ? active : null
}

/** AUTH-SC-06: what a page load can do with what localStorage holds. */
export function storedSessionState(input: { token: string | null, hasUser: boolean, now: number }): StoredSessionState {
  if (!input.hasUser) return 'none'
  return isUsable(readJwtLifetime(input.token), input.now) ? 'authenticated' : 'restorable'
}
