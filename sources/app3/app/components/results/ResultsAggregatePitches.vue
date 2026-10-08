<script setup lang="ts">
import type { ResultsGame } from '#kpi-layer/utils/results/types'

// « Pitches » tab of an event or a group view (AGG-01).
const props = defineProps<{ gamesPath: string }>()
const { data: games, error, refresh } = await useApiResource<ResultsGame[]>(() => props.gamesPath)
</script>

<template>
  <p v-if="error" class="text-kpi-red-600" data-testid="results-unavailable">{{ $t('results.unavailable') }}</p>
  <ResultsPitchesTab v-else :games="games ?? []" show-competition @refresh="refresh" />
</template>
