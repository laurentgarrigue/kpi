/**
 * Admin page of the match medals (DOC/specs/public/FEATURE_MATCH_MEDALS.md § 9.3): pure display rules. The family
 * classes and the pictogram ids follow sources/app3/app/utils/medals.ts (app4 does not use kpi-layer).
 */

export type MedalFamily = 'elan' | 'reaction' | 'endurance' | 'superiorite' | 'finition' | 'defense' | 'esprit' | 'arbitrage' | 'match' | 'analyse'

export interface AdminMedalItem {
  code: string
  type: 'medal' | 'label' | 'stat'
  scopes: string[]
  family: MedalFamily
  data_level: 'S' | 'T'
  name_fr: string
  name_en: string
  explanation_fr: string
  explanation_en: string
  explanation_fr_template: string
  explanation_en_template: string
  icon_svg: string
  status: 'active' | 'retired'
  published_app2: boolean
  published_app3: boolean
  sort_order: number
  awards: number | null
  updated_at: string | null
  updated_by: string | null
}

/** Family colour of the shield; literal classes, so that Tailwind generates them. */
export const FAMILY_CLASSES: Record<MedalFamily, string> = {
  elan: 'text-fam-elan',
  reaction: 'text-fam-reaction',
  endurance: 'text-fam-endurance',
  superiorite: 'text-fam-superiorite',
  finition: 'text-fam-finition',
  defense: 'text-fam-defense',
  esprit: 'text-fam-esprit',
  arbitrage: 'text-fam-arbitrage',
  match: 'text-fam-match',
  analyse: 'text-fam-analyse',
}

const NO_VALUE = '—'
const SECONDS_PER_MINUTE = 60
const REFEREE_KINDS = /^referee_|^medals_referees$/

