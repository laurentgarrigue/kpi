<script setup lang="ts">
import type { EventCompetition } from '#kpi-layer/utils/results/types'
import { competitionPath, eventTabPath } from '~/utils/competitions'

// Competitions of an event, each with its place in the event (« 1 gameday out of 2 ») and a direct link to the
// whole competition: an event often holds only a few gamedays of a competition (EVT-09).
const props = defineProps<{ eventId: number, competitions: EventCompetition[] }>()
const localePath = useLocalePath()
const partial = (item: EventCompetition) => item.gamedays < item.total_gamedays
const eventLink = (item: EventCompetition) => localePath(eventTabPath(props.eventId, 'games', item.code))
const competitionLink = (item: EventCompetition) => localePath(competitionPath(item.season, item.code, undefined, props.eventId))
</script>

<template>
  <section aria-labelledby="event-competitions-title" class="space-y-2" data-testid="event-competitions">
    <h2 id="event-competitions-title" class="text-2xl text-navy">{{ $t('results.eventCompetitions') }}</h2>
    <ul class="grid gap-2 md:grid-cols-2">
      <li v-for="item in competitions" :key="item.code" class="flex flex-wrap items-center justify-between gap-2 rounded border border-l-4 border-line border-l-kpi-blue-600 p-3" :data-competition="item.code">
        <span>
          <NuxtLink :to="eventLink(item)" class="font-semibold hover:underline">{{ item.soustitre2 || item.display_title }}</NuxtLink>
          <span v-if="item.soustitre2" class="block text-sm text-ink/70">{{ item.display_title }}</span>
          <span class="block text-sm" data-testid="event-gamedays">
            {{ partial(item) ? $t('results.gamedaysOf', { count: item.gamedays, total: item.total_gamedays }, item.gamedays) : $t('results.allGamedays') }}
          </span>
        </span>
        <NuxtLink :to="competitionLink(item)" class="text-sm font-semibold text-kpi-blue-600 underline" data-testid="competition-full-link">{{ $t('results.seeCompetition') }}</NuxtLink>
      </li>
    </ul>
  </section>
</template>
