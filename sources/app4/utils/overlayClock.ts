/** The part of a live clock needed to know its remaining time (4-value model, plan §3.1). */
export interface ClockTiming {
  initMs: number
  elapsedMs: number
  running: boolean
  /** Absolute start instant (epoch ms) shipped by api2 — never a naive local datetime. */
  startedAtMs: number | null
}

/**
 * Remaining milliseconds of a clock at `nowMs`. Based on the absolute start instant only:
 * parsing the stored `startedAt` string as local time shifted every running clock by the
 * timezone offset (UTC server, Paris browser → 1 h → 00:00 on screen).
 */
export function remainingMs(clock: ClockTiming, nowMs: number): number {
  const base = clock.initMs - clock.elapsedMs
  if (!clock.running || clock.startedAtMs === null) return Math.max(0, base)
  return Math.max(0, base - (nowMs - clock.startedAtMs))
}
