import type { CompetitionDetails } from '#kpi-layer/utils/results/types'
import type { CompetitionTab } from '~/utils/competitions'

/** Common to every competition tab: api2 path prefix and page title « {tab} — {competition} {season} » (§ 5). */
export function useCompetitionTab(competition: () => CompetitionDetails, tab: CompetitionTab) {
  const { t } = useI18n()
  const route = useRoute()
  const apiPath = (suffix: string) => `/competition/${competition().season}/${competition().code}/${suffix}`
  const eventId = computed(() => (route.query.event ? String(route.query.event) : undefined))

  useSeoMeta({
    title: () => t('results.tabTitle', { tab: t(`results.tab.${tab}`), competition: competition().display_title, season: competition().season }),
    description: () => t(`results.tabDescription.${tab}`, { competition: competition().display_title, season: competition().season }),
  })

  return { apiPath, eventId }
}
