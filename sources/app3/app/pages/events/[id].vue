<script setup lang="ts">
import { joinURL } from 'ufo'
import type { EventCompetitions, ResultsGame } from '#kpi-layer/utils/results/types'
import { AGGREGATE_TABS, competitionPath } from '~/utils/competitions'
import { formatEventDates } from '~/utils/events'

// Event view: every game of an event's competitions (PAGE_EVENT_GROUP.md, EVT-*).
const route = useRoute()
const { t } = useI18n()
const localePath = useLocalePath()
const { app2BaseUrl } = useRuntimeConfig().public
const id = computed(() => Number(route.params.id))

const { data } = await useApiResource<EventCompetitions>(() => `/event/${id.value}/competitions`)
if (data.value && data.value.competitions.length === 0) {
  throw createError({ statusCode: 404, fatal: true })
}
// `data` can be momentarily empty while another event loads: every reader goes through `event`.
const event = computed(() => data.value?.event)
const language = useLanguageTag()
const current = computed(() => AGGREGATE_TABS.find(tab => route.path.endsWith(`/${tab}`)) ?? 'games')

const chips = computed(() => (data.value?.competitions ?? []).map(competition => ({
  code: competition.code,
  label: competition.soustitre2 || competition.display_title,
  to: localePath(competitionPath(competition.season, competition.code, current.value, id.value)),
  current: false,
})))
const tabs = computed(() => AGGREGATE_TABS.map(tab => ({ id: tab, to: localePath(`/events/${id.value}/${tab}`) })))
const competitionHref = (game: ResultsGame) => localePath(competitionPath(game.c_season, game.c_code, 'games', id.value))
const subtitle = computed(() => (event.value ? [event.value.place, formatEventDates(event.value.start, event.value.end, language.value)].filter(Boolean).join(' · ') : ''))

useSeoMeta({
  title: () => t('results.scopeTitle', { tab: t(`results.tab.${current.value}`), scope: event.value?.libelle ?? '' }),
  description: () => t('results.scopeDescription', { scope: event.value?.libelle ?? '' }),
})
useHead(() => ({
  script: !event.value ? [] : [{
    type: 'application/ld+json',
    innerHTML: JSON.stringify({
      '@context': 'https://schema.org',
      '@type': 'SportsEvent',
      'name': event.value.libelle,
      'sport': 'Canoe polo',
      'startDate': event.value.start,
      'endDate': event.value.end,
      'location': { '@type': 'Place', 'name': event.value.place },
    }),
  }],
}))
</script>

<template>
  <div v-if="event" class="space-y-6">
    <SiteBreadcrumb :items="[{ label: $t('nav.home'), to: localePath('/') }, { label: event.libelle }]" />
    <ResultsScopeHeader :title="event.libelle" :subtitle="subtitle" :logo="event.logo" :live-url="joinURL(app2BaseUrl, 'event', String(id))" />
    <ResultsCompetitionChips :items="chips" :all="{ to: localePath(`/events/${id}/${current}`), current: true }" />
    <ResultsTabs :tabs="tabs" :current="current" />
    <NuxtPage :games-path="`/event/${id}/games`" :pdf-target="{ kind: 'games', event: id }" :competition-href="competitionHref" />
  </div>
</template>
