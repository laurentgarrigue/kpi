<script setup lang="ts">
import { defaultPitchDay, gameDates, isLive, pitchGrid } from '#kpi-layer/utils/results/games'
import type { ResultsGame } from '#kpi-layer/utils/results/types'
import { todayInParis } from '~/utils/events'

// « Pitches » tab: one day as a time × pitch grid; one section per pitch on small screens (CMP-07).
const props = defineProps<{ games: ResultsGame[], showCompetition?: boolean }>()
const emit = defineEmits<{ refresh: [] }>()
const route = useRoute()
const now = useMinuteClock()
const { locale, locales } = useI18n()
const language = computed(() => locales.value.find(item => item.code === locale.value)?.language ?? locale.value)

const dates = computed(() => gameDates(props.games))
const day = computed(() => {
  const requested = route.query.day
  return typeof requested === 'string' && dates.value.includes(requested) ? requested : defaultPitchDay(dates.value, todayInParis(now.value))
})
const grid = computed(() => pitchGrid(props.games.filter(game => game.g_date === day.value)))

function shortDate(date: string): string {
  return new Intl.DateTimeFormat(language.value, { weekday: 'short', day: 'numeric', month: 'short', timeZone: 'UTC' }).format(new Date(date))
}

useAutoRefresh(() => isLive(props.games, now.value), () => emit('refresh'))
</script>

<template>
  <div class="space-y-6">
    <p v-if="games.length === 0" data-testid="no-games">{{ $t('results.noGames') }}</p>
    <template v-else>
      <nav :aria-label="$t('results.filters.day')" class="flex flex-wrap gap-2" data-testid="pitch-days">
        <NuxtLink
          v-for="date in dates"
          :key="date"
          :to="{ path: route.path, query: { ...route.query, day: date } }"
          :aria-current="date === day ? 'page' : undefined"
          class="rounded border px-3 py-1 text-sm"
          :class="date === day ? 'border-kpi-blue-600 bg-kpi-blue-600 text-white' : 'border-line hover:bg-kpi-blue-50'"
        >
          {{ shortDate(date) }}
        </NuxtLink>
      </nav>

      <!-- md and up: grid -->
      <div class="hidden overflow-x-auto md:block">
        <table class="w-full table-fixed text-sm" data-testid="pitch-grid">
          <caption class="sr-only">{{ $t('results.pitchesOf', { date: day ? shortDate(day) : '' }) }}</caption>
          <thead>
            <tr>
              <th scope="col" class="w-20 px-2 py-1 text-left">{{ $t('results.col.time') }}</th>
              <th v-for="pitch in grid.pitches" :key="pitch" scope="col" class="px-2 py-1">{{ $t('results.pitch', { pitch }) }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in grid.rows" :key="row.time" class="border-t border-line align-top">
              <th scope="row" class="px-2 py-2 text-left tabular-nums">{{ row.time }}</th>
              <td v-for="pitch in grid.pitches" :key="pitch" class="px-2 py-2">
                <ResultsPitchGame v-if="row.cells[pitch]" :game="row.cells[pitch]!" :show-competition="showCompetition" />
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- small screens: one section per pitch -->
      <div class="space-y-6 md:hidden">
        <section v-for="pitch in grid.pitches" :key="pitch">
          <h3 class="mb-2 text-xl text-kpi-blue-600">{{ $t('results.pitch', { pitch }) }}</h3>
          <ul class="space-y-2">
            <template v-for="row in grid.rows" :key="row.time">
              <li v-if="row.cells[pitch]" class="flex gap-3">
                <span class="w-12 tabular-nums">{{ row.time }}</span>
                <ResultsPitchGame :game="row.cells[pitch]!" :show-competition="showCompetition" />
              </li>
            </template>
          </ul>
        </section>
      </div>
    </template>
  </div>
</template>
