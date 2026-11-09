import { describe, expect, it } from 'vitest'
import { BUZZER_SOUNDS, playedDurationMs } from '../../utils/buzzerSounds'

/** Length of public/sounds/buzzer.mp3 (legacy online score sheet buzzer). */
const BUZZER_FILE_MS = 1070

// PAGE_SCORING.md §0.9 « Buzzer du chronomètre de tir »: the rules require the shot clock signal
// to differ from the main signal (period ends) — higher and shorter.
describe('buzzer sounds', () => {
  const { main, shotclock } = BUZZER_SOUNDS

  it('plays the main buzzer as recorded, in full', () => {
    expect(main.playbackRate).toBe(1)
    expect(playedDurationMs(main, BUZZER_FILE_MS)).toBe(BUZZER_FILE_MS)
  })

  it('plays the shot clock buzzer higher than the main one', () => {
    expect(shotclock.playbackRate).toBeGreaterThan(main.playbackRate)
    expect(shotclock.fallbackHz).toBeGreaterThan(main.fallbackHz)
  })

  it('plays the shot clock buzzer clearly shorter than the main one', () => {
    expect(playedDurationMs(shotclock, BUZZER_FILE_MS)).toBeLessThanOrEqual(BUZZER_FILE_MS / 2)
    expect(shotclock.fallbackMs).toBeLessThanOrEqual(main.fallbackMs / 2)
  })

  it('never plays longer than the sped-up recording itself', () => {
    expect(playedDurationMs(shotclock, 200)).toBeCloseTo(200 / shotclock.playbackRate)
  })
})
