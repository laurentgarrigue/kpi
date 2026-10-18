// Pure helpers of the games page « Pitches » view (PAGE_MATCHS.md § 2.7) and of the pitch
// double-booking check of the planning bar (§ 2.6): no Nuxt, no state, unit-tested.

/** Minimum gap (minutes) between the start times of two games on the same pitch, by default. */
export const DEFAULT_PITCH_MIN_INTERVAL = 30

/** Fields of a game this module reads (structurally compatible with `Game`). */
export interface PitchGame {
  id: number
  dateMatch: string | null
  heureMatch: string | null
  terrain: string | null
}

export interface PitchRow<G> {
  time: string
  /** Games of the slot, per pitch (several games = double-booked slot). */
  cells: Record<string, G[]>
}

export interface PitchDay<G> {
  date: string
  pitches: string[]
  rows: PitchRow<G>[]
}

/** "HH:MM[:SS]" → "HH:MM", or null when not a time. */
export function normalizeTime(time: string | null): string | null {
  const match = /^(\d{1,2}):(\d{2})/.exec(time ?? '')
  return match ? `${match[1]!.padStart(2, '0')}:${match[2]}` : null
}

function toMinutes(time: string): number {
  const [hours, minutes] = time.split(':').map(Number)
  return hours! * 60 + minutes!
}

/** Pitch key: games on « 1 » and « 1 » are on the same pitch; an empty pitch stays empty. */
function pitchKey(pitch: string | null): string {
  return (pitch ?? '').trim()
}

/** Numeric pitches first in numeric order, then the others alphabetically, the unassigned one last. */
export function comparePitches(a: string, b: string): number {
  if (a === '' || b === '') return a === b ? 0 : a === '' ? 1 : -1
  const [na, nb] = [Number(a), Number(b)]
  const [ia, ib] = [Number.isFinite(na), Number.isFinite(nb)]
  if (ia && ib) return na - nb
  if (ia !== ib) return ia ? -1 : 1
  return a.localeCompare(b)
}

/**
 * Ids of the games whose pitch is double-booked: on the same date and pitch, two consecutive
 * games start less than `minInterval` minutes apart (a same-slot clash is the 0-minute case).
 * Games without a pitch, a date or a time are ignored.
 */
export function pitchConflictIds(games: readonly PitchGame[], minInterval = DEFAULT_PITCH_MIN_INTERVAL): Set<number> {
  const groups = new Map<string, { id: number, minutes: number }[]>()
  for (const game of games) {
    const pitch = pitchKey(game.terrain).toLowerCase()
    const time = normalizeTime(game.heureMatch)
    if (!pitch || !game.dateMatch || !time) continue
    const key = `${game.dateMatch}|${pitch}`
    groups.set(key, [...(groups.get(key) ?? []), { id: game.id, minutes: toMinutes(time) }])
  }
  const conflicted = new Set<number>()
  for (const list of groups.values()) {
    list.sort((a, b) => a.minutes - b.minutes)
    for (let i = 1; i < list.length; i++) {
      if (list[i]!.minutes - list[i - 1]!.minutes < minInterval) {
        conflicted.add(list[i - 1]!.id)
        conflicted.add(list[i]!.id)
      }
    }
  }
  return conflicted
}

/**
 * One time × pitch grid per date, dates and times in chronological order. Games without a date
 * are grouped under the '' date, games without a time under the '' row (shown last).
 */
export function pitchDays<G extends PitchGame>(games: readonly G[]): PitchDay<G>[] {
  const byDate = new Map<string, G[]>()
  for (const game of games) {
    const date = game.dateMatch ?? ''
    byDate.set(date, [...(byDate.get(date) ?? []), game])
  }
  const lastWhenEmpty = (a: string, b: string) => (a === '' || b === '') ? (a === '' ? 1 : 0) - (b === '' ? 1 : 0) : a.localeCompare(b)
  return [...byDate.keys()].sort(lastWhenEmpty).map((date) => {
    const dayGames = byDate.get(date)!
    const pitches = [...new Set(dayGames.map(game => pitchKey(game.terrain)))].sort(comparePitches)
    const rows = new Map<string, Record<string, G[]>>()
    for (const game of dayGames) {
      const time = normalizeTime(game.heureMatch) ?? ''
      const cells = rows.get(time) ?? {}
      const pitch = pitchKey(game.terrain)
      cells[pitch] = [...(cells[pitch] ?? []), game]
      rows.set(time, cells)
    }
    return {
      date,
      pitches,
      rows: [...rows.keys()].sort(lastWhenEmpty).map(time => ({ time, cells: rows.get(time)! })),
    }
  })
}
