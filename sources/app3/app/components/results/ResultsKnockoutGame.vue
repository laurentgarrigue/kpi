<script setup lang="ts">
import { knockoutSides } from '#kpi-layer/utils/results/charts'
import type { ResultsGame } from '#kpi-layer/utils/results/types'

// One game of a phase, the winner first and highlighted (CMP-09).
const props = defineProps<{ game: ResultsGame, competition: string }>()
const sides = computed(() => knockoutSides(props.game))
</script>

<template>
  <div class="flex items-center gap-2 text-sm" :data-game="game.g_id">
    <span class="w-10 shrink-0 text-xs italic text-ink/70">#{{ game.g_number }}</span>
    <ul class="flex-1 space-y-1">
      <li v-for="side in sides" :key="side.side" class="flex items-center justify-between gap-2" :data-winner="side.winner || undefined">
        <ResultsTeamName
          :label="side.label"
          :number="side.side === 'A' ? game.t_a_number : game.t_b_number"
          :competition="competition"
          :strong="side.winner"
        />
        <span
          class="min-w-8 rounded px-2 text-center tabular-nums"
          :class="side.winner ? 'bg-navy text-white' : 'bg-line/50'"
        >{{ side.score ?? '' }}<span v-if="side.winner" class="sr-only"> {{ $t('results.winner') }}</span></span>
      </li>
    </ul>
  </div>
</template>
