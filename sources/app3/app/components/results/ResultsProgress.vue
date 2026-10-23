<script setup lang="ts">
import { chartRounds, phasesByLevel } from '#kpi-layer/utils/results/charts'
import { isFinal } from '#kpi-layer/utils/results/games'
import type { ChartPhase, CompetitionChart } from '#kpi-layer/utils/results/types'
import type { ProgressView } from '~/utils/competitions'

/**
 * Progress of a competition (CMP-09, CMP-24): one column per round, titled for screen readers only and centred
 * vertically (kpchart.php), or phases by level (kpphases.php). Touching a team highlights its whole path.
 */
const props = defineProps<{ chart: CompetitionChart, view: ProgressView }>()
const rounds = computed(() => chartRounds(props.chart))
const phases = computed(() => phasesByLevel(props.chart))

const followed = ref<number | null>(null)
const follow = (team: number) => {
  followed.value = followed.value === team ? null : team
}
const followedLabel = computed(() => {
  const team = rounds.value.flatMap(round => round.phases).flatMap(phase => [
    ...(phase.teams ?? []).map(item => ({ number: item.t_number, label: item.t_label })),
    ...(phase.games ?? []).flatMap(game => [{ number: game.t_a_number, label: game.t_a_label }, { number: game.t_b_number, label: game.t_b_label }]),
  ]).find(item => item.number === followed.value)
  return team?.label ?? ''
})
const final = (phase: ChartPhase) => isFinal({ d_phase: phase.libelle })
const pool = (phase: ChartPhase) => phase.type === 'C' && !!phase.teams?.length
</script>

<template>
  <div v-if="view === 'horizontal'" class="space-y-4">
    <p class="flex flex-wrap items-baseline justify-between gap-x-5 gap-y-2 text-sm text-muted">
      <span>{{ $t('results.follow.hint') }}</span>
      <button v-if="followed !== null" type="button" class="text-kpi-blue-700 underline" data-testid="unfollow" @click="followed = null">
        {{ $t('results.follow.stop', { team: followedLabel }) }}
      </button>
    </p>
    <div class="overflow-x-auto pb-1.5 max-[600px]:overflow-visible">
      <div
        class="grid grid-cols-[repeat(var(--rounds),minmax(14.375rem,1fr))] max-[600px]:grid-cols-1 max-[600px]:gap-y-6.5 min-[601px]:min-w-[calc(var(--rounds)*14.375rem)]"
        :class="{ '[&_[data-game]:not([data-followed])]:opacity-40 [&_tbody_tr:not([data-followed])]:opacity-40': followed !== null }"
        :style="{ '--rounds': rounds.length }"
        :data-following="followed !== null ? '' : undefined"
        data-testid="progress-horizontal"
      >
        <section
          v-for="round in rounds"
          :key="round.round"
          class="flex min-w-0 flex-col justify-center border-l border-line px-4.5 first:border-l-0 first:pl-0 max-[600px]:justify-start max-[600px]:border-l-0 max-[600px]:px-0"
          :aria-labelledby="`round-${round.round}`"
        >
          <h2 :id="`round-${round.round}`" class="sr-only">{{ $t('results.round', { round: round.round }) }}</h2>
          <article v-for="phase in round.phases" :key="phase.d_id" class="mb-5.5 last:mb-0" data-testid="phase">
            <h3
              class="mb-1.5 flex items-center gap-2 font-sans text-sm font-bold"
              :class="{ 'text-medal-gold before:size-2.5 before:rounded-full before:bg-medal-gold': final(phase) }"
              :data-final="final(phase) ? '' : undefined"
            >
              {{ phase.libelle }}
            </h3>
            <ResultsPoolTable v-if="phase.type === 'C' && phase.teams?.length" :phase="phase" :competition="chart.code" :followed="followed" @follow="follow" />
            <div v-else class="divide-y divide-line">
              <ResultsDuel v-for="game in phase.games ?? []" :key="game.g_id" :game="game" :followed="followed" @follow="follow" />
            </div>
          </article>
        </section>
      </div>
    </div>
  </div>
  <div v-else class="space-y-10" data-testid="progress-vertical">
    <!-- CMP-26: a pool table of bounded width beside its games, laid out in bounded columns filling the row. -->
    <section v-for="phase in phases" :key="phase.d_id" class="space-y-3" data-testid="phase">
      <h3 class="text-2xl" :class="final(phase) ? 'text-medal-gold' : 'text-kpi-blue-700'">{{ phase.libelle }}</h3>
      <div class="grid items-start gap-x-10 gap-y-4" :class="{ 'lg:grid-cols-[minmax(0,26rem)_minmax(0,1fr)]': pool(phase) }">
        <div v-if="pool(phase)" data-testid="phase-standing">
          <ResultsPoolTable :phase="phase" :competition="chart.code" />
        </div>
        <div v-if="phase.games?.length" class="grid gap-x-8 sm:grid-cols-[repeat(auto-fill,minmax(15rem,1fr))]" data-testid="phase-games">
          <ResultsDuel v-for="game in phase.games" :key="game.g_id" :game="game" class="border-b border-line" />
        </div>
      </div>
    </section>
  </div>
</template>
