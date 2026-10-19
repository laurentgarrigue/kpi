import type { LastUpdateInfo } from '~/types/presence'
import type { EquipmentParameter, ScrutineeringContext, ScrutineeringOverview, ScrutineeringSheet, ScrutineeringTeam } from '~/types/scrutineering'
import { contextParams } from '~/utils/scrutineering'

/**
 * api2 /staff endpoints of the scrutineering (DOC/specs/PAGE_SCRUTINEERING.md § 5). A write made in the context of an
 * event passes it (`eventId`) for the journal.
 */
export const useScrutineeringApi = () => {
  const api = useApi()
  const team = (teamId: number) => `/staff/team/${teamId}`
  const withEvent = (endpoint: string, eventId: number | null) => (eventId === null ? endpoint : `${endpoint}?event=${eventId}`)

  return {
    teams: (context: ScrutineeringContext) => api.get<ScrutineeringTeam[]>('/staff/teams', contextParams(context)),
    overview: (context: ScrutineeringContext) => api.get<ScrutineeringOverview>('/staff/overview', contextParams(context)),
    sheet: (teamId: number) => api.get<ScrutineeringSheet>(`${team(teamId)}/players`),
    setEquipment: (teamId: number, playerId: number, parameter: EquipmentParameter, value: number, eventId: number | null) =>
      api.put<{ value: number, lastUpdate: LastUpdateInfo | null }>(withEvent(`${team(teamId)}/player/${playerId}/${parameter}/${value}`, eventId)),
    setComment: (teamId: number, playerId: number, comment: string, eventId: number | null) =>
      api.put<{ comment: string, lastUpdate: LastUpdateInfo | null }>(withEvent(`${team(teamId)}/player/${playerId}/comment`, eventId), { comment }),
    reset: (teamId: number, eventId: number | null) => api.del<{ reset: boolean, deleted: number }>(withEvent(`${team(teamId)}/reset`, eventId)),
  }
}
