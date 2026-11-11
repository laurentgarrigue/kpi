<script setup lang="ts">
import { svgWithUniqueIds, type MedalItem } from '~/utils/medals'

// Pictogram of a medal, label or statistic (FEATURE_MATCH_MEDALS.md § 7.1), drawn by api2 in `currentColor`.
const props = withDefaults(defineProps<{ item: MedalItem, size?: number }>(), { size: 20 })
const id = useId()
const markup = computed(() => svgWithUniqueIds(props.item.icon_svg, id.replace(/[^a-z0-9-]/gi, '')))
</script>

<template>
  <!-- eslint-disable-next-line vue/no-v-html -- markup of the api2 catalogue (static pictograms), not user content -->
  <svg :width="size" :height="size" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="shrink-0" v-html="markup" />
</template>
