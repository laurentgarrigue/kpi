<script setup lang="ts">
import { clubWebsite, type ClubSheet } from '~/utils/clubs'
import { NEW_TAB_ATTRS } from '~/utils/links'
import { legacyImageUrl } from '~/utils/page-links'

// Club page: committees, contact details of the structure, position, teams (PAGE_CLUBS.md § 2.2, CLB-03).
const route = useRoute()
const { t } = useI18n()
const localePath = useLocalePath()
const { legacyBaseUrl } = useRuntimeConfig().public
const code = computed(() => String(route.params.code))

const { data: club } = await useApiResource<ClubSheet>(() => `/club/${code.value}`)
const logo = computed(() => legacyImageUrl(club.value?.logo ?? null, legacyBaseUrl))
const website = computed(() => clubWebsite(club.value?.www ?? null))
const MAP_ZOOM = 12

useSeoMeta({
  title: () => club.value?.label ?? '',
  description: () => t('clubs.pageDescription', { club: club.value?.label ?? '' }),
})
useHead(() => ({
  script: club.value
    ? [{
        type: 'application/ld+json',
        innerHTML: JSON.stringify({
          '@context': 'https://schema.org',
          '@type': 'SportsOrganization',
          'name': club.value.label,
          'sport': 'Canoe polo',
          ...(club.value.postal ? { address: club.value.postal } : {}),
          ...(website.value ? { url: website.value } : {}),
        }),
      }]
    : [],
}))
</script>

<template>
  <div v-if="club" class="space-y-8">
    <SiteBreadcrumb :items="[{ label: $t('nav.home'), to: localePath('/') }, { label: $t('clubs.title'), to: localePath('/clubs') }, { label: club.label }]" />
    <header class="flex items-center gap-4">
      <img v-if="logo" :src="logo" :alt="$t('clubs.logoAlt', { club: club.label })" class="size-20 object-contain" data-testid="club-logo">
      <h1 class="text-5xl text-kpi-blue-600">{{ club.label }}</h1>
    </header>
    <dl class="grid max-w-3xl grid-cols-[auto_1fr] gap-x-6 gap-y-2" data-testid="club-details">
      <template v-if="club.department.label">
        <dt class="text-ink/70">{{ $t('clubs.department') }}</dt><dd>{{ club.department.label }}</dd>
      </template>
      <template v-if="club.region.label">
        <dt class="text-ink/70">{{ $t('clubs.region') }}</dt><dd>{{ club.region.label }}</dd>
      </template>
      <template v-if="website">
        <dt class="text-ink/70">{{ $t('clubs.website') }}</dt>
        <dd><a :href="website" v-bind="NEW_TAB_ATTRS" class="underline" data-testid="club-website">{{ club.www }}<span class="sr-only"> {{ $t('a11y.newTab') }}</span></a></dd>
      </template>
      <template v-if="club.email">
        <!-- Q-P3-2: the e-mail of the structure, as text (no indexable mailto: link). -->
        <dt class="text-ink/70">{{ $t('clubs.email') }}</dt><dd data-testid="club-email">{{ club.email }}</dd>
      </template>
      <template v-if="club.postal">
        <dt class="text-ink/70">{{ $t('clubs.address') }}</dt><dd>{{ club.postal }}</dd>
      </template>
    </dl>
    <ClubMap v-if="club.position" :markers="[{ code: club.code, label: club.label, position: club.position }]" :zoom="MAP_ZOOM" class="max-w-3xl" />
    <section aria-labelledby="club-teams">
      <h2 id="club-teams" class="mb-3 text-3xl text-kpi-blue-600">{{ $t('clubs.teams') }}</h2>
      <p v-if="club.teams.length === 0">{{ $t('clubs.noTeam') }}</p>
      <ul v-else class="space-y-1" data-testid="club-teams">
        <li v-for="team in club.teams" :key="team.number">
          <NuxtLink :to="localePath(`/teams/${team.number}`)" class="hover:underline">{{ team.label }}</NuxtLink>
        </li>
      </ul>
    </section>
  </div>
</template>
