<script setup lang="ts">
// « Pitches » tab of the view, configured by the parent page (PAGE_EVENT_GROUP.md § 3).
defineOptions({ inheritAttrs: false })
defineProps<{ gamesPath: string }>()
</script>

<template>
  <ResultsAggregatePitches :games-path="gamesPath" />
</template>
