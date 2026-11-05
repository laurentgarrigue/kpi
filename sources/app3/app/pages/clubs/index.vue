<script setup lang="ts">
import { withQuery } from 'ufo'
import { clubMarkers, clubWebsite, committeeName, titleCase, type ClubSheet, type ClubSummary } from '~/utils/clubs'
import { SEARCH_MIN_LENGTH, searchQuery } from '~/utils/search'
import type { TeamSummary } from '~/utils/teams'

// « Clubs and teams » (PAGE_CLUBS.md § 2.1, CLB-01 to CLB-07): search and the sheet of the chosen club beside the map.
const route = useRoute()
const { t } = useI18n()
const localePath = useLocalePath()

const raw = computed(() => (typeof route.query.q === 'string' ? route.query.q : ''))
const query = computed(() => searchQuery(raw.value))
const code = computed(() => (typeof route.query.club === 'string' && route.query.club ? route.query.club : null))

// Every club for the map; the searches and the chosen club each fail alone (an error is an empty answer).
const { data: clubs } = await useApiResource<ClubSummary[]>('/clubs', { notFoundIsError: false })
const { data: foundClubs, error: clubsError } = await useApiResource<ClubSummary[]>(() => (query.value ? withQuery('/clubs', { q: query.value }) : null), { notFoundIsError: false })
const { data: foundTeams, error: teamsError } = await useApiResource<TeamSummary[]>(() => (query.value ? withQuery('/teams', { q: query.value }) : null), { notFoundIsError: false })
const { data: club } = await useApiResource<ClubSheet>(() => (code.value ? `/club/${code.value}` : null), { notFoundIsError: false })

const markers = computed(() => clubMarkers(clubs.value ?? []).map(item => ({ code: item.code, label: titleCase(item.label), position: item.position })))
const clubHits = computed(() => (clubsError.value ? [] : foundClubs.value ?? []))
const teamHits = computed(() => (teamsError.value ? [] : foundTeams.value ?? []))
const searched = computed(() => query.value !== null && (foundClubs.value !== null || foundTeams.value !== null || clubsError.value || teamsError.value))
const clubLink = (target: string) => ({ path: localePath('/clubs'), query: { club: target } })
const select = (target: string) => navigateTo(clubLink(target))

useSeoMeta({
  title: () => (club.value ? titleCase(club.value.label) : t('clubs.title')),
  description: () => t('clubs.description'),
})
// The chosen club as structured data (PAGE_CLUBS.md § 4).
useHead(() => {
  if (!club.value) {
    return {}
  }
  const website = clubWebsite(club.value.www)
  const organization = {
    '@context': 'https://schema.org',
    '@type': 'SportsOrganization',
    'name': titleCase(club.value.label),
    'sport': 'Canoe polo',
    ...(club.value.postal ? { address: club.value.postal } : {}),
    ...(website ? { url: website } : {}),
  }
  return { script: [{ type: 'application/ld+json', innerHTML: JSON.stringify(organization) }] }
})
</script>

<template>
  <div class="grid gap-y-4 pb-8 lg:h-[calc(100vh-9.5rem)] lg:min-h-[35rem] lg:grid-cols-[25rem_minmax(0,1fr)] lg:grid-rows-[auto_minmax(0,1fr)] lg:gap-x-6">
    <div class="space-y-4 lg:col-start-1 lg:row-start-1">
      <div>
        <h1 class="mb-3 text-[2.5rem] leading-none text-navy dark:text-ink">{{ $t('clubs.title') }}</h1>
        <form method="get" :action="localePath('/clubs')" role="search" class="relative" data-testid="club-search">
          <UIcon name="i-heroicons-magnifying-glass" class="pointer-events-none absolute top-1/2 left-3 size-4.5 -translate-y-1/2 text-muted" aria-hidden="true" />
          <input
            name="q"
            type="search"
            :value="raw"
            maxlength="50"
            :placeholder="$t('clubs.search')"
            :aria-label="$t('clubs.searchLabel')"
            class="w-full rounded-lg border border-line bg-surface py-2.5 pr-3 pl-9.5 text-base"
          >
        </form>
      </div>

      <p v-if="raw && !query" class="text-sm" data-testid="clubs-min-length">{{ $t('teams.minLength', { min: SEARCH_MIN_LENGTH }) }}</p>
      <template v-else-if="searched">
        <p v-if="clubHits.length === 0 && teamHits.length === 0" class="text-sm text-muted" data-testid="clubs-no-result">{{ $t('clubs.noResult', { query: query ?? '' }) }}</p>
        <section v-if="clubHits.length" aria-labelledby="club-results-title" data-testid="club-results">
          <h2 id="club-results-title" class="mb-1 font-sans text-xs font-bold text-muted">{{ $t('clubs.resultsClubs') }}</h2>
          <ul>
            <li v-for="item in clubHits" :key="item.code">
              <NuxtLink :to="clubLink(item.code)" class="flex items-center gap-2.5 rounded-md px-2 py-1.5 hover:bg-wash" :data-club="item.code">
                <ResultsTeamLogo :path="item.logo" />
                <span>{{ titleCase(item.label) }}<small class="block text-xs text-muted">{{ committeeName(item.department.label) }} · {{ item.code }}</small></span>
              </NuxtLink>
            </li>
          </ul>
        </section>
        <section v-if="teamHits.length" aria-labelledby="team-results-title" data-testid="team-results">
          <h2 id="team-results-title" class="mb-1 font-sans text-xs font-bold text-muted">{{ $t('clubs.resultsTeams') }}</h2>
          <ul>
            <li v-for="item in teamHits" :key="item.number">
              <NuxtLink :to="localePath(`/teams/${item.number}`)" class="block rounded-md px-2 py-1.5 hover:bg-wash">
                {{ item.label }}<small class="block text-xs text-muted">{{ titleCase(item.club.label) }}</small>
              </NuxtLink>
            </li>
          </ul>
        </section>
      </template>
    </div>
    <ClubMap :markers="markers" :selected="club?.code ?? null" class="h-[52vh] lg:col-start-2 lg:row-span-2 lg:row-start-1 lg:h-full" @select="select" />
    <section :aria-label="$t('clubs.sheet')" class="min-h-0 lg:col-start-1 lg:row-start-2 lg:overflow-auto lg:pr-2">
      <ClubSheet v-if="club" :club="club" class="border-t border-line pt-4" />
      <div v-else class="space-y-2.5 border-t border-line pt-4 text-muted" data-testid="club-hint">
        <p><b class="text-ink">{{ $t('clubs.hint', { count: markers.length }, markers.length) }}</b></p>
        <p>{{ $t('clubs.hintHow') }}</p>
      </div>
    </section>
  </div>
</template>
