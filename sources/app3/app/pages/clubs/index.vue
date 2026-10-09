<script setup lang="ts">
import { withQuery } from 'ufo'
import { CLUB_VIEWS, clubMarkers, clubView, type ClubSummary } from '~/utils/clubs'
import { SEARCH_MIN_LENGTH, searchQuery } from '~/utils/search'

// Clubs: search, list (default) or map on demand (PAGE_CLUBS.md § 2.1, CLB-01, CLB-02, CLB-04).
const route = useRoute()
const { t } = useI18n()
const localePath = useLocalePath()

const raw = computed(() => (typeof route.query.q === 'string' ? route.query.q : ''))
const query = computed(() => searchQuery(raw.value))
const view = computed(() => clubView(route.query.view))
const { data: clubs, error } = await useApiResource<ClubSummary[]>(() => (query.value ? withQuery('/clubs', { q: query.value }) : '/clubs'), { notFoundIsError: false })
const markers = computed(() => clubMarkers(clubs.value ?? []).map(club => ({ code: club.code, label: club.label, position: club.position })))
const viewLink = (target: string) => ({ path: localePath('/clubs'), query: { ...(query.value ? { q: query.value } : {}), ...(target === 'map' ? { view: 'map' } : {}) } })

useSeoMeta({ title: () => t('clubs.title'), description: () => t('clubs.description') })
</script>

<template>
  <div class="space-y-6">
    <h1 class="text-5xl text-kpi-blue-600">{{ $t('clubs.title') }}</h1>
    <div class="flex flex-wrap items-end justify-between gap-4">
      <form method="get" :action="localePath('/clubs')" role="search" class="flex items-end gap-2" data-testid="club-search">
        <label class="flex flex-col text-sm">
          {{ $t('clubs.search') }}
          <input name="q" type="search" :value="raw" maxlength="50" class="rounded border border-line px-2 py-1">
        </label>
        <input v-if="view === 'map'" type="hidden" name="view" value="map">
        <UButton type="submit" variant="outline">{{ $t('header.search') }}</UButton>
      </form>
      <nav class="flex gap-1" data-testid="club-views">
        <NuxtLink
          v-for="item in CLUB_VIEWS"
          :key="item"
          :to="viewLink(item)"
          :aria-current="item === view ? 'page' : undefined"
          class="rounded border px-3 py-1"
          :class="item === view ? 'border-kpi-blue-600 bg-kpi-blue-600 text-white' : 'border-line hover:bg-kpi-blue-50'"
        >
          {{ $t(`clubs.view.${item}`) }}
        </NuxtLink>
      </nav>
    </div>
    <p v-if="raw && !query" data-testid="clubs-min-length">{{ $t('teams.minLength', { min: SEARCH_MIN_LENGTH }) }}</p>
    <p v-if="error" class="text-kpi-red-600" data-testid="clubs-unavailable">{{ $t('common.unavailable') }}</p>
    <p v-else-if="clubs && clubs.length === 0" data-testid="clubs-no-result">{{ $t('clubs.noResult', { query: query ?? '' }) }}</p>
    <template v-else-if="clubs">
      <p class="text-sm text-ink/70">{{ $t('clubs.count', clubs.length) }}</p>
      <ClubMap v-if="view === 'map'" :markers="markers" />
      <ul v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" data-testid="club-list">
        <li v-for="club in clubs" :key="club.code"><ClubCard :club="club" /></li>
      </ul>
    </template>
  </div>
</template>
