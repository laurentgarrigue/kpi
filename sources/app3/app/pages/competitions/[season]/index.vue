<script setup lang="ts">
import { GROUP_EVENT_MIN_SHARE, mainEvent } from '#kpi-layer/utils/results/events'
import type { GroupCompetitions, GroupSection, Seasons } from '#kpi-layer/utils/results/types'
import { defaultGroup, groupLabel } from '~/utils/competitions'

// « Competitions and results » of a season and a group (PAGE_COMPETITIONS.md, CPL-*).
const route = useRoute()
const { t, locale } = useI18n()
const localePath = useLocalePath()
const season = computed(() => String(route.params.season))

const { data: seasons } = await useApiResource<Seasons>('/seasons')
if (seasons.value && !seasons.value.seasons.includes(season.value)) {
  throw createError({ statusCode: 404, fatal: true })
}
const { data: groups } = await useApiResource<{ sections: GroupSection[] }>(() => `/groups/${season.value}`)
const sections = computed(() => groups.value?.sections ?? [])
const group = computed(() => defaultGroup(sections.value, typeof route.query.group === 'string' ? route.query.group : undefined))
const groupName = computed(() => {
  const found = sections.value.flatMap(section => section.groups).find(item => item.code === group.value)
  return found ? groupLabel(found, locale.value) : ''
})

const { data: list, error } = await useApiResource<GroupCompetitions>(
  () => (group.value ? `/group/${season.value}/${group.value}/competitions` : null),
  { notFoundIsError: false },
)
const competitions = computed(() => list.value?.competitions ?? [])
const main = computed(() => mainEvent(list.value?.events ?? [], GROUP_EVENT_MIN_SHARE))

useSeoMeta({
  title: () => t('results.listTitle', { season: season.value }),
  description: () => t('results.listDescription', { group: groupName.value, season: season.value }),
})
</script>

<template>
  <div class="space-y-8">
    <h1 class="text-5xl text-kpi-blue-600">{{ $t('results.listTitle', { season }) }}</h1>
    <ResultsCompetitionSelector :seasons="seasons?.seasons ?? [season]" :season="season" :sections="sections" :group="group" />

    <p v-if="error && error.statusCode !== 404" class="text-kpi-red-600" data-testid="results-unavailable">{{ $t('results.unavailable') }}</p>
    <p v-else-if="competitions.length === 0" data-testid="no-competition">{{ $t('results.noCompetition') }}</p>
    <template v-else>
      <ResultsEventBanner :events="list?.events ?? []" :main="main" :scope="groupName" />
      <p>
        <NuxtLink :to="localePath(`/groups/${season}/${group}/games`)" class="font-semibold underline" data-testid="group-games-link">
          {{ $t('results.allGroupGames') }}
        </NuxtLink>
      </p>
      <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3" data-testid="competition-cards">
        <ResultsCompetitionCard v-for="competition in competitions" :key="competition.code" :competition="competition" />
      </div>
    </template>
  </div>
</template>
