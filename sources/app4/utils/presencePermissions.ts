/**
 * Pure permission rules of the presence sheet (DOC/specs/PAGE_PRESENCE.md § 1.2). No Nuxt import: unit-tested with Vitest.
 */
import { canWriteTeamPresence } from './scrutineering'

/** Profile 9 "Table de marque": score sheets and match presence sheets. */
export const SCORER_PROFILE = 9
const MATCH_PRESENCE_WRITE_MAX_PROFILE = 7
/** Copies of a match composition to the other matches of the team: the day, the competition. */
const MATCH_COPY_TO_DAY_MAX_PROFILE = 6
const MATCH_COPY_TO_COMPETITION_MAX_PROFILE = 4

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

/**
 * Match presence sheet edition (inline edit, add, delete, initialize, clear): profiles ≤ 7 and the profile 9, the
 * profile 10 has no access to the match sheets.
 */
export function canWriteMatchPresence(profile: number): boolean {
  return profile <= MATCH_PRESENCE_WRITE_MAX_PROFILE || profile === SCORER_PROFILE
}

/** Copy of a match composition to the matches of the same day: profiles ≤ 6 (api2 applies the same limit). */
export function canCopyMatchToDay(profile: number): boolean {
  return profile <= MATCH_COPY_TO_DAY_MAX_PROFILE
}

/** Copy of a match composition to the matches of the competition: profiles ≤ 4 (api2 applies the same limit). */
export function canCopyMatchToCompetition(profile: number): boolean {
  return profile <= MATCH_COPY_TO_COMPETITION_MAX_PROFILE
}
