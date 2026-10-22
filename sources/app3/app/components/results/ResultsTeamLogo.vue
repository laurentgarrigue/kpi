<script setup lang="ts">
import { teamLogoUrl } from '~/utils/page-links'

// Club logo of a team, decorative (the name is next to it); an empty square keeps names aligned when there is none.
const props = defineProps<{ path: string | null | undefined, size?: 'sm' | 'md' | 'lg' | 'podium' | 'champion' }>()
const { legacyBaseUrl } = useRuntimeConfig().public
const src = computed(() => teamLogoUrl(props.path ?? null, legacyBaseUrl))
const SIZES = { sm: 'size-5', md: 'size-6.5', lg: 'size-8', podium: 'size-12 md:size-18', champion: 'size-15 md:size-23' }
</script>

<template>
  <img
    v-if="src"
    :src="src"
    alt=""
    loading="lazy"
    class="kpi-logo shrink-0 object-contain"
    :class="SIZES[size ?? 'md']"
    data-testid="team-logo"
  >
  <span v-else class="shrink-0" :class="SIZES[size ?? 'md']" aria-hidden="true" />
</template>
