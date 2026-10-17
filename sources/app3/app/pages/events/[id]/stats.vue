<script setup lang="ts">
import type { CompetitionDetails } from '#kpi-layer/utils/results/types'

// Stats tab of the selected competition, inside the event view (EVT-07); the other attributes of the parent page are for games and pitches.
defineOptions({ inheritAttrs: false })
defineProps<{ competition?: CompetitionDetails }>()
</script>

<template>
  <ResultsTabStats v-if="competition" :competition="competition" />
</template>
