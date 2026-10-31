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

// A cup only shows its final ranking: its points come from the phases, not from a league table.
const withPoints = computed(() => props.type !== 'CP')
const detailed = computed(() => withPoints.value && !props.compact && props.type !== 'MULTI')
// Secondary columns are hidden on phones (CMP-21).
const DETAIL_COLUMNS = [
  { key: 'won', secondary: false },
  { key: 'drawn', secondary: true },
  { key: 'lost', secondary: false },
  { key: 'forfeits', secondary: true },
  { key: 'goals_for', secondary: true },
  { key: 'goals_against', secondary: true },
] as const
const signed = (value: number | undefined) => (value && value > 0 ? `+${value}` : String(value ?? ''))
</script>

<template>
  <table class="w-full text-sm tabular-nums" :class="{ 'md:text-[0.9375rem]': !compact }">
    <caption class="sr-only">{{ caption }}</caption>
    <thead class="text-xs font-semibold text-muted">
      <tr>
        <th scope="col" class="w-8 px-1 pb-1.5"><span class="sr-only">{{ $t('results.col.mark') }}</span></th>
        <th scope="col" class="px-1 pb-1.5 text-left">{{ $t('results.col.rank') }}</th>
        <th scope="col" class="px-1 pb-1.5 text-left">{{ $t('results.col.team') }}</th>
        <template v-if="withPoints">
          <th scope="col" class="px-2 pb-1.5 text-right">{{ $t('results.col.points') }}</th>
          <th scope="col" class="px-2 pb-1.5 text-right">{{ $t('results.col.played') }}</th>
        </template>
        <template v-if="detailed">
          <th
            v-for="column in DETAIL_COLUMNS"
            :key="column.key"
            scope="col"
            class="px-2 pb-1.5 text-right"
            :class="{ 'hidden sm:table-cell': column.secondary }"
          >
            {{ $t(`results.col.${column.key}`) }}
          </th>
          <th scope="col" class="px-2 pb-1.5 text-right">{{ $t('results.col.goal_diff') }}</th>
        </template>
      </tr>
    </thead>
    <tbody>
      <tr v-for="{ row, mark } in shown" :key="row.team.id" class="border-t border-line" data-testid="ranking-row">
        <td class="px-1 text-center" :class="compact ? 'py-1' : 'py-2'"><ResultsRankingMark :mark="mark" /></td>
        <td class="px-1 font-display leading-none" :class="[compact ? 'text-lg' : 'text-[1.375rem]', row.medal ? 'text-navy dark:text-ink' : 'text-muted']">{{ row.rank }}</td>
        <td class="px-1" :class="compact ? 'py-1' : 'py-2'">
          <span class="flex min-w-0 items-center gap-2.5">
            <ResultsTeamLogo :path="row.team.logo" :size="compact ? 'sm' : 'md'" />
            <ResultsTeamName :label="row.team.label" :number="row.team.number" :competition="competition" />
          </span>
        </td>
        <template v-if="withPoints">
          <td class="px-2 text-right font-bold">{{ row.points }}</td>
          <td class="px-2 text-right">{{ row.played }}</td>
        </template>
        <template v-if="detailed">
          <td v-for="column in DETAIL_COLUMNS" :key="column.key" class="px-2 text-right" :class="{ 'hidden sm:table-cell': column.secondary }">{{ row[column.key] }}</td>
          <td class="px-2 text-right">{{ signed(row.goal_diff) }}</td>
        </template>
      </tr>
    </tbody>
  </table>
</template>
