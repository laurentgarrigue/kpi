import { describe, expect, it } from 'vitest'
import { scrollArrows } from '../../app/utils/scroll'

describe('scrollArrows', () => {
  it('LAY-11: at the top of a long page, only « go to bottom » is offered', () => {
    expect(scrollArrows(0, 3000, 800)).toEqual({ up: false, down: true })
  })

  it('LAY-11: in the middle, both arrows; at the bottom, only « back to top »', () => {
    expect(scrollArrows(1000, 3000, 800)).toEqual({ up: true, down: true })
    expect(scrollArrows(2200, 3000, 800)).toEqual({ up: true, down: false })
  })

  it('LAY-11: a page shorter than the window offers no arrow', () => {
    expect(scrollArrows(0, 700, 800)).toEqual({ up: false, down: false })
  })
})
