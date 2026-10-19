import type { EquipmentParameter, ScrutineeringOverview, ScrutineeringSheet, ScrutineeringTeam } from '~/types/scrutineering'

/** api2 /staff endpoints of the scrutineering (DOC/specs/PAGE_SCRUTINEERING.md § 5). */
export const useScrutineeringApi = () => {
  const api = useApi()
  const base = (eventId: number) => `/staff/${eventId}`

  return {
    teams: (eventId: number) => api.get<ScrutineeringTeam[]>(`${base(eventId)}/teams`),
    sheet: (eventId: number, teamId: number) => api.get<ScrutineeringSheet>(`${base(eventId)}/team/${teamId}/players`),
    setEquipment: (eventId: number, teamId: number, playerId: number, parameter: EquipmentParameter, value: number) =>
      api.put<{ value: number }>(`${base(eventId)}/team/${teamId}/player/${playerId}/${parameter}/${value}`),
    setComment: (eventId: number, teamId: number, playerId: number, comment: string) =>
      api.put<{ comment: string }>(`${base(eventId)}/team/${teamId}/player/${playerId}/comment`, { comment }),
    reset: (eventId: number, teamId: number) => api.del<{ reset: boolean, deleted: number }>(`${base(eventId)}/team/${teamId}/reset`),
    overview: (eventId: number) => api.get<ScrutineeringOverview>(`${base(eventId)}/overview`),
  }
}
