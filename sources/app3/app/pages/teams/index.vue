<script setup lang="ts">
import { withQuery } from 'ufo'
import { SEARCH_MIN_LENGTH, searchQuery } from '~/utils/search'
import type { TeamSummary } from '~/utils/teams'

// Team search (PAGE_TEAM.md § 2.1, TEA-01): GET form, suggestions while typing with JavaScript.
const route = useRoute()
const { t } = useI18n()
const localePath = useLocalePath()

const raw = computed(() => (typeof route.query.q === 'string' ? route.query.q : ''))
const query = computed(() => searchQuery(raw.value))
const teamsPath = (value: string) => withQuery('/teams', { q: value })

const { data: teams, error } = await useApiResource<TeamSummary[]>(() => (query.value ? teamsPath(query.value) : null), { notFoundIsError: false })

const input = ref(raw.value)
const { results } = useSuggestions<TeamSummary[]>(input, teamsPath)
const groups = computed(() => (results.value?.length
  ? [{ label: t('teams.results'), hits: results.value.map(team => ({ key: `t-${team.number}`, label: team.label, detail: team.club.label, to: `/teams/${team.number}` })) }]
  : []))

useSeoMeta({ title: () => t('teams.title'), description: () => t('teams.description') })
</script>

<template>
  <div class="space-y-8">
    <h1 class="text-5xl text-kpi-blue-600">{{ $t('teams.title') }}</h1>
    <div class="max-w-xl">
      <SearchCombobox id="team-search" v-model="input" action="/teams" :label="$t('teams.search')" :groups="groups" />
    </div>
    <p v-if="raw && !query" data-testid="teams-min-length">{{ $t('teams.minLength', { min: SEARCH_MIN_LENGTH }) }}</p>
    <p v-else-if="error" class="text-kpi-red-600" data-testid="teams-unavailable">{{ $t('common.unavailable') }}</p>
    <p v-else-if="query && teams && teams.length === 0" data-testid="teams-no-result">{{ $t('teams.noResult', { query }) }}</p>
    <section v-else-if="teams && teams.length" aria-labelledby="teams-results">
      <h2 id="teams-results" class="mb-3 text-3xl text-kpi-blue-600">{{ $t('teams.results') }}</h2>
      <ul class="grid gap-2 md:grid-cols-2" data-testid="teams-results">
        <li v-for="team in teams" :key="team.number">
          <NuxtLink :to="localePath(`/teams/${team.number}`)" class="block rounded border border-line p-3 hover:border-kpi-blue-500 hover:bg-kpi-blue-50">
            <span class="font-semibold text-kpi-blue-600">{{ team.label }}</span>
            <span v-if="team.club.label" class="block text-sm">{{ team.club.label }}</span>
          </NuxtLink>
        </li>
      </ul>
    </section>
  </div>
</template>
