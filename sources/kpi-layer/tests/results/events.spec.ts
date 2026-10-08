import { describe, expect, it } from 'vitest'
import { GROUP_EVENT_MIN_SHARE, MAIN_EVENT_MIN_SHARE, mainEvent } from '../../app/utils/results/events'
import type { LinkedEvent } from '../../app/utils/results/types'

const event = (id: number, share: number): LinkedEvent => ({
  id, libelle: `E${id}`, place: null, start: null, end: null, logo: null, share,
})

describe('mainEvent', () => {
  it('CMP-16: the first event when it covers at least 75 % of the gamedays', () => {
    expect(mainEvent([event(1, 0.8), event(2, 0.2)], MAIN_EVENT_MIN_SHARE)?.id).toBe(1)
    expect(mainEvent([event(1, 0.5)], MAIN_EVENT_MIN_SHARE)).toBeNull()
    expect(mainEvent([], MAIN_EVENT_MIN_SHARE)).toBeNull()
  })

  it('CPL-11: a group needs an event covering all its gamedays', () => {
    expect(mainEvent([event(1, 0.9)], GROUP_EVENT_MIN_SHARE)).toBeNull()
    expect(mainEvent([event(1, 1)], GROUP_EVENT_MIN_SHARE)?.id).toBe(1)
  })

  it('CMP-16: the event of the `event` parameter wins whatever its share', () => {
    expect(mainEvent([event(1, 0.9), event(2, 0.1)], MAIN_EVENT_MIN_SHARE, 2)?.id).toBe(2)
  })
})
