/** api2 /staff responses (DOC/specs/PAGE_SCRUTINEERING.md § 5). */

export type EquipmentParameter = 'kayak_status' | 'vest_status' | 'helmet_status' | 'paddle_count'

export interface ScrutineeringTeam {
  team_id: number
  label: string
  club: string
  club_label: string
  nation: string
  logo: string | null
  competition_code: string
  competition_label: string
  season: string
  locked: boolean
}

export interface ScrutineeringPlayer {
  player_id: number
  last_name: string
  first_name: string
  num: number | null
  /** '-' player, 'C' captain, 'E' staff (referees 'A' and inactive 'X' are not returned) */
  cap: string
  kayak_status: number
  vest_status: number
  helmet_status: number
  paddle_count: number
  comment: string
}

export interface ScrutineeringSheet {
  team: ScrutineeringTeam
  players: ScrutineeringPlayer[]
}

export type OverviewStatus = 'complete' | 'partial' | 'none'

export interface ScrutineeringOverview {
  categories: string[]
  clubs: { code: string, label: string }[]
  teams: { team_id: number, label: string, club: string, logo: string | null, category: string, status: OverviewStatus }[]
}
