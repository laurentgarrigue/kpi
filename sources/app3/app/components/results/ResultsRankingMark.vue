<script setup lang="ts">
import type { RankingMark } from '#kpi-layer/utils/results/ranking'

// Medal, qualified or eliminated mark: colour AND text (CPL-06, CPL-10, CMP-10).
defineProps<{ mark: RankingMark }>()
// Medal colours: gold only for the gold medal (DOC/specs/public/maquettes/README.md), each with its digit.
const MEDAL_CLASSES = { 1: 'bg-medal-gold text-white dark:text-navy', 2: 'bg-medal-silver text-white dark:text-navy', 3: 'bg-medal-bronze text-white dark:text-navy' } as const
</script>

<template>
  <span v-if="mark?.kind === 'medal'" class="inline-flex size-6 items-center justify-center rounded-full text-xs font-bold" :class="MEDAL_CLASSES[mark.medal]" :data-medal="mark.medal">
    <span aria-hidden="true">{{ mark.medal }}</span>
    <span class="sr-only">{{ $t(`results.medal.${mark.medal}`) }}</span>
  </span>
  <span v-else-if="mark?.kind === 'qualified'" class="text-kpi-green-700" data-mark="qualified">
    <UIcon name="i-heroicons-arrow-up-circle" class="size-5" aria-hidden="true" />
    <span class="sr-only">{{ $t('results.qualified') }}</span>
  </span>
  <span v-else-if="mark?.kind === 'eliminated'" class="text-kpi-red-700" data-mark="eliminated">
    <UIcon name="i-heroicons-arrow-down-circle" class="size-5" aria-hidden="true" />
    <span class="sr-only">{{ $t('results.eliminated') }}</span>
  </span>
</template>
