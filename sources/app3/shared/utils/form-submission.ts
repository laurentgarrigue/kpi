/**
 * Submission of a registration form without JavaScript (FEATURE_FORMS.md § 3, FRM-01): the browser POSTs the form to
 * its own page, the app3 server relays the answers to api2 and renders the outcome. Shared by the server middleware
 * (server/middleware/form-submission.ts) and the form page.
 */

export interface SubmissionResult {
  status: 'confirmed' | 'waiting'
  reason: 'full' | 'manual' | null
  payment_url: string | null
}

/** Answers as posted by the browser: one value, or several for a checkbox list. */
export type PostedValues = Record<string, string | string[]>

/** Outcome of a submission, rendered by the page (FRM-01: errors rendered by the server). */
export type SubmissionOutcome =
  | { kind: 'success', result: SubmissionResult }
  | { kind: 'invalid', errors: Record<string, string>, values: PostedValues }
  | { kind: 'rejected', reason: string, values: PostedValues }

/** Anti-bot fields sent with the answers (FEATURE_FORMS.md § 5). */
export const TOKEN_FIELD = '_token'
export const HONEYPOT_FIELD = 'website'
const UNPROCESSABLE = 422
const TOO_MANY_REQUESTS = 429
const FORM_PAGE = /^\/(?:(en)\/)?forms\/([a-z0-9-]{1,120})\/?$/

/** Form page targeted by a POST (`/forms/{slug}`, `/en/forms/{slug}`), or `null`. */
export function formSubmissionTarget(pathname: string): { slug: string, lang: string | null } | null {
  const match = FORM_PAGE.exec(pathname)
  return match ? { slug: match[2]!, lang: match[1] ?? null } : null
}

/**
 * Body sent to api2: string values only (a checkbox list posts its key once per box), empty values dropped (api2
 * reports a missing required answer). api2 ignores keys that are not fields of the form.
 */
export function submissionBody(posted: unknown): PostedValues {
  const body: PostedValues = {}
  for (const [key, raw] of Object.entries(posted && typeof posted === 'object' ? posted : {})) {
    const values = (Array.isArray(raw) ? raw : [raw]).filter((value): value is string => typeof value === 'string' && value.trim() !== '')
    if (values.length > 0) {
      body[key] = values.length === 1 ? values[0]! : values
    }
  }
  return body
}

/** Outcome of an api2 error response. */
export function submissionFailure(statusCode: number | undefined, data: unknown, values: PostedValues): SubmissionOutcome {
  const { errors, error } = (data ?? {}) as { errors?: Record<string, string>, error?: string }
  // The token and the honeypot never come back to the page: a fresh token comes with the definition.
  const kept = Object.fromEntries(Object.entries(values).filter(([key]) => key !== TOKEN_FIELD && key !== HONEYPOT_FIELD))
  if (statusCode === UNPROCESSABLE && errors) {
    return { kind: 'invalid', errors, values: kept }
  }
  const reason = statusCode === TOO_MANY_REQUESTS ? 'too_many_requests' : (error ?? 'unavailable')
  return { kind: 'rejected', reason, values: kept }
}
