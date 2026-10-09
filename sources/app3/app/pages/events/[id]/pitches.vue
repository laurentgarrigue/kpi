<script setup lang="ts">
// « Pitches » tab of the view, configured by the parent page (PAGE_EVENT_GROUP.md § 3); `selectedCode` = one competition.
defineOptions({ inheritAttrs: false })
defineProps<{ gamesPath: string, selectedCode?: string }>()
</script>

<template>
  <ResultsAggregatePitches :games-path="gamesPath" :competition="selectedCode" />
</template>
