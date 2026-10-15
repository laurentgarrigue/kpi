<script setup lang="ts">
import { joinURL } from 'ufo'

// Header of the event and group views (EVT-02, GRP-02). The dark band and the « Event » / « Group » label tell it
// apart from a competition header (white, « Competition » label) for visitors new to the structure (EVT-08).
const props = defineProps<{ kind: 'event' | 'group', title: string, subtitle?: string, logo?: string | null, liveUrl: string }>()
const { legacyBaseUrl } = useRuntimeConfig().public
const logoUrl = computed(() => (props.logo ? joinURL(legacyBaseUrl, 'img', props.logo) : null))
</script>

<template>
  <header class="flex flex-wrap items-center gap-4 rounded bg-navy p-4 text-white" :data-kind="kind" data-testid="scope-header">
    <img v-if="logoUrl" :src="logoUrl" alt="" class="size-20 rounded bg-white object-contain p-1">
    <div class="min-w-0 flex-1 space-y-1">
      <p class="text-sm font-semibold uppercase tracking-wide text-sky" data-testid="scope-kind">{{ $t(`results.kind.${kind}`) }}</p>
      <h1 class="text-4xl text-white sm:text-5xl">{{ title }}</h1>
      <p v-if="subtitle" class="text-lg">{{ subtitle }}</p>
    </div>
    <ResultsLiveButton :href="liveUrl" data-testid="scope-live" />
  </header>
</template>
