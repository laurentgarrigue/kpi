import { canAdministerContent, canEditContent } from '~/utils/cms'
import { defineStore } from 'pinia'
import type { User, UserFilters } from '~/types'
import type { MandateSummary, MandateFilters } from '~/types/users'
import { storedSessionState, type StoredSessionState } from '~/utils/session'

const TOKEN_KEY = 'kpi_admin_token'
/** Written by earlier versions; the expiry is now read from the JWT itself (AUTH-SC-01). Only removed. */
const LEGACY_EXPIRES_AT_KEY = 'kpi_admin_token_expires_at'

interface AuthState {
  user: User | null
  token: string | null
  isAuthenticated: boolean
  mandates: MandateSummary[]
  activeMandate: { id: number; libelle: string } | null
  effectiveProfile: number | null
  effectiveFilters: MandateFilters | null
}

export const useAuthStore = defineStore('auth', {
  state: (): AuthState => ({
    user: null,
    token: null,
    isAuthenticated: false,
    mandates: [],
    activeMandate: null,
    effectiveProfile: null,
    effectiveFilters: null
  }),

  getters: {
    // Get effective profile level (mandate override or base profile)
    profile: (state): number => {
      return state.effectiveProfile ?? state.user?.profile ?? 99
    },

    // Check if user has required profile level (using effective profile)
    hasProfile: (state) => (requiredProfile: number): boolean => {
      if (!state.user) return false
      const effective = state.effectiveProfile ?? state.user.profile
      return effective <= requiredProfile
    },

    // Check if user is super admin (base profile 1, not mandate)
    isSuperAdmin: (state): boolean => {
      return state.user?.profile === 1
    },

    // Editorial section: Editor capability or profile 1–2 (PAGE_CMS.md, ADM-CMS-01)
    canEditContent(): boolean {
      return canEditContent(this.profile, this.user?.capabilities)
    },

    // Menu, home blocks, redirections, deletion: profile 1–2 only
    canAdministerContent(): boolean {
      return canAdministerContent(this.profile)
    },

    // Whether user has mandates available
    hasMandates: (state): boolean => {
      return state.mandates.length > 0
    },

    // Get current filters (effective or base)
    currentFilters: (state): UserFilters | MandateFilters | null => {
      return state.effectiveFilters ?? state.user?.filters ?? null
    }
  },

  actions: {
    setAuth(user: User, token: string, mandates?: MandateSummary[], activeMandate?: { id: number; libelle: string } | null, effectiveProfile?: number, effectiveFilters?: MandateFilters) {
      this.user = user
      this.token = token
      this.isAuthenticated = true
      this.mandates = (mandates ?? []).slice().sort((a, b) => a.niveau - b.niveau)
      this.activeMandate = activeMandate ?? null
      this.effectiveProfile = effectiveProfile ?? user.profile
      this.effectiveFilters = effectiveFilters ?? null

      if (import.meta.client) {
        localStorage.setItem(TOKEN_KEY, token)
        localStorage.removeItem(LEGACY_EXPIRES_AT_KEY)
        localStorage.setItem('kpi_admin_user', JSON.stringify(user))
        localStorage.setItem('kpi_admin_mandates', JSON.stringify(this.mandates))
        if (this.activeMandate) {
          localStorage.setItem('kpi_admin_active_mandate', JSON.stringify(this.activeMandate))
        } else {
          localStorage.removeItem('kpi_admin_active_mandate')
        }
        if (this.effectiveProfile !== null) {
          localStorage.setItem('kpi_admin_effective_profile', String(this.effectiveProfile))
        }
        if (this.effectiveFilters) {
          localStorage.setItem('kpi_admin_effective_filters', JSON.stringify(this.effectiveFilters))
        } else {
          localStorage.removeItem('kpi_admin_effective_filters')
        }
      }
    },

    setMandate(token: string, activeMandate: { id: number; libelle: string } | null, effectiveProfile: number, effectiveFilters: MandateFilters | null) {
      this.token = token
      this.activeMandate = activeMandate
      this.effectiveProfile = effectiveProfile
      this.effectiveFilters = effectiveFilters

      if (import.meta.client) {
        localStorage.setItem(TOKEN_KEY, token)
        if (activeMandate) {
          localStorage.setItem('kpi_admin_active_mandate', JSON.stringify(activeMandate))
        } else {
          localStorage.removeItem('kpi_admin_active_mandate')
        }
        localStorage.setItem('kpi_admin_effective_profile', String(effectiveProfile))
        if (effectiveFilters) {
          localStorage.setItem('kpi_admin_effective_filters', JSON.stringify(effectiveFilters))
        } else {
          localStorage.removeItem('kpi_admin_effective_filters')
        }

        // Reset work context so it reloads with the new mandate's permissions
        const workContext = useWorkContextStore()
        workContext.resetForNewUser()
      }
    },

    /** Replaces the access token only (refresh, token adopted from another tab): the JWT does not carry the mandate. */
    setToken(token: string) {
      this.token = token
      this.isAuthenticated = this.user !== null
      if (import.meta.client) {
        localStorage.setItem(TOKEN_KEY, token)
      }
    },

    clearAuth() {
      this.user = null
      this.token = null
      this.isAuthenticated = false
      this.mandates = []
      this.activeMandate = null
      this.effectiveProfile = null
      this.effectiveFilters = null

      if (import.meta.client) {
        localStorage.removeItem(TOKEN_KEY)
        localStorage.removeItem(LEGACY_EXPIRES_AT_KEY)
        localStorage.removeItem('kpi_admin_user')
        localStorage.removeItem('kpi_admin_mandates')
        localStorage.removeItem('kpi_admin_active_mandate')
        localStorage.removeItem('kpi_admin_effective_profile')
        localStorage.removeItem('kpi_admin_effective_filters')

        // Reset work context so it reloads with the new user's permissions
        const workContext = useWorkContextStore()
        workContext.resetForNewUser()
      }
    },

    /** Token another tab may have stored since this one loaded (AUTH-SC-04). */
    readStoredToken(): string | null {
      if (!import.meta.client) return null
      return localStorage.getItem(TOKEN_KEY)
    },

    /**
     * Loads the session kept in localStorage (AUTH-SC-06). `restorable`: the user is known but the token is expired;
     * the user, mandates and active mandate are loaded so that a cookie refresh can bring the session back as it was.
     */
    initAuth(): StoredSessionState {
      if (!import.meta.client) return 'none'

      const token = localStorage.getItem(TOKEN_KEY)
      const userJson = localStorage.getItem('kpi_admin_user')
      const state = storedSessionState({ token, hasUser: !!userJson, now: Date.now() })
      if (state === 'none') return state

      try {
        const user = JSON.parse(userJson!) as User
        this.user = user
        this.token = state === 'authenticated' ? token : null
        this.isAuthenticated = state === 'authenticated'

        const mandatesJson = localStorage.getItem('kpi_admin_mandates')
        this.mandates = mandatesJson ? (JSON.parse(mandatesJson) as MandateSummary[]).slice().sort((a, b) => a.niveau - b.niveau) : []

        const activeMandateJson = localStorage.getItem('kpi_admin_active_mandate')
        this.activeMandate = activeMandateJson ? JSON.parse(activeMandateJson) : null

        const effectiveProfileStr = localStorage.getItem('kpi_admin_effective_profile')
        this.effectiveProfile = effectiveProfileStr ? Number(effectiveProfileStr) : user.profile

        const effectiveFiltersJson = localStorage.getItem('kpi_admin_effective_filters')
        this.effectiveFilters = effectiveFiltersJson ? JSON.parse(effectiveFiltersJson) : null
        return state
      } catch {
        this.clearAuth()
        return 'none'
      }
    }
  }
})
