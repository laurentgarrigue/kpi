import { withQuery } from 'ufo'
import { competitionPath } from './competitions'

/** Level of a competition in the calendar filter and badges (PAGE_CALENDAR.md § 2). */
export const CALENDAR_LEVELS = ['INT', 'NAT', 'REG'] as const
export type CalendarLevel = typeof CALENDAR_LEVELS[number]

/** Gameday of `GET /calendar` (API_PUBLIC_TRANSVERSE.md § 3.1). Dates are `YYYY-MM-DD`. */
export interface CalendarEntry {
  id: number
  competition: {
    season: string
    code: string
    display_title: string
    type: string
    level: string | null
    group: string | null
  }
  name: string | null
  place: string | null
  department: string | null
  start: string
  end: string
  event: { id: number, libelle: string | null } | null
}

export interface CalendarFilters {
  level?: CalendarLevel
  group?: string
}

/** A day of the month grid, with the gamedays taking place on it. */
export interface CalendarDay {
  date: string
  inMonth: boolean
  entries: CalendarEntry[]
}

const MONTH_PATTERN = /^(\d{4})-(0[1-9]|1[0-2])$/
const GROUP_PATTERN = /^[\w-]{1,12}$/
/** How far ahead an empty month looks for the next month having gamedays (CAL-05). */
export const NEXT_MONTH_LOOKAHEAD = 12
const DAYS_PER_WEEK = 7

/** `?month=YYYY-MM` if valid, otherwise the month of `today` (CAL-01). */
export function parseMonth(value: unknown, today: string): string {
  return typeof value === 'string' && MONTH_PATTERN.test(value) ? value : today.slice(0, 7)
}

/** Level and group filters of the query, dropped when invalid (CAL-04). */
export function parseFilters(query: Record<string, unknown>): CalendarFilters {
  const filters: CalendarFilters = {}
  if (typeof query.level === 'string' && (CALENDAR_LEVELS as readonly string[]).includes(query.level)) {
    filters.level = query.level as CalendarLevel
  }
  if (typeof query.group === 'string' && GROUP_PATTERN.test(query.group)) {
    filters.group = query.group
  }
  return filters
}

/** Calendar day `YYYY-MM-DD` of a UTC date. */
function isoDay(date: Date): string {
  return date.toISOString().slice(0, 10)
}

function utcDate(day: string): Date {
  return new Date(`${day}T00:00:00Z`)
}

function addDays(day: string, days: number): string {
  const date = utcDate(day)
  date.setUTCDate(date.getUTCDate() + days)
  return isoDay(date)
}

/** `2026-06` + 1 → `2026-07` (months are calendar months, no time zone involved). */
export function shiftMonth(month: string, delta: number): string {
  const [year, monthNumber] = month.split('-').map(Number) as [number, number]
  return isoDay(new Date(Date.UTC(year, monthNumber - 1 + delta, 1))).slice(0, 7)
}

/** First and last days of a month. */
export function monthRange(month: string): { start: string, end: string } {
  return { start: `${month}-01`, end: addDays(`${shiftMonth(month, 1)}-01`, -1) }
}

/** Monday before the first day to Sunday after the last day: the period shown by the month grid. */
export function gridRange(month: string): { start: string, end: string } {
  const { start, end } = monthRange(month)
  // getUTCDay: 0 = Sunday … 6 = Saturday; days since Monday.
  const sinceMonday = (utcDate(start).getUTCDay() + DAYS_PER_WEEK - 1) % DAYS_PER_WEEK
  const untilSunday = (DAYS_PER_WEEK - utcDate(end).getUTCDay()) % DAYS_PER_WEEK
  return { start: addDays(start, -sinceMonday), end: addDays(end, untilSunday) }
}

/** api2 request of a period, with the filters. */
export function calendarApiPath(range: { start: string, end: string }, filters: CalendarFilters): string {
  return withQuery('/calendar', { start: range.start, end: range.end, ...filters })
}

/** Period searched for the next month having gamedays: the twelve months after `month`. */
export function lookaheadRange(month: string): { start: string, end: string } {
  return { start: `${shiftMonth(month, 1)}-01`, end: monthRange(shiftMonth(month, NEXT_MONTH_LOOKAHEAD)).end }
}

