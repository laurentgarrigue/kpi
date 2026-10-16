<script setup lang="ts">
import { chartRounds, phasesByLevel } from '#kpi-layer/utils/results/charts'
import type { CompetitionChart } from '#kpi-layer/utils/results/types'
import type { ProgressView } from '~/utils/competitions'

// Progress of a competition: rounds in columns (kpchart.php) or phases by level (kpphases.php) — CMP-09.
const props = defineProps<{ chart: CompetitionChart, view: ProgressView }>()
const rounds = computed(() => chartRounds(props.chart))
const phases = computed(() => phasesByLevel(props.chart))
</script>

<template>
  <div v-if="view === 'horizontal'" class="flex gap-4 overflow-x-auto pb-2" data-testid="progress-horizontal">
    <section v-for="round in rounds" :key="round.round" class="w-80 shrink-0 space-y-4 rounded border border-line bg-kpi-blue-50/40 p-3">
      <h3 class="sr-only">{{ $t('results.round', { round: round.round }) }}</h3>
      <article v-for="phase in round.phases" :key="phase.d_id" class="space-y-2" data-testid="phase">
        <h4 class="text-center font-semibold">{{ phase.libelle }}</h4>
        <ResultsPoolTable v-if="phase.type === 'C'" :phase="phase" :competition="chart.code" />
        <template v-else>
          <ResultsKnockoutGame v-for="game in phase.games ?? []" :key="game.g_id" :game="game" :competition="chart.code" />
        </template>
      </article>
    </section>
  </div>
  <div v-else class="space-y-8" data-testid="progress-vertical">
    <section v-for="phase in phases" :key="phase.d_id" class="space-y-3" data-testid="phase">
      <h3 class="text-2xl text-kpi-blue-600">{{ phase.libelle }}</h3>
      <ResultsPoolTable v-if="phase.type === 'C'" :phase="phase" :competition="chart.code" />
      <div class="grid gap-2 sm:grid-cols-2">
        <ResultsKnockoutGame v-for="game in phase.games ?? []" :key="game.g_id" :game="game" :competition="chart.code" />
      </div>
    </section>
  </div>
</template>
