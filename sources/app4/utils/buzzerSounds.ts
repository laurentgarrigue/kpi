/**
 * The two buzzer signals of the scoring console (PAGE_SCORING.md §0.9).
 *
 * The rules require the shot clock signal to differ from the main signal (period ends, end
 * of break): it plays the same recording, sped up — so higher — and cut short.
 * Both have a synthesized stand-in for when the recording is not loaded yet.
 */
export interface BuzzerSound {
  /** Speed of the recording: above 1 = higher pitch (and shorter). */
  playbackRate: number
  /** Cut after this many ms of playback (with a short fade); null = play in full. */
  maxMs: number | null
  /** Synthesized square-wave stand-in. */
  fallbackHz: number
  fallbackMs: number
}

export type BuzzerKind = 'main' | 'shotclock'

export const BUZZER_SOUNDS: Record<BuzzerKind, BuzzerSound> = {
  main: { playbackRate: 1, maxMs: null, fallbackHz: 660, fallbackMs: 900 },
  // ×1.6 ≈ 8 semitones up; 0.5 s against 1.07 s for the main buzzer.
  shotclock: { playbackRate: 1.6, maxMs: 500, fallbackHz: 1056, fallbackMs: 400 },
}

/** How long a sound actually plays, given the recording's length at normal speed. */
export function playedDurationMs(sound: BuzzerSound, recordingMs: number): number {
  const full = recordingMs / sound.playbackRate
  return sound.maxMs === null ? full : Math.min(full, sound.maxMs)
}
