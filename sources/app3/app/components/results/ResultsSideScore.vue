<script setup lang="ts">
import { hasScore, isProvisional, winnerSide } from '#kpi-layer/utils/results/games'
import type { ResultsGame, Side } from '#kpi-layer/utils/results/types'

// Score of one team, shown at the right of its line: provisional in italic, the winner in bold (CMP-05, CMP-07).
const props = defineProps<{ game: ResultsGame, side: Side }>()
const score = computed(() => (props.side === 'A' ? props.game.g_score_a : props.game.g_score_b))
const coefficient = computed(() => (props.side === 'A' ? props.game.g_coef_a : props.game.g_coef_b))
const provisional = computed(() => isProvisional(props.game))
const winner = computed(() => winnerSide(props.game) === props.side)
</script>

<template>
  <span
    class="inline-flex min-w-6 items-baseline justify-end gap-1 tabular-nums"
    :class="{ 'italic': provisional, 'font-bold': winner, 'font-semibold': !winner }"
    data-testid="side-score"
  >
    <template v-if="hasScore(game)">
      <span v-if="coefficient !== 1" class="text-xs font-normal text-ink/70">×{{ coefficient }}</span>
      {{ score }}
      <span v-if="provisional" class="sr-only">{{ $t('results.provisional') }}</span>
    </template>
  </span>
</template>
