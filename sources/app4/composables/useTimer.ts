import { ref, computed, onUnmounted, type Ref } from 'vue'

/**
 * Match countdown timer (game clock) — timestamp-based, like useShotclock/usePenalties.
 *
 * Semantics (simpler than the legacy fm3 encoding — see DOC/specs/PAGE_SCORING.md):
 * - `maxTime`  = period duration in seconds (the countdown starts from here)
 * - `elapsed`  = seconds already played
 * - the timer counts DOWN from (maxTime - elapsed)
 *
 * Why not easytimer.js (removed 2026-08-02): its `startValues` only accepts whole seconds
 * AND its tick overwrites our remaining time with its own rounded value. A clock stopped at
 * 09:06.5 therefore restarted at 09:07.0, and that corrupted value was persisted on the next
 * stop — so the tenths were lost again at every stop/restart cycle. Here the truth is an
 * epoch instant (`endAt`); the interval only refreshes the display, so no rounding can ever
 * feed back into the state. Same model as the shotclock and the penalties, which is also
 * what the overlay (useInterpolatedClock) does — the three clocks now share one principle.
 *
 * Server persistence (scoring_live_clock kind GAME, via /admin/scoring/gameTimer):
 * - on run/stop we send { action, startTime: elapsedPrecise, runTime: <unused>, maxTime }
 * - on reload, restoreFromServer() rebuilds the running clock from the persisted state:
 *   if running, realElapsed = elapsed + (nowServer - startTimeServer)
 *
 * The composable is display-oriented; persistence calls live in the page/store so the
 * timer stays a pure UI concern.
 */

export interface TimerOptions {
  /** Called whenever the displayed time changes (for broadcast/scoreboard in Phase 2) */
  onTick?: (display: string) => void
  /** Called once when the countdown reaches zero */
  onTargetReached?: () => void
}

const pad = (n: number): string => (n < 10 ? '0' + n : String(n))

export function useTimer(options: TimerOptions = {}) {
  const isRunning = ref(false)
  /** Remaining milliseconds — the single source of truth for the display. */
  const remainingMs = ref(0)
  /** Total period duration in seconds. */
  const maxTime = ref(0)

  /** Epoch instant of expiry while running; only meaningful when `isRunning`. */
  let endAt = 0
  let intervalId: ReturnType<typeof setInterval> | null = null
  let targetFired = false

  const clearTick = () => {
    if (intervalId !== null) {
      clearInterval(intervalId)
      intervalId = null
    }
  }

  /** Remaining tenths, for display and for the value we persist. */
  const remainingTenths = computed(() => Math.round(remainingMs.value / 100))

  /** "MM:SS.d" display string */
  const display = computed(() => {
    const totalTenths = remainingTenths.value
    const minutes = Math.floor(totalTenths / 600)
    const seconds = Math.floor((totalTenths % 600) / 10)
    const tenths = totalTenths % 10
    return `${pad(minutes)}:${pad(seconds)}.${tenths}`
  })

  /** "MM:SS" — used to timestamp events */
  const gameTime = computed(() => {
    const totalSeconds = Math.ceil(remainingMs.value / 1000)
    const minutes = Math.floor(totalSeconds / 60)
    const seconds = totalSeconds % 60
    return `${pad(minutes)}:${pad(seconds)}`
  })

  /**
   * Whole seconds already elapsed in the current period. Ceil-rounded on purpose: this is
   * what timestamps the facts (`gameTime`) and drives the legacy `shotClockShow` masking
   * rule — both reason in whole seconds.
   */
  const elapsed = computed(() => maxTime.value - Math.ceil(remainingMs.value / 1000))

  /**
   * Same value at full millisecond resolution — this is what gets persisted to
   * `scoring_live_clock.elapsed_ms`. Kept separate from `elapsed` so the fact timestamps
   * and the masking rule keep their whole-second semantics.
   */
  const elapsedPrecise = computed(() => maxTime.value - remainingMs.value / 1000)

  const tick = () => {
    remainingMs.value = Math.max(0, endAt - Date.now())
    options.onTick?.(display.value)
    if (remainingMs.value <= 0) {
      clearTick()
      isRunning.value = false
      if (!targetFired) {
        targetFired = true
        options.onTargetReached?.()
      }
    }
  }

  const startTick = () => {
    clearTick()
    intervalId = setInterval(tick, 100)
    tick()
  }

  /**
   * Configure the countdown for a period without starting it.
   * `elapsedSeconds` may carry any sub-second part (restore from the live state): it is
   * kept as-is — nothing here rounds it.
   */
  const setPeriod = (durationSeconds: number, elapsedSeconds = 0) => {
    maxTime.value = durationSeconds
    clearTick()
    isRunning.value = false
    remainingMs.value = Math.max(0, Math.round((durationSeconds - elapsedSeconds) * 1000))
    targetFired = remainingMs.value <= 0
    options.onTick?.(display.value)
  }

  const start = () => {
    if (isRunning.value || remainingMs.value <= 0) return
    // The remaining time is carried over exactly: no rounding on the restart path (this is
    // precisely what easytimer got wrong — see the header note).
    endAt = Date.now() + remainingMs.value
    targetFired = false
    isRunning.value = true
    startTick()
  }

  const stop = () => {
    if (isRunning.value) remainingMs.value = Math.max(0, endAt - Date.now())
    clearTick()
    isRunning.value = false
    options.onTick?.(display.value)
  }

  const reset = () => {
    clearTick()
    isRunning.value = false
    remainingMs.value = maxTime.value * 1000
    targetFired = remainingMs.value <= 0
    options.onTick?.(display.value)
  }

  /**
   * Rebuild the clock from the persisted live state (scoring_live_clock, kind GAME).
   * All the time values carry tenths (the server serialises `elapsed_ms`/`init_ms` and the
   * `datetime(3)` start instant as floats), so the drift compensation stays sub-second.
   * @param state.action       'run' | 'stop'
   * @param state.maxTime      period duration (seconds, may carry tenths)
   * @param state.elapsed      elapsed seconds at the last run/stop (may carry tenths)
   * @param state.startTimeServer  server time (seconds % 86400, with ms) at the last 'run'
   * @param state.nowServer    current server time (seconds % 86400, with ms)
   */
  const restoreFromServer = (state: {
    action: 'run' | 'stop'
    maxTime: number
    elapsed: number
    startTimeServer?: number
    nowServer?: number
  }) => {
    let realElapsed = state.elapsed
    if (state.action === 'run' && state.startTimeServer != null && state.nowServer != null) {
      let delta = state.nowServer - state.startTimeServer
      if (delta < 0) delta += 86400 // crossed midnight
      realElapsed = state.elapsed + delta
    }
    setPeriod(state.maxTime, realElapsed)
    if (state.action === 'run') start()
  }

  onUnmounted(clearTick)

  return {
    isRunning: isRunning as Ref<boolean>,
    display,
    gameTime,
    elapsed,
    elapsedPrecise,
    maxTime,
    setPeriod,
    start,
    stop,
    reset,
    restoreFromServer
  }
}
