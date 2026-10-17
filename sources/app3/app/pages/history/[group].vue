<script setup lang="ts">
import { groupLabel } from '~/utils/competitions'
import { seasonAnchor, type History, type HistoryGroups } from '~/utils/history'

// Honours of a group, season by season (PAGE_HISTORY.md, HIS-02 to HIS-05).
const route = useRoute()
const { t, locale } = useI18n()
const localePath = useLocalePath()
const code = computed(() => String(route.params.group))

const { data: history } = await useApiResource<History>(() => `/history/${code.value}`)
const { data: groups } = await useApiResource<HistoryGroups>('/history', { notFoundIsError: false })
const name = computed(() => (history.value ? groupLabel(history.value.group, locale.value) : ''))

useSeoMeta({
  title: () => t('history.pageTitle', { group: name.value }),
  description: () => t('history.description', { group: name.value }),
})
</script>

<template>
  <div v-if="history" class="space-y-8">
    <SiteBreadcrumb :items="[{ label: $t('nav.home'), to: localePath('/') }, { label: $t('history.title') }, { label: name }]" />
    <h1 class="text-5xl text-kpi-blue-600">{{ $t('history.pageTitle', { group: name }) }}</h1>
    <HistoryGroupSelector v-if="groups" :sections="groups.sections" :group="history.group.code" />
    <nav :aria-label="$t('history.seasons')" data-testid="season-anchors">
      <ul class="flex flex-wrap gap-2">
        <li v-for="item in history.seasons" :key="item.season">
          <a :href="`#${seasonAnchor(item.season)}`" class="rounded border border-line px-2 py-0.5 text-sm hover:bg-kpi-blue-50">{{ item.season }}</a>
        </li>
      </ul>
    </nav>
    <section v-for="item in history.seasons" :id="seasonAnchor(item.season)" :key="item.season" :aria-labelledby="`${seasonAnchor(item.season)}-title`" data-testid="history-season">
      <h2 :id="`${seasonAnchor(item.season)}-title`" class="mb-3 text-3xl text-kpi-blue-600">{{ item.season }}</h2>
      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <HistoryCompetitionCard v-for="competition in item.competitions" :key="competition.code" :competition="competition" :season="item.season" />
      </div>
    </section>
  </div>
</template>
