<script setup lang="ts">
import { filterGames, gameDates, gamedayOptions, isLive } from '#kpi-layer/utils/results/games'
import type { ResultsGame } from '#kpi-layer/utils/results/types'
import { NEW_TAB_ATTRS } from '~/utils/links'

// « Games » tab of a competition, an event or a group (PAGE_COMPETITION.md § 3.1, PAGE_EVENT_GROUP.md § 3).
const props = defineProps<{
  games: ResultsGame[]
  /** Gameday filter: championships only (not in event / group views). */
  withGamedays?: boolean
  competitionHref?: (game: ResultsGame) => string
  pdfHref: string
  hiddenFields?: Record<string, string>
}>()
const emit = defineEmits<{ refresh: [] }>()
const route = useRoute()
const now = useMinuteClock()

const filters = computed(() => ({
  gameday: props.withGamedays && route.query.gameday ? Number(route.query.gameday) : undefined,
  day: typeof route.query.day === 'string' && route.query.day ? route.query.day : undefined,
  upcoming: route.query.upcoming === '1',
}))
const filtered = computed(() => filterGames(props.games, filters.value, now.value))

useAutoRefresh(() => isLive(props.games, now.value), () => emit('refresh'))
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
      <ResultsGameFilters
        :gamedays="withGamedays ? gamedayOptions(games) : undefined"
        :dates="gameDates(games)"
        :gameday="filters.gameday"
        :day="filters.day"
        :upcoming="filters.upcoming"
        :hidden-fields="hiddenFields"
      />
      <a :href="pdfHref" v-bind="NEW_TAB_ATTRS" class="flex items-center gap-1 text-sm underline" data-testid="games-pdf">
        <UIcon name="i-heroicons-document-arrow-down" class="size-4" aria-hidden="true" />
        {{ $t('results.gamesPdf') }}<span class="sr-only"> {{ $t('a11y.newTab') }}</span>
      </a>
    </div>
    <p v-if="games.length === 0" data-testid="no-games">{{ $t('results.noGames') }}</p>
    <p v-else-if="filtered.length === 0" data-testid="no-filtered-games">{{ $t('results.noFilteredGames') }}</p>
    <ResultsGameList v-else :games="filtered" :competition-href="competitionHref" />
  </div>
</template>
