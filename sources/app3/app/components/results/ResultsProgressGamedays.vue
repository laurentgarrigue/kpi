<script setup lang="ts">
import { standingsByGameday } from '#kpi-layer/utils/results/charts'
import type { CompetitionChart, Gameday } from '#kpi-layer/utils/results/types'
import { FOLLOWING_CLASSES } from '~/composables/useFollowedTeam'
import { formatEventDates } from '~/utils/events'

/**
 * Progress of a championship (CMP-25): one card per gameday with its dates, place, own standing and a link to its
 * games in the « Games » tab. Touching a team highlights its row in every gameday.
 */
const props = defineProps<{ chart: CompetitionChart, gamedays: Gameday[] }>()
const route = useRoute()
const language = useLanguageTag()
const standings = computed(() => standingsByGameday(props.chart, props.gamedays))
const { followed, follow, unfollow, label: followedLabel } = useFollowedTeam(() => props.chart)
const gamesHref = (gameday: number) => ({
  path: route.path.replace(/\/progress$/, '/games'),
  query: { ...route.query, view: undefined, gameday },
})
const where = (gameday: Gameday) => [formatEventDates(gameday.start, gameday.end, language.value), gameday.place].filter(Boolean).join(' · ')
</script>

<template>
  <div class="space-y-4">
    <ResultsFollowBar :following="followed !== null" :team="followedLabel" @stop="unfollow" />
    <div
      class="grid gap-x-10 gap-y-8 sm:grid-cols-[repeat(auto-fill,minmax(22rem,1fr))]"
      :class="{ [FOLLOWING_CLASSES]: followed !== null }"
      :data-following="followed !== null ? '' : undefined"
      data-testid="progress-gamedays"
    >
      <section v-for="(standing, index) in standings" :key="standing.gameday.id" class="flex min-w-0 flex-col gap-3" data-testid="progress-gameday">
        <header>
          <h3 class="text-2xl text-kpi-blue-700">{{ $t('results.progressGameday.title', { n: index + 1 }) }}</h3>
          <p class="text-sm text-muted">{{ where(standing.gameday) }}</p>
        </header>
        <ResultsPoolTable v-if="standing.phase.teams?.length" :phase="standing.phase" :competition="chart.code" :followed="followed" spread @follow="follow" />
        <p v-else class="text-sm italic text-muted">{{ $t('results.progressGameday.pending') }}</p>
        <NuxtLink :to="gamesHref(standing.gameday.id)" class="mt-auto text-sm text-kpi-blue-700 underline" data-testid="gameday-games">
          {{ $t('results.progressGameday.games') }} →
        </NuxtLink>
      </section>
    </div>
  </div>
</template>
