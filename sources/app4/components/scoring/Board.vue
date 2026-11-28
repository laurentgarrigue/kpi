<script setup lang="ts">
import type { PenaltyClock } from '~/composables/usePenalties'
import type { TeamSide } from '~/types/scoring'
import type { Sides } from '~/utils/scoringSides'

/**
 * The console's display panel: a replica of the pitch scoreboard (home score | game clock |
 * away score, shot clock under the clock), black in both themes because that is what the
 * public sees. Presentational only — the controls live in the deck right under it.
 *
 * The game clock is green while it runs, red when it is stopped or at zero (the operator
 * sees at a glance that time is not running).
 *
 * `clock` is null in post-match (no live clock): the centre then only carries the state line.
 * Running penalties sit under each team's score, as on the pitch scoreboard: the operator
 * watches one place for every countdown (game clock, shot clock, exclusions).
 *
 * Every countdown is corrected the same way: click on it → ScoringTimeAdjust popover (±10/±1 s
 * around a live copy of the value). No ± keys in the deck any more.
 *
 * Pitch sides (§0.18): each team sits on the half it occupies, seen from the table (`sides`), and
 * carries its letter A / B (D16) — the operator reads the teams by their game-sheet letter. The
 * page passes a change-of-ends notice, shown until the clock starts.
 */
const props = withDefaults(defineProps<{
  teamA: string
  teamB: string
  scoreA: number | string
  scoreB: number | string
  clock: string | null
  clockRunning?: boolean
  shotclock?: string | null
  shotclockState?: 'IDLE' | 'RUNNING' | 'SUSPENDED'
  breakDisplay?: string | null
  /** Running penalties of each team (live mode only). */
  penaltiesA?: PenaltyClock[]
  penaltiesB?: PenaltyClock[]
  /** Penalty countdowns can be corrected (game clock stopped). */
  canAdjustPenalties?: boolean
  /** Score-only mode: ±1 under each score, the board becomes the whole entry surface. */
  scoreOnly?: boolean
  canScore?: boolean
  /** Game clock and shot clock open their fine adjust on click (live, match in progress). */
  canOpenClockAdjust?: boolean
  /** Game clock correctable now (stopped, unless the competition allows it running). */
  clockAdjustable?: boolean
  /** Shot clock correctable now (suspended: game clock stopped). */
  shotclockAdjustable?: boolean
  /** Which half team A occupies in the current period (§0.18). */
  sides?: Sides
  /** Letters A / B next to the names (the console); off for a public look. */
  showLetters?: boolean
  /** Change-of-ends notice (D16), shown under the state line. */
  sidesNotice?: string | null
}>(), {
  shotclock: null,
  shotclockState: 'IDLE',
  breakDisplay: null,
  penaltiesA: () => [],
  penaltiesB: () => [],
  sides: 'A_LEFT',
  showLetters: true,
  sidesNotice: null
})

const emit = defineEmits<{
  bump: [team: TeamSide, delta: 1 | -1]
  'remove-penalty': [id: string]
  'adjust-penalty': [id: string, deltaSec: number]
  'adjust-clock': [deltaSec: number]
  'adjust-shotclock': [deltaSec: number]
}>()

const { t } = useI18n()

/** Left then right half: the team, its name, score and penalties, and which way it is aligned. */
const halves = computed(() => {
  const a = { team: 'A' as TeamSide, name: props.teamA, score: props.scoreA, penalties: props.penaltiesA }
  const b = { team: 'B' as TeamSide, name: props.teamB, score: props.scoreB, penalties: props.penaltiesB }
  const [left, right] = props.sides === 'A_LEFT' ? [a, b] : [b, a]
  return { left, right }
})

/** The game clock also takes whole minutes (a period started late, a forgotten stop). */
const CLOCK_STEPS = [-60, -10, -1, 1, 10, 60] as const
</script>

