<script setup lang="ts">
import { hasScore, isProvisional, winnerSide } from '#kpi-layer/utils/results/games'
import type { ResultsGame } from '#kpi-layer/utils/results/types'

// Score « 3 – 1 », read « 3 à 1 », provisional until validated; the winning score is in bold (CMP-05).
const props = defineProps<{ game: ResultsGame }>()
const provisional = computed(() => isProvisional(props.game))
const winner = computed(() => winnerSide(props.game))
const weight = (side: 'A' | 'B') => (winner.value === side ? 'font-bold' : 'font-normal')
const coefficients = computed(() => (props.game.g_coef_a !== 1 || props.game.g_coef_b !== 1)
  ? `×${props.game.g_coef_a} / ×${props.game.g_coef_b}`
  : null)
</script>

<template>
  <span class="inline-flex flex-col items-center tabular-nums" data-testid="score">
    <template v-if="hasScore(game)">
      <span :class="{ italic: provisional }">
        <span aria-hidden="true">
          <span :class="weight('A')" data-side="A">{{ game.g_score_a }}</span> – <span :class="weight('B')" data-side="B">{{ game.g_score_b }}</span>
        </span>
        <span class="sr-only">{{ $t('results.scoreA11y', { a: game.g_score_a, b: game.g_score_b }) }}</span>
      </span>
      <span v-if="provisional" class="text-xs italic" data-testid="provisional">{{ $t('results.provisional') }}</span>
    </template>
    <span v-else aria-hidden="true">–</span>
    <span v-if="coefficients" class="text-xs text-ink/70">{{ coefficients }}</span>
  </span>
</template>
