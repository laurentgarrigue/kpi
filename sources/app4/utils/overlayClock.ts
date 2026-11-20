/** The part of a live clock needed to know its remaining time (4-value model, plan §3.1). */
export interface ClockTiming {
  initMs: number
  elapsedMs: number
  running: boolean
  /** Absolute start instant (epoch ms) shipped by api2 — never a naive local datetime. */
  startedAtMs: number | null
}

/**
 * Milliseconds a clock has run since its absolute start instant — what its persisted
 * `elapsedMs` (written at start) does not include yet. Zero when it is not running, has no
 * start instant, or the local clock is behind the server's (never adds time back).
 */
export function runningDriftMs(clock: ClockTiming, nowMs: number): number {
  if (!clock.running || clock.startedAtMs === null) return 0
  return Math.max(0, nowMs - clock.startedAtMs)
}

/**
 * Remaining milliseconds of a clock at `nowMs`. Based on the absolute start instant only:
 * parsing the stored `startedAt` string as local time shifted every running clock by the
 * timezone offset (UTC server, Paris browser → 1 h → 00:00 on screen).
 */
export function remainingMs(clock: ClockTiming, nowMs: number): number {
  return Math.max(0, clock.initMs - clock.elapsedMs - runningDriftMs(clock, nowMs))
}
