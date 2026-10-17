<script setup lang="ts">
import type { CompetitionDetails } from '#kpi-layer/utils/results/types'

// Tab of the competition page: the content is shared with the event view (ResultsTabProgress).
defineProps<{ competition: CompetitionDetails }>()
</script>

<template>
  <ResultsTabProgress :competition="competition" />
</template>
