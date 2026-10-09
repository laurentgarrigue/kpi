<script setup lang="ts">
import type { RankingMark } from '#kpi-layer/utils/results/ranking'

// Medal, qualified or eliminated mark: colour AND text (CPL-06, CPL-10, CMP-10).
defineProps<{ mark: RankingMark }>()
const MEDAL_CLASSES = { 1: 'bg-kpi-gold-400 text-ink', 2: 'bg-line text-ink', 3: 'bg-kpi-gold-800 text-white' } as const
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
