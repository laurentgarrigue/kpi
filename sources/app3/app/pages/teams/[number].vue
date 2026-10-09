<script setup lang="ts">
import { legacyImageUrl } from '~/utils/page-links'
import { selectedRoster, type Roster, type TeamSheet } from '~/utils/teams'

// Team page: club, colours and team photo, honours, roster by competition (PAGE_TEAM.md § 2.2, TEA-02, TEA-03, TEA-05).
const route = useRoute()
const { t } = useI18n()
const localePath = useLocalePath()
const { legacyBaseUrl } = useRuntimeConfig().public
const number = computed(() => String(route.params.number))

const { data: team } = await useApiResource<TeamSheet>(() => `/team/${number.value}`)
const roster = computed(() => (team.value ? selectedRoster(team.value.seasons, route.query) : null))
const competitionTitle = computed(() => team.value?.seasons
  .find(item => item.season === roster.value?.season)?.competitions
  .find(item => item.code === roster.value?.competition)?.display_title ?? '')
const { data: players, error: rosterError } = await useApiResource<Roster>(
  () => (roster.value ? `/team/${number.value}/roster/${roster.value.season}/${roster.value.competition}` : null),
  { notFoundIsError: false },
)

const logo = computed(() => legacyImageUrl(team.value?.logo ?? null, legacyBaseUrl))
const colors = computed(() => legacyImageUrl(team.value?.colors?.image ?? null, legacyBaseUrl))
// Team photo kept until the GDPR study (Q-P3-3).
const photo = computed(() => legacyImageUrl(team.value?.photo?.image ?? null, legacyBaseUrl))

useSeoMeta({
  title: () => team.value?.label ?? '',
  description: () => t('teams.pageDescription', { team: team.value?.label ?? '' }),
})
useHead(() => ({
  script: team.value
    ? [{
        type: 'application/ld+json',
        innerHTML: JSON.stringify({
          '@context': 'https://schema.org',
          '@type': 'SportsTeam',
          'name': team.value.label,
          'sport': 'Canoe polo',
          ...(team.value.club.label ? { memberOf: { '@type': 'SportsOrganization', 'name': team.value.club.label } } : {}),
        }),
      }]
    : [],
}))
</script>

<template>
  <div v-if="team" class="space-y-10">
    <SiteBreadcrumb :items="[{ label: $t('nav.home'), to: localePath('/') }, { label: $t('teams.title'), to: localePath('/teams') }, { label: team.label }]" />
    <header class="flex flex-wrap items-center gap-6" data-testid="team-header">
      <img v-if="logo" :src="logo" :alt="$t('clubs.logoAlt', { club: team.club.label ?? team.label })" class="size-20 object-contain" data-testid="team-logo">
      <div class="space-y-1">
        <h1 class="text-5xl text-kpi-blue-600">{{ team.label }}</h1>
        <NuxtLink v-if="team.club.code" :to="localePath(`/clubs/${team.club.code}`)" class="underline" data-testid="team-club">
          {{ team.club.label ?? team.club.code }}
        </NuxtLink>
      </div>
      <img v-if="colors" :src="colors" :alt="$t('teams.colorsAlt', { team: team.label })" class="h-20 w-auto" data-testid="team-colors">
    </header>
    <figure v-if="photo && team.photo" class="max-w-3xl" data-testid="team-photo">
      <img :src="photo" :alt="$t('teams.photoAlt', { team: team.label, season: team.photo.season })" loading="lazy" class="w-full rounded">
    </figure>

    <section aria-labelledby="honours-title">
      <h2 id="honours-title" class="mb-3 text-3xl text-kpi-blue-600">{{ $t('teams.honours') }}</h2>
      <TeamHonours :honours="team.honours" :team="team.label" />
    </section>

    <section v-if="roster" aria-labelledby="roster-title" class="space-y-4">
      <h2 id="roster-title" class="text-3xl text-kpi-blue-600">{{ $t('teams.roster') }}</h2>
      <TeamRosterSelector :seasons="team.seasons" :season="roster.season" :competition="roster.competition" :path="`/teams/${team.number}`" />
      <p v-if="rosterError" class="text-kpi-red-600" data-testid="roster-unavailable">{{ $t('common.unavailable') }}</p>
      <TeamRoster v-else-if="players" :players="players.players" :caption="$t('teams.rosterCaption', { team: team.label, competition: competitionTitle, season: roster.season })" />
    </section>
  </div>
</template>
