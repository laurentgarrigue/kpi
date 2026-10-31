<script setup lang="ts">
import type { ResultsGame, Side } from '#kpi-layer/utils/results/types'

// One team of a game: logo and name (link to its page), or the draw slot of a team not assigned yet (CMP-19, CMP-20).
const props = defineProps<{ game: ResultsGame, side: Side, winner: boolean }>()
const { label, number, logo, waiting } = useGameSide(() => props.game, () => props.side)
</script>

<template>
  <span class="flex min-w-0 items-center gap-2.5">
    <template v-if="waiting">
      <span class="truncate italic text-muted" data-testid="draw-slot">{{ waiting }}</span>
    </template>
    <template v-else>
      <ResultsTeamLogo :path="logo" />
      <ResultsTeamName
        class="truncate"
        :label="label"
        :number="number"
        :competition="game.c_code"
        :season="game.c_season"
        :strong="winner"
      />
    </template>
  </span>
</template>
