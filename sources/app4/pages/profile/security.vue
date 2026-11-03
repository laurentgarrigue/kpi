<script setup lang="ts">
/**
 * /profile/security — gestion utilisateur de ses sessions et moyens d'auth (AUTH_HARDENING § 3bis).
 *
 * Trois blocs :
 *  1. Mes appareils actifs : liste + bouton révoquer par ligne (désactivé sur l'appareil courant)
 *     + action globale « déconnecter toutes les autres sessions ».
 *  2. Compte Google : afficher si lié, proposer de délier (api2 renvoie 409 si le compte n'a pas
 *     de mot de passe actif → on affiche le message plutôt que de bloquer en amont).
 *  3. Phase 3 (TOTP) : emplacement réservé pour le futur enrôlement 2FA.
 */
definePageMeta({
  layout: 'admin',
  middleware: ['auth']
})

interface Device {
  id: number
  label: string
  createdAt: string
  lastSeenAt: string
  isCurrent: boolean
}

const { t } = useI18n()
const config = useRuntimeConfig()
const authStore = useAuthStore()
const { logout } = useAuth()
const route = useRoute()

const devices = ref<Device[]>([])
const googleLinked = ref<boolean>(false)
const loading = ref(false)
const actionError = ref<string | null>(null)
const actionNotice = ref<string | null>(null)

const api = (path: string, init: RequestInit = {}) => fetch(`${config.public.api2BaseUrl}${path}`, {
  credentials: 'include',
  headers: {
    'Authorization': `Bearer ${authStore.token}`,
    ...(init.headers ?? {})
  },
  ...init
})

async function reloadDevices() {
  const resp = await api('/auth/devices')
  if (!resp.ok) throw new Error('failed to load devices')
  const body = await resp.json()
  devices.value = body.devices as Device[]
}

async function reloadMe() {
  const resp = await api('/auth/me')
  if (!resp.ok) return
  const body = await resp.json()
  googleLinked.value = Boolean(body?.user?.oauthProviders?.google)
}

async function revokeDevice(device: Device) {
  if (device.isCurrent) return
  actionError.value = null
  actionNotice.value = null
  loading.value = true
  try {
    const resp = await api(`/auth/devices/${device.id}/revoke`, { method: 'POST' })
    if (!resp.ok) {
      actionError.value = t('security.devices.revoke_failed')
      return
    }
    actionNotice.value = t('security.devices.revoke_done')
    await reloadDevices()
  } finally {
    loading.value = false
  }
}

async function revokeOthers() {
  actionError.value = null
  actionNotice.value = null
  loading.value = true
  try {
    const resp = await api('/auth/devices/revoke-others', { method: 'POST' })
    if (!resp.ok) {
      actionError.value = t('security.devices.revoke_failed')
      return
    }
    const body = await resp.json()
    actionNotice.value = t('security.devices.revoke_others_done', { count: body.revoked ?? 0 })
    await reloadDevices()
  } finally {
    loading.value = false
  }
}

async function unlinkGoogle() {
  actionError.value = null
  actionNotice.value = null
  loading.value = true
  try {
    const resp = await api('/auth/google/unlink', { method: 'POST' })
    const body = await resp.json().catch(() => ({ message: '', code: '' }))
    if (resp.status === 409 && body?.code === 'PASSWORD_REQUIRED') {
      actionError.value = t('security.google.password_required')
      return
    }
    if (!resp.ok) {
      actionError.value = body?.message || t('security.google.unlink_failed')
      return
    }
    actionNotice.value = t('security.google.unlink_done')
    googleLinked.value = false
  } finally {
    loading.value = false
  }
}

function formatDate(iso: string): string {
  const d = new Date(iso.replace(' ', 'T'))
  if (Number.isNaN(d.getTime())) return iso
  return d.toLocaleString()
}

// Lancement du flux « lier Google depuis le profil » (AUTH-OA-07).
// Le cookie kpi_access (HttpOnly) porte le JWT ; la redirection complète (window.location) suffit,
// pas besoin de transporter l'en-tête Authorization. api2 lira le JWT via l'extracteur `cookie`.
function startGoogleLink() {
  window.location.href = `${config.public.api2BaseUrl}/auth/google/connect`
}

// Au retour du callback api2, l'URL porte `?link=ok` ou `?link=error&code=…` :
// on affiche la bannière puis on nettoie l'URL pour éviter de ré-afficher au rafraîchissement.
function handleLinkQuery() {
  const status = String(route.query.link ?? '')
  if (status === 'ok') {
    actionNotice.value = t('security.google.link_done')
    googleLinked.value = true
  } else if (status === 'error') {
    const code = String(route.query.code ?? '')
    const key = `security.google.link_error.${code}`
    const translated = t(key)
    actionError.value = translated === key ? t('security.google.link_error.generic') : translated
  } else {
    return
  }
  // Nettoie l'URL sans provoquer de reroutage Nuxt.
  if (typeof window !== 'undefined') {
    window.history.replaceState({}, '', '/profile/security')
  }
}

