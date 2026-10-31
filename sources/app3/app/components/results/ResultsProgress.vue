<script setup lang="ts">
import { chartRounds, phasesByLevel } from '#kpi-layer/utils/results/charts'
import { isFinal } from '#kpi-layer/utils/results/games'
import type { ChartPhase, CompetitionChart } from '#kpi-layer/utils/results/types'
import { FOLLOWING_CLASSES } from '~/composables/useFollowedTeam'
import type { ProgressView } from '~/utils/competitions'

/**
 * Progress of a competition (CMP-09, CMP-24, CMP-26): one column per round, titled for screen readers only and
 * centred vertically (kpchart.php), or phases by level (kpphases.php). Touching a team highlights its whole path.
 */
const props = defineProps<{ chart: CompetitionChart, view: ProgressView }>()
const rounds = computed(() => chartRounds(props.chart))
const phases = computed(() => phasesByLevel(props.chart))

const { followed, follow, unfollow, label: followedLabel } = useFollowedTeam(() => props.chart)
const final = (phase: ChartPhase) => isFinal({ d_phase: phase.libelle })
</script>

<template>
  <div v-if="view === 'horizontal'" class="space-y-4">
    <ResultsFollowBar :following="followed !== null" :team="followedLabel" @stop="unfollow" />
    <div class="overflow-x-auto pb-1.5 max-[600px]:overflow-visible">
      <div
        class="grid grid-cols-[repeat(var(--rounds),minmax(14.375rem,1fr))] max-[600px]:grid-cols-1 max-[600px]:gap-y-6.5 min-[601px]:min-w-[calc(var(--rounds)*14.375rem)]"
        :class="{ [FOLLOWING_CLASSES]: followed !== null }"
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
  <div v-else class="space-y-4">
    <ResultsFollowBar :following="followed !== null" :team="followedLabel" @stop="unfollow" />
    <div
      class="space-y-8"
      :class="{ [FOLLOWING_CLASSES]: followed !== null }"
      :data-following="followed !== null ? '' : undefined"
      data-testid="progress-vertical"
    >
      <section v-for="phase in phases" :key="phase.d_id" class="space-y-3" data-testid="phase">
        <h3 class="text-2xl" :class="final(phase) ? 'text-medal-gold' : 'text-kpi-blue-700'">{{ phase.libelle }}</h3>
        <!-- CMP-26: the standing above its games, its columns spread over the width. -->
        <ResultsPoolTable v-if="phase.type === 'C'" :phase="phase" :competition="chart.code" :followed="followed" spread @follow="follow" />
        <div class="grid gap-x-6 sm:grid-cols-2">
          <ResultsDuel v-for="game in phase.games ?? []" :key="game.g_id" :game="game" :followed="followed" class="border-b border-line" @follow="follow" />
        </div>
      </section>
    </div>
  </div>
</template>
