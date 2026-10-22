<script setup lang="ts">
import { joinURL } from 'ufo'
import type { CompetitionHeader } from '#kpi-layer/utils/results/types'
import { NEW_TAB_ATTRS } from '~/utils/links'

// Compact competition header (CMP-02, CMP-18): title, season, written status, « Follow live »; the banner when the
// organiser gave one (else the logo), the subtitle only when it differs from the title.
const props = defineProps<{ competition: CompetitionHeader }>()
const { legacyBaseUrl, app2BaseUrl } = useRuntimeConfig().public

const visual = computed(() => {
  const path = props.competition.banner ?? props.competition.logo
  return path ? (path.startsWith('http') ? path : joinURL(legacyBaseUrl, 'img', path)) : null
})
const subtitle = computed(() => {
  const value = props.competition.soustitre2?.trim()
  return value && value.toLowerCase() !== props.competition.display_title.trim().toLowerCase() ? value : null
})
const liveUrl = computed(() => joinURL(app2BaseUrl, 'group', props.competition.season, props.competition.group.code))
</script>

<template>
  <header class="space-y-3" data-testid="competition-header">
    <img
      v-if="visual && competition.banner"
      :src="visual"
      alt=""
      class="kpi-logo max-h-28 w-full object-contain object-left"
      data-testid="competition-visual"
    >
    <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1.5">
      <img v-if="visual && !competition.banner" :src="visual" alt="" class="kpi-logo size-12 self-center object-contain" data-testid="competition-visual">
      <h1 class="text-[2.5rem] leading-none text-navy md:text-[3.25rem] dark:text-ink">{{ competition.display_title }}</h1>
      <span class="font-display text-2xl leading-none text-kpi-blue-500 md:text-3xl">{{ competition.season }}</span>
      <span class="text-sm font-semibold text-muted" data-testid="status-badge">{{ $t(`results.competitionStatus.${competition.status}`) }}</span>
      <ResultsLiveButton :href="liveUrl" class="self-center md:ml-auto" data-testid="competition-live" />
    </div>
    <p v-if="subtitle || competition.web" class="flex flex-wrap items-baseline gap-x-4 gap-y-1 text-muted">
      <span v-if="subtitle" class="text-lg" data-testid="competition-subtitle">{{ subtitle }}</span>
      <a v-if="competition.web" :href="competition.web" v-bind="NEW_TAB_ATTRS" class="text-sm underline" data-testid="competition-web">
        {{ $t('results.website') }}<span class="sr-only"> {{ $t('a11y.newTab') }}</span>
      </a>
    </p>
  </header>
</template>
