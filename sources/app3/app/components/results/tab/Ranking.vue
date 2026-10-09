<script setup lang="ts">
import { isLive } from '#kpi-layer/utils/results/games'
import type { CompetitionDetails, Ranking, ResultsGame } from '#kpi-layer/utils/results/types'
import { NEW_TAB_ATTRS } from '~/utils/links'
import { pdfUrl } from '~/utils/page-links'

// « Ranking » tab (PAGE_COMPETITION.md § 3.6, CMP-10), refreshed while games are on (CMP-13).
const props = defineProps<{ competition: CompetitionDetails }>()
const { apiPath } = useCompetitionTab(() => props.competition, 'ranking')
const { t } = useI18n()
const context = usePageLinkContext()
const now = useMinuteClock()
const { data: ranking, error, refresh } = await useApiResource<Ranking>(() => apiPath('ranking'))
// Games are only needed in the browser, to know whether the ranking can still change.
const { data: games } = useLazyAsyncData(() => `api2:${apiPath('games')}:live`, () => useApi2()<ResultsGame[]>(apiPath('games')), { server: false })
useAutoRefresh(() => isLive(games.value ?? [], now.value), refresh)

const pdfHref = computed(() => pdfUrl({ kind: 'ranking', type: props.competition.type, season: props.competition.season, competition: props.competition.code }, context.value))
const badge = computed(() => (ranking.value?.status === 'END' ? 'final' : ranking.value?.status === 'ON' ? 'provisional' : null))
</script>

<template>
  <p v-if="error || !ranking" class="text-kpi-red-600" data-testid="results-unavailable">{{ $t('results.unavailable') }}</p>
  <div v-else class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <UBadge v-if="badge" :color="badge === 'final' ? 'success' : 'warning'" variant="subtle" size="lg" data-testid="ranking-badge">
        {{ $t(`results.ranking.${badge}`) }}
      </UBadge>
      <a :href="pdfHref" v-bind="NEW_TAB_ATTRS" class="flex items-center gap-1 text-sm underline" data-testid="ranking-pdf">
        <UIcon name="i-heroicons-document-arrow-down" class="size-4" aria-hidden="true" />
        {{ $t('results.rankingPdf') }}<span class="sr-only"> {{ $t('a11y.newTab') }}</span>
      </a>
    </div>
    <p v-if="ranking.rows.length === 0" data-testid="no-ranking">{{ $t('results.rankingLater') }}</p>
    <ResultsRankingTable
      v-else
      :rows="ranking.rows"
      :type="ranking.type"
      :competition="competition.code"
      :qualified="ranking.qualified"
      :eliminated="ranking.eliminated"
      :caption="t('results.tab.ranking')"
    />
  </div>
</template>
