import type { ResultsGame, Side } from './types'

/** A game started less than this long ago is still « upcoming » (legacy kpmatchs.php rule). */
export const UPCOMING_GRACE_MINUTES = 35

/** A game starting within this delay makes a results page refresh itself (PAGE_COMPETITION.md § 2.4). */
export const LIVE_LOOKAHEAD_MINUTES = 60

const PARIS_TIME_ZONE = 'Europe/Paris'
const MINUTE_MS = 60_000
const UNKNOWN_TIME = '00:00'
const UNKNOWN_SCORES = new Set(['', '?'])
const FORFEIT = 'F'

/** `YYYY-MM-DDTHH:MM` of an instant, in Paris local time: comparable as a string with game date/times. */
export function parisDateTime(instant: Date): string {
  const parts = Object.fromEntries(new Intl.DateTimeFormat('en-GB', {
    timeZone: PARIS_TIME_ZONE,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
  }).formatToParts(instant).map(part => [part.type, part.value]))
  return `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}`
}

/** `HH:MM` from api2 times (`9:40`, `09:40`, `09h40`). */
export function normalizeTime(time: string | null): string {
  const match = time?.match(/^(\d{1,2})[:h](\d{2})/)
  return match ? `${match[1]!.padStart(2, '0')}:${match[2]}` : UNKNOWN_TIME
}

function gameKey(game: ResultsGame): string | null {
  return game.g_date ? `${game.g_date}T${normalizeTime(game.g_time)}` : null
}

function pitchOrder(a: string | null, b: string | null): number {
  return (Number.parseInt(a ?? '', 10) || 0) - (Number.parseInt(b ?? '', 10) || 0) || (a ?? '').localeCompare(b ?? '')
}

/** Date, then time, then pitch number. */
export function sortGames(games: readonly ResultsGame[]): ResultsGame[] {
  return [...games].sort((a, b) => (gameKey(a) ?? '').localeCompare(gameKey(b) ?? '') || pitchOrder(a.g_pitch, b.g_pitch))
}

export interface GamesOfDate {
  date: string
  games: ResultsGame[]
}

/** Sorted games grouped by date (games without a date come last, under an empty date). */
export function groupGamesByDate(games: readonly ResultsGame[]): GamesOfDate[] {
  const groups = new Map<string, ResultsGame[]>()
  for (const game of sortGames(games)) {
    const date = game.g_date ?? ''
    groups.set(date, [...(groups.get(date) ?? []), game])
  }
  return [...groups].map(([date, gamesOfDate]) => ({ date, games: gamesOfDate }))
}

/** Starts after now − 35 min (Paris time). */
export function isUpcoming(game: ResultsGame, now: Date): boolean {
  const key = gameKey(game)
  return key !== null && key >= parisDateTime(new Date(now.getTime() - UPCOMING_GRACE_MINUTES * MINUTE_MS))
}

export interface GameFilters {
  gameday?: number
  day?: string
  upcoming?: boolean
}

/** Games matching every given filter, sorted. */
export function filterGames(games: readonly ResultsGame[], filters: GameFilters, now: Date): ResultsGame[] {
  return sortGames(games).filter(game =>
    (filters.gameday === undefined || game.d_id === filters.gameday)
    && (filters.day === undefined || game.g_date === filters.day)
    && (!filters.upcoming || isUpcoming(game, now)))
}

/** Distinct dates with games, in order. */
export function gameDates(games: readonly ResultsGame[]): string[] {
  return [...new Set(games.map(game => game.g_date).filter((date): date is string => Boolean(date)))].sort()
}

export interface GamedayOption {
  id: number
  label: string | null
  place: string | null
  start: string | null
  end: string | null
}

/** Gamedays present in the games, by first date, with their date range. */
export function gamedayOptions(games: readonly ResultsGame[]): GamedayOption[] {
  const options = new Map<number, GamedayOption>()
  for (const game of sortGames(games)) {
    const option = options.get(game.d_id)
    if (option) {
      option.end = game.g_date ?? option.end
    } else {
      options.set(game.d_id, { id: game.d_id, label: game.d_label, place: game.d_place, start: game.g_date, end: game.g_date })
    }
  }
  return [...options.values()]
}

/** Today when it has games, otherwise the next day with games, otherwise the last one. */
export function defaultPitchDay(days: readonly string[], today: string): string | null {
  return days.find(day => day >= today) ?? days.at(-1) ?? null
}

export interface PitchGrid {
  pitches: string[]
  rows: { time: string, cells: Record<string, ResultsGame> }[]
}

/** Games of one day as a time × pitch grid. */
export function pitchGrid(games: readonly ResultsGame[]): PitchGrid {
  const pitches = [...new Set(games.map(game => game.g_pitch ?? ''))].sort(pitchOrder)
  const rows = new Map<string, Record<string, ResultsGame>>()
  for (const game of sortGames(games)) {
    const time = normalizeTime(game.g_time)
    rows.set(time, { ...rows.get(time), [game.g_pitch ?? '']: game })
  }
  return { pitches, rows: [...rows].map(([time, cells]) => ({ time, cells })) }
}

export function hasScore(game: ResultsGame): boolean {
  return !UNKNOWN_SCORES.has(game.g_score_a ?? '') && !UNKNOWN_SCORES.has(game.g_score_b ?? '')
}

/** Score shown but not yet validated by the officials. */
export function isProvisional(game: ResultsGame): boolean {
  return game.g_status !== 'ATT' && hasScore(game) && game.g_validation !== 'O'
}

/** Winner of a validated finished game; a forfeit (« F ») loses. */
export function winnerSide(game: ResultsGame): Side | null {
  if (game.g_status !== 'END' || game.g_validation !== 'O' || !hasScore(game)) {
    return null
  }
  const [a, b] = [game.g_score_a!, game.g_score_b!]
  if (a === FORFEIT || b === FORFEIT) {
    return a === FORFEIT ? (b === FORFEIT ? null : 'B') : 'A'
  }
  const difference = Number.parseInt(a, 10) - Number.parseInt(b, 10)
  return difference > 0 ? 'A' : difference < 0 ? 'B' : null
}

/** A game is on, or starts within the hour: the page should refresh itself. */
export function isLive(games: readonly ResultsGame[], now: Date): boolean {
  const from = parisDateTime(new Date(now.getTime() - UPCOMING_GRACE_MINUTES * MINUTE_MS))
  const to = parisDateTime(new Date(now.getTime() + LIVE_LOOKAHEAD_MINUTES * MINUTE_MS))
  return games.some((game) => {
    const key = gameKey(game)
    return game.g_status === 'ON' || (game.g_status === 'ATT' && key !== null && key >= from && key <= to)
  })
}
