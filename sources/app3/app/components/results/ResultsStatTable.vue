<script setup lang="ts">
import type { Stat } from '#kpi-layer/utils/results/types'

// Generic statistic table driven by api2 `columns` (CMP-11): only the titles are specific to each statistic.
const props = defineProps<{ stat: Stat, competition: string }>()

function player(row: Stat['rows'][number]): string {
  const name = [row.last_name, row.first_name].filter(Boolean).join(' ')
  return row.number === null || row.number === undefined ? name : `${name} #${row.number}`
}
const team = (row: Stat['rows'][number]) => row.team as { label: string, number: number | null } | undefined
const hasPlayers = computed(() => props.stat.rows.some(row => 'last_name' in row))
</script>

<template>
  <table class="w-full text-sm" data-testid="stat-table">
    <caption class="sr-only">{{ $t(`stats.${stat.kind}.title`) }}</caption>
    <thead class="text-xs uppercase text-ink/70">
      <tr>
        <th scope="col" class="px-1 py-1 text-left">{{ $t('results.col.rank') }}</th>
        <th v-if="hasPlayers" scope="col" class="px-1 py-1 text-left">{{ $t('results.col.player') }}</th>
        <th scope="col" class="px-1 py-1 text-left">{{ $t('results.col.team') }}</th>
        <th v-for="column in stat.columns" :key="column.key" scope="col" class="px-1 py-1 text-right">{{ $t(`stats.${stat.kind}.${column.key}`) }}</th>
      </tr>
    </thead>
    <tbody>
      <tr v-for="(row, index) in stat.rows" :key="index" class="border-t border-line" data-testid="stat-row">
        <td class="px-1 py-1 tabular-nums">{{ row.rank }}</td>
        <td v-if="hasPlayers" class="px-1 py-1">{{ player(row) }}</td>
        <td class="px-1 py-1">
          <ResultsTeamName v-if="team(row)" :label="team(row)!.label" :number="team(row)!.number" :competition="competition" />
        </td>
        <td v-for="column in stat.columns" :key="column.key" class="px-1 py-1 text-right font-semibold tabular-nums">{{ row[column.key] }}</td>
      </tr>
    </tbody>
  </table>
</template>
