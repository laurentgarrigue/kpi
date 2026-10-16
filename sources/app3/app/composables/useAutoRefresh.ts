import { useDocumentVisibility, useIntervalFn } from '@vueuse/core'

/** Results refresh period while a game is on or about to start (PAGE_COMPETITION.md § 2.4, CMP-13). */
export const RESULTS_REFRESH_MS = 60_000

/** Calls `refresh` every minute while `active()` is true and the browser tab is visible. */
export function useAutoRefresh(active: () => boolean, refresh: () => unknown) {
  const visibility = useDocumentVisibility()
  const { pause, resume } = useIntervalFn(refresh, RESULTS_REFRESH_MS, { immediate: false })

  watchEffect(() => {
    if (import.meta.client && active() && visibility.value === 'visible') {
      resume()
    } else {
      pause()
    }
  })
}
