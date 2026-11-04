<script setup lang="ts">
import type { CompetitionDetails, CompetitionInfo, Ranking } from '#kpi-layer/utils/results/types'

// « Info » tab (PAGE_COMPETITION.md § 3.3, CMP-08), with SportsEvent structured data (§ 5).
const props = defineProps<{ competition: CompetitionDetails, subscriptionUrl?: string }>()
const { apiPath } = useCompetitionTab(() => props.competition, 'info')
const { data: info, error } = await useApiResource<CompetitionInfo>(() => apiPath('info'))
// Podium of a finished competition, next to the infos (CMP-23).
const { data: ranking } = await useApiResource<Ranking>(
  () => (props.competition.status === 'END' ? apiPath('ranking') : null),
  { notFoundIsError: false },
)
const route = useRoute()
const rankingHref = computed(() => ({ path: route.path.replace(/\/info$/, '/ranking'), query: route.query }))

useHead(() => {
  const gamedays = info.value?.gamedays ?? []
  if (gamedays.length === 0) {
    return {}
  }
  const sportsEvent = {
    '@context': 'https://schema.org',
    '@type': 'SportsEvent',
    'name': `${props.competition.display_title} ${props.competition.season}`,
    'sport': 'Canoe polo',
    'startDate': gamedays[0]!.start,
    'endDate': gamedays.at(-1)!.end,
    'location': { '@type': 'Place', 'name': gamedays[0]!.place },
  }
  return { script: [{ type: 'application/ld+json', innerHTML: JSON.stringify(sportsEvent) }] }
})
</script>

<template>
  <p v-if="error || !info" class="text-kpi-red-600" data-testid="results-unavailable">{{ $t('results.unavailable') }}</p>
  <ResultsInfo
    v-else
    :info="info"
    :competition="competition"
    :subscription-url="subscriptionUrl"
    :podium="ranking?.rows.filter(row => row.medal) ?? []"
    :ranking-href="rankingHref"
  />
</template>
