/**
 * Second factor helpers (AUTH_HARDENING.md § 5), pure and tested without Nuxt.
 */

export type SecondFactorMethod = 'totp' | 'email'

/** What /auth/login (or the Google callback) asks for when it opens no session. */
export interface SecondFactorStep {
  mode: 'challenge' | 'enroll'
  token: string
  method: SecondFactorMethod | null
  maskedEmail: string | null
  totpAvailable: boolean
  codeSent: boolean
}

/** Second factor summary sent by api2 with the session (`user.secondFactor`). */
export interface SecondFactorSummary {
  method: SecondFactorMethod | null
  required: boolean
  enrollmentDueAt: string | null
}

const SIX_DIGITS = /^\d{6}$/
const RECOVERY_CODE = /^[a-z0-9]{5}-?[a-z0-9]{5}$/i
const DAY_MS = 86_400_000

const methodOf = (value: string | null): SecondFactorMethod | null =>
  value === 'totp' || value === 'email' ? value : null

/**
 * The Google callback hands the token over in the URL fragment (never sent to a server):
 * `#challenge=…&method=…&maskedEmail=…` or `#enroll=…&totpAvailable=0|1&maskedEmail=…`.
 */
export function parseSecondFactorFragment(hash: string): SecondFactorStep | null {
  const params = new URLSearchParams(hash.replace(/^#/, ''))
  const challenge = params.get('challenge')
  const enroll = params.get('enroll')
  const token = challenge ?? enroll
  if (!token) return null

  const method = methodOf(params.get('method'))
  return {
    mode: challenge ? 'challenge' : 'enroll',
    token,
    method,
    maskedEmail: params.get('maskedEmail') || null,
    totpAvailable: params.get('totpAvailable') !== '0',
    // The callback already sent the e-mail code of a challenge.
    codeSent: method === 'email'
  }
}

export function normalizeCode(code: string): string {
  return code.replace(/\s+/g, '')
}

/** A 6-digit code (application or e-mail) or a recovery code `abcde-fghij`. */
export function isCompleteCode(code: string): boolean {
  const normalized = normalizeCode(code)
  return SIX_DIGITS.test(normalized) || RECOVERY_CODE.test(normalized)
}

/** Days left before the enrollment becomes mandatory (rounded up), null outside the grace. */
export function graceDaysLeft(enrollmentDueAt: string | null, now: Date = new Date()): number | null {
  if (!enrollmentDueAt) return null
  const due = new Date(enrollmentDueAt).getTime()
  if (Number.isNaN(due)) return null
  return Math.max(0, Math.ceil((due - now.getTime()) / DAY_MS))
}

/** Text file offered to save the recovery codes. */
export function recoveryCodesText(codes: string[], heading: string): string {
  return `${heading}\n\n${codes.join('\n')}\n`
}

/** Account code carried by a sign-in token `{purpose}.{base64(code)}.{expiry}.{signature}` (read only, not trusted). */
export function accountOf(token: string): string {
  const encoded = token.split('.')[1] ?? ''
  try {
    return atob(encoded)
  } catch {
    return ''
  }
}
