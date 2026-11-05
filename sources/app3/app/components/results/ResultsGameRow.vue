<script setup lang="ts">
import { displayStatus, drawCode, isFinal, normalizeTime, winnerSide } from '#kpi-layer/utils/results/games'
import type { ResultsGame } from '#kpi-layer/utils/results/types'
import { gameLink } from '~/utils/page-links'
import { personName } from '~/utils/people'

/**
 * One game of a games list (PAGE_COMPETITION.md § 3.1, CMP-19): time, pitch, phase, team A, score column, team B,
 * referees, score sheet. Phone: teams stacked, scores stacked in the column on the right. The competition is shown in
 * event / group views (`competitionHref`).
 */
const props = defineProps<{ game: ResultsGame, competitionHref?: string }>()
const context = usePageLinkContext()

const winner = computed(() => winnerSide(props.game))
const sheet = computed(() => (props.game.g_status === 'ATT' ? null : gameLink(props.game.g_id, context.value).href))
const referees = computed(() => [props.game.r_1, props.game.r_2].map(personName).filter(Boolean).join(', '))
const final = computed(() => isFinal(props.game))
const code = computed(() => drawCode(props.game))
const phaseClass = computed(() => (final.value
  ? 'font-bold text-medal-gold before:mr-1.5 before:inline-block before:size-2 before:rounded-full before:bg-medal-gold'
  : 'text-muted'))
</script>

<template>
  <li
    class="grid grid-cols-[3.75rem_minmax(0,1fr)_3.5rem] border-b border-line last:border-b-0 md:min-h-13 md:grid-cols-[3.25rem_4.5rem_minmax(7rem,9.5rem)_minmax(0,1fr)_6.75rem_minmax(0,1fr)] md:items-center lg:grid-cols-[3.25rem_4.5rem_minmax(7rem,9.5rem)_minmax(0,1fr)_6.75rem_minmax(0,1fr)_minmax(10rem,14rem)]"
    :data-game="game.g_id"
    :data-status="displayStatus(game)"
    :data-final="final ? '' : undefined"
  >
    <span class="col-start-1 row-start-1 self-start pt-2.5 font-semibold tabular-nums md:self-center md:pt-0">{{ normalizeTime(game.g_time) }}</span>
    <span class="col-start-1 row-start-2 self-start text-xs text-muted md:col-start-2 md:row-start-1 md:self-center md:text-sm" data-testid="game-pitch">
      {{ $t('results.pitch', { pitch: game.g_pitch ?? '' }) }}
    </span>
    <span class="hidden text-sm leading-tight md:col-start-3 md:row-start-1 md:block">
      <NuxtLink v-if="competitionHref" :to="competitionHref" class="block font-semibold hover:underline">{{ game.c_label ?? game.c_code }}</NuxtLink>
      <span :class="phaseClass">{{ game.d_phase }}</span>
      <span v-if="code" class="block text-xs text-muted opacity-80" data-testid="draw-code">{{ code }}</span>
    </span>
    <ResultsGameTeam
      :game="game"
      side="A"
      :winner="winner === 'A'"
      class="col-start-2 row-start-1 pt-2.5 pr-2.5 md:col-start-4 md:flex-row-reverse md:px-4 md:pt-0 md:text-right"
    />
    <ResultsScoreCell
      :game="game"
      :href="sheet"
      class="col-start-3 row-span-2 row-start-1 overflow-hidden md:col-start-5 md:row-span-1"
    />
    <ResultsGameTeam
      :game="game"
      side="B"
      :winner="winner === 'B'"
      class="col-start-2 row-start-2 pr-2.5 pb-2.5 md:col-start-6 md:row-start-1 md:px-4 md:pb-0"
    />
    <span class="hidden text-sm leading-snug text-muted lg:col-start-7 lg:row-start-1 lg:block lg:pl-4">{{ referees }}</span>
    <span class="col-start-2 row-start-3 -mt-1.5 pb-2.5 text-xs leading-tight md:hidden">
      <NuxtLink v-if="competitionHref" :to="competitionHref" class="font-semibold hover:underline">{{ game.c_label ?? game.c_code }}</NuxtLink>
      <template v-if="competitionHref"> · </template>
      <span :class="phaseClass">{{ game.d_phase }}</span>
    </span>
  </li>
</template>
