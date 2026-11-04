<script setup lang="ts">
import { joinURL } from 'ufo'

// Header of the event and group views (EVT-02, EVT-08, GRP-02): a navy band (the gathering) with the place and dates,
// the number of competitions in large figures and « Follow live »; a competition header is white and compact.
const props = defineProps<{
  kind: 'event' | 'group'
  title: string
  subtitle?: string
  logo?: string | null
  liveUrl: string
  competitionCount?: number
}>()
const { legacyBaseUrl } = useRuntimeConfig().public
const logoUrl = computed(() => (props.logo ? joinURL(legacyBaseUrl, 'img', props.logo) : null))
</script>

<template>
  <header class="flex flex-wrap items-end gap-x-7 gap-y-3 rounded-lg bg-navy px-5 pt-5 pb-6 text-white md:px-7" :data-kind="kind" data-testid="scope-header">
    <img v-if="logoUrl" :src="logoUrl" alt="" class="size-16 self-center rounded bg-white object-contain p-1">
    <div class="min-w-0 flex-[1_1_20rem]">
      <h1 class="text-[2.5rem] leading-none text-white md:text-[3.5rem]">{{ title }}</h1>
      <p v-if="subtitle" class="mt-2 text-[1.0625rem] text-white/85">{{ subtitle }}</p>
    </div>
    <p v-if="competitionCount" class="font-display text-[4rem] leading-[0.85] text-sky md:text-right" data-testid="scope-count">
      {{ competitionCount }}<small class="block font-sans text-[0.8125rem] font-semibold leading-snug text-white/85">{{ $t('results.competitionCount', competitionCount) }}</small>
    </p>
    <ResultsLiveButton :href="liveUrl" class="md:ml-auto" data-testid="scope-live" />
  </header>
</template>
