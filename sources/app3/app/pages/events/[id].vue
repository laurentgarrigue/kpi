<script setup lang="ts">
import { joinURL } from 'ufo'
import type { CompetitionDetails, EventCompetitions, ResultsGame } from '#kpi-layer/utils/results/types'
import { AGGREGATE_TABS, COMPETITION_TABS, eventTabPath, selectedCompetition, type CompetitionTab } from '~/utils/competitions'
import { formatEventDates } from '~/utils/events'

// Event view: every game of an event's competitions, or one competition (chip) with all its tabs, without leaving
// the event (PAGE_EVENT_GROUP.md, EVT-*).
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
const current = computed<CompetitionTab>(() => COMPETITION_TABS.find(tab => route.path.endsWith(`/${tab}`)) ?? 'games')
// Games and pitches can cover the whole event; the other tabs need one competition (the first by default, EVT-07).
const needsCompetition = computed(() => !(AGGREGATE_TABS as readonly string[]).includes(current.value))
const competitions = computed(() => data.value?.competitions ?? [])
const selected = computed(() => selectedCompetition(competitions.value, route.query.competition, needsCompetition.value))

const { data: competition } = await useApiResource<CompetitionDetails>(
  () => (selected.value && needsCompetition.value ? `/competition/${selected.value.season}/${selected.value.code}` : null),
  { notFoundIsError: false },
)

const chips = computed(() => competitions.value.map(item => ({
  code: item.code,
  label: item.soustitre2 || item.display_title,
  to: localePath(eventTabPath(id.value, current.value, item.code)),
  current: item.code === selected.value?.code,
})))
const all = computed(() => ({ to: localePath(eventTabPath(id.value, current.value)), current: !selected.value, disabled: needsCompetition.value }))
// Tabs keep the competition explicitly asked for (when the event has it), not the default one.
const asked = computed(() => selectedCompetition(competitions.value, route.query.competition, false))
const tabs = computed(() => COMPETITION_TABS.map(tab => ({ id: tab, to: localePath(eventTabPath(id.value, tab, asked.value?.code)) })))
const competitionHref = (game: ResultsGame) => localePath(eventTabPath(id.value, 'games', game.c_code))
const pdfTarget = computed(() => (selected.value
  ? { kind: 'games' as const, season: selected.value.season, competitions: [selected.value.code] }
  : { kind: 'games' as const, event: id.value }))
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
    <ResultsScopeHeader kind="event" :title="event.libelle" :subtitle="subtitle" :logo="event.logo" :live-url="joinURL(app2BaseUrl, 'event', String(id))" />
    <ResultsEventCompetitions :event-id="id" :competitions="competitions" />
    <ResultsCompetitionChips :items="chips" :all="all" />
    <ResultsTabs :tabs="tabs" :current="current" />
    <NuxtPage
      :games-path="`/event/${id}/games`"
      :pdf-target="pdfTarget"
      :competition-href="competitionHref"
      :selected-code="needsCompetition ? undefined : selected?.code"
      :competition="competition ?? undefined"
    />
  </div>
</template>
