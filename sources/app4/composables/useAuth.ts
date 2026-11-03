import type { MandateSummary, MandateFilters } from '~/types/users'

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

  // Refresh the JWT token silently using the existing valid token.
  // Phase 1b : si le JWT en localStorage est perdu/expiré mais que le cookie refresh existe,
  // on bascule sur /auth/refresh-session qui ne lit QUE le cookie — pas d'en-tête Authorization.
  const refreshToken = async (): Promise<boolean> => {
    if (!authStore.token) {
      try {
        const sessionResp = await fetch(`${baseUrl}/auth/refresh-session`, {
          method: 'POST',
          credentials: 'include'
        })
        return sessionResp.ok
      } catch {
        return false
      }
    }

    try {
      const response = await fetch(`${baseUrl}/auth/refresh`, {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Authorization': `Bearer ${authStore.token}`,
          ...(authStore.activeMandate ? { 'X-Active-Mandate': String(authStore.activeMandate.id) } : {})
        }
      })

      if (!response.ok) return false

      const data: RefreshResponse = await response.json()

      authStore.setAuth(
        authStore.user!,
        data.token,
        data.user.mandates,
        data.user.activeMandate,
        data.user.effectiveProfile,
        data.user.effectiveFilters ?? undefined
      )

      return true
    } catch {
      return false
    }
  }

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
    switchMandate
  }
}
