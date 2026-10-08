<script setup lang="ts">
import type { CompetitionChart, CompetitionDetails } from '#kpi-layer/utils/results/types'
import { PROGRESS_VIEWS, progressView } from '~/utils/competitions'

// « Progress » tab: horizontal (kpchart.php) or vertical (kpphases.php) view of the same data (CMP-09).
const props = defineProps<{ competition: CompetitionDetails }>()
const { apiPath } = useCompetitionTab(() => props.competition, 'progress')
const route = useRoute()
const { data: charts, error } = await useApiResource<CompetitionChart[]>(() => apiPath('charts'))
const chart = computed(() => charts.value?.find(item => item.code === props.competition.code) ?? null)
const view = computed(() => progressView(route.query.view))
</script>

<template>
  <div class="space-y-4">
    <nav :aria-label="$t('results.progressView')" class="flex gap-2" data-testid="progress-switch">
      <NuxtLink
        v-for="option in PROGRESS_VIEWS"
        :key="option"
        :to="{ path: route.path, query: { ...route.query, view: option } }"
        :aria-pressed="option === view"
        class="rounded border px-3 py-1 text-sm"
        :class="option === view ? 'border-kpi-blue-600 bg-kpi-blue-600 text-white' : 'border-line hover:bg-kpi-blue-50'"
      >
        {{ $t(`results.view.${option}`) }}
      </NuxtLink>
    </nav>
    <p v-if="error" class="text-kpi-red-600" data-testid="results-unavailable">{{ $t('results.unavailable') }}</p>
    <p v-else-if="!chart" data-testid="no-progress">{{ $t('results.noProgress') }}</p>
    <ResultsProgress v-else :chart="chart" :view="view" />
  </div>
</template>
