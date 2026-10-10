<script setup lang="ts">
import { joinURL } from 'ufo'
import type { CompetitionHeader } from '#kpi-layer/utils/results/types'
import { NEW_TAB_ATTRS } from '~/utils/links'

// Competition header: visual, title, season, type and status badges, links (CMP-02).
const props = defineProps<{ competition: CompetitionHeader }>()
const { legacyBaseUrl, app2BaseUrl } = useRuntimeConfig().public

const visual = computed(() => {
  const path = props.competition.banner ?? props.competition.logo
  return path ? (path.startsWith('http') ? path : joinURL(legacyBaseUrl, 'img', path)) : null
})
const liveUrl = computed(() => joinURL(app2BaseUrl, 'group', props.competition.season, props.competition.group.code))
</script>

<template>
  <header class="space-y-3 border-l-4 border-kpi-blue-600 pl-4" data-testid="competition-header">
    <img
      v-if="visual"
      :src="visual"
      alt=""
      :class="competition.banner ? 'max-h-32 w-full object-contain' : 'size-20 object-contain'"
      data-testid="competition-visual"
    >
    <p class="text-sm font-semibold uppercase tracking-wide text-kpi-blue-600" data-testid="competition-kind">{{ $t('results.kind.competition') }}</p>
    <div class="flex flex-wrap items-baseline gap-3">
      <h1 class="text-4xl text-kpi-blue-600 sm:text-5xl">{{ competition.display_title }}</h1>
      <span class="text-xl tabular-nums">{{ competition.season }}</span>
      <UBadge variant="subtle" color="neutral" data-testid="type-badge">{{ $t(`results.type.${competition.type}`) }}</UBadge>
      <UBadge variant="subtle" :color="competition.status === 'ON' ? 'error' : 'primary'" data-testid="status-badge">
        {{ $t(`results.competitionStatus.${competition.status}`) }}
      </UBadge>
    </div>
    <p v-if="competition.soustitre2" class="text-lg">{{ competition.soustitre2 }}</p>
    <p class="flex flex-wrap gap-4 text-sm">
      <a v-if="competition.web" :href="competition.web" v-bind="NEW_TAB_ATTRS" class="underline" data-testid="competition-web">
        {{ $t('results.website') }}<span class="sr-only"> {{ $t('a11y.newTab') }}</span>
      </a>
    </p>
    <ResultsLiveButton :href="liveUrl" data-testid="competition-live" />
  </header>
</template>
