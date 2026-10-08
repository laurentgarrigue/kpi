<script setup lang="ts">
import type { CompetitionDetails, ResultsGame } from '#kpi-layer/utils/results/types'
import { pdfUrl } from '~/utils/page-links'

// « Games » tab (PAGE_COMPETITION.md § 3.1, CMP-05/06/13).
const props = defineProps<{ competition: CompetitionDetails }>()
const { apiPath, eventId } = useCompetitionTab(() => props.competition, 'games')
const context = usePageLinkContext()
const { data: games, error, refresh } = await useApiResource<ResultsGame[]>(() => apiPath('games'))
const pdfHref = computed(() => pdfUrl({ kind: 'games', season: props.competition.season, competitions: [props.competition.code] }, context.value))
</script>

<template>
  <p v-if="error" class="text-kpi-red-600" data-testid="results-unavailable">{{ $t('results.unavailable') }}</p>
  <ResultsGamesTab
    v-else
    :games="games ?? []"
    :with-gamedays="competition.type === 'CHPT'"
    :pdf-href="pdfHref"
    :hidden-fields="eventId ? { event: eventId } : undefined"
    @refresh="refresh"
  />
</template>
