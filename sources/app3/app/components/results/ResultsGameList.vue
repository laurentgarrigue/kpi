<script setup lang="ts">
import { groupGamesByDate } from '#kpi-layer/utils/results/games'
import type { ResultsGame } from '#kpi-layer/utils/results/types'

// Games grouped by date, sorted by time then pitch (CMP-05); the scores of a day form one column (CMP-19).
// `competitionHref` adds the competition to each game (event and group views).
const props = defineProps<{ games: ResultsGame[], competitionHref?: (game: ResultsGame) => string }>()
const language = useLanguageTag()
const groups = computed(() => groupGamesByDate(props.games))

function longDate(date: string): string {
  return date ? new Intl.DateTimeFormat(language.value, { dateStyle: 'full', timeZone: 'UTC' }).format(new Date(date)) : ''
}
</script>

<template>
  <div class="space-y-7">
    <section v-for="group in groups" :key="group.date" :aria-label="longDate(group.date)" data-testid="games-of-date">
      <h2 class="mb-2 flex flex-wrap items-baseline gap-x-3.5">
        <span class="text-3xl capitalize leading-tight text-kpi-blue-700 md:text-[2rem]">{{ longDate(group.date) }}</span>
        <span class="font-sans text-sm text-muted">{{ $t('results.gamesCount', { n: group.games.length }, group.games.length) }}</span>
      </h2>
      <ol class="[&>li:first-child>[data-testid=score-cell]]:rounded-t-md [&>li:last-child>[data-testid=score-cell]]:rounded-b-md">
        <ResultsGameRow
          v-for="game in group.games"
          :key="game.g_id"
          :game="game"
          :competition-href="competitionHref?.(game)"
        />
      </ol>
    </section>
  </div>
</template>
