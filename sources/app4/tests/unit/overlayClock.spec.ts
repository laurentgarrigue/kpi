import { describe, expect, it } from 'vitest'
import { remainingMs, runningDriftMs } from '../../utils/overlayClock'

// Overlay clock interpolation (PAGE_INCRUSTATION.md): 4-value model + absolute start instant.
describe('overlay clock remaining time', () => {
  const started = Date.UTC(2026, 10, 9, 12, 40, 0) // 13:40 in Paris, 12:40 UTC

  it('a stopped clock shows init minus elapsed', () => {
    expect(remainingMs({ initMs: 600_000, elapsedMs: 68_918, running: false, startedAtMs: null }, started)).toBe(531_082)
  })

  it('a running clock counts down from its absolute start instant, whatever the local timezone', () => {
    const clock = { initMs: 600_000, elapsedMs: 60_000, running: true, startedAtMs: started }
    expect(remainingMs(clock, started + 5_000)).toBe(535_000)
  })

  it('never goes below zero', () => {
    const clock = { initMs: 40_000, elapsedMs: 0, running: true, startedAtMs: started }
    expect(remainingMs(clock, started + 60_000)).toBe(0)
  })

  it('a running clock without start instant is shown frozen rather than wrong', () => {
    expect(remainingMs({ initMs: 40_000, elapsedMs: 10_000, running: true, startedAtMs: null }, started)).toBe(30_000)
  })
})

// Console Scoring reload (issue #401): a running clock is restored from its absolute start
// instant, not from the elapsed time persisted when it was started.
describe('running clock drift since its start instant', () => {
  const started = Date.UTC(2026, 10, 17, 14, 0, 0)

  it('a 60 s shotclock started 25 s ago has drifted 25 s (→ 35 s left)', () => {
    const clock = { initMs: 60_000, elapsedMs: 0, running: true, startedAtMs: started }
    expect(runningDriftMs(clock, started + 25_000)).toBe(25_000)
    expect(remainingMs(clock, started + 25_000)).toBe(35_000)
  })

  it('a suspended clock has no drift: its persisted value is kept', () => {
    expect(runningDriftMs({ initMs: 60_000, elapsedMs: 22_000, running: false, startedAtMs: null }, started)).toBe(0)
  })

  it('a running clock without start instant has no drift', () => {
    expect(runningDriftMs({ initMs: 60_000, elapsedMs: 0, running: true, startedAtMs: null }, started)).toBe(0)
  })

  it('a browser clock behind the server never adds time back', () => {
    const clock = { initMs: 60_000, elapsedMs: 0, running: true, startedAtMs: started }
    expect(runningDriftMs(clock, started - 500)).toBe(0)
    expect(remainingMs(clock, started - 500)).toBe(60_000)
  })
})
