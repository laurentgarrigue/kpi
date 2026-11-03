<script setup lang="ts">
/**
 * Page de fin du flux OAuth (AUTH_HARDENING § 4).
 *
 * À l'issue de /auth/google/callback, api2 pose les cookies kpi_access et kpi_refresh puis
 * redirige ici. app4 lit encore son authentification depuis localStorage.kpi_admin_token
 * (transition hybride, voir AUTH-CK-02). Si on laissait l'utilisateur atterrir directement
 * sur `/`, le middleware `auth` verrait `isAuthenticated = false` et renverrait sur `/login`.
 *
 * Cette page appelle `POST /auth/refresh-session` (qui lit le cookie kpi_refresh, émet un
 * nouveau couple de tokens et renvoie `{token, user, hasMandates}`), remplit le store et
 * navigue vers /select-mandate ou /.
 */
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

definePageMeta({
  layout: 'default',
  middleware: 'auth'
})

const { t } = useI18n()
const config = useRuntimeConfig()
const authStore = useAuthStore()

const error = ref<string | null>(null)

onMounted(async () => {
  try {
    const resp = await fetch(`${config.public.api2BaseUrl}/auth/refresh-session`, {
      method: 'POST',
      credentials: 'include'
    })
    if (!resp.ok) {
      // Les cookies n'étaient pas là ou étaient invalides : retour login avec message.
      error.value = t('login.oauth_error.oauth_generic')
      await new Promise(r => setTimeout(r, 1500))
      await navigateTo('/login?oauth_error=oauth_generic')
      return
    }
    const data = await resp.json() as LoginResponse
    authStore.setAuth(
      data.user,
      data.token,
      data.user.mandates,
      data.user.activeMandate,
      data.user.effectiveProfile,
      data.user.effectiveFilters
    )
    await navigateTo(data.hasMandates ? '/select-mandate' : '/')
  } catch {
    error.value = t('login.oauth_error.oauth_generic')
    await new Promise(r => setTimeout(r, 1500))
    await navigateTo('/login?oauth_error=oauth_generic')
  }
})
</script>

<template>
  <div class="min-h-screen flex items-center justify-center px-4">
    <div class="text-center space-y-4">
      <div v-if="!error" class="text-header-900 dark:text-header-50">
        <UIcon name="heroicons:arrow-path" class="w-8 h-8 animate-spin inline-block" />
        <p class="mt-2">{{ t('login.oauth_completing') }}</p>
      </div>
      <div
        v-else
        class="p-3 bg-danger-50 dark:bg-danger-950 border border-danger-200 dark:border-danger-800 rounded-lg text-danger-700 dark:text-danger-400 text-sm max-w-md"
      >
        {{ error }}
      </div>
    </div>
  </div>
</template>
