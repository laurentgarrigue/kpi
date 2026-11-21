import { PASSWORD_RENEWAL_REQUIRED, PasswordRenewalRequiredError } from '~/composables/useAuth'
import type { SecondFactorStep, SecondFactorSummary } from '~/utils/secondFactor'
import { retryAfterMinutes } from '~/utils/loginThrottle'

/**
 * Refusal of a second factor call: api2 code (INVALID_CODE, INVALID_TOKEN, TOO_MANY_CODES, EMAIL_REQUIRED…), and the
 * minutes of its Retry-After when api2 sends one (ACCOUNT_LOCKED, AUTH-LK-04).
 */
export class SecondFactorError extends Error {
  constructor(
    public readonly code: string,
    public readonly status: number,
    public readonly retryAfterMinutes: number | null = null
  ) {
    super(code)
  }
}

export interface TotpSetup {
  secret: string
  otpauthUri: string
  qrSvg: string
}

export interface TrustedDevice {
  id: number
  label: string
  createdAt: string
  expiresAt: string
}

export interface SecondFactorStatus extends SecondFactorSummary {
  maxProfile: number
  enforcedFrom: string | null
  canDisable: boolean
  totpAvailable: boolean
  maskedEmail: string | null
  recoveryCodesLeft: number
  trustedDevices: TrustedDevice[]
}

/** Enrollment calls, the same for the enrollment token (sign-in) and for the session (/profile/security). */
export interface EnrollmentApi {
  setupTotp: () => Promise<TotpSetup>
  activateTotp: (code: string) => Promise<string[]>
  setupEmail: () => Promise<void>
  activateEmail: (code: string) => Promise<string[]>
}

/** Where to go once an enrollment made at sign-in is acknowledged. */
export type AfterEnrollment = '/' | '/select-mandate' | '/renew-password'

/** Token handed over by /auth/login (or the Google callback): in memory only, never in localStorage. */
export const useSecondFactorStep = () => useState<SecondFactorStep | null>('second-factor-step', () => null)

type Json = Record<string, unknown>

export const useSecondFactor = () => {
  const config = useRuntimeConfig()
  const authStore = useAuthStore()
  const { openSession } = useAuth()
  const renewalToken = usePasswordRenewalToken()
  const baseUrl = config.public.api2BaseUrl

  const call = async (method: 'GET' | 'POST', path: string, body?: Json, withSession = false): Promise<Json> => {
    const response = await fetch(`${baseUrl}${path}`, {
      method,
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        ...(withSession && authStore.token ? { Authorization: `Bearer ${authStore.token}` } : {})
      },
      body: body === undefined ? undefined : JSON.stringify(body)
    })
    const data = (await response.json().catch(() => ({}))) as Json
    if (response.status === 403 && data.code === PASSWORD_RENEWAL_REQUIRED && typeof data.renewalToken === 'string') {
      renewalToken.value = data.renewalToken
      throw new PasswordRenewalRequiredError(data.renewalToken)
    }
    if (!response.ok) {
      throw new SecondFactorError(
        typeof data.code === 'string' ? data.code : 'ERROR',
        response.status,
        retryAfterMinutes(response.headers.get('Retry-After'))
      )
    }
    return data
  }

  const sessionCall = (method: 'GET' | 'POST', path: string, body?: Json) => call(method, path, body, true)

  // ------------------------------------------------------------------ sign-in (challenge token)

  /** AUTH-2FA-07: proves the factor and opens the session (or throws PasswordRenewalRequiredError). */
  const verify = async (token: string, code: string, trustDevice: boolean): Promise<{ hasMandates: boolean }> =>
    openSession(await call('POST', '/auth/2fa/verify', { challengeToken: token, code, trustDevice }) as never)

  const sendLoginCode = async (token: string): Promise<void> => {
    await call('POST', '/auth/2fa/email/send', { challengeToken: token })
  }

  /**
   * AUTH-2FA-02: enrollment required at sign-in. The activation opens the session (or asks for the password
   * renewal) and returns the recovery codes; `next()` tells where to go once they are acknowledged.
   */
  const tokenEnrollment = (token: string): EnrollmentApi & { next: () => AfterEnrollment } => {
    let destination: AfterEnrollment = '/'
    const activate = async (path: string, code: string): Promise<string[]> => {
      const response = await fetch(`${baseUrl}${path}`, {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ enrollmentToken: token, code })
      })
      const data = (await response.json().catch(() => ({}))) as Json
      if (response.status === 403 && data.code === PASSWORD_RENEWAL_REQUIRED && typeof data.renewalToken === 'string') {
        renewalToken.value = data.renewalToken
        destination = '/renew-password'
        return (data.recoveryCodes as string[]) ?? []
      }
      if (!response.ok) {
        throw new SecondFactorError(typeof data.code === 'string' ? data.code : 'ERROR', response.status)
      }
      destination = openSession(data as never).hasMandates ? '/select-mandate' : '/'
      return data.recoveryCodes as string[]
    }

    return {
      setupTotp: async () => await call('POST', '/auth/2fa/enroll/totp/setup', { enrollmentToken: token }) as unknown as TotpSetup,
      activateTotp: code => activate('/auth/2fa/enroll/totp/activate', code),
      setupEmail: async () => { await call('POST', '/auth/2fa/enroll/email/setup', { enrollmentToken: token }) },
      activateEmail: code => activate('/auth/2fa/enroll/email/activate', code),
      next: () => destination
    }
  }

  // ------------------------------------------------------------------ session (/profile/security)

  const status = async (): Promise<SecondFactorStatus> => await sessionCall('GET', '/auth/2fa') as unknown as SecondFactorStatus

  /** AUTH-2FA-14: `currentCode` proves the active factor before switching to another one. */
  const sessionEnrollment = (currentCode: string | null): EnrollmentApi => ({
    setupTotp: async () => await sessionCall('POST', '/auth/2fa/totp/setup', currentCode ? { currentCode } : {}) as unknown as TotpSetup,
    activateTotp: async code => (await sessionCall('POST', '/auth/2fa/totp/activate', { code })).recoveryCodes as string[],
    setupEmail: async () => { await sessionCall('POST', '/auth/2fa/email/setup', currentCode ? { currentCode } : {}) },
    activateEmail: async code => (await sessionCall('POST', '/auth/2fa/email/activate', { code })).recoveryCodes as string[]
  })

  const sendProofCode = async (): Promise<void> => {
    await sessionCall('POST', '/auth/2fa/email/code')
  }

  const regenerateRecoveryCodes = async (code: string): Promise<string[]> =>
    (await sessionCall('POST', '/auth/2fa/recovery-codes', { code })).recoveryCodes as string[]

  const disable = async (code: string): Promise<void> => {
    await sessionCall('POST', '/auth/2fa/disable', { code })
  }

  const revokeTrustedDevice = async (id: number): Promise<void> => {
    await sessionCall('POST', `/auth/2fa/trusted-devices/${id}/revoke`)
  }

  /** Keeps the grace banner in step once the factor changed. */
  const refreshSummary = (summary: SecondFactorSummary): void => {
    authStore.setSecondFactor(summary)
  }

  return {
    verify,
    sendLoginCode,
    tokenEnrollment,
    status,
    sessionEnrollment,
    sendProofCode,
    regenerateRecoveryCodes,
    disable,
    revokeTrustedDevice,
    refreshSummary
  }
}
