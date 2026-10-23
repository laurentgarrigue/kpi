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
  <div v-if="team" class="space-y-8 pb-10">
    <SiteBreadcrumb :items="[{ label: $t('nav.home'), to: localePath('/') }, { label: $t('teams.title'), to: localePath('/teams') }, { label: team.label }]" />
    <header class="grid grid-cols-[4.5rem_minmax(0,1fr)] items-center gap-6 border-b border-line pb-7 md:grid-cols-[auto_minmax(0,1fr)_auto]" data-testid="team-header">
      <TeamZoomImage v-if="logo" :src="logo" :alt="$t('clubs.logoAlt', { club: team.club.label ?? team.label })" origin="left" class="size-18 object-contain md:size-30" data-testid="team-logo" />
      <div :class="{ 'col-span-2 md:col-span-1': !logo }">
        <h1 class="text-[2.625rem] leading-none text-navy md:text-[4rem] dark:text-ink">{{ team.label }}</h1>
        <i18n-t v-if="team.club.code" keypath="teams.clubLine" tag="p" scope="global" class="mt-1.5" data-testid="team-club-line">
          <template #club>
            <NuxtLink :to="localePath(`/clubs/${team.club.code}`)" class="font-semibold underline decoration-kpi-blue-500 underline-offset-3" data-testid="team-club">{{ team.club.label ?? team.club.code }}</NuxtLink>
          </template>
        </i18n-t>
      </div>
      <TeamZoomImage v-if="colors" :src="colors" :alt="$t('teams.colorsAlt', { team: team.label })" origin="right" class="hidden h-30 w-auto md:block" data-testid="team-colors" />
    </header>
    <TeamMedalTally :honours="team.honours" />
    <figure v-if="photo && team.photo" class="max-w-3xl" data-testid="team-photo">
      <img :src="photo" :alt="$t('teams.photoAlt', { team: team.label, season: team.photo.season })" loading="lazy" class="w-full rounded">
    </figure>

    <section aria-labelledby="honours-title">
      <h2 id="honours-title" class="mb-3 text-[1.875rem] leading-none text-kpi-blue-700">
        {{ team.honours.length ? $t('teams.honoursSince', { season: team.honours.at(-1)!.season }) : $t('teams.honours') }}
      </h2>
      <TeamHonours :honours="team.honours" />
    </section>

    <section v-if="roster" aria-labelledby="roster-title" class="space-y-4">
      <h2 id="roster-title" class="text-[1.875rem] leading-none text-kpi-blue-700">{{ $t('teams.roster') }}</h2>
      <TeamRosterSelector :seasons="team.seasons" :season="roster.season" :competition="roster.competition" :path="`/teams/${team.number}`" />
      <p v-if="rosterError" class="text-kpi-red-600" data-testid="roster-unavailable">{{ $t('common.unavailable') }}</p>
      <TeamRoster v-else-if="players" :players="players.players" :caption="$t('teams.rosterCaption', { team: team.label, competition: competitionTitle, season: roster.season })" />
    </section>
  </div>
</template>
