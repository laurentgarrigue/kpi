import type { Ref } from 'vue'
import type { ScrutineeringTeam } from '~/types/scrutineering'
import { adjacentTeams, canManageScrutineering, resolveContext, sameClubTeams, teamsByCompetition } from '~/utils/scrutineering'

export type TeamView = 'presence' | 'scrutineering'

const VIEW_PATHS: Record<TeamView, string> = { presence: '/presence/team', scrutineering: '/scrutineering/team' }

export function teamViewPath(view: TeamView, teamId: number): string {
  return `${VIEW_PATHS[view]}/${teamId}`
}

/**
 * Navigation between the teams of the context (PAGE_SCRUTINEERING.md § 1.2 and 1.3: event of the page filter, else page
 * competition): selector, previous / next (also with ← / →), same club / nation, and the presence ⇄ scrutineering switch. Profiles 1–2 and 10 only.
 */
/** `keyboardPaused` is true while a modal of the page is open, so ← / → do not leave the page. */
export function useScrutineeringNavigation(teamId: Ref<number>, view: TeamView, keyboardPaused: Ref<boolean> = ref(false)) {
  const authStore = useAuthStore()
  const workContext = useWorkContextStore()
  const scrutineeringApi = useScrutineeringApi()
  const router = useRouter()

  const context = computed(() => resolveContext(workContext.pageEventGroupSelection, workContext.season, workContext.pageCompetitionCode))
  const enabled = computed(() => canManageScrutineering(authStore.profile) && context.value !== null)
  const teams = ref<ScrutineeringTeam[]>([])

  const current = computed(() => teams.value.find(team => team.team_id === teamId.value) ?? null)
  const groups = computed(() => teamsByCompetition(teams.value))
  const adjacent = computed(() => adjacentTeams(teams.value, teamId.value))
  const sameClub = computed(() => sameClubTeams(teams.value, teamId.value))

  async function load() {
    if (!enabled.value || context.value === null) {
      teams.value = []
      return
    }
    try {
      teams.value = await scrutineeringApi.teams(context.value)
    }
    catch {
      teams.value = [] // the navigation is a convenience: the page stays usable without it
    }
  }

  function go(targetTeamId: number, targetView: TeamView = view) {
    if (targetTeamId !== teamId.value || targetView !== view) {
      router.push(teamViewPath(targetView, targetTeamId))
    }
  }

  /** First team of a competition of the season (competition filter of the page changed); false when it has none. */
  async function goToCompetition(competition: string): Promise<boolean> {
    const first = (await scrutineeringApi.teams({ season: workContext.season, competition }))[0]
    if (first) {
      go(first.team_id)
    }
    return first !== undefined
  }

  function onKeydown(event: KeyboardEvent) {
    const target = event.target as HTMLElement | null
    if (keyboardPaused.value || event.altKey || event.ctrlKey || event.metaKey || target?.closest('input, textarea, select, [contenteditable]')) {
      return
    }
    const destination = event.key === 'ArrowLeft' ? adjacent.value.previous : event.key === 'ArrowRight' ? adjacent.value.next : null
    if (destination) {
      event.preventDefault()
      go(destination.team_id)
    }
  }

  // Same context with a new value (another event or competition) reloads too
  watch(() => JSON.stringify(context.value), load)
  onMounted(() => {
    load()
    window.addEventListener('keydown', onKeydown)
  })
  onUnmounted(() => window.removeEventListener('keydown', onKeydown))

  return { context, enabled, teams, current, groups, adjacent, sameClub, go, goToCompetition, load }
}
