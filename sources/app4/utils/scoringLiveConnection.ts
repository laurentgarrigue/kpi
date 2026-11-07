/**
 * When to tell the operator that the console lost its real-time subscription
 * (PAGE_SCORING.md, UX review « Clarté », revised 2026-11-07).
 *
 * The Mercure hub recycles every SSE subscription every ~8–10 min (clean close); the browser
 * re-subscribes ~5 s later and the hub replays what was missed (Last-Event-ID). That gap is
 * normal: only an outage still unresolved after the grace delay deserves the red banner.
 */
export const LIVE_RECONNECT_GRACE_MS = 10_000

export function createLiveConnectionWatch(
  onLostChange: (lost: boolean) => void,
  graceMs: number = LIVE_RECONNECT_GRACE_MS,
) {
  let graceTimer: ReturnType<typeof setTimeout> | null = null
  let lost = false
  let hasOpened = false
  let erroredSinceOpen = false

  const clearGrace = () => {
    if (graceTimer !== null) {
      clearTimeout(graceTimer)
      graceTimer = null
    }
  }

  return {
    /** The subscription failed (the browser fires this on every retry): start the grace delay once. */
    error() {
      erroredSinceOpen = true
      if (graceTimer !== null || lost) return
      graceTimer = setTimeout(() => {
        graceTimer = null
        lost = true
        onLostChange(true)
      }, graceMs)
    },

    /** The subscription is (re)established. `reconnected` = not the first one: state may be stale. */
    open(): { reconnected: boolean } {
      clearGrace()
      if (lost) {
        lost = false
        onLostChange(false)
      }
      const reconnected = hasOpened || erroredSinceOpen
      hasOpened = true
      erroredSinceOpen = false
      return { reconnected }
    },

    dispose() {
      clearGrace()
    },
  }
}
