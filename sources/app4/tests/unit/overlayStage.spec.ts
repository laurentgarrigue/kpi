import { describe, expect, it } from 'vitest'
import { STAGE_HEIGHT, STAGE_WIDTH, stageScale } from '../../utils/overlayStage'

// PAGE_INCRUSTATION.md: the overlay is authored at 1920×1080 and fitted to the browser source.
describe('overlay stage scale', () => {
  it('is 1 at broadcast resolution', () => {
    expect(stageScale(STAGE_WIDTH, STAGE_HEIGHT)).toBe(1)
  })

  it('shrinks to fit a smaller 16:9 source', () => {
    expect(stageScale(1280, 720)).toBeCloseTo(2 / 3)
  })

  it('fits the limiting side when the ratio differs (letterbox)', () => {
    expect(stageScale(1920, 905)).toBeCloseTo(905 / 1080)
    expect(stageScale(960, 1080)).toBeCloseTo(0.5)
  })

  it('never returns zero or a negative scale for an empty viewport', () => {
    expect(stageScale(0, 0)).toBeGreaterThan(0)
  })
})
