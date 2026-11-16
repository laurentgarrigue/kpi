/**
 * Pure permission rules of the presence sheet (DOC/specs/PAGE_PRESENCE.md § 1.2). No Nuxt import: unit-tested with Vitest.
 */
import { canWriteTeamPresence } from './scrutineering'

/** State of the team presence sheet that conditions the writes. */
export interface TeamSheetAccess {
  /** Competition locked (`kp_competition.Verrou = 'O'`) */
  locked: boolean
  /** Club and competition perimeter check returned by api2 (`canEdit` of GET /admin/teams/{id}/players) */
  inPerimeter: boolean
}

/**
 * Copy of the composition from another competition (team mode): profiles ≤ 7 and the profile 10, on a team of
 * their perimeter, competition not locked — the same rule as the other writes of the team sheet.
 */
export function canCopyTeamComposition(profile: number, sheet: TeamSheetAccess): boolean {
  return !sheet.locked && sheet.inPerimeter && canWriteTeamPresence(profile)
}
