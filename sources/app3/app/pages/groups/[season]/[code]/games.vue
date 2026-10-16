<script setup lang="ts">
import type { ResultsGame } from '#kpi-layer/utils/results/types'
import type { PdfTarget } from '~/utils/page-links'

// « Games » tab of the view, configured by the parent page (PAGE_EVENT_GROUP.md § 3).
defineProps<{ gamesPath: string, pdfTarget: PdfTarget, competitionHref: (game: ResultsGame) => string }>()
</script>

<template>
  <ResultsAggregateGames :games-path="gamesPath" :pdf-target="pdfTarget" :competition-href="competitionHref" />
</template>
