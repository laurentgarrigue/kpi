<script setup lang="ts">
import { rankingMark } from '#kpi-layer/utils/results/ranking'
import type { CompetitionType, RankingRow } from '#kpi-layer/utils/results/types'

// Ranking table, compact (competitions list, CPL-05) or complete by type (CMP-10). Marks are computed on the
// whole ranking; `from` / `to` only select the lines shown (« see all » of the compact list).
const props = defineProps<{
  rows: RankingRow[]
  type: CompetitionType
  competition: string
  qualified: number
  eliminated: number
  compact?: boolean
  caption: string
  from?: number
  to?: number
}>()

const shown = computed(() => props.rows
  .map((row, index) => ({ row, mark: rankingMark(row, index, props.rows.length, props.qualified, props.eliminated) }))
  .slice(props.from ?? 0, props.to))

const detailed = computed(() => !props.compact && props.type !== 'MULTI')
const DETAIL_COLUMNS = ['won', 'drawn', 'lost', 'forfeits', 'goals_for', 'goals_against'] as const
</script>

<template>
  <table class="w-full text-sm">
    <caption class="sr-only">{{ caption }}</caption>
    <thead class="text-xs uppercase text-ink/70">
      <tr>
        <th scope="col" class="w-8 px-1 py-1"><span class="sr-only">{{ $t('results.col.mark') }}</span></th>
        <th scope="col" class="px-1 py-1 text-left">{{ $t('results.col.rank') }}</th>
        <th scope="col" class="px-1 py-1 text-left">{{ $t('results.col.team') }}</th>
        <th scope="col" class="px-1 py-1 text-right">{{ $t('results.col.points') }}</th>
        <th scope="col" class="px-1 py-1 text-right">{{ $t('results.col.played') }}</th>
        <template v-if="detailed">
          <th v-for="column in DETAIL_COLUMNS" :key="column" scope="col" class="hidden px-1 py-1 text-right sm:table-cell">{{ $t(`results.col.${column}`) }}</th>
          <th scope="col" class="px-1 py-1 text-right">{{ $t('results.col.goal_diff') }}</th>
        </template>
      </tr>
    </thead>
    <tbody>
      <tr v-for="{ row, mark } in shown" :key="row.team.id" class="border-t border-line" data-testid="ranking-row">
        <td class="px-1 py-1 text-center"><ResultsRankingMark :mark="mark" /></td>
        <td class="px-1 py-1 tabular-nums">{{ row.rank }}</td>
        <td class="px-1 py-1"><ResultsTeamName :label="row.team.label" :number="row.team.number" :competition="competition" /></td>
        <td class="px-1 py-1 text-right font-semibold tabular-nums">{{ row.points }}</td>
        <td class="px-1 py-1 text-right tabular-nums">{{ row.played }}</td>
        <template v-if="detailed">
          <td v-for="column in DETAIL_COLUMNS" :key="column" class="hidden px-1 py-1 text-right tabular-nums sm:table-cell">{{ row[column] }}</td>
          <td class="px-1 py-1 text-right tabular-nums">{{ row.goal_diff }}</td>
        </template>
      </tr>
    </tbody>
  </table>
</template>
