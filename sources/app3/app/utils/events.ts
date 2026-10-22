import { legacyImageUrl } from './page-links'

/** Public event, as returned by api2 `GET /events/{mode}` (PAGE_HOME.md § 3). Dates are `YYYY-MM-DD`. */
export interface PublicEvent {
  id: number
  libelle: string
  place: string
  logo: string | null
  start: string | null
  end: string | null
}

/** Anything spanning calendar days `YYYY-MM-DD`: an event, a fixture of the home page. */
export type Dated = Pick<PublicEvent, 'start' | 'end'>

const PARIS_TIME_ZONE = 'Europe/Paris'

/** Last day: the end date, or the start date when the end is missing. */
function lastDay(item: Dated): string | null {
  return item.end ?? item.start
}

/** First day: the start date, or the end date when the start is missing. */
function firstDay(item: Dated): string | null {
  return item.start ?? item.end
}

/** Years in which at least one dated event takes place, most recent first. */
export function eventYears(events: readonly PublicEvent[]): number[] {
  const years = events.flatMap(event => [firstDay(event), lastDay(event)])
    .filter((day): day is string => day !== null)
    .map(day => Number(day.slice(0, 4)))
  return [...new Set(years)].sort((a, b) => b - a)
}

/** Events taking place, at least partly, in the year; the earliest first. */
export function eventsOfYear(events: readonly PublicEvent[], year: number): PublicEvent[] {
  const [from, to] = [`${year}-01-01`, `${year}-12-31`]
  return events
    .filter((event) => {
      const [first, last] = [firstDay(event), lastDay(event)]
      return first !== null && last !== null && first <= to && last >= from
    })
    .sort((a, b) => (firstDay(a) ?? '').localeCompare(firstDay(b) ?? ''))
}

/** It takes place today (first and last days included). */
export function isOngoing(item: Dated, today: string): boolean {
  const first = firstDay(item)
  const last = lastDay(item)
  return first !== null && last !== null && first <= today && today <= last
}

/** Calendar date `YYYY-MM-DD` in Europe/Paris (the `en-CA` locale formats dates as ISO). */
export function todayInParis(now: Date): string {
  return new Intl.DateTimeFormat('en-CA', { timeZone: PARIS_TIME_ZONE }).format(now)
}

/** Localised date or date range (« 12–14 juin 2026 »); empty without any date. */
export function formatEventDates(start: string | null, end: string | null, locale: string): string {
  const first = start ?? end
  if (!first) {
    return ''
  }
  // Dates are calendar days: format them in UTC so that no time zone shifts the day.
  const format = new Intl.DateTimeFormat(locale, { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' })
  const last = end ?? first
  return format.formatRange(new Date(first), new Date(last))
}

/** Date tile of an event card: « 3–5 » over « juil. » (CMP-18); `null` without any date. */
export function dateTile(start: string | null, end: string | null, locale: string): { days: string, month: string } | null {
  const first = start ?? end
  if (!first) {
    return null
  }
  const last = end ?? first
  const day = (date: string) => String(Number(date.slice(8, 10)))
  const month = new Intl.DateTimeFormat(locale, { month: 'short', timeZone: 'UTC' }).format(new Date(last))
  return { days: first === last ? day(first) : `${day(first)}–${day(last)}`, month }
}

/** Absolute URL of an event logo stored under the legacy `/img/` tree, or `null` without logo. */
export function eventLogoUrl(logo: string | null, legacyBaseUrl: string): string | null {
  return legacyImageUrl(logo, legacyBaseUrl)
}
