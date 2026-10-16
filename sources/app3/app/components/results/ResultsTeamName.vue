<script setup lang="ts">
import { teamLink } from '~/utils/page-links'

// Team name linking to its team page, opened on the roster of the competition (CMP-12, TEA-04); waiting labels
// are plain text. Without `season`, the season of the current route (competition pages) is used.
const props = defineProps<{ label: string | null, number: number | null, competition: string, season?: string, strong?: boolean }>()
const route = useRoute()
const localePath = useLocalePath()
const season = computed(() => props.season ?? (typeof route.params.season === 'string' ? route.params.season : undefined))
const link = computed(() => (props.number === null ? null : teamLink({ number: props.number, competition: props.competition, season: season.value })))
</script>

<template>
  <NuxtLink v-if="link && label" :to="localePath(link.href)" class="hover:underline" :class="{ 'font-semibold': strong }" data-kind="team">
    {{ label }}
  </NuxtLink>
  <span v-else class="italic text-ink/70">{{ label ?? '—' }}</span>
</template>
