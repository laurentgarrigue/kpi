<script setup lang="ts">
import { clubCity, clubWebsite, committeeName, directionsUrl, isPlaceholderTeam, titleCase, type ClubSheet } from '~/utils/clubs'
import { NEW_TAB_ATTRS } from '~/utils/links'
import { legacyImageUrl } from '~/utils/page-links'

// Sheet of the club chosen on the map or in the search, the only presentation of a club (CLB-06, CLB-08): identity,
// website and directions (outgoing links said as such), contact details as text (Q-P3-2), committees, teams without
// the placeholders of tournaments, news linked to the club.
const props = defineProps<{ club: ClubSheet }>()
const localePath = useLocalePath()
const { legacyBaseUrl } = useRuntimeConfig().public
const logo = computed(() => legacyImageUrl(props.club.logo, legacyBaseUrl))
const website = computed(() => clubWebsite(props.club.www))
const teams = computed(() => props.club.teams.filter(team => !isPlaceholderTeam(team.label)))
const where = computed(() => clubCity(props.club.postal) || committeeName(props.club.department.label))
</script>

<template>
  <section aria-labelledby="club-sheet-title" data-testid="club-sheet">
    <div class="mb-4 grid grid-cols-[6rem_minmax(0,1fr)] items-center gap-4">
      <img v-if="logo" :src="logo" alt="" class="kpi-logo size-24 rounded-lg bg-wash object-contain">
      <span v-else class="grid size-24 place-items-center rounded-lg bg-wash font-display text-3xl text-kpi-blue-500" aria-hidden="true">{{ club.code }}</span>
      <div>
        <h2 id="club-sheet-title" class="text-[1.875rem] leading-none text-navy dark:text-ink">{{ titleCase(club.label) }}</h2>
        <p class="mt-1 text-sm text-muted" data-testid="club-city">{{ where }}</p>
      </div>
    </div>
    <p class="mb-5 flex flex-wrap gap-2" data-testid="club-actions">
      <a v-if="website" :href="website" v-bind="NEW_TAB_ATTRS" class="inline-flex items-center gap-1.5 rounded-md border border-line px-3 py-1.5 text-sm font-semibold hover:bg-wash">
        {{ $t('clubs.website') }}<UIcon name="i-heroicons-arrow-top-right-on-square" class="size-3.5" aria-hidden="true" /><span class="sr-only">{{ $t('a11y.newTab') }}</span>
      </a>
      <a v-if="club.position" :href="directionsUrl(club.position)" v-bind="NEW_TAB_ATTRS" class="inline-flex items-center gap-1.5 rounded-md border border-line px-3 py-1.5 text-sm font-semibold hover:bg-wash">
        {{ $t('clubs.directions') }}<UIcon name="i-heroicons-arrow-top-right-on-square" class="size-3.5" aria-hidden="true" /><span class="sr-only">{{ $t('a11y.newTab') }}</span>
      </a>
    </p>
    <dl class="mb-4.5 grid grid-cols-[6.875rem_minmax(0,1fr)] gap-x-3 gap-y-1.5 text-sm">
      <template v-if="club.postal"><dt class="text-muted">{{ $t('clubs.address') }}</dt><dd class="[overflow-wrap:anywhere]">{{ club.postal }}</dd></template>
      <!-- Q-P3-2: the e-mail of the structure, as text (no indexable mailto: link). -->
      <template v-if="club.email"><dt class="text-muted">{{ $t('clubs.email') }}</dt><dd class="[overflow-wrap:anywhere]">{{ club.email }}</dd></template>
      <template v-if="club.department.label"><dt class="text-muted">{{ $t('clubs.department') }}</dt><dd>{{ committeeName(club.department.label) }}</dd></template>
      <template v-if="club.region.label"><dt class="text-muted">{{ $t('clubs.region') }}</dt><dd>{{ committeeName(club.region.label) }}</dd></template>
      <dt class="text-muted">{{ $t('clubs.code') }}</dt><dd>{{ club.code }}</dd>
    </dl>
    <template v-if="teams.length">
      <h3 class="mb-1.5 font-sans text-[0.9375rem] font-bold">{{ $t('clubs.teamCount', { n: teams.length }, teams.length) }}</h3>
      <ul class="text-sm sm:columns-2 sm:gap-4" data-testid="club-teams">
        <li v-for="team in teams" :key="team.number" class="break-inside-avoid border-t border-line py-1">
          <NuxtLink :to="localePath(`/teams/${team.number}`)" class="hover:underline">{{ team.label.trim() }}</NuxtLink>
        </li>
      </ul>
    </template>
    <p v-else class="text-muted">{{ $t('clubs.noTeam') }}</p>
    <ContentLinked kind="club" :reference="club.code" class="mt-6" />
  </section>
</template>
