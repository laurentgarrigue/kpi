/**
 * Immediate PWA update (spec §0.9 / plan §4.9): the user must always run the latest
 * version — never a silently stale app shell.
 *
 * The service worker is registered with `autoUpdate` + skipWaiting/clientsClaim
 * (nuxt.config), so a new build activates as soon as it is installed. This composable
 * closes the loop on the page side:
 *   1. it asks the browser to check for a new worker on load, when the tab becomes
 *      visible again and every 5 minutes (long events keep a tab open for days);
 *   2. when the new worker takes control, it reloads the page once, so the running tab
 *      picks up the new code instead of waiting for the operator to close it.
 *
 * Reloading is safe here because the console is online-first and every mutation is
 * already persisted server-side (scoring_live_*); the clock is rebuilt from the server
 * state on mount.
 *
 * Designed to be reusable as-is on app2 (same need on the public display side).
 */
export function usePwaUpdate(options: { autoReload?: boolean, checkIntervalMs?: number } = {}) {
  const { autoReload = true, checkIntervalMs = 5 * 60 * 1000 } = options

  /** True once a new version has taken control (exposed for an optional UI hint). */
  const updated = ref(false)

  let interval: ReturnType<typeof setInterval> | null = null
  let reloading = false

  const supported = (): boolean =>
    typeof window !== 'undefined' && 'serviceWorker' in navigator

  /** Ask the browser to fetch the worker script again (no-op when unchanged). */
  const checkForUpdate = async () => {
    if (!supported()) return
    const registration = await navigator.serviceWorker.getRegistration()
    await registration?.update()
  }

  const onControllerChange = () => {
    updated.value = true
    // `controllerchange` also fires on the very first registration; only reload when a
    // controller was already in place (i.e. this really is a version change).
    if (autoReload && !reloading) {
      reloading = true
      window.location.reload()
    }
  }

  const onVisible = () => {
    if (document.visibilityState === 'visible') void checkForUpdate()
  }

  onMounted(async () => {
    if (!supported()) return
    // A tab with no controller yet is a first load: register the listener only once a
    // controller exists, so the initial activation does not trigger a reload loop.
    if (navigator.serviceWorker.controller) {
      navigator.serviceWorker.addEventListener('controllerchange', onControllerChange)
    } else {
      await navigator.serviceWorker.ready
      navigator.serviceWorker.addEventListener('controllerchange', onControllerChange)
    }

    void checkForUpdate()
    document.addEventListener('visibilitychange', onVisible)
    interval = setInterval(() => void checkForUpdate(), checkIntervalMs)
  })

  onUnmounted(() => {
    if (!supported()) return
    navigator.serviceWorker.removeEventListener('controllerchange', onControllerChange)
    document.removeEventListener('visibilitychange', onVisible)
    if (interval !== null) clearInterval(interval)
  })

  return { updated, checkForUpdate }
}
