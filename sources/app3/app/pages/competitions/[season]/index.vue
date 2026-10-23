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
  <div class="space-y-2">
    <SitePageHeading :title="$t('results.listTitle', { season })" />
    <div class="grid gap-5 pb-10 lg:grid-cols-[16.25rem_minmax(0,1fr)] lg:gap-10">
      <div class="self-start lg:sticky lg:top-3 lg:max-h-[calc(100vh-1.5rem)] lg:overflow-auto lg:pr-1.5">
        <ResultsCompetitionSelector :seasons="seasons?.seasons ?? [season]" :season="season" :sections="sections" :group="group" />
        <ResultsGroupIndex :season="season" :sections="sections" :group="group" class="hidden lg:block" />
      </div>
      <section aria-labelledby="group-title" class="space-y-4">
        <div class="flex flex-wrap items-baseline gap-x-4.5 gap-y-2">
          <h2 id="group-title" class="text-[2.375rem] leading-none text-navy md:text-[2.875rem] dark:text-ink" data-testid="group-title">{{ groupName }}</h2>
          <NuxtLink v-if="group" :to="localePath(`/groups/${season}/${group}/games`)" class="text-sm underline" data-testid="group-games-link">
            {{ $t('results.allGroupGames') }}
          </NuxtLink>
        </div>
        <p v-if="error && error.statusCode !== 404" class="text-kpi-red-600" data-testid="results-unavailable">{{ $t('results.unavailable') }}</p>
        <p v-else-if="competitions.length === 0" data-testid="no-competition">{{ $t('results.noCompetition') }}</p>
        <template v-else>
          <ResultsEventBanner :events="list?.events ?? []" :main="main" :scope="groupName" />
          <div class="grid grid-cols-[repeat(auto-fill,minmax(min(20rem,100%),1fr))] gap-6 pt-2" data-testid="competition-cards">
            <ResultsCompetitionCard v-for="competition in competitions" :key="competition.code" :competition="competition" />
          </div>
        </template>
      </section>
    </div>
  </div>
</template>
