<script setup lang="ts">
import { MAIN_EVENT_MIN_SHARE, mainEvent } from '#kpi-layer/utils/results/events'
import type { CompetitionDetails, EventCompetitions } from '#kpi-layer/utils/results/types'
import { COMPETITION_TABS, competitionPath, type CompetitionTab } from '~/utils/competitions'

// Competition page frame: breadcrumb, header, event, siblings, tabs; the tab is the child page (PAGE_COMPETITION.md § 2).
const route = useRoute()
const { t, locale } = useI18n()
const localePath = useLocalePath()
const season = computed(() => String(route.params.season))
const code = computed(() => String(route.params.code))
const eventId = computed(() => (route.query.event ? Number(route.query.event) : undefined))

const { data: competition } = await useApiResource<CompetitionDetails>(() => `/competition/${season.value}/${code.value}`)
const { data: eventCompetitions } = await useApiResource<EventCompetitions>(
  () => (eventId.value ? `/event/${eventId.value}/competitions` : null),
  { notFoundIsError: false },
)

const current = computed(() => COMPETITION_TABS.find(tab => route.path.endsWith(`/${tab}`)) ?? null)
const main = computed(() => mainEvent(competition.value?.events ?? [], MAIN_EVENT_MIN_SHARE, eventId.value))
const pathTo = (target: string, tab: CompetitionTab | null) => localePath(competitionPath(season.value, target, tab ?? undefined, eventId.value))

const siblings = computed(() => (eventCompetitions.value?.competitions ?? competition.value?.siblings ?? []).map(sibling => ({
  code: sibling.code,
  label: sibling.soustitre2 || sibling.display_title,
  to: pathTo(sibling.code, current.value),
  current: sibling.code === code.value,
})))
const tabs = computed(() => COMPETITION_TABS.map(id => ({ id, to: pathTo(code.value, id) })))

const groupName = computed(() => {
  const group = competition.value?.group
  return (locale.value === 'en' && group?.libelle_en) || group?.libelle || ''
})
const breadcrumb = computed(() => {
  const home = { label: t('nav.home'), to: localePath('/') }
  const here = { label: competition.value?.display_title ?? '' }
  if (main.value) {
    return [home, { label: main.value.libelle, to: localePath(`/events/${main.value.id}`) }, here]
  }
  const list = localePath(`/competitions/${season.value}`) + `?group=${competition.value?.group.code}`
  return [home, { label: t('results.listTitle', { season: season.value }), to: list }, { label: groupName.value, to: list }, here]
})
</script>

<template>
  <div v-if="competition" class="space-y-6">
    <SiteBreadcrumb :items="breadcrumb" />
    <ResultsCompetitionHeader :competition="competition" />
    <ResultsEventBanner :events="competition.events" :main="main" :scope="competition.display_title" />
    <ResultsCompetitionChips v-if="siblings.length > 1" :items="siblings" />
    <ResultsTabs :tabs="tabs" :current="current ?? ''" />
    <NuxtPage :competition="competition" />
  </div>
</template>
