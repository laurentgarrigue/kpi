import type { MandateSummary, MandateFilters } from '~/types/users'
import { mandateToKeep, planRefresh, refreshOutcomeOf, type RefreshOutcome } from '~/utils/session'

/** Web Locks name shared by every app4 tab: one refresh at a time per browser (AUTH-SC-04). */
const REFRESH_LOCK = 'kpi-auth-refresh'
/** A refresh that hangs must not hold the lock (and every pending request) forever. */
const REFRESH_TIMEOUT_MS = 10_000

/** Fallback when the Web Locks API is missing: serialises the refreshes of this tab only. */
let localRefreshQueue: Promise<unknown> = Promise.resolve()

const withRefreshLock = <T>(task: () => Promise<T>): Promise<T> => {
  if (typeof navigator !== 'undefined' && navigator.locks) {
    return navigator.locks.request(REFRESH_LOCK, task)
  }
  const run = localRefreshQueue.then(task, task)
  localRefreshQueue = run.catch(() => undefined)
  return run
}

/** fetch with a timeout; resolves to null when no response came back (network error, timeout). */
const fetchOrNull = async (url: string, init: RequestInit): Promise<Response | null> => {
  const controller = new AbortController()
  const timer = setTimeout(() => controller.abort(), REFRESH_TIMEOUT_MS)
  try {
    return await fetch(url, { ...init, signal: controller.signal })
  } catch {
    return null
  } finally {
    clearTimeout(timer)
  }
}

export interface RefreshOptions {
  /** Token the server just answered 401 to: never reused, even if its `exp` looks fine. */
  rejectedToken?: string | null
  /** Renew unless the session stays valid at least this long (AUTH-SC-20: 2 h when a match is loaded). */
  minValidityMs?: number
}

interface LoginResponse {
  token: string
  user: {
    id: string
    name: string
    firstname: string
    profile: number
    filters: MandateFilters
    mandates: MandateSummary[]
    activeMandate: { id: number; libelle: string } | null
    effectiveProfile: number
    effectiveFilters: MandateFilters
  }
  hasMandates: boolean
}

/** Body of /auth/refresh-session: same shape as /auth/login (base profile, no active mandate). */
type SessionResponse = LoginResponse

interface RefreshResponse {
  token: string
  user: {
    mandates: MandateSummary[]
    activeMandate: { id: number; libelle: string } | null
    effectiveProfile: number
    effectiveFilters: MandateFilters | null
  }
}

interface SwitchMandateResponse {
  token: string
  user: {
    activeMandate: { id: number; libelle: string } | null
    effectiveProfile: number
    effectiveFilters: MandateFilters | null
  }
}

