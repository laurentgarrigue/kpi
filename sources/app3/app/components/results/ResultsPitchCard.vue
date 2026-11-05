<script setup lang="ts">
import { isFinal } from '#kpi-layer/utils/results/games'
import type { ResultsGame } from '#kpi-layer/utils/results/types'

// One game of the pitches board (CMP-17): category (and phase for a cup), number, then the duel; its pitch when the
// board is a single column (`withPitch`, always on small screens); the final edged in gold.
const props = defineProps<{ game: ResultsGame, showCompetition?: boolean, withPitch?: boolean }>()
const cups = useCupCompetitions()
const label = computed(() => (props.showCompetition ? props.game.c_label ?? props.game.c_code : props.game.c_label))
const phase = computed(() => (cups.value.has(props.game.c_code) ? props.game.d_phase : null))
const final = computed(() => isFinal(props.game))
</script>

<template>
  <article
    class="min-w-0 rounded-lg border bg-surface px-2.5 pt-2 pb-0.5"
    :class="final ? 'border-medal-gold shadow-[inset_3px_0_0_var(--color-medal-gold)]' : 'border-line'"
    :data-game="game.g_id"
    :data-final="final ? '' : undefined"
  >
    <p class="flex justify-between gap-2 text-xs">
      <span class="truncate font-bold" :class="final ? 'text-medal-gold' : 'text-kpi-blue-700'">
        <span class="mr-1 text-ink" :class="withPitch ? '' : 'hidden max-[820px]:inline'" data-testid="card-pitch">{{ $t('results.pitch', { pitch: game.g_pitch ?? '' }) }}</span>
        <span data-testid="pitch-category">{{ label }} <span v-if="phase" class="font-normal text-muted">{{ phase }}</span></span>
      </span>
      <span v-if="game.g_number" class="shrink-0 text-muted tabular-nums" data-testid="pitch-number">{{ $t('results.numberShort', { number: game.g_number }) }}</span>
    </p>
    <ResultsDuel :game="game" untagged />
  </article>
</template>
