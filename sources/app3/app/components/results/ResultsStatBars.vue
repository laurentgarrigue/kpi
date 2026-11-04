<script setup lang="ts">
import type { Stat } from '#kpi-layer/utils/results/types'

type StatRow = Stat['rows'][number]
interface StatTeam { label: string, number: number | null, logo?: string | null }

/**
 * A players statistic with one figure (top scorers, CMP-22): the leader put forward, one bar per player on a common
 * scale (the maximum of the list), a filter by team as links (`team=`, no JavaScript).
 */
const props = defineProps<{ stat: Stat, competition: string }>()
const route = useRoute()

const figure = computed(() => props.stat.columns[0]!.key)
const value = (row: StatRow) => Number(row[figure.value] ?? 0)
const teamOf = (row: StatRow) => row.team as StatTeam | undefined
const name = (row: StatRow) => [row.last_name, row.first_name].filter(Boolean).join(' ')
const maximum = computed(() => Math.max(1, ...props.stat.rows.map(value)))

const teams = computed(() => [...new Map(props.stat.rows
  .map(teamOf)
  .filter((team): team is StatTeam => team?.number !== null && team?.number !== undefined)
  .map(team => [team.number, team])).values()])
const selected = computed(() => {
  const number = Number(route.query.team)
  return teams.value.some(team => team.number === number) ? number : null
})
const rows = computed(() => props.stat.rows.filter(row => selected.value === null || teamOf(row)?.number === selected.value))
const leader = computed(() => (selected.value === null ? props.stat.rows[0] ?? null : null))
const filterLink = (team: number | null) => ({ path: route.path, query: { ...route.query, team: team ?? undefined } })
</script>

<template>
  <div class="grid gap-10 lg:grid-cols-[minmax(0,1fr)_17.5rem]">
    <div>
      <div v-if="leader" class="mb-5.5 grid grid-cols-[auto_1fr] items-center gap-x-4.5 gap-y-1 rounded-lg bg-navy px-5 py-4.5 text-white" data-testid="lead-scorer">
        <span class="row-span-3 text-center font-display text-[5.5rem] leading-[0.85] text-kpi-gold-300 tabular-nums">
          {{ value(leader) }}
          <small class="mt-1 block font-sans text-[0.8125rem] font-semibold text-white/85">{{ $t('results.stats.goalsShort', value(leader)) }}</small>
        </span>
        <b class="font-display text-[2.125rem] font-normal leading-none">{{ name(leader) }}</b>
        <span class="text-white/85">
          {{ teamOf(leader)?.label }}<template v-if="leader.number !== null && leader.number !== undefined">, {{ $t('results.stats.playerNumber', { number: leader.number }) }}</template>
        </span>
      </div>
      <ol>
        <li
          v-for="(row, index) in rows"
          :key="index"
          class="grid grid-cols-[1.875rem_minmax(0,1fr)_4.375rem_1.5rem] items-center gap-2.5 border-t border-line py-1.5 sm:grid-cols-[2.125rem_minmax(0,1fr)_minmax(5.625rem,38%)_1.75rem]"
          data-testid="stat-row"
        >
          <span class="font-display text-xl leading-none text-muted" data-testid="stat-rank">{{ row.rank }}</span>
          <span class="min-w-0">
            <b class="font-semibold">{{ name(row) }}</b>
            <small class="block truncate text-[0.8125rem] text-muted">
              <ResultsTeamName v-if="teamOf(row)" :label="teamOf(row)!.label" :number="teamOf(row)!.number" :competition="competition" />
              <template v-if="row.number !== null && row.number !== undefined">, {{ $t('results.stats.playerNumber', { number: row.number }) }}</template>
            </small>
          </span>
          <span class="h-2.5 overflow-hidden rounded-full bg-wash" aria-hidden="true">
            <i class="block h-full rounded-full bg-kpi-blue-500" :style="{ width: `${(value(row) / maximum) * 100}%` }" data-testid="stat-bar" />
          </span>
          <span class="text-right font-bold tabular-nums">
            {{ value(row) }}<span class="sr-only"> {{ $t(`stats.${stat.kind}.${figure}`) }}</span>
          </span>
        </li>
      </ol>
    </div>
    <nav v-if="teams.length > 1" :aria-labelledby="`${stat.kind}-teams`" class="-order-1 lg:order-none" data-testid="stat-teams">
      <h3 :id="`${stat.kind}-teams`" class="mb-2 font-sans text-[0.9375rem] font-bold">{{ $t('results.stats.filterByTeam') }}</h3>
      <ul class="flex flex-wrap gap-2">
        <li v-for="team in [null, ...teams]" :key="team?.number ?? 'all'">
          <NuxtLink
            :to="filterLink(team?.number ?? null)"
            :aria-current="(team?.number ?? null) === selected ? 'true' : undefined"
            class="inline-block rounded-full border px-3 py-1 text-sm"
            :class="(team?.number ?? null) === selected ? 'border-navy bg-navy text-white' : 'border-line hover:bg-wash'"
          >
            {{ team ? team.label : $t('results.stats.allTeams') }}
          </NuxtLink>
        </li>
      </ul>
    </nav>
  </div>
</template>
