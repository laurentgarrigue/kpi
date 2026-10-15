export type TeamSide = 'A' | 'B'

export interface GameEvent {
  e_id: number | string
  e_team: TeamSide
  e_type: string
  e_period: string | null
  e_time: string | number | null
  e_number: string | number | null
  e_name: string | null
  e_firstname: string | null
}

export interface TimelineSegment {
  period: string
  /** Left offset and width, in % of the whole bar */
  left: number
  width: number
}

export interface TimelineMarker {
  event: GameEvent
  /** Position, in % of the whole bar */
  position: number
  /** 0 = closest to the line; higher lanes stack away from it when markers overlap */
  lane: number
}

export interface HorizontalTimeline {
  segments: TimelineSegment[]
  markers: TimelineMarker[]
  laneCount: Record<TeamSide, number>
}

/** Periods drawn on the bar, in chronological order (the shoot-out has no game time) */
const TIMED_PERIODS = ['M1', 'M2', 'P1', 'P2']
const PERIOD_ORDER = [...TIMED_PERIODS, 'TB']
const REGULAR_PERIOD_SECONDS = 10 * 60
/** Each overtime period lasts 5 minutes (ICF and FFCK) */
const EXTRA_PERIOD_SECONDS = 5 * 60
const SECONDS_PER_MINUTE = 60
/** Same-team markers closer than this (in % of the bar) are stacked on separate lanes */
const MIN_MARKER_GAP_PERCENT = 3

/** Converts an event time (seconds, "mm:ss" or "hh:mm:ss") to seconds */
export const parseEventTime = (time: string | number | null | undefined): number => {
  if (!time) return 0
  if (typeof time === 'number') return time
  if (/^\d+$/.test(time)) return parseInt(time)
  const parts = time.split(':')
  if (parts.length < 2) return 0
  const minutes = parseInt(parts.length === 3 ? parts[1] : parts[0])
  const seconds = parseInt(parts.length === 3 ? parts[2] : parts[1])
  return minutes * SECONDS_PER_MINUTE + seconds
}

const defaultPeriodSeconds = (period: string): number =>
  period.startsWith('P') ? EXTRA_PERIOD_SECONDS : REGULAR_PERIOD_SECONDS

/**
 * Period length is not stored: take the default, stretched to the whole minute
 * above the largest remaining time recorded in that period.
 */
const periodSeconds = (period: string, events: GameEvent[]): number => {
  const maxRemaining = Math.max(0, ...events.map(e => parseEventTime(e.e_time)))
  const recorded = Math.ceil(maxRemaining / SECONDS_PER_MINUTE) * SECONDS_PER_MINUTE
  return Math.max(defaultPeriodSeconds(period), recorded)
}

/** Regular time is always drawn; extra periods only once reached */
const displayedPeriods = (events: GameEvent[], currentPeriod: string | null): string[] => {
  const reached = [currentPeriod, ...events.map(e => e.e_period)]
    .map(p => PERIOD_ORDER.indexOf(p ?? ''))
  const lastIndex = Math.max(TIMED_PERIODS.indexOf('M2'), ...reached)
  return TIMED_PERIODS.slice(0, lastIndex + 1)
}

const assignLanes = (markers: Omit<TimelineMarker, 'lane'>[]): TimelineMarker[] => {
  const lastPositionByLane: number[] = []
  return [...markers]
    .sort((a, b) => a.position - b.position)
    .map((marker) => {
      let lane = lastPositionByLane.findIndex(last => marker.position - last >= MIN_MARKER_GAP_PERCENT)
      if (lane === -1) lane = lastPositionByLane.length
      lastPositionByLane[lane] = marker.position
      return { ...marker, lane }
    })
}

/**
 * Lays out game events on a horizontal bar: each period takes a width
 * proportional to its duration, each event sits at its elapsed time.
 * Event times are the remaining time on the chrono (countdown).
 */
export const buildHorizontalTimeline = (
  events: GameEvent[],
  currentPeriod: string | null
): HorizontalTimeline => {
  const periods = displayedPeriods(events, currentPeriod)
  const eventsOf = (period: string) => events.filter(e => (e.e_period || 'M1') === period)
  const durations = periods.map(p => periodSeconds(p, eventsOf(p)))
  const total = durations.reduce((sum, d) => sum + d, 0)

  let offset = 0
  const segments: TimelineSegment[] = []
  const positioned: Omit<TimelineMarker, 'lane'>[] = []
  periods.forEach((period, index) => {
    const duration = durations[index]
    segments.push({ period, left: (offset / total) * 100, width: (duration / total) * 100 })
    for (const event of eventsOf(period)) {
      const elapsed = Math.min(duration, Math.max(0, duration - parseEventTime(event.e_time)))
      positioned.push({ event, position: ((offset + elapsed) / total) * 100 })
    }
    offset += duration
  })

  const markersA = assignLanes(positioned.filter(m => m.event.e_team === 'A'))
  const markersB = assignLanes(positioned.filter(m => m.event.e_team !== 'A'))
  const laneCount = (markers: TimelineMarker[]) => Math.max(1, ...markers.map(m => m.lane + 1))

  return {
    segments,
    markers: [...markersA, ...markersB],
    laneCount: { A: laneCount(markersA), B: laneCount(markersB) }
  }
}

/** Formats an event time as "mm:ss" */
export const formatEventTime = (time: string | number | null | undefined): string => {
  if (!time) return '--:--'
  const totalSeconds = Math.max(0, parseEventTime(time))
  const minutes = Math.floor(totalSeconds / SECONDS_PER_MINUTE)
  const seconds = totalSeconds % SECONDS_PER_MINUTE
  return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`
}
