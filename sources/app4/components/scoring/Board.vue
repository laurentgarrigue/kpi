<script setup lang="ts">
import type { TeamSide } from '~/types/scoring'

/**
 * The console's display panel: a replica of the pitch scoreboard (home score | game clock |
 * away score, shot clock under the clock), black in both themes because that is what the
 * public sees. Presentational only — the controls live in the deck right under it.
 *
 * `clock` is null in post-match (no live clock): the centre then only carries the state line.
 */
defineProps<{
  teamA: string
  teamB: string
  scoreA: number | string
  scoreB: number | string
  clock: string | null
  clockRunning?: boolean
  shotclock?: string | null
  shotclockState?: 'IDLE' | 'RUNNING' | 'SUSPENDED'
  breakDisplay?: string | null
  /** Score-only mode: ±1 under each score, the board becomes the whole entry surface. */
  scoreOnly?: boolean
  canScore?: boolean
}>()

const emit = defineEmits<{ bump: [team: TeamSide, delta: 1 | -1] }>()

const { t } = useI18n()
</script>

<template>
  <!-- `dark`: the board is black in both themes, so the controls slotted in it (period,
       status) must render with the dark tokens even when the admin is in light mode. -->
  <section class="board dark rounded-t-xl bg-black text-white px-4 pt-3 pb-4 sm:px-6">
    <!-- State line: period + status (slot — both are clickable controls) -->
    <div class="flex items-center justify-center gap-2 flex-wrap">
      <slot name="state" />
    </div>

    <div class="mt-2 grid grid-cols-[1fr_auto_1fr] items-end gap-3 sm:gap-10">
      <!-- Team A: name over score, pushed towards the centre -->
      <div class="min-w-0 text-right">
        <div class="board-team line-clamp-2 break-words">{{ teamA }}</div>
        <div class="scoring-digits board-score">{{ scoreA }}</div>
        <div v-if="scoreOnly" class="mt-1 flex justify-end gap-1">
          <button type="button" class="board-key" :disabled="!canScore" @click="emit('bump', 'A', -1)">−1</button>
          <button type="button" class="board-key board-key--strong" :disabled="!canScore" @click="emit('bump', 'A', 1)">+1</button>
        </div>
      </div>

      <!-- Centre: game clock, then shot clock (or the inter-period break) -->
      <div class="flex flex-col items-center pb-1">
        <div
          v-if="clock !== null"
          class="scoring-digits board-clock"
          :class="clockRunning ? 'text-green-400' : 'text-white'"
        >{{ clock }}</div>
        <!-- Post-match: no clock, the scores still need their separator -->
        <div v-else class="scoring-digits board-score text-white/40" aria-hidden="true">–</div>
        <div v-if="breakDisplay" class="mt-1 flex items-baseline gap-2 text-amber-400">
          <span class="text-sm">{{ t('scoring.break.title') }}</span>
          <span class="scoring-digits text-3xl">{{ breakDisplay }}</span>
        </div>
        <div v-else-if="shotclock !== undefined && shotclock !== null" class="mt-1 flex items-baseline gap-2">
          <span class="text-sm text-white/60">{{ t('scoring.shotclock.short') }}</span>
          <span
            class="scoring-digits text-4xl sm:text-5xl leading-none"
            :class="shotclockState === 'RUNNING' ? 'text-amber-400'
              : shotclockState === 'SUSPENDED' ? 'text-amber-400/70' : 'text-white/30'"
          >{{ shotclock }}</span>
        </div>
      </div>

      <!-- Team B: mirrored -->
      <div class="min-w-0 text-left">
        <div class="board-team line-clamp-2 break-words">{{ teamB }}</div>
        <div class="scoring-digits board-score">{{ scoreB }}</div>
        <div v-if="scoreOnly" class="mt-1 flex gap-1">
          <button type="button" class="board-key board-key--strong" :disabled="!canScore" @click="emit('bump', 'B', 1)">+1</button>
          <button type="button" class="board-key" :disabled="!canScore" @click="emit('bump', 'B', -1)">−1</button>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
/* Team names in the admin's condensed display face: it reads like scoreboard lettering. */
.board-team {
  font-family: 'Agency FB', 'Arial Narrow', Arial, sans-serif;
  font-size: clamp(1.1rem, 2.4vw, 1.75rem);
  line-height: 1.1;
  color: rgb(255 255 255 / 0.85);
}
.board-score {
  font-size: clamp(3.5rem, 9vw, 6.5rem);
  line-height: 0.95;
}
.board-clock {
  font-size: clamp(2.75rem, 7vw, 5.25rem);
  line-height: 1;
}
.board-key {
  min-width: 2.5rem;
  padding: 0.25rem 0.5rem;
  border-radius: 0.375rem;
  border: 1px solid rgb(255 255 255 / 0.35);
  font-variant-numeric: tabular-nums;
}
.board-key--strong {
  background: white;
  color: black;
  border-color: white;
}
.board-key:disabled {
  opacity: 0.4;
}
.board-key:focus-visible {
  outline: 2px solid var(--ui-primary);
  outline-offset: 2px;
}
</style>
