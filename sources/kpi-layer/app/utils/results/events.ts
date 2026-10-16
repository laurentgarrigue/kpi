import type { LinkedEvent } from './types'

/** A competition « belongs » to an event covering almost all its gamedays (PAGE_COMPETITION.md § 2.3). */
export const MAIN_EVENT_MIN_SHARE = 0.75

/** A group « belongs » to an event covering all its gamedays (PAGE_COMPETITIONS.md § 2.4). */
export const GROUP_EVENT_MIN_SHARE = 1

/** Event to put forward: the one of the `event` parameter, otherwise the first one covering enough gamedays. */
export function mainEvent(events: readonly LinkedEvent[], minShare: number, forcedId?: number): LinkedEvent | null {
  const forced = forcedId === undefined ? undefined : events.find(event => event.id === forcedId)
  if (forced) {
    return forced
  }
  const first = events[0]
  return first && first.share >= minShare ? first : null
}
