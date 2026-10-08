<script setup lang="ts">
import { groupGamesByDate } from '#kpi-layer/utils/results/games'
import type { ResultsGame } from '#kpi-layer/utils/results/types'

// Games grouped by date, sorted by time then pitch (CMP-05). `competitionHref` adds the competition column.
const props = defineProps<{ games: ResultsGame[], competitionHref?: (game: ResultsGame) => string }>()
const { locale, locales } = useI18n()
const language = computed(() => locales.value.find(item => item.code === locale.value)?.language ?? locale.value)
const groups = computed(() => groupGamesByDate(props.games))

function longDate(date: string): string {
  return date ? new Intl.DateTimeFormat(language.value, { dateStyle: 'full', timeZone: 'UTC' }).format(new Date(date)) : ''
}
</script>

<template>
  <div class="space-y-8">
    <section v-for="group in groups" :key="group.date" :aria-label="longDate(group.date)" data-testid="games-of-date">
      <h3 class="mb-2 text-2xl capitalize text-kpi-blue-600">{{ longDate(group.date) }}</h3>
      <div class="overflow-x-auto">
        <table class="w-full text-left">
          <caption class="sr-only">{{ $t('results.gamesOf', { date: longDate(group.date) }) }}</caption>
          <thead class="text-xs uppercase text-ink/70">
            <tr>
              <th scope="col" class="hidden px-2 py-1 sm:table-cell">{{ $t('results.col.number') }}</th>
              <th scope="col" class="px-2 py-1">{{ $t('results.col.time') }}</th>
              <th scope="col" class="hidden px-2 py-1 sm:table-cell">{{ $t('results.col.pitch') }}</th>
              <th v-if="competitionHref" scope="col" class="hidden px-2 py-1 sm:table-cell">{{ $t('results.col.competition') }}</th>
              <th scope="col" class="hidden px-2 py-1 md:table-cell">{{ $t('results.col.phase') }}</th>
              <th scope="col" class="px-2 py-1 text-right">{{ $t('results.col.teamA') }}</th>
              <th scope="col" class="px-2 py-1 text-center">{{ $t('results.col.score') }}</th>
              <th scope="col" class="px-2 py-1">{{ $t('results.col.teamB') }}</th>
              <th scope="col" class="hidden px-2 py-1 lg:table-cell">{{ $t('results.col.referees') }}</th>
              <th scope="col" class="px-2 py-1">{{ $t('results.col.status') }}</th>
            </tr>
          </thead>
          <tbody>
            <ResultsGameRow
              v-for="game in group.games"
              :key="game.g_id"
              :game="game"
              :competition-href="competitionHref?.(game)"
            />
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>
