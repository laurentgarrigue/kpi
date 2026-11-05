import { chartRounds } from '#kpi-layer/utils/results/charts'
import type { CompetitionChart } from '#kpi-layer/utils/results/types'

/** Dims every game and standing row not of the followed team (container classes, while following). */
export const FOLLOWING_CLASSES = '[&_[data-game]:not([data-followed])]:opacity-40 [&_tbody_tr:not([data-followed])]:opacity-40'

/**
 * The team whose path is highlighted in the progress tab (CMP-24 to CMP-26), by its number: touching it again
 * stops following it.
 */
export function useFollowedTeam(chart: () => CompetitionChart) {
  const followed = ref<number | null>(null)
  const follow = (team: number) => {
    followed.value = followed.value === team ? null : team
  }
  const unfollow = () => {
    followed.value = null
  }
  const label = computed(() => {
    const team = chartRounds(chart()).flatMap(round => round.phases).flatMap(phase => [
      ...(phase.teams ?? []).map(item => ({ number: item.t_number, label: item.t_label })),
      ...(phase.games ?? []).flatMap(game => [{ number: game.t_a_number, label: game.t_a_label }, { number: game.t_b_number, label: game.t_b_label }]),
    ]).find(item => item.number === followed.value)
    return team?.label ?? ''
  })
  return { followed, follow, unfollow, label }
}
