<script setup lang="ts">
import type { RankingRow } from '#kpi-layer/utils/results/types'
import { teamLink } from '~/utils/page-links'
import { ordinal } from '~/utils/games'

// Podium on steps of their height, second – first – third (CMP-21): logo, name, points and wins (not for a cup,
// which only shows its final ranking, CMP-10).
const props = withDefaults(defineProps<{ rows: RankingRow[], competition: string, season: string, withPoints?: boolean }>(), { withPoints: true })
const localePath = useLocalePath()
const language = useLanguageTag()
const PODIUM_ORDER = [2, 1, 3] as const
const STEPS = {
  1: { block: 'h-27.5 md:h-37.5 bg-navy', logo: 'champion', name: 'text-sm md:text-[1.3125rem]', digit: 'text-kpi-gold-300' },
  2: { block: 'h-20 md:h-27.5 bg-navy/80 dark:bg-navy', logo: 'podium', name: 'text-sm md:text-[1.0625rem]', digit: '' },
  3: { block: 'h-15 md:h-20.5 bg-navy/65 dark:bg-navy/80', logo: 'podium', name: 'text-sm md:text-[1.0625rem]', digit: '' },
} as const
const steps = computed(() => PODIUM_ORDER
  .map(medal => props.rows.find(row => row.medal === medal))
  .filter((row): row is RankingRow => row !== undefined))
const href = (row: RankingRow) => (row.team.number === null
  ? undefined
  : localePath(teamLink({ number: row.team.number, competition: props.competition, season: props.season }).href))
</script>

<template>
  <section
    v-if="steps.length === PODIUM_ORDER.length"
    :aria-label="$t('results.podium')"
    class="mx-auto my-5 grid max-w-3xl grid-cols-3 items-end gap-1.5 md:gap-3"
    data-testid="podium"
  >
    <div v-for="row in steps" :key="row.team.id" class="text-center" :data-podium-rank="row.rank">
      <NuxtLink :to="href(row)" class="group grid justify-items-center gap-2 px-1.5 pb-3">
        <ResultsTeamLogo :path="row.team.logo" :size="STEPS[row.medal!].logo" />
        <b class="leading-tight group-hover:underline" :class="STEPS[row.medal!].name">{{ row.team.label }}</b>
        <small v-if="withPoints" class="text-muted tabular-nums">
          {{ $t('results.podiumPoints', { points: row.points }) }}<template v-if="row.won !== undefined">, {{ $t('results.podiumWins', { n: row.won }, row.won) }}</template>
        </small>
      </NuxtLink>
      <span class="grid place-items-center rounded-t-lg font-display text-[2.5rem] leading-none text-white md:text-[3.375rem]" :class="STEPS[row.medal!].block">
        <span :class="STEPS[row.medal!].digit" aria-hidden="true">{{ row.rank }}</span>
        <span class="sr-only">{{ $t('results.place', { rank: ordinal(row.rank, language) }) }}, {{ $t(`results.medal.${row.medal}`) }}</span>
      </span>
    </div>
  </section>
</template>
