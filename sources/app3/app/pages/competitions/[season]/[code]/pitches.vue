<script setup lang="ts">
import type { CompetitionDetails, ResultsGame } from '#kpi-layer/utils/results/types'

// « Pitches » tab (PAGE_COMPETITION.md § 3.2, CMP-07).
const props = defineProps<{ competition: CompetitionDetails }>()
const { apiPath } = useCompetitionTab(() => props.competition, 'pitches')
const { data: games, error, refresh } = await useApiResource<ResultsGame[]>(() => apiPath('games'))
</script>

<template>
  <p v-if="error" class="text-kpi-red-600" data-testid="results-unavailable">{{ $t('results.unavailable') }}</p>
  <ResultsPitchesTab v-else :games="games ?? []" @refresh="refresh" />
</template>
