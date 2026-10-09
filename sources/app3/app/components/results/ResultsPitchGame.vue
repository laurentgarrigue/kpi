<script setup lang="ts">
import type { ResultsGame } from '#kpi-layer/utils/results/types'

// Compact game of the pitch grid: category and status on the top line, then each team with its score at the
// right of its own line (CMP-07).
const props = defineProps<{ game: ResultsGame, showCompetition?: boolean }>()
const category = computed(() => (props.showCompetition ? props.game.c_label ?? props.game.c_code : props.game.c_label))
</script>

<template>
  <div class="rounded border border-line p-2" :data-game="game.g_id">
    <p class="flex items-center justify-between gap-2" data-testid="pitch-top">
      <span class="text-xs font-semibold text-kpi-blue-600" data-testid="pitch-category">{{ category }}</span>
      <ResultsGameStatus :game="game" />
    </p>
    <p class="flex items-baseline justify-between gap-2" data-testid="pitch-team">
      <ResultsTeamName :label="game.t_a_label" :number="game.t_a_number" :competition="game.c_code" :season="game.c_season" />
      <ResultsSideScore :game="game" side="A" />
    </p>
    <p class="flex items-baseline justify-between gap-2" data-testid="pitch-team">
      <ResultsTeamName :label="game.t_b_label" :number="game.t_b_number" :competition="game.c_code" :season="game.c_season" />
      <ResultsSideScore :game="game" side="B" />
    </p>
  </div>
</template>
