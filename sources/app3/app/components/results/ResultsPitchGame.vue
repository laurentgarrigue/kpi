<script setup lang="ts">
import type { ResultsGame } from '#kpi-layer/utils/results/types'

// Compact game of the pitch grid: teams, score, status.
defineProps<{ game: ResultsGame, showCompetition?: boolean }>()
</script>

<template>
  <div class="rounded border border-line p-2" :data-game="game.g_id">
    <p v-if="showCompetition" class="text-xs font-semibold text-kpi-blue-600">{{ game.c_code }}</p>
    <p><ResultsTeamName :label="game.t_a_label" :number="game.t_a_number" :competition="game.c_code" /></p>
    <p><ResultsTeamName :label="game.t_b_label" :number="game.t_b_number" :competition="game.c_code" /></p>
    <p class="flex items-center justify-between gap-2"><ResultsScore :game="game" /><ResultsGameStatus :game="game" /></p>
  </div>
</template>
