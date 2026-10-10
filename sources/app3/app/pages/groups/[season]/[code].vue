<script setup lang="ts">
import { joinURL } from 'ufo'
import { GROUP_EVENT_MIN_SHARE, mainEvent } from '#kpi-layer/utils/results/events'
import type { GroupCompetitions, GroupSection, ResultsGame } from '#kpi-layer/utils/results/types'
import { AGGREGATE_TABS, competitionPath, groupLabel } from '~/utils/competitions'

// Group view: every game of a group's competitions (PAGE_EVENT_GROUP.md, GRP-*).
const route = useRoute()
const { t, locale } = useI18n()
const localePath = useLocalePath()
const { app2BaseUrl } = useRuntimeConfig().public
const season = computed(() => String(route.params.season))
const code = computed(() => String(route.params.code))

const { data: list } = await useApiResource<GroupCompetitions>(() => `/group/${season.value}/${code.value}/competitions`)
const { data: groups } = await useApiResource<{ sections: GroupSection[] }>(() => `/groups/${season.value}`)
const name = computed(() => {
  const group = (groups.value?.sections ?? []).flatMap(section => section.groups).find(item => item.code === code.value)
  return group ? groupLabel(group, locale.value) : code.value
})
const current = computed(() => AGGREGATE_TABS.find(tab => route.path.endsWith(`/${tab}`)) ?? 'games')
const main = computed(() => mainEvent(list.value?.events ?? [], GROUP_EVENT_MIN_SHARE))

const competitions = computed(() => list.value?.competitions ?? [])
const chips = computed(() => competitions.value.map(competition => ({
  code: competition.code,
  label: competition.soustitre2 || competition.display_title,
  to: localePath(competitionPath(season.value, competition.code, current.value)),
  current: false,
})))
const tabs = computed(() => AGGREGATE_TABS.map(tab => ({ id: tab, to: localePath(`/groups/${season.value}/${code.value}/${tab}`) })))
const competitionHref = (game: ResultsGame) => localePath(competitionPath(season.value, game.c_code, 'games'))
const listPath = computed(() => `${localePath(`/competitions/${season.value}`)}?group=${code.value}`)

useSeoMeta({
  title: () => t('results.scopeTitle', { tab: t(`results.tab.${current.value}`), scope: `${name.value} ${season.value}` }),
  description: () => t('results.scopeDescription', { scope: `${name.value} ${season.value}` }),
})
</script>

<template>
  <div v-if="list" class="space-y-6">
    <SiteBreadcrumb :items="[{ label: $t('nav.home'), to: localePath('/') }, { label: $t('results.listTitle', { season }), to: listPath }, { label: name }]" />
    <ResultsScopeHeader kind="group" :title="name" :subtitle="season" :live-url="joinURL(app2BaseUrl, 'group', season, code)" />
    <ResultsEventBanner :events="list.events" :main="main" :scope="name" />
    <ResultsCompetitionChips :items="chips" :all="{ to: localePath(`/groups/${season}/${code}/${current}`), current: true }" />
    <ResultsTabs :tabs="tabs" :current="current" />
    <NuxtPage
      :games-path="`/group/${season}/${code}/games`"
      :pdf-target="{ kind: 'games', season, competitions: competitions.map(competition => competition.code) }"
      :competition-href="competitionHref"
    />
  </div>
</template>
