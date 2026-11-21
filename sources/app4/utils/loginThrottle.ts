const SECONDS_PER_MINUTE = 60
const HTTP_LOCKED = 423
const HTTP_TOO_MANY_REQUESTS = 429

/** A sign-in refused for a while: rate limit (AUTH-RL-01/02) or locked account (AUTH-LK-01). */
export interface LoginBlock {
  reason: 'rate_limited' | 'account_locked'
  /** Minutes to wait, null when api2 gave no readable Retry-After. */
  retryAfterMinutes: number | null
}

/**
 * AUTH-RL-03 / AUTH-LK-04: minutes to wait from a Retry-After header (seconds, as api2 sends it).
 * Rounded up, one minute at least; null when the header is missing or unreadable.
 */
export const retryAfterMinutes = (header: string | null): number | null => {
  if (header === null || !/^\d+$/.test(header.trim())) {
    return null
  }
  return Math.max(1, Math.ceil(Number(header) / SECONDS_PER_MINUTE))
}

/** The block behind a refused /auth/login, or null for another refusal. */
export const loginBlockOf = (status: number, retryAfterHeader: string | null): LoginBlock | null => {
  if (status === HTTP_TOO_MANY_REQUESTS) {
    return { reason: 'rate_limited', retryAfterMinutes: retryAfterMinutes(retryAfterHeader) }
  }
  if (status === HTTP_LOCKED) {
    return { reason: 'account_locked', retryAfterMinutes: retryAfterMinutes(retryAfterHeader) }
  }
  return null
}

/** The `t` of vue-i18n, as far as these messages need it. */
type Translate = (key: string, named?: Record<string, unknown>, plural?: number) => string

/** AUTH-RL-03 / AUTH-LK-04: the message of a block, on the login page as on the second factor page. */
export const loginBlockMessage = ({ reason, retryAfterMinutes: minutes }: LoginBlock, t: Translate): string =>
  minutes === null
    ? t(`login.error_${reason}_unknown`)
    : t(`login.error_${reason}`, { n: minutes }, minutes)