onMounted(async () => {
  loading.value = true
  try {
    await Promise.all([reloadDevices(), reloadMe()])
  } catch {
    actionError.value = t('security.load_failed')
  } finally {
    loading.value = false
  }
  handleLinkQuery()
})
</script>

<template>
  <div class="max-w-3xl mx-auto px-4 py-6 space-y-8">
    <header>
      <h1 class="text-2xl font-bold text-header-900 dark:text-header-50">{{ t('security.title') }}</h1>
      <p class="mt-1 text-sm text-header-600 dark:text-header-300">{{ t('security.subtitle') }}</p>
    </header>

    <div
      v-if="actionError"
      class="p-3 bg-danger-50 dark:bg-danger-950 border border-danger-200 dark:border-danger-800 rounded text-sm text-danger-700 dark:text-danger-400"
    >
      {{ actionError }}
    </div>
    <div
      v-if="actionNotice"
      class="p-3 bg-success-50 dark:bg-success-950 border border-success-200 dark:border-success-800 rounded text-sm text-success-700 dark:text-success-400"
    >
      {{ actionNotice }}
    </div>

    <!-- Appareils actifs -->
    <section class="bg-white dark:bg-header-900 rounded-lg shadow p-6">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-header-900 dark:text-header-50">{{ t('security.devices.title') }}</h2>
        <UButton
          color="warning"
          variant="outline"
          size="sm"
          :disabled="loading || devices.filter(d => !d.isCurrent).length === 0"
          @click="revokeOthers"
        >
          {{ t('security.devices.revoke_others') }}
        </UButton>
      </div>
      <p class="text-sm text-header-600 dark:text-header-300 mb-4">{{ t('security.devices.help') }}</p>

      <ul class="divide-y divide-header-200 dark:divide-header-800">
        <li v-for="device in devices" :key="device.id" class="py-3 flex items-center justify-between gap-4">
          <div class="min-w-0">
            <div class="font-medium text-header-900 dark:text-header-50 flex items-center gap-2">
              {{ device.label }}
              <span
                v-if="device.isCurrent"
                class="text-xs font-semibold px-2 py-0.5 rounded bg-primary-100 dark:bg-primary-900 text-primary-800 dark:text-primary-200"
              >
                {{ t('security.devices.current') }}
              </span>
            </div>
            <div class="text-xs text-header-500 dark:text-header-400">
              {{ t('security.devices.last_seen', { date: formatDate(device.lastSeenAt) }) }}
              · {{ t('security.devices.created', { date: formatDate(device.createdAt) }) }}
            </div>
          </div>
          <UButton
            color="danger"
            variant="ghost"
            size="sm"
            :disabled="device.isCurrent || loading"
            @click="revokeDevice(device)"
          >
            {{ t('security.devices.revoke') }}
          </UButton>
        </li>
        <li v-if="devices.length === 0" class="py-4 text-sm text-header-500 dark:text-header-400 italic">
          {{ t('security.devices.empty') }}
        </li>
      </ul>
    </section>

    <!-- Compte Google -->
    <section class="bg-white dark:bg-header-900 rounded-lg shadow p-6">
      <h2 class="text-lg font-semibold text-header-900 dark:text-header-50 mb-2">{{ t('security.google.title') }}</h2>
      <p v-if="googleLinked" class="text-sm text-header-700 dark:text-header-200">
        {{ t('security.google.linked') }}
      </p>
      <p v-else class="text-sm text-header-700 dark:text-header-200">
        {{ t('security.google.link_cta') }}
      </p>
      <div class="mt-3">
        <UButton
          v-if="!googleLinked"
          color="primary"
          variant="outline"
          size="sm"
          :disabled="loading"
          @click="startGoogleLink"
        >
          {{ t('security.google.link') }}
        </UButton>
        <UButton
          v-else
          color="neutral"
          variant="outline"
          size="sm"
          :disabled="loading"
          @click="unlinkGoogle"
        >
          {{ t('security.google.unlink') }}
        </UButton>
      </div>
    </section>

    <!-- TOTP (phase 3, placeholder) -->
    <section class="bg-white dark:bg-header-900 rounded-lg shadow p-6 opacity-60">
      <h2 class="text-lg font-semibold text-header-900 dark:text-header-50 mb-2">{{ t('security.totp.title') }}</h2>
      <p class="text-sm text-header-600 dark:text-header-300">{{ t('security.totp.coming_soon') }}</p>
    </section>

    <!-- Déconnexion -->
    <section class="pt-2">
      <UButton color="neutral" variant="ghost" size="sm" @click="logout().then(() => navigateTo('/login'))">
        {{ t('security.sign_out') }}
      </UButton>
    </section>
  </div>
</template>
