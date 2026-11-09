/** Broadcast resolution the overlay is authored at (PAGE_INCRUSTATION.md). */
export const STAGE_WIDTH = 1920
export const STAGE_HEIGHT = 1080

/** Smallest scale accepted, so an empty viewport (hidden source) never collapses the stage. */
const MIN_SCALE = 0.01

/**
 * Scale fitting the 1920×1080 stage into a viewport, letterboxed on the limiting side.
 * Computed in JS on purpose: CSS cannot divide a length by a length in the Chromium
 * shipped with OBS, and `scale(100vw / 1920)` is an invalid declaration (stage off-screen).
 */
export function stageScale(viewportWidth: number, viewportHeight: number): number {
  return Math.max(MIN_SCALE, Math.min(viewportWidth / STAGE_WIDTH, viewportHeight / STAGE_HEIGHT))
}
