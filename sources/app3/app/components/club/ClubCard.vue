<script setup lang="ts">
import { legacyImageUrl } from '~/utils/page-links'
import type { ClubSummary } from '~/utils/clubs'

// One club of the list: logo, name, department (CLB-01). The list also replaces the logos page (CLB-04).
const props = defineProps<{ club: ClubSummary }>()
const localePath = useLocalePath()
const { legacyBaseUrl } = useRuntimeConfig().public
const logo = computed(() => legacyImageUrl(props.club.logo, legacyBaseUrl))
</script>

<template>
  <NuxtLink :to="localePath(`/clubs/${club.code}`)" class="flex h-full items-center gap-3 rounded border border-line p-3 hover:border-kpi-blue-500 hover:bg-kpi-blue-50" :data-club="club.code">
    <img v-if="logo" :src="logo" :alt="$t('clubs.logoAlt', { club: club.label })" loading="lazy" class="size-12 shrink-0 object-contain">
    <span v-else class="size-12 shrink-0" aria-hidden="true" />
    <span class="flex flex-col">
      <span class="font-semibold text-kpi-blue-600">{{ club.label }}</span>
      <span v-if="club.department.label" class="text-sm">{{ club.department.label }}</span>
    </span>
  </NuxtLink>
</template>
