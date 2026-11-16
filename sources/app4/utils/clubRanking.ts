/**
 * Admin page of the national club ranking (DOC/specs/PAGE_CLASSEMENT_NATIONAL_CLUBS.md § 8): pure display rules —
 * columns of the ranking, actions of the life cycle, alerts, CSV exports.
 */

export type SeasonStatus = 'draft' | 'computed' | 'validated' | 'published'

export interface ClubRankingRule {
  id: number
  code: string
  article: string
  rank: number
  label: string
  short_label: string
  points: number
  type: string
  params: Record<string, unknown>
  note: string
}

export interface ClubRankingCategory {
  id: number
  code: string
  label: string
  entry_codes: string[]
  final_code: string | null
  regular_code: string | null
}

export interface StandingRow {
  rank: number
  club: string
  label: string
  department: string
  region: string
  total: number
  by_rule: Record<string, number>
}

export interface DetailLine {
  rule: string
  club: string
  points: number
  reason: string
  source_type: string
  source_id: string
  matric: number | null
}

export interface RankingAlert {
  kind: string
  message: string
  rule: string | null
  blocking: boolean
  context: Record<string, unknown>
}

export interface SeasonState {
  status: SeasonStatus
  stale: boolean
  computed_at: string | null
}

export const MANUAL_TYPE = 'MANUAL_LIST'
const EDITABLE: readonly SeasonStatus[] = ['draft', 'computed']
const CSV_SEPARATOR = ';'

/** The rules grouped by article (7.1 … 7.4), for the two header rows of the ranking table. */
export function articleGroups(rules: ClubRankingRule[]): { article: string, rules: ClubRankingRule[] }[] {
  const groups = new Map<string, ClubRankingRule[]>()
  for (const rule of [...rules].sort(compareRules)) {
    groups.set(rule.article, [...(groups.get(rule.article) ?? []), rule])
  }
  return [...groups].map(([article, items]) => ({ article, rules: items }))
}

export function compareRules(a: { article: string, rank: number }, b: { article: string, rank: number }): number {
  return a.article === b.article ? a.rank - b.rank : a.article.localeCompare(b.article, 'fr', { numeric: true })
}

/** Actions of the life cycle (§ 8): what the page may offer for the season as it is. */
export function seasonActions(season: SeasonState) {
  const editable = EDITABLE.includes(season.status)
  return {
    editable,
    compute: editable,
    validate: season.status === 'computed' && !season.stale,
    publish: season.status === 'validated',
    reopen: season.status === 'validated' || season.status === 'published',
  }
}

/** Blocking alerts first, then by kind; each kind with its alerts, for the review. */
export function groupAlerts(alerts: RankingAlert[]): { kind: string, blocking: boolean, items: RankingAlert[] }[] {
  const groups = new Map<string, RankingAlert[]>()
  for (const alert of alerts) {
    groups.set(alert.kind, [...(groups.get(alert.kind) ?? []), alert])
  }
  return [...groups]
    .map(([kind, items]) => ({ kind, blocking: items.some(item => item.blocking), items }))
    .sort((a, b) => Number(b.blocking) - Number(a.blocking) || a.kind.localeCompare(b.kind))
}

/** Names of gameday officials still to match with a licensee (N-5). */
export function unresolvedOfficials(alerts: RankingAlert[]): { name: string, gamedays: number[], candidates: number[] }[] {
  return alerts
    .filter(alert => alert.kind === 'official_unresolved')
    .map(alert => ({
      name: String(alert.context.name ?? ''),
      gamedays: (alert.context.gamedays as number[] | undefined) ?? [],
      candidates: (alert.context.candidates as number[] | undefined) ?? [],
    }))
}

/** « N15*, N15A  CF15 » → ['N15*', 'N15A', 'CF15'] */
export function parseCodes(text: string): string[] {
  return text.split(/[\s,;]+/).map(code => code.trim().toUpperCase()).filter(code => code !== '')
}

function csvCell(value: string | number): string {
  const text = String(value)
  return /[";\n]/.test(text) ? `"${text.replaceAll('"', '""')}"` : text
}

function csv(rows: (string | number)[][]): string {
  return rows.map(row => row.map(csvCell).join(CSV_SEPARATOR)).join('\n')
}

/** The ranking as the calculation tab of the workbook: one column per rule (§ 8 step 3, export tableur). */
export function standingsCsv(standings: StandingRow[], rules: ClubRankingRule[], headers: { rank: string, club: string, number: string, department: string, region: string, total: string }): string {
  const ordered = [...rules].sort(compareRules)
  return csv([
    [headers.rank, headers.club, headers.number, headers.department, headers.region, ...ordered.map(rule => rule.code), headers.total],
    ...standings.map(row => [row.rank, row.label, row.club, row.department, row.region, ...ordered.map(rule => row.by_rule[rule.code] ?? ''), row.total]),
  ])
}

/** Every line of detail, to justify each point. */
export function linesCsv(lines: DetailLine[], clubLabel: (code: string) => string, headers: { rule: string, club: string, number: string, points: string, reason: string }): string {
  return csv([
    [headers.rule, headers.club, headers.number, headers.points, headers.reason],
    ...lines.map(line => [line.rule, clubLabel(line.club), line.club, line.points, line.reason]),
  ])
}