/** Composed pictograms hold a mask: its id must be unique in the page. */
export function svgWithUniqueIds(svg: string, suffix: string): string {
  return svg.replace(/(id="|url\(#)(m-[a-z_]+)/g, `$1$2-${suffix}`)
}

export function isRefereeKind(kind: string): boolean {
  return REFEREE_KINDS.test(kind)
}

interface Person { first_name?: string | null, last_name?: string | null }
type Row = Record<string, unknown>

const personName = (person: Person | undefined) => [person?.last_name, person?.first_name].filter(Boolean).join(' ')

/** What a row of a statistic is about: a team, a referee, a pair, a player, a game or a slice of time. */
export function statSubject(row: Row): string {
  const team = row.team as { label?: string } | undefined
  const game = row.game as { number: number | null, team_a: { label: string } | null, team_b: { label: string } | null, score_a: number, score_b: number } | undefined
  if (row.referees) {
    return (row.referees as Person[]).map(personName).join(' + ')
  }
  if (row.referee) {
    return personName(row.referee as Person)
  }
  if (row.last_name !== undefined) {
    return `${personName(row as Person)}${team?.label ? ` (${team.label})` : ''}`
  }
  if (game) {
    return `n° ${game.number ?? '?'} · ${game.team_a?.label ?? '?'} ${game.score_a}-${game.score_b} ${game.team_b?.label ?? '?'}`
  }
  if (team) {
    return team.label ?? NO_VALUE
  }
  if (row.from !== undefined) {
    return row.to === null ? `${row.from} s +` : `${Number(row.from) / SECONDS_PER_MINUTE}–${Number(row.to) / SECONDS_PER_MINUTE}′`
  }
  return NO_VALUE
}

/** A value of a statistic, as read in a table cell. */
export function formatStatValue(value: unknown, type: string): string {
  if (value === null || value === undefined) {
    return NO_VALUE
  }
  if (Array.isArray(value)) {
    return value.join(' ')
  }
  if (typeof value === 'object') {
    return Object.entries(value as Record<string, unknown>).map(([key, count]) => `${key} ${count}`).join(' · ')
  }
  return type === 'percent' ? `${value} %` : String(value)
}

/** Lower case without accents, to search « superiorite » in « Supériorité ». */
const fold = (text: string) => text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase()

/** Front-end search: every word of the query is found in one of the values (no case, no accents). */
export function matchesQuery(query: string, ...values: (string | number | null | undefined)[]): boolean {
  const words = fold(query).split(/\s+/).filter(Boolean)
  const haystack = fold(values.join(' '))
  return words.every(word => haystack.includes(word))
}

/** Text of a statistic row to search in: its subject and its displayed values. */
export function statRowText(row: Row, columns: { key: string, type: string }[]): string {
  return [statSubject(row), ...columns.map(column => formatStatValue(row[column.key], column.type)), row.medals ? formatStatValue(row.medals, 'integer') : ''].join(' ')
}

export type Thresholds = Record<string, unknown>

/** The current thresholds an element's explanation cites (`{bounceBackSeconds}`), in order of appearance. */
export function thresholdsUsed(template: string, current: Thresholds): { key: string, value: unknown }[] {
  const keys = [...new Set([...template.matchAll(/\{(\w+)\}/g)].map(match => match[1]!))]
  return keys.filter(key => key in current).map(key => ({ key, value: current[key] }))
}

/** Thresholds a season froze with another value than today's. */
export function changedThresholds(frozen: Thresholds, current: Thresholds): { key: string, frozen: unknown, current: unknown }[] {
  return Object.keys(frozen)
    .filter(key => JSON.stringify(frozen[key]) !== JSON.stringify(current[key]))
    .map(key => ({ key, frozen: frozen[key], current: current[key] }))
}

/** `bounceBackSeconds` → « Bounce back seconds », for a reader who is not in the code. */
export function humanizeKey(key: string): string {
  const words = key.replace(/([a-z0-9])([A-Z])/g, '$1 $2').toLowerCase()
  return words.charAt(0).toUpperCase() + words.slice(1)
}

export function formatThreshold(value: unknown): string {
  return Array.isArray(value) ? value.join(', ') : String(value)
}

const CARD_ORDER = ['V', 'J', 'R', 'D']

/** What the form changes: the fields that differ from the current thresholds, numbers read as numbers. */
export function thresholdChanges(draft: Record<string, number | string | string[]>, current: Thresholds): Thresholds {
  const changes: Thresholds = {}
  for (const [key, value] of Object.entries(draft)) {
    if (Array.isArray(value)) {
      const cards = CARD_ORDER.filter(card => value.includes(card))
      if (JSON.stringify(cards) !== JSON.stringify(current[key])) {
        changes[key] = cards
      }
    } else if (value !== '' && Number(value) !== current[key]) {
      changes[key] = Number(value)
    }
  }
  return changes
}

/** Games an element is measured on: complete sheets for a timeline element (level T), validated games otherwise. */
export function awardsBasis(level: 'S' | 'T', season: { games_count: number, reliable_games_count: number } | undefined): { games: number, reliable: boolean } | null {
  if (!season) {
    return null
  }
  return level === 'T' ? { games: season.reliable_games_count, reliable: true } : { games: season.games_count, reliable: false }
}

/** Awards per game of the season: above 1 when an element is given to several players or both teams. */
export function awardsPerGame(awards: number | null, games: number): number | null {
  return awards === null || games <= 0 ? null : awards / games
}

/** An award as listed by the admin check of a medal (GET /admin/medals/catalog/{code}/games). */
export interface AuditAward {
  scope: 'team' | 'player' | 'referee'
  side: string | null
  first_name: string | null
  last_name: string | null
  period: string | null
  /** Remaining time on the chrono, in seconds */
  remaining: number | null
}

/** A game behind a figure of the admin page: a medal's games, or the games set aside (MED-80). */
export interface AuditGame {
  id: number
  date: string | null
  time: string | null
  competition: string
  number: number | null
  team_a: string | null
  team_b: string | null
  score_a: string | null
  score_b: string | null
  timeline_issue: string | null
  awards?: AuditAward[]
}

const clock = (seconds: number) => `${String(Math.floor(seconds / SECONDS_PER_MINUTE)).padStart(2, '0')}:${String(seconds % SECONDS_PER_MINUTE).padStart(2, '0')}`

/** "MEDAL Ana · M1 09:45": who received it and when the fact happened, as typed on the sheet (remaining time). */
export function auditAwardText(award: Omit<AuditAward, 'scope'> & { scope: string }): string {
  const who = personName(award) || award.side || ''
  return award.period && award.remaining !== null ? `${who} · ${award.period} ${clock(award.remaining)}` : who
}

/** Sheet of a game in app2, with its timeline: the place to check what was typed. */
export function gameSheetUrl(app2BaseUrl: string, gameId: number): string {
  return `${app2BaseUrl.replace(/\/+$/, '')}/game/${gameId}`
}
