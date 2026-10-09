import type { Gameday } from './types'

export interface GamedayGroup {
  /** Gamedays sharing every parameter, in api2 order. */
  gamedays: Gameday[]
  /** Their distinct phase names, in order. */
  phases: string[]
}

/** Everything a gameday card shows besides its phase: two gamedays with the same key are one card. */
function parametersKey(gameday: Gameday): string {
  return JSON.stringify([gameday.start, gameday.end, gameday.place, gameday.department, gameday.organizer, gameday.officials])
}

/**
 * Gamedays grouped by identical parameters (dates, place, organizer, officials): the phases of a cup all share
 * them, so they are shown once instead of repeated (CMP-08). A championship, whose gamedays differ, keeps one
 * group per gameday.
 */
export function groupGamedays(gamedays: readonly Gameday[]): GamedayGroup[] {
  const groups = new Map<string, GamedayGroup>()
  for (const gameday of gamedays) {
    const key = parametersKey(gameday)
    const group = groups.get(key) ?? { gamedays: [], phases: [] }
    group.gamedays.push(gameday)
    const phase = gameday.phase || gameday.name
    if (phase && !group.phases.includes(phase)) {
      group.phases.push(phase)
    }
    groups.set(key, group)
  }
  return [...groups.values()]
}
