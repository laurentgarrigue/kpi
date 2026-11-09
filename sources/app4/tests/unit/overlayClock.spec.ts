import { describe, expect, it } from 'vitest'
import { remainingMs } from '../../utils/overlayClock'

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
