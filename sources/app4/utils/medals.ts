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

/** Literal classes, so that Tailwind generates them. */
export const FAMILY_CLASSES: Record<MedalFamily, string> = {
  elan: 'text-fam-elan bg-fam-elan/15',
  reaction: 'text-fam-reaction bg-fam-reaction/15',
  endurance: 'text-fam-endurance bg-fam-endurance/15',
  superiorite: 'text-fam-superiorite bg-fam-superiorite/15',
  finition: 'text-fam-finition bg-fam-finition/15',
  defense: 'text-fam-defense bg-fam-defense/15',
  esprit: 'text-fam-esprit bg-fam-esprit/15',
  arbitrage: 'text-fam-arbitrage bg-fam-arbitrage/15',
  match: 'text-fam-match bg-fam-match/15',
  analyse: 'text-fam-analyse bg-fam-analyse/15',
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
