import type { Ref, ComputedRef } from 'vue'

/**
 * Permission management for the Scoring console.
 * Mirrors usePresencePermissions (profile thresholds + lock state).
 *
 * Access: profiles 1 to 6 and profile 9 "Table de marque". Validation / lock stays
 * reserved to profiles <= 6. Server side: ScoringController (ROLE_ORGANIZER or level 9).
 * See DOC/specs/PAGE_SCORING.md §6.3.
 */

/** Highest profile of the management hierarchy allowed on Scoring */
const SCORING_MANAGER_MAX_PROFILE = 6
/** "Table de marque" profile, outside the linear hierarchy */
const SCORING_TABLE_PROFILE = 9

export function useScoringPermissions(isLocked: Ref<boolean> | ComputedRef<boolean>) {
  const authStore = useAuthStore()

  const isManager = computed(() => authStore.profile <= SCORING_MANAGER_MAX_PROFILE)
  const hasAccess = computed(() => isManager.value || authStore.profile === SCORING_TABLE_PROFILE)

  // View console + see the "Scoring" link in /games
  const canView = computed(() => hasAccess.value)

  // Enter goals / cards / control the timer
  const canScore = computed(() => hasAccess.value && !isLocked.value)

  // Manage players (status, numbers)
  const canManagePlayers = computed(() => hasAccess.value && !isLocked.value)

  // Validate / lock the match (reuses AdminGames toggleValidation)
  const canValidate = computed(() => isManager.value)
  const canLock = computed(() => isManager.value)

  return {
    canView,
    canScore,
    canManagePlayers,
    canValidate,
    canLock
  }
}