export const useAuth = () => {
  const config = useRuntimeConfig()
  const authStore = useAuthStore()

  const baseUrl = config.public.api2BaseUrl

  // Login with username and password
  const login = async (username: string, password: string): Promise<{ hasMandates: boolean }> => {
    try {
      const response = await fetch(`${baseUrl}/auth/login`, {
        method: 'POST',
        credentials: 'include', // phase 1b : récupérer les cookies kpi_access/kpi_refresh
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ username, password })
      })

      if (!response.ok) {
        if (response.status === 401) {
          throw new Error('Invalid credentials')
        }
        throw new Error('Login failed')
      }

      const data: LoginResponse = await response.json()

      authStore.setAuth(
        data.user,
        data.token,
        data.user.mandates,
        data.user.activeMandate,
        data.user.effectiveProfile,
        data.user.effectiveFilters
      )

      return { hasMandates: data.hasMandates }
    } catch (error) {
      authStore.clearAuth()
      throw error
    }
  }

  // Logout: révoque le refresh token côté api2 (AUTH_HARDENING § 3) puis nettoie l'état local.
  // L'appel passe le cookie kpi_refresh via credentials: 'include'. Les erreurs réseau sont
  // tolérées : la déconnexion locale ne doit jamais être bloquée par un backend injoignable.
  const logout = async (): Promise<void> => {
    try {
      await fetch(`${baseUrl}/auth/logout`, {
        method: 'POST',
        credentials: 'include'
      })
    } catch {
      // ignore — on nettoie quand même l'état local
    }
    authStore.clearAuth()
  }

  const mandateHeader = (): Record<string, string> =>
    authStore.activeMandate ? { 'X-Active-Mandate': String(authStore.activeMandate.id) } : {}

  // POST /auth/refresh: a valid JWT renews itself, without rotating the refresh cookie (AUTH-SC-03).
  const renewWithToken = async (token: string): Promise<RefreshOutcome> => {
    const response = await fetchOrNull(`${baseUrl}/auth/refresh`, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Authorization': `Bearer ${token}`, ...mandateHeader() }
    })
    const outcome = refreshOutcomeOf(response?.status ?? null)
    if (outcome !== 'refreshed') return outcome

    const data: RefreshResponse = await response!.json()
    authStore.setAuth(
      authStore.user!,
      data.token,
      data.user.mandates,
      data.user.activeMandate,
      data.user.effectiveProfile,
      data.user.effectiveFilters ?? undefined
    )
    return outcome
  }

  // POST /auth/refresh-session: the refresh cookie brings back an expired session (AUTH-SC-03, AUTH-SC-06).
  // The body carries the base profile; the active mandate is kept while still granted (AUTH-SC-05).
  const renewWithCookie = async (): Promise<RefreshOutcome> => {
    const response = await fetchOrNull(`${baseUrl}/auth/refresh-session`, {
      method: 'POST',
      credentials: 'include'
    })
    const outcome = refreshOutcomeOf(response?.status ?? null)
    if (outcome !== 'refreshed') return outcome

    const data: SessionResponse = await response!.json()
    const keptMandate = mandateToKeep(authStore.activeMandate, data.user.mandates)
    authStore.setAuth(
      { ...data.user, token: data.token },
      data.token,
      data.user.mandates,
      keptMandate,
      keptMandate ? authStore.effectiveProfile ?? undefined : data.user.effectiveProfile,
      keptMandate ? authStore.effectiveFilters ?? undefined : data.user.effectiveFilters
    )
    return outcome
  }

  /**
   * Renews the session (AUTH_HARDENING.md § 3ter). Never logs out by itself: the caller decides, and only a
   * `denied` outcome may end the session (AUTH-SC-07/08).
   */
  const refreshToken = (options: RefreshOptions = {}): Promise<RefreshOutcome> =>
    withRefreshLock(async () => {
      const plan = planRefresh({
        current: authStore.token,
        stored: authStore.readStoredToken(),
        rejected: options.rejectedToken,
        now: Date.now(),
        minValidityMs: options.minValidityMs
      })

      if (plan.action === 'use') {
        if (plan.token !== authStore.token) authStore.setToken(plan.token)
        return 'refreshed'
      }

      if (plan.route === 'refresh' && plan.token && authStore.user) {
        const outcome = await renewWithToken(plan.token)
        if (outcome !== 'denied') return outcome
      }
      return renewWithCookie()
    })

  /** AUTH-SC-06: brings back, from the refresh cookie, a session whose stored token expired (page reload). */
  const restoreSession = (): Promise<RefreshOutcome> => refreshToken()

  // Check if token is still valid
  const checkAuth = async (): Promise<boolean> => {
    if (!authStore.token) return false

    try {
      const response = await fetch(`${baseUrl}/auth/me`, {
        credentials: 'include',
        headers: {
          'Authorization': `Bearer ${authStore.token}`,
          ...(authStore.activeMandate ? { 'X-Active-Mandate': String(authStore.activeMandate.id) } : {})
        }
      })

      if (!response.ok) {
        authStore.clearAuth()
        return false
      }

      return true
    } catch {
      authStore.clearAuth()
      return false
    }
  }

  // Switch active mandate (or revert to base profile with mandateId = null)
  const switchMandate = async (mandateId: number | null): Promise<void> => {
    const response = await fetch(`${baseUrl}/auth/switch-mandate`, {
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${authStore.token}`
      },
      body: JSON.stringify({ mandateId })
    })

    if (!response.ok) {
      throw new Error('Failed to switch mandate')
    }

    const data: SwitchMandateResponse = await response.json()

    authStore.setMandate(
      data.token,
      data.user.activeMandate,
      data.user.effectiveProfile,
      data.user.effectiveFilters
    )
  }

  return {
    login,
    logout,
    checkAuth,
    refreshToken,
    restoreSession,
    switchMandate
  }
}