/** The gameday takes place, at least partly, in the period. */
function overlaps(entry: CalendarEntry, start: string, end: string): boolean {
  return entry.start <= end && entry.end >= start
}

/** Gamedays of the month, grouped by start date (a gameday started the month before is listed on the 1st). */
export function entriesByDate(entries: readonly CalendarEntry[], month: string): { date: string, entries: CalendarEntry[] }[] {
  const { start, end } = monthRange(month)
  const groups = new Map<string, CalendarEntry[]>()
  for (const entry of entries.filter(item => overlaps(item, start, end))) {
    const date = entry.start < start ? start : entry.start
    groups.set(date, [...(groups.get(date) ?? []), entry])
  }
  return [...groups.entries()].sort(([a], [b]) => a.localeCompare(b)).map(([date, items]) => ({ date, entries: items }))
}

/** Weeks (Monday → Sunday) of the month grid; each day lists the gamedays covering it. */
export function monthGrid(entries: readonly CalendarEntry[], month: string): CalendarDay[][] {
  const { start, end } = gridRange(month)
  const weeks: CalendarDay[][] = []
  for (let day = start; day <= end; day = addDays(day, 1)) {
    if (weeks.length === 0 || weeks.at(-1)!.length === DAYS_PER_WEEK) {
      weeks.push([])
    }
    weeks.at(-1)!.push({ date: day, inMonth: day.startsWith(month), entries: entries.filter(entry => overlaps(entry, day, day)) })
  }
  return weeks
}

/** Month of the first gameday starting after `month`, or `null`. */
export function nextMonthWithEntries(entries: readonly CalendarEntry[], month: string): string | null {
  const first = entries.map(entry => entry.start).filter(date => date.slice(0, 7) > month).sort()[0]
  return first ? first.slice(0, 7) : null
}

/** Competition page of a gameday: its games, filtered on the gameday for a championship (CAL-03). */
export function gamedayCompetitionPath(entry: CalendarEntry): string {
  const path = competitionPath(entry.competition.season, entry.competition.code, 'games')
  return entry.competition.type === 'CHPT' ? withQuery(path, { gameday: entry.id }) : path
}

/** Level of a gameday, when it is one of the calendar levels. */
export function entryLevel(entry: CalendarEntry): CalendarLevel | null {
  const level = entry.competition.level
  return level && (CALENDAR_LEVELS as readonly string[]).includes(level) ? level as CalendarLevel : null
}

/** One bar of the month grid: a gameday over the days of a week it covers (CAL-02). */
export interface WeekSegment {
  entry: CalendarEntry
  /** First day of the bar, 0 = Monday … 6 = Sunday. */
  column: number
  /** Number of days covered in this week. */
  span: number
  /** Row of the bar, so that overlapping bars never share a row. */
  lane: number
  /** The gameday started in a previous week / ends in a following one. */
  continuesBefore: boolean
  continuesAfter: boolean
}

/**
 * Bars of a week (Monday → Sunday): one per gameday, as wide as its days in the week. Longer bars first, then
 * each takes the first row not yet occupied where it starts.
 */
export function weekSegments(week: readonly CalendarDay[]): WeekSegment[] {
  const first = week[0]!.date
  const last = week.at(-1)!.date
  const entries = [...new Map(week.flatMap(day => day.entries).map(entry => [entry.id, entry])).values()]

  const segments = entries.map((entry) => {
    const column = week.findIndex(day => day.date >= entry.start)
    const end = week.findLastIndex(day => day.date <= entry.end)
    const startColumn = entry.start < first ? 0 : column
    return {
      entry, column: startColumn, span: end - startColumn + 1, lane: 0,
      continuesBefore: entry.start < first, continuesAfter: entry.end > last,
    }
  }).sort((a, b) => a.column - b.column || b.span - a.span || a.entry.id - b.entry.id)

  const laneEnds: number[] = []
  for (const segment of segments) {
    const free = laneEnds.findIndex(laneEnd => laneEnd < segment.column)
    segment.lane = free === -1 ? laneEnds.length : free
    laneEnds[segment.lane] = segment.column + segment.span - 1
  }
  return segments
}
