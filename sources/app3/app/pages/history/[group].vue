<script setup lang="ts">
import { groupLabel } from '~/utils/competitions'
import { decadeAnchors, seasonAnchor, seasonFinals, teamKey, titleHolder, type History, type HistoryGroups } from '~/utils/history'

// Honours of a group (PAGE_HISTORY.md § 2 bis): the title holder, the last ten champions, every season (HIS-01 to HIS-07).
const route = useRoute()
const { t, locale } = useI18n()
const localePath = useLocalePath()
const code = computed(() => String(route.params.group))

const { data: history } = await useApiResource<History>(() => `/history/${code.value}`)
const { data: groups } = await useApiResource<HistoryGroups>('/history', { notFoundIsError: false })
const name = computed(() => (history.value ? groupLabel(history.value.group, locale.value) : ''))
const finals = computed(() => (history.value ? seasonFinals(history.value) : []))
const holder = computed(() => titleHolder(finals.value))
const decades = computed(() => decadeAnchors(history.value?.seasons.map(item => item.season) ?? []))

// Team whose podiums are highlighted (HIS-07), by its key; touching it again undoes it.
const highlighted = ref<string | null>(null)
const highlight = (team: string) => {
  highlighted.value = highlighted.value === team ? null : team
}
const highlightedLabel = computed(() => history.value?.seasons
  .flatMap(item => item.competitions.flatMap(competition => competition.podium))
  .find(entry => teamKey(entry.team) === highlighted.value)?.team.label ?? '')

useSeoMeta({
  title: () => t('history.pageTitle', { group: name.value }),
  description: () => t('history.description', { group: name.value }),
})
</script>

<template>
  <div v-if="history" class="space-y-6">
    <SiteBreadcrumb :items="[{ label: $t('nav.home'), to: localePath('/') }, { label: $t('history.title') }, { label: name }]" />
    <SitePageHeading :title="$t('history.pageTitle', { group: name })" />
    <HistoryGroupSelector v-if="groups" :sections="groups.sections" :group="history.group.code" />
    <HistoryHolder v-if="holder" :holder="holder" :competition="finals[0]!.competition.code" />
    <HistoryStrip :finals="finals" :highlighted="highlighted" />
    <section aria-labelledby="all-seasons" class="space-y-3 pt-4">
      <div class="flex flex-wrap items-baseline gap-x-3 gap-y-2">
        <h2 id="all-seasons" class="mr-3 text-[1.875rem] leading-none text-kpi-blue-700">{{ $t('history.allSeasons') }}</h2>
        <nav :aria-label="$t('history.allSeasons')" data-testid="decade-anchors">
          <ul class="flex flex-wrap gap-x-3 text-sm">
            <li v-for="item in decades" :key="item.decade"><a :href="`#${seasonAnchor(item.season)}`" class="underline">{{ $t('history.decade', { decade: item.decade }) }}</a></li>
          </ul>
        </nav>
      </div>
      <p class="text-sm text-muted">
        <template v-if="highlighted">
          {{ $t('history.highlighted', { team: highlightedLabel }) }}
          <button type="button" class="text-kpi-blue-700 underline" data-testid="history-unfollow" @click="highlighted = null">{{ $t('history.unhighlight') }}</button>
        </template>
        <template v-else>{{ $t('history.hint') }}</template>
      </p>
      <HistoryTable :history="history" :highlighted="highlighted" :anchor="seasonAnchor" @highlight="highlight" />
    </section>
  </div>
</template>
