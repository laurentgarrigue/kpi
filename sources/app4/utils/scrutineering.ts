/**
 * Pure helpers of the scrutineering (DOC/specs/PAGE_SCRUTINEERING.md). No Nuxt import: unit-tested with Vitest.
 */
import type { EquipmentParameter, ScrutineeringPlayer, ScrutineeringTeam } from '~/types/scrutineering'

/** Profile 10 "Contrôle équipement": scrutineering and presence sheets of every team of its competitions. */
export const SCRUTINEER_PROFILE = 10
const ADMIN_MAX_PROFILE = 2
const TEAM_PRESENCE_WRITE_MAX_PROFILE = 7

export const EQUIPMENT_PARAMETERS: readonly EquipmentParameter[] = ['kayak_status', 'vest_status', 'helmet_status', 'paddle_count']
const EQUIPMENT_MAX: Record<EquipmentParameter, number> = { kayak_status: 4, vest_status: 4, helmet_status: 4, paddle_count: 6 }

const STAFF = 'E'
const NOT_CONTROLLED = ['A', 'X']

export function isScrutineerProfile(profile: number): boolean {
  return profile === SCRUTINEER_PROFILE
}

/** Scrutineering screens: profiles 1–2 and the profile 10 (ADM-SCR-02). */
export function canManageScrutineering(profile: number): boolean {
  return profile <= ADMIN_MAX_PROFILE || isScrutineerProfile(profile)
}

/** Team presence sheet edition: profiles ≤ 7 and the profile 10 (ADM-SCR-05). */
export function canWriteTeamPresence(profile: number): boolean {
  return profile <= TEAM_PRESENCE_WRITE_MAX_PROFILE || isScrutineerProfile(profile)
}

/** Value after a click: statuses cycle 0 → 4 → 0, paddles 0 → 6 → 0 (ADM-SCR-08). */
export function nextEquipmentValue(parameter: EquipmentParameter, current: number): number {
  return current >= EQUIPMENT_MAX[parameter] ? 0 : current + 1
}

export function isStaff(player: ScrutineeringPlayer): boolean {
  return player.cap === STAFF
}

/** Players shown by the control table: referees and inactive players are excluded (ADM-SCR-10). */
export function controlPlayers(players: readonly ScrutineeringPlayer[]): ScrutineeringPlayer[] {
  return players.filter(player => !NOT_CONTROLLED.includes(player.cap))
}

/** Valid paddles of the team, staff excluded (ADM-SCR-09). */
export function totalValidPaddles(players: readonly ScrutineeringPlayer[]): number {
  return players.reduce((sum, player) => (isStaff(player) ? sum : sum + player.paddle_count), 0)
}

/** Other teams of the same club in the event, every competition (ADM-SCR-15). */
export function sameClubTeams(teams: readonly ScrutineeringTeam[], teamId: number): ScrutineeringTeam[] {
  const current = teams.find(team => team.team_id === teamId)
  return current ? teams.filter(team => team.team_id !== teamId && team.club === current.club) : []
}

/** Other teams of the same nation, those of the same club excepted (ADM-SCR-16). */
export function sameNationTeams(teams: readonly ScrutineeringTeam[], teamId: number): ScrutineeringTeam[] {
  const current = teams.find(team => team.team_id === teamId)
  return current
    ? teams.filter(team => team.team_id !== teamId && team.club !== current.club && team.nation === current.nation)
    : []
}

/** Previous and next teams in the selector order (ADM-SCR-17). */
export function adjacentTeams(teams: readonly ScrutineeringTeam[], teamId: number): { previous: ScrutineeringTeam | null, next: ScrutineeringTeam | null } {
  const index = teams.findIndex(team => team.team_id === teamId)
  if (index < 0) {
    return { previous: null, next: null }
  }
  return { previous: teams[index - 1] ?? null, next: teams[index + 1] ?? null }
}

/** Teams grouped by competition for the selector optgroups, in the order of the list. */
export function teamsByCompetition(teams: readonly ScrutineeringTeam[]): { code: string, label: string, teams: ScrutineeringTeam[] }[] {
  const groups = new Map<string, { code: string, label: string, teams: ScrutineeringTeam[] }>()
  for (const team of teams) {
    if (!groups.has(team.competition_code)) {
      groups.set(team.competition_code, { code: team.competition_code, label: team.competition_label, teams: [] })
    }
    groups.get(team.competition_code)!.teams.push(team)
  }
  return [...groups.values()]
}

const EVENT_SELECTION_PREFIX = 'event:'

/** Event of the scrutineering: the page event filter (`event:{id}`), else the work context event. */
export function resolveEventId(pageEventGroupSelection: string, selectionType: string | null, contextEventId: number | null): number | null {
  if (pageEventGroupSelection.startsWith(EVENT_SELECTION_PREFIX)) {
    return Number.parseInt(pageEventGroupSelection.slice(EVENT_SELECTION_PREFIX.length), 10) || null
  }
  return selectionType === 'event' ? contextEventId : null
}
