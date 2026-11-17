import type { ScoringEvent } from '~/types/scoring'
import { overtimeIndex } from './scoringRules'

/**
 * Order of the facts timeline (PAGE_SCORING.md §7.1). A fact's time is the time LEFT in its
 * period — the game clock counts down — so within a period the most recent fact has the
 * SMALLEST time. Presentation only: no api2 mirror.
 */

/** Tirs au but come after every overtime, however many there were. */
const SHOOTOUT_RANK = Number.MAX_SAFE_INTEGER

/** Chronological rank of a period: M1 < M2 < P1 < P2 < … (unbounded, spec §0.6) < TB. */
export function periodRank(period: string): number {
  if (period === 'TB') return SHOOTOUT_RANK
  const overtime = overtimeIndex(period)
  if (overtime !== null) return 2 + overtime
  return period === 'M2' ? 2 : 1
}

/** Seconds of a fact time, "MM:SS" or "00:MM:SS" (legacy TIME column). */
function timeToSeconds(time: string): number {
  const parts = (time || '0:0').split(':').map(Number)
  const [m, s] = parts.length >= 3 ? parts.slice(1) : parts
  return (m || 0) * 60 + (s || 0)
}

/** Most recent fact first: latest period first, then smallest time left. Returns a copy. */
export function timelineOrder(events: ScoringEvent[]): ScoringEvent[] {
  return [...events].sort((a, b) =>
    periodRank(b.period) - periodRank(a.period) || timeToSeconds(a.tpsJeu) - timeToSeconds(b.tpsJeu))
}

/** Match order, oldest fact first; facts at the same moment keep their entry order. Returns a copy. */
export function matchOrder(events: ScoringEvent[]): ScoringEvent[] {
  return [...events].sort((a, b) =>
    periodRank(a.period) - periodRank(b.period) || timeToSeconds(b.tpsJeu) - timeToSeconds(a.tpsJeu))
}

/**
 * Facts as displayed: the `limit` most recent ones (0 = all), newest at the top by default or
 * oldest at the top (the operator's choice). The « latest N » are always the most recent
 * facts, whichever way they are shown. Returns a copy.
 */
export function timelineView(
  events: ScoringEvent[],
  { newestFirst = true, limit = 0 }: { newestFirst?: boolean, limit?: number } = {}
): ScoringEvent[] {
  const ordered = timelineOrder(events)
  const shown = limit > 0 ? ordered.slice(0, limit) : ordered
  return newestFirst ? shown : shown.reverse()
}
