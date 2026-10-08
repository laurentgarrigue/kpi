<script setup lang="ts">
import type { ResultsGame } from '#kpi-layer/utils/results/types'
import { pdfUrl, type PdfTarget } from '~/utils/page-links'

// « Games » tab of an event or a group view: the competition page component, plus the competition column (AGG-01).
const props = defineProps<{ gamesPath: string, pdfTarget: PdfTarget, competitionHref: (game: ResultsGame) => string }>()
const context = usePageLinkContext()
const { data: games, error, refresh } = await useApiResource<ResultsGame[]>(() => props.gamesPath)
</script>

<template>
  <p v-if="error" class="text-kpi-red-600" data-testid="results-unavailable">{{ $t('results.unavailable') }}</p>
  <ResultsGamesTab v-else :games="games ?? []" :competition-href="competitionHref" :pdf-href="pdfUrl(pdfTarget, context)" @refresh="refresh" />
</template>
