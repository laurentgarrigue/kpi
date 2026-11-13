import { needsRefresh, readJwtLifetime } from '~/utils/session'

/**
 * Proactive session renewal for every profile (AUTH_HARDENING.md § 3ter, AUTH-SC-02).
 *
 * The check is cheap (it decodes the JWT) and runs every minute; browsers slow down timers in background tabs and
 * stop them during sleep, so the check also runs when the tab comes back to the foreground, when the network comes
 * back and when the window gets the focus. A failed proactive refresh never logs out (AUTH-SC-08): the next api2
 * request settles it.
 */
const CHECK_INTERVAL_MS = 60_000

export default defineNuxtPlugin(() => {
  const authStore = useAuthStore()
  const { refreshToken } = useAuth()

  let running = false
  /** The server refused to renew this token: wait for a new one instead of asking again every minute. */
  let deniedToken: string | null = null

  const refreshIfDue = async () => {
    if (running || !authStore.isAuthenticated || !authStore.token || authStore.token === deniedToken) return
    if (!needsRefresh(readJwtLifetime(authStore.token), Date.now())) return

    running = true
    const tokenBefore = authStore.token
    try {
      if (await refreshToken() === 'denied') deniedToken = tokenBefore
    } finally {
      running = false
    }
  }

  setInterval(refreshIfDue, CHECK_INTERVAL_MS)
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') refreshIfDue()
  })
  window.addEventListener('online', refreshIfDue)
  window.addEventListener('focus', refreshIfDue)
  watch(() => authStore.isAuthenticated, refreshIfDue, { immediate: true })
})
