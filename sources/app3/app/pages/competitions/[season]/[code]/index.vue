<script setup lang="ts">
import type { CompetitionDetails } from '#kpi-layer/utils/results/types'
import { competitionPath, defaultTab } from '~/utils/competitions'

// /competitions/{season}/{code} → default tab: ranking when finished, games otherwise (CMP-01).
defineOptions({ inheritAttrs: false })
const props = defineProps<{ competition: CompetitionDetails }>()
const route = useRoute()
const localePath = useLocalePath()
await navigateTo(
  { path: localePath(competitionPath(props.competition.season, props.competition.code, defaultTab(props.competition.status))), query: route.query },
  { redirectCode: 302 },
)
</script>

<template>
  <div />
</template>
