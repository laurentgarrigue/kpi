<script setup lang="ts">
import { poolStandings } from '#kpi-layer/utils/results/charts'
import type { ChartPhase } from '#kpi-layer/utils/results/types'

// Standings of a pool phase (CMP-09): rank, team, points, played, goal difference.
const props = defineProps<{ phase: ChartPhase, competition: string, detailed?: boolean }>()
const rows = computed(() => poolStandings(props.phase))
const played = computed(() => (props.phase.teams ?? []).some(team => (team.t_pld ?? 0) > 0))
</script>

<template>
  <table class="w-full text-sm" data-testid="pool-table">
    <caption class="sr-only">{{ phase.libelle }}</caption>
    <thead class="text-xs uppercase text-ink/70">
      <tr>
        <th scope="col" class="px-1 text-left">{{ $t('results.col.rank') }}</th>
        <th scope="col" class="px-1 text-left">{{ $t('results.col.team') }}</th>
        <th scope="col" class="px-1 text-right">{{ $t('results.col.points') }}</th>
        <th scope="col" class="px-1 text-right">{{ $t('results.col.played') }}</th>
        <th scope="col" class="px-1 text-right">{{ $t('results.col.goal_diff') }}</th>
      </tr>
    </thead>
    <tbody>
      <tr v-for="(row, index) in rows" :key="index" class="border-t border-line">
        <template v-if="'empty' in row">
          <td class="px-1 text-ink/70">{{ index + 1 }}</td>
          <td class="px-1 italic text-ink/70" colspan="4">—</td>
        </template>
        <template v-else>
          <td class="px-1 tabular-nums">{{ played ? (row.t_clt || index + 1) : index + 1 }}</td>
          <td class="px-1"><ResultsTeamName :label="row.t_label" :number="row.placeholder ? null : row.t_number" :competition="competition" /></td>
          <td class="px-1 text-right font-semibold tabular-nums">{{ played ? (row.t_pts ?? 0) / 100 : '' }}</td>
          <td class="px-1 text-right tabular-nums">{{ played ? (row.t_pld ?? 0) : '' }}</td>
          <td class="px-1 text-right tabular-nums">{{ played ? (row.t_diff ?? 0) : '' }}</td>
        </template>
      </tr>
    </tbody>
  </table>
</template>
