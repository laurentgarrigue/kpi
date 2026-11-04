<script setup lang="ts">
import type { EventCompetition, Ranking } from '#kpi-layer/utils/results/types'
import { competitionPath, eventTabPath } from '~/utils/competitions'

/**
 * Competitions of an event (EVT-09, EVT-11): one row per competition with its podium and links that stay in the
 * event. For an event of several groups, the gamedays held at the event (« 1 gameday out of 2 ») and a link to the
 * whole competition: an event often holds only a few gamedays of a competition.
 */
const props = defineProps<{ eventId: number, competitions: EventCompetition[], singleGroup: boolean, rankings: Record<string, Ranking | null> }>()
const localePath = useLocalePath()
const LINKS = ['games', 'progress', 'ranking'] as const
const MEDAL_DOTS: Record<number, string> = { 1: 'bg-medal-gold', 2: 'bg-medal-silver', 3: 'bg-medal-bronze' }

const partial = (item: EventCompetition) => item.gamedays < item.total_gamedays
const podium = (item: EventCompetition) => (props.rankings[item.code]?.rows ?? [])
  .filter(row => row.medal)
  .sort((a, b) => (a.medal ?? 0) - (b.medal ?? 0))
const tabLink = (item: EventCompetition, tab: 'info' | typeof LINKS[number]) => localePath(eventTabPath(props.eventId, tab, item.code))
const competitionLink = (item: EventCompetition) => localePath(competitionPath(item.season, item.code, undefined, props.eventId))
</script>

<template>
  <ol class="border-b border-line" data-testid="event-competitions">
    <li
      v-for="item in competitions"
      :key="item.code"
      class="grid items-center gap-x-8 gap-y-2.5 border-t border-line py-4.5 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)_auto]"
      :data-competition="item.code"
    >
      <div>
        <h2 class="text-[2rem] leading-none text-navy dark:text-ink">
          <NuxtLink :to="tabLink(item, 'info')" class="hover:underline hover:decoration-1 hover:underline-offset-4">{{ item.soustitre2 || item.display_title }}</NuxtLink>
        </h2>
        <p class="mt-1 text-sm text-muted">
          <template v-if="item.soustitre2">{{ item.display_title }}</template>
          <template v-if="!singleGroup">
            <template v-if="item.soustitre2">, </template>
            <span data-testid="event-gamedays">{{ partial(item) ? $t('results.gamedaysOf', { count: item.gamedays, total: item.total_gamedays }, item.gamedays) : $t('results.allGamedays') }}</span>
            · <NuxtLink :to="competitionLink(item)" class="underline" data-testid="competition-full-link">{{ $t('results.seeCompetition') }}</NuxtLink>
          </template>
        </p>
      </div>
      <ol v-if="podium(item).length" :aria-label="$t('results.podium')" class="grid gap-1" data-testid="event-podium">
        <li v-for="row in podium(item)" :key="row.team.id" class="flex items-center gap-2.5" :class="{ 'font-bold': row.medal === 1 }">
          <span class="size-3 shrink-0 rounded-full" :class="MEDAL_DOTS[row.medal ?? 0]" aria-hidden="true" />
          <span class="sr-only">{{ $t(`results.medal.${row.medal}`) }}</span>
          <ResultsTeamLogo :path="row.team.logo" />
          <ResultsTeamName :label="row.team.label" :number="row.team.number" :competition="item.code" :season="item.season" />
        </li>
      </ol>
      <p v-else class="text-sm text-muted" data-testid="podium-later">{{ $t('results.podiumLater') }}</p>
      <nav :aria-label="item.soustitre2 || item.display_title" class="flex flex-wrap gap-2 lg:justify-end" data-testid="competition-links">
        <NuxtLink
          v-for="tab in LINKS"
          :key="tab"
          :to="tabLink(item, tab)"
          class="whitespace-nowrap rounded-md border px-3 py-1.5 text-sm font-semibold"
          :class="tab === 'games' ? 'border-navy bg-navy text-white' : 'border-line hover:bg-wash'"
        >
          {{ $t(`results.tab.${tab}`) }}
        </NuxtLink>
      </nav>
    </li>
  </ol>
</template>
