import { withQuery } from 'ufo'
import { competitionPath } from './competitions'

import type { GroupSection } from '#kpi-layer/utils/results/types'

/**
 * Sections of the competition groups (kp_groupe.section), used for the calendar colours, badges and filter
 * (PAGE_CALENDAR.md § 2): international, national, regional, tournaments, continents, misc.
 */
export const CALENDAR_SECTIONS = [1, 2, 3, 4, 5, 100] as const
export type CalendarSection = typeof CALENDAR_SECTIONS[number]
/** Section of a competition whose group has none, or an unknown one. */
export const MISC_SECTION: CalendarSection = 100

/** Light background and edge of a gameday bar, and its badge, per section. */
export const SECTION_STYLES: Record<CalendarSection, { bar: string, badge: string }> = {
  1: { bar: 'bg-kpi-gold-100 border-kpi-gold-500', badge: 'bg-kpi-gold-100 text-kpi-gold-900' },
  2: { bar: 'bg-kpi-blue-100 border-kpi-blue-500', badge: 'bg-kpi-blue-100 text-kpi-blue-800' },
  3: { bar: 'bg-kpi-green-100 border-kpi-green-600', badge: 'bg-kpi-green-100 text-kpi-green-800' },
  4: { bar: 'bg-kpi-red-100 border-kpi-red-500', badge: 'bg-kpi-red-100 text-kpi-red-800' },
  5: { bar: 'bg-sky/25 border-sky', badge: 'bg-sky/25 text-navy' },
  100: { bar: 'bg-line/40 border-ink/50', badge: 'bg-line/60 text-ink' },
}

/** Gameday of `GET /calendar` (API_PUBLIC_TRANSVERSE.md § 3.1). Dates are `YYYY-MM-DD`. */
export interface CalendarEntry {
  id: number
  competition: {
    season: string
    code: string
    display_title: string
    type: string
    section: number
    group: string | null
  }
  /** « name - place (department) », as in the legacy calendar and in the .ics files. */
  label: string
  name: string | null
  place: string | null
  department: string | null
  start: string
  end: string
  event: { id: number, libelle: string | null } | null
}

export interface CalendarFilters {
  section?: CalendarSection
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
const MONTHS_PER_YEAR = 12

/** `?month=YYYY-MM` if valid, otherwise the month of `today` (CAL-01). */
export function parseMonth(value: unknown, today: string): string {
  return typeof value === 'string' && MONTH_PATTERN.test(value) ? value : today.slice(0, 7)
}

/** Section and group filters of the query, dropped when invalid (CAL-04). */
export function parseFilters(query: Record<string, unknown>): CalendarFilters {
  const filters: CalendarFilters = {}
  const section = typeof query.section === 'string' && /^\d{1,3}$/.test(query.section) ? Number(query.section) : null
  if (section !== null && (CALENDAR_SECTIONS as readonly number[]).includes(section)) {
    filters.section = section as CalendarSection
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

/** Same month, `delta` years later or earlier. */
export function shiftYear(month: string, delta: number): string {
  return shiftMonth(month, delta * MONTHS_PER_YEAR)
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

/** Section of a gameday; the misc one when it is none of the known sections. */
export function entrySection(entry: CalendarEntry): CalendarSection {
  const section = entry.competition.section
  return (CALENDAR_SECTIONS as readonly number[]).includes(section) ? section as CalendarSection : MISC_SECTION
}

/** The groups of the chosen section only (every group without section filter), for the group dropdown. */
export function groupsOfSection(sections: readonly GroupSection[], section: CalendarSection | undefined): GroupSection[] {
  return section === undefined ? [...sections] : sections.filter(item => item.section === section)
}

/** Filters whose group belongs to the chosen section: a group of another section would show an empty calendar. */
export function reconcileFilters(filters: CalendarFilters, sections: readonly GroupSection[]): CalendarFilters {
  if (filters.group === undefined || filters.section === undefined) {
    return filters
  }
  const known = groupsOfSection(sections, filters.section).some(item => item.groups.some(group => group.code === filters.group))
  return known ? filters : { section: filters.section }
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
