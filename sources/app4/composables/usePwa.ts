import { computed, ref, watch } from 'vue'

/**
 * Service worker lifecycle for app4 — contrat : `DOC/specs/PWA_AUTO_UPDATE.md` §5.
 *
 * Pattern **prompt-to-update** : le nouveau worker est installé puis **attend**
 * (`skipWaiting`/`clientsClaim` volontairement absents de `nuxt.config.ts`).
 * `needRefresh` passe à `true`, `PwaUpdatePrompt` affiche le bandeau « Recharger », et
 * c'est l'utilisateur qui déclenche la bascule — jamais un rechargement surprise en
 * pleine saisie de match sur la console de scoring.
 *
 * Re-vérifications : au chargement, au retour au premier plan, au retour en ligne et
 * toutes les 60 minutes (un événement garde un onglet ouvert plusieurs jours).
 *
 * L'état online/offline n'est **pas** redéfini ici : il est lu depuis
 * `useOnlineStatus()`, seule source de vérité côté app4.
 */

// Singleton state: one registration per document, shared by every caller.
let initialized = false
const needRefresh = ref(false)
const offlineReady = ref(false)
let updateServiceWorker: ((reloadPage?: boolean) => Promise<void>) | null = null
let registration: ServiceWorkerRegistration | null = null

const RECHECK_INTERVAL_MS = 60 * 60 * 1000

/** Ask the browser to re-fetch the worker script (no-op when unchanged). */
const checkForUpdates = async (): Promise<void> => {
  if (!import.meta.env.PROD || !registration) return
  try {
    await registration.update()
  } catch {
    // Offline or server unreachable: the next check will do.
  }
}

const register = (isOnline: Ref<boolean>): void => {
  if (initialized || !import.meta.client) return
  initialized = true

  // Re-check when the tab comes back to the foreground…
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible' && navigator.onLine) void checkForUpdates()
  })
  // …and when connectivity returns (online state owned by useOnlineStatus).
  watch(isOnline, (online) => {
    if (online) void checkForUpdates()
  })

  if (!import.meta.env.PROD || !('serviceWorker' in navigator)) return

  import('virtual:pwa-register/vue')
    .then(({ useRegisterSW }) => {
      const {
        offlineReady: swOfflineReady,
        needRefresh: swNeedRefresh,
        updateServiceWorker: swUpdate
      } = useRegisterSW({
        immediate: true,
        onRegistered(reg) {
          registration = reg ?? null
          if (reg) {
            setInterval(() => {
              if (navigator.onLine) void reg.update()
            }, RECHECK_INTERVAL_MS)
          }
        },
        onRegisterError(error) {
          console.error('[PWA] Service Worker registration error:', error)
        }
      })

      updateServiceWorker = swUpdate
      watch(swOfflineReady, (value) => { offlineReady.value = value })
      watch(swNeedRefresh, (value) => { needRefresh.value = value })
    })
    .catch(() => {
      // Module unavailable (SW unsupported): the app keeps working, no prompt.
    })

  // A worker already waiting when the page loads must also raise the banner.
  void navigator.serviceWorker.ready.then((reg) => {
    registration = reg
    if (reg.waiting) needRefresh.value = true
  }).catch(() => { /* no worker yet */ })
}

export const usePwa = () => {
  const { isOnline } = useOnlineStatus()
  register(isOnline)

  /** Activate the waiting worker and reload the page. */
  const updateApp = async (): Promise<void> => {
    if (updateServiceWorker) {
      await updateServiceWorker(true)
    } else {
      window.location.reload()
    }
  }

  return {
    isOnline,
    needRefresh: computed(() => needRefresh.value),
    offlineReady: computed(() => offlineReady.value),
    updateApp,
    checkForUpdates
  }
}