<template>
  <!-- `dark`: the board is black in both themes, so the controls slotted in it (period,
       status) must render with the dark tokens even when the admin is in light mode. -->
  <section class="board dark rounded-t-xl bg-black text-white px-4 pt-3 pb-4 sm:px-6">
    <!-- State line: period + status (slot — both are clickable controls) -->
    <div class="flex items-center justify-center gap-2 flex-wrap">
      <slot name="state" />
    </div>
    <!-- Change of ends (§0.18 D16): stays until the clock starts -->
    <p
      v-if="sidesNotice"
      role="status"
      class="mt-2 mx-auto w-fit rounded-md bg-amber-500 px-3 py-1 text-sm font-semibold text-black flex items-center gap-2"
    >
      <UIcon name="i-heroicons-arrows-right-left" class="size-4 shrink-0" />
      {{ sidesNotice }}
    </p>

    <div class="mt-2 grid grid-cols-[1fr_auto_1fr] items-start gap-3 sm:gap-10">
      <!-- Left half: name over score, pushed towards the centre -->
      <div class="board-side min-w-0 text-right">
        <div class="flex items-baseline justify-end gap-2">
          <div class="board-team line-clamp-2 break-words">{{ halves.left.name }}</div>
          <span v-if="showLetters" class="board-letter">{{ halves.left.team }}</span>
        </div>
        <div class="scoring-digits board-score">{{ halves.left.score }}</div>
        <ScoringPenalties
          v-if="halves.left.penalties?.length"
          class="mt-1 justify-end"
          :penalties="halves.left.penalties"
          :can-score="canScore"
          :can-adjust="canAdjustPenalties"
          @remove="emit('remove-penalty', $event)"
          @adjust="(id, d) => emit('adjust-penalty', id, d)"
        />
        <div v-if="scoreOnly" class="mt-1 flex justify-end gap-1">
          <button type="button" class="board-key" :disabled="!canScore" @click="emit('bump', halves.left.team, -1)">−1</button>
          <button type="button" class="board-key board-key--strong" :disabled="!canScore" @click="emit('bump', halves.left.team, 1)">+1</button>
        </div>
      </div>

      <!-- Centre: game clock, then shot clock (or the inter-period break) -->
      <div class="flex flex-col items-center pb-1">
        <!-- Size and colour on a wrapper: this component's scoped styles do not reach the
             trigger rendered by ScoringTimeAdjust, which inherits them instead. No `bg-white…`
             class on the triggers: admin.css forces dark text on those in light mode. -->
        <div
          v-if="clock !== null"
          class="scoring-digits board-clock"
          :class="clockRunning ? 'text-green-700' : 'text-red-700'"
        >
          <ScoringTimeAdjust
            :display="clock"
            :steps="CLOCK_STEPS"
            :disabled="!canOpenClockAdjust"
            :can-adjust="clockAdjustable"
            :locked-hint="t('scoring.timer.adjust_locked')"
            :label="t('scoring.timer.adjust')"
            trigger-class="px-2 hover:bg-header-50/10"
            @adjust="emit('adjust-clock', $event)"
          >{{ clock }}</ScoringTimeAdjust>
        </div>
        <!-- Post-match: no clock, the scores still need their separator -->
        <div v-else class="scoring-digits board-score text-white/40" aria-hidden="true">–</div>
        <div v-if="breakDisplay" class="mt-1 flex items-baseline gap-2 text-amber-700">
          <span class="text-sm">{{ t('scoring.break.title') }}</span>
          <span class="scoring-digits text-3xl">{{ breakDisplay }}</span>
        </div>
        <div v-else-if="shotclock !== undefined && shotclock !== null" class="mt-1 flex items-baseline gap-2">
          <span class="text-sm text-white/60">{{ t('scoring.shotclock.short') }}</span>
          <ScoringTimeAdjust
            :display="shotclock"
            :disabled="!canOpenClockAdjust"
            :can-adjust="shotclockAdjustable"
            :locked-hint="t('scoring.shotclock.adjust_locked')"
            :label="t('scoring.shotclock.adjust')"
            :trigger-class="`scoring-digits text-4xl sm:text-5xl leading-none px-1 hover:bg-header-50/10 ${shotclockState === 'RUNNING' ? 'text-amber-700'
              : shotclockState === 'SUSPENDED' ? 'text-amber-700/70' : 'text-white/30'}`"
            @adjust="emit('adjust-shotclock', $event)"
          >{{ shotclock }}</ScoringTimeAdjust>
        </div>
      </div>

      <!-- Right half: mirrored -->
      <div class="board-side min-w-0 text-left">
        <div class="flex items-baseline gap-2">
          <span v-if="showLetters" class="board-letter">{{ halves.right.team }}</span>
          <div class="board-team line-clamp-2 break-words">{{ halves.right.name }}</div>
        </div>
        <div class="scoring-digits board-score">{{ halves.right.score }}</div>
        <ScoringPenalties
          v-if="halves.right.penalties?.length"
          class="mt-1"
          :penalties="halves.right.penalties"
          :can-score="canScore"
          :can-adjust="canAdjustPenalties"
          @remove="emit('remove-penalty', $event)"
          @adjust="(id, d) => emit('adjust-penalty', id, d)"
        />
        <div v-if="scoreOnly" class="mt-1 flex gap-1">
          <button type="button" class="board-key board-key--strong" :disabled="!canScore" @click="emit('bump', halves.right.team, 1)">+1</button>
          <button type="button" class="board-key" :disabled="!canScore" @click="emit('bump', halves.right.team, -1)">−1</button>
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
.board-letter {
  flex-shrink: 0;
  min-width: 1.75rem;
  padding: 0 0.35rem;
  border: 2px solid rgb(255 255 255 / 0.85);
  border-radius: 0.375rem;
  font-weight: 700;
  font-size: clamp(1rem, 2vw, 1.4rem);
  line-height: 1.4;
  text-align: center;
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
