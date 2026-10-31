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

/** Status as shown to visitors: a finished game whose score is not locked yet is « provisional » (CMP-17, as app2). */
export type DisplayStatus = 'ATT' | 'ON' | 'PROVISIONAL' | 'END'

export function displayStatus(game: Pick<ResultsGame, 'g_status' | 'g_validation'>): DisplayStatus {
  if (game.g_status === 'END' && game.g_validation !== 'O') {
    return 'PROVISIONAL'
  }
  return game.g_status
}

export interface GamePeriod {
  kind: 'regular' | 'overtime' | 'shootout'
  number: number | null
}

const PERIOD_PATTERN = /^([MP])(\d)$/
const SHOOTOUT = 'TB'

/** Period of a game in progress: `Mx` regular time, `Px` overtime, `TB` shoot-out; anything else is unknown. */
export function gamePeriod(period: string | null): GamePeriod | null {
  if (period === SHOOTOUT) {
    return { kind: 'shootout', number: null }
  }
  const match = PERIOD_PATTERN.exec(period ?? '')
  return match ? { kind: match[1] === 'M' ? 'regular' : 'overtime', number: Number(match[2]) } : null
}

/** Winner of a finished game (its score may still be provisional); a forfeit (« F ») loses. */
export function winnerSide(game: ResultsGame): Side | null {
  if (game.g_status !== 'END' || !hasScore(game)) {
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

/** Games of one competition; every game when no competition is given (event view filtered by a chip, EVT-06). */
export function gamesOfCompetition(games: readonly ResultsGame[], code: string | undefined): ResultsGame[] {
  return code === undefined ? [...games] : games.filter(game => game.c_code === code)
}

/**
 * A team not yet known, read from the draw (CMP-20): rank in a pool, winner or loser of a game, team of the draw, or
 * plain text. Same reading as api2's `MatchLabelParser`, the reference.
 */
export type DrawSlot =
  | { kind: 'pool', rank: number, pool: string }
  | { kind: 'winner' | 'loser' | 'team', game: number }
  | { kind: 'text', text: string }

const DRAW_BRACKETS = /^\[|\]$/g
/** `[1A-2B]`, `[V11/P12]`…: one code per side, A first. */
const DRAW_SEPARATORS = /[-/*,;]/
const RANK_IN_POOL = /^(\d+)([A-Z_]+)$/
const DRAW_REFERENCE = /^([A-Z_]+)(\d+)$/
const REFERENCE_KINDS: Record<string, 'winner' | 'loser' | 'team'> = { V: 'winner', G: 'winner', W: 'winner', P: 'loser', L: 'loser', T: 'team', D: 'team' }
/** Waiting labels written by api2 from the same codes: « (Winner game #11) », « (2nd Group B) », « (Team 7) ». */
const WAITING_GAME = /^\((winner|vainqueur|loser|perdant)\s+(?:game|match)\s*#?(\d+)\)$/i
const WAITING_POOL = /^\((\d+)(?:st|nd|rd|th)\s+group\s+([A-Z_]+)\)$/i
const WAITING_TEAM = /^\(team\s+(\d+)\)$/i
const BRACKETED_LABEL = /^\((.*)\)$/
const WINNER_WORDS = new Set(['winner', 'vainqueur'])
const FINAL_PHASES = new Set(['finale', 'final'])

/** Draw code without its brackets (`[1A-1C]` → `1A-1C`), only for a game still to play (CMP-20). */
export function drawCode(game: Pick<ResultsGame, 'g_status' | 'g_code'>): string | null {
  return game.g_status === 'ATT' && game.g_code ? game.g_code.replace(DRAW_BRACKETS, '') : null
}

function slotFromCode(code: string): DrawSlot | null {
  const rank = RANK_IN_POOL.exec(code)
  if (rank) {
    return { kind: 'pool', rank: Number(rank[1]), pool: rank[2]! }
  }
  const reference = DRAW_REFERENCE.exec(code)
  const kind = reference ? REFERENCE_KINDS[reference[1]!] : undefined
  if (kind) {
    return { kind, game: Number(reference![2]) }
  }
  return code ? { kind: 'text', text: code } : null
}

/** Slot of a waiting label written by api2 (« (Winner game #11) », « (2nd Group B) », pool placeholders, CMP-20). */
export function slotFromLabel(label: string): DrawSlot {
  const game = WAITING_GAME.exec(label)
  if (game) {
    return { kind: WINNER_WORDS.has(game[1]!.toLowerCase()) ? 'winner' : 'loser', game: Number(game[2]) }
  }
  const pool = WAITING_POOL.exec(label)
  if (pool) {
    return { kind: 'pool', rank: Number(pool[1]), pool: pool[2]!.toUpperCase() }
  }
  const team = WAITING_TEAM.exec(label)
  return team ? { kind: 'team', game: Number(team[1]) } : { kind: 'text', text: BRACKETED_LABEL.exec(label)?.[1] ?? label }
}

/** The team of one side of a game to play, when it is not assigned yet: from its waiting label, else the draw code. */
export function drawSlot(game: ResultsGame, side: Side): DrawSlot | null {
  const label = side === 'A' ? game.t_a_label : game.t_b_label
  if (game.g_status !== 'ATT' || (label && !BRACKETED_LABEL.test(label))) {
    return null
  }
  if (label) {
    return slotFromLabel(label)
  }
  const code = drawCode(game)?.split(DRAW_SEPARATORS)[side === 'A' ? 0 : 1]?.trim() ?? ''
  return slotFromCode(code)
}

/** The final of a cup, the only game marked in gold (CMP-19). */
export function isFinal(game: Pick<ResultsGame, 'd_phase'>): boolean {
  return FINAL_PHASES.has((game.d_phase ?? '').trim().toLowerCase())
}
