<script setup lang="ts">
import type { ResultsGame } from '#kpi-layer/utils/results/types'

// Game status, never conveyed by colour only (CMP-05).
defineProps<{ game: ResultsGame }>()
</script>

<template>
  <span
    class="text-xs font-semibold uppercase"
    :class="{ 'text-kpi-red-700': game.g_status === 'ON', 'text-ink/70': game.g_status !== 'ON' }"
    :data-status="game.g_status"
  >
    {{ $t(`results.status.${game.g_status}`) }}<template v-if="game.g_status === 'ON' && game.g_period"> · {{ game.g_period }}</template>
  </span>
</template>
