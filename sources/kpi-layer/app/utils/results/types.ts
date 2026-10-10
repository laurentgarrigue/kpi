/**
 * Formats api2 des résultats publics (DOC/specs/public/API_PUBLIC_RESULTS.md). Les noms de champs sont ceux
 * d'api2 : `g_` match, `t_a`/`t_b` équipes, `d_` journée, `c_` compétition.
 */

export type GameStatus = 'ATT' | 'ON' | 'END'
export type CompetitionType = 'CHPT' | 'CP' | 'MULTI'
export type CompetitionStatus = 'ATT' | 'ON' | 'END'
export type Side = 'A' | 'B'

/** Match d'une liste (`/competition/…/games`, `/group/…/games`, `/event/{id}/games`). */
export interface ResultsGame {
  c_code: string
  c_season: string
  c_label: string | null
  d_id: number
  d_phase: string | null
  d_level: number | null
  d_place: string | null
  d_label: string | null
  g_id: number
  g_number: number | null
  g_date: string | null
  g_time: string | null
  g_pitch: string | null
  g_code: string | null
  g_validation: string
  g_status: GameStatus
  g_period: string | null
  g_score_a: string | null
  g_score_b: string | null
  g_coef_a: number
  g_coef_b: number
  t_a_id: number | null
  t_b_id: number | null
  t_a_label: string | null
  t_b_label: string | null
  t_a_number: number | null
  t_b_number: number | null
  t_a_logo: string | null
  t_b_logo: string | null
  r_1?: string | null
  r_2?: string | null
}

/** Équipe d'une phase de tableau (`/competition/…/charts`). */
export interface ChartTeam {
  t_id: number | null
  t_number: number | null
  t_label: string | null
  t_logo: string | null
  t_clt: number | null
  t_pts: number | null
  t_pld: number | null
  t_diff: number | null
}

export interface ChartPhase {
  type: 'C' | 'E'
  libelle: string
  level: number
  t_count: number
  d_id: number
  teams?: ChartTeam[]
  games: ResultsGame[] | null
}

export interface ChartRound {
  type: 'C' | 'E'
  phases: Record<string, ChartPhase>
}

export interface CompetitionChart {
  type: CompetitionType
  code: string
  libelle: string | null
  status: CompetitionStatus
  season: string
  rounds: Record<string, ChartRound>
}

export interface TeamRef {
  id: number
  number: number | null
  label: string
  logo?: string | null
}

export interface RankingRow {
  rank: number
  team: TeamRef
  points: number
  played: number
  won?: number
  drawn?: number
  lost?: number
  forfeits?: number
  goals_for?: number
  goals_against?: number
  goal_diff?: number
  medal: 1 | 2 | 3 | null
}

export interface Ranking {
  status: CompetitionStatus
  type: CompetitionType
  qualified: number
  eliminated: number
  rows: RankingRow[]
}

export interface LinkedEvent {
  id: number
  libelle: string
  place: string | null
  start: string | null
  end: string | null
  logo: string | null
  share: number
}

export interface CompetitionSummary {
  code: string
  season?: string
  display_title: string
  soustitre2: string | null
}

export interface CompetitionHeader {
  code: string
  season: string
  group: { code: string, libelle: string | null, libelle_en: string | null }
  libelle: string | null
  soustitre: string | null
  soustitre2: string | null
  display_title: string
  type: CompetitionType
  status: CompetitionStatus
  level: string | null
  banner: string | null
  logo: string | null
  web: string | null
  qualified: number
  eliminated: number
  has_games: boolean
  round: number | null
  final: boolean
}

export interface CompetitionDetails extends CompetitionHeader {
  siblings: CompetitionSummary[]
  events: LinkedEvent[]
}

export interface GroupCompetition extends CompetitionHeader {
  ranking: RankingRow[]
}

export interface GroupCompetitions {
  events: LinkedEvent[]
  competitions: GroupCompetition[]
}

export interface Seasons {
  active: string | null
  seasons: string[]
}

export interface CompetitionGroup {
  code: string
  libelle: string
  libelle_en: string | null
}

export interface GroupSection {
  section: number
  label: string
  groups: CompetitionGroup[]
}

export interface Gameday {
  id: number
  name: string | null
  phase: string | null
  start: string | null
  end: string | null
  place: string | null
  department: string | null
  organizer: string | null
  officials: { rc: string | null, r1: string | null, delegate: string | null, chief_referee: string | null }
}

export interface CompetitionInfo {
  gamedays: Gameday[]
  teams_by_pool: { pool: string, teams: TeamRef[] }[]
  schema: string | null
}

export interface StatColumn {
  key: string
  type: 'integer' | 'string'
}

export interface Stat {
  kind: string
  columns: StatColumn[]
  rows: (Record<string, unknown> & { rank: number })[]
}

/** Competition of an event, with the gamedays held at the event out of all its gamedays (PAGE_EVENT_GROUP.md § 2.2). */
export interface EventCompetition extends Required<CompetitionSummary> {
  type: string
  gamedays: number
  total_gamedays: number
}

export interface EventCompetitions {
  event: { id: number, libelle: string, place: string | null, logo: string | null, start: string | null, end: string | null }
  competitions: EventCompetition[]
}
