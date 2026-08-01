import type { ShotclockState } from '~/utils/scoringRules'

/**
 * Shotclock (chronomètre de tir) — the 3-command model of spec §6.5 (decision 2026-07-27):
 *
 *  - start(seconds): load AND run — the start IS a reset (60 s engagement / 40 s rebound),
 *    independent from the game clock;
 *  - stopToIdle(): back to the initial state, displayed "--" (this is NOT a pause);
 *  - suspend()/resume(): the ONLY pause, automatic, driven by the game clock (the page
 *    watches the game clock's running state).
 *
 * At zero the countdown freezes at 0 (state stays RUNNING), `onExpired` fires once
 * (buzzer); the operator then starts a new possession or stops the clock.
 *
 * Timing is timestamp-based (no drift): while RUNNING we keep the epoch instant of
 * expiry; the 100 ms interval only refreshes the display. State transitions mirror
 * utils/scoringRules.shotclockTransition (tested reference: api2 ScoringRules).
 */
export function useShotclock(options: { onExpired?: () => void } = {}) {
  const state = ref<ShotclockState>('IDLE')
  /** Remaining milliseconds (meaningful when state !== 'IDLE'). */
  const remainingMs = ref(0)
  /** Duration loaded by the last start (60 or 40 s) — persisted as init_ms. */
  const initSeconds = ref(60)

  let endAt = 0
  let intervalId: ReturnType<typeof setInterval> | null = null
  let expiredFired = false

  const clearTick = () => {
    if (intervalId !== null) {
      clearInterval(intervalId)
      intervalId = null
    }
  }

  const tick = () => {
    const left = Math.max(0, endAt - Date.now())
    remainingMs.value = left
    // TODO(temporaire) — trace dixièmes chronomètre de tir, à retirer après validation 1.2
    console.log(
      `[shotclock] ${display.value}  restant=${(left / 1000).toFixed(1)}s  ` +
      `elapsed=${elapsedSeconds.value.toFixed(1)}s  → elapsed_ms=${Math.round(elapsedSeconds.value * 1000)}  ` +
      `state=${state.value}`
    )
    if (left <= 0) {
      clearTick()
      if (!expiredFired) {
        expiredFired = true
        options.onExpired?.()
      }
    }
  }

  const startTick = () => {
    clearTick()
    intervalId = setInterval(tick, 100)
    tick()
  }

  /**
   * Displayed value (spec §6.5 — decision 2026-07-29):
   *  - "--" when idle;
   *  - whole seconds, rounded up, from 60 down to 10 (max width: 2 chars, "60");
   *  - **tenths under 10 s** ("8.8"), as scoreboards do for the last seconds.
   * The 100 ms tick gives exactly the resolution the tenths need.
   */
  const display = computed(() => {
    if (state.value === 'IDLE') return '--'
    const ms = remainingMs.value
    if (ms >= 10_000) return String(Math.ceil(ms / 1000))
    return (Math.floor(ms / 100) / 10).toFixed(1)
  })

  const isRunning = computed(() => state.value === 'RUNNING')

  /** Elapsed seconds since the last start — what the backend persists (elapsed_ms). */
  const elapsedSeconds = computed(() =>
    Math.max(0, initSeconds.value - remainingMs.value / 1000)
  )

  /** Start/reset: load `seconds` and run (legal from any state — 60 s and 40 s share the
   * same transition, only the loaded duration differs). */
  const start = (seconds: number) => {
    initSeconds.value = seconds
    remainingMs.value = seconds * 1000
    endAt = Date.now() + seconds * 1000
    expiredFired = false
    state.value = shotclockTransition(state.value, 'start60')
    startTick()
  }

  /** Arrêt: back to IDLE ("--"), waiting for a new start — the countdown is discarded. */
  const stopToIdle = () => {
    state.value = shotclockTransition(state.value, 'stop')
    remainingMs.value = 0
    clearTick()
  }

  /** Game clock stopped → automatic suspension (the only pause). */
  const suspend = () => {
    if (state.value !== 'RUNNING') return
    remainingMs.value = Math.max(0, endAt - Date.now())
    state.value = shotclockTransition(state.value, 'gameClockStopped')
    clearTick()
  }

  /** Game clock restarted → automatic resume of a suspended countdown. */
  const resume = () => {
    if (state.value !== 'SUSPENDED') return
    state.value = shotclockTransition(state.value, 'gameClockStarted')
    endAt = Date.now() + remainingMs.value
    expiredFired = remainingMs.value <= 0
    startTick()
  }

  /** ±1 s fine adjust (spec §6.5: only while the game clock is stopped → never RUNNING). */
  const adjust = (deltaSeconds: number) => {
    if (state.value !== 'SUSPENDED') return
    remainingMs.value = Math.min(
      initSeconds.value * 1000,
      Math.max(0, remainingMs.value + deltaSeconds * 1000)
    )
  }

  /** Rebuild from the persisted live clock (GET /state → kind SHOTCLOCK). */
  const restore = (clock: {
    initMs: number
    elapsedMs: number
    running: boolean
    /** Extra elapsed ms accumulated server-side while running (derived by the caller). */
    driftMs?: number
  }) => {
    initSeconds.value = Math.round(clock.initMs / 1000)
    const left = Math.max(0, clock.initMs - clock.elapsedMs - (clock.running ? (clock.driftMs ?? 0) : 0))
    remainingMs.value = left
    expiredFired = left <= 0
    if (clock.running) {
      state.value = 'RUNNING'
      endAt = Date.now() + left
      startTick()
    } else {
      state.value = 'SUSPENDED'
      clearTick()
    }
  }

  onUnmounted(clearTick)

  return { state, display, isRunning, remainingMs, initSeconds, elapsedSeconds, start, stopToIdle, suspend, resume, adjust, restore }
}
