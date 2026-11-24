/**
 * How the console and the scoreboard show their clocks (PAGE_SCORING.md §6.5). Pure
 * presentation. `formatGameClock` has a PHP twin, `ScoringRules::clockDisplay` (api2), used
 * to journal the clock writes — change both together.
 */

/** Below this remaining time, clocks switch to tenths (as scoreboards do for the end). */
const TENTHS_BELOW_MS = 10_000

/**
 * Game clock as shown to people: "9:52" (no leading zero on the minutes, whole seconds
 * rounded up so a countdown never reaches 0 early), then "9.9" … "0.0" under 10 seconds.
 */
export function formatGameClock(remainingMs: number): string {
  const ms = Math.max(0, remainingMs)
  if (ms < TENTHS_BELOW_MS) return (Math.floor(ms / 100) / 10).toFixed(1)
  const totalSeconds = Math.ceil(ms / 1000)
  const minutes = Math.floor(totalSeconds / 60)
  const seconds = totalSeconds % 60
  return `${minutes}:${String(seconds).padStart(2, '0')}`
}

/** Finest step a clock display changes on (tenths under 10 s; whole seconds are multiples of it). */
const DISPLAY_STEP_MS = 100

/**
 * Milliseconds until a counting-down clock crosses its next display boundary (+1 ms to be
 * strictly past it). Console clocks schedule their refresh on it, so their digits flip at
 * the same instant as the overlay's (which repaints every frame) — a free-running 100 ms
 * interval showed each change up to 100 ms late.
 */
export function msUntilDisplayChange(remainingMs: number): number {
  return (Math.max(0, remainingMs) % DISPLAY_STEP_MS) + 1
}

/**
 * ±1 s on the shot clock lands on a whole second: the operator corrects what they read,
 * so the base is the displayed second (rounded up above 10 s, nearest under 10 s where
 * tenths are shown), clamped between 0 and the loaded duration.
 */
export function snapShotclockAdjust(remainingMs: number, deltaSeconds: number, maxMs: number): number {
  const shownSeconds = remainingMs >= TENTHS_BELOW_MS
    ? Math.ceil(remainingMs / 1000)
    : Math.round(remainingMs / 1000)
  return Math.min(maxMs, Math.max(0, (shownSeconds + deltaSeconds) * 1000))
}
