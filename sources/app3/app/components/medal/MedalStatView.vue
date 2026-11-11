<script setup lang="ts">
import type { Stat } from '#kpi-layer/utils/results/types'
import { medalExplanation, medalLabel, type MedalItem } from '~/utils/medals'
import { personName } from '~/utils/people'

/**
 * A timeline statistic of the « Stats » tab (FEATURE_MATCH_MEDALS.md § 3.4, § 9.2, MED-45): its pictogram, name and
 * explanation, the games it is computed on, then a display per kind (maquette `stats-medailles`). A kind published
 * later without a display of its own falls back to a generic table of its columns.
 */
interface Team { id: number, number: number | null, label: string }
interface Person { first_name: string | null, last_name: string | null }
type Row = Record<string, unknown> & Partial<Person> & { team?: Team, referee?: Person, medals?: Record<string, number> }

const props = defineProps<{ stat: Stat, item: MedalItem | undefined, byCode: Map<string, MedalItem>, competition: string }>()
const { locale } = useI18n()
const localePath = useLocalePath()
const rows = computed(() => props.stat.rows as Row[])
const summary = computed(() => (props.stat.summary ?? {}) as Record<string, number | null | string[]>)
const teamCodes = computed(() => ((summary.value.codes as string[] | undefined) ?? []).filter(code => props.byCode.has(code)))
const name = (person: Partial<Person> | undefined) => personName([person?.last_name, person?.first_name].filter(Boolean).join(' ')) ?? '—'
const medalsOf = (row: Row) => Object.entries(row.medals ?? {}).filter(([code]) => props.byCode.has(code))
const percent = (value: unknown) => (value === null || value === undefined ? '—' : `${value} %`)
</script>

<template>
  <section class="space-y-4" :data-kind="stat.kind" data-testid="medal-stat">
    <header class="grid grid-cols-[auto_minmax(0,1fr)] items-center gap-x-3.5 gap-y-1">
      <MedalShield v-if="item" :item="item" :size="56" class="row-span-2" />
      <h2 class="text-3xl leading-none text-kpi-blue-700">{{ item ? medalLabel(item, locale) : $t(`stats.${stat.kind}.title`) }}</h2>
      <p v-if="item" class="text-sm text-muted">{{ medalExplanation(item, locale) }}</p>
    </header>
    <p class="text-sm text-muted" data-testid="medal-stat-basis">
      {{ stat.needs_reliable_timeline
        ? $t('medals.basisTimeline', { reliable: stat.reliable_games_count, games: stat.games_count })
        : $t('medals.basisGames', { games: stat.games_count }) }}
      <NuxtLink :to="localePath('/medals')" class="underline">{{ $t('medals.howLink') }}</NuxtLink>
    </p>

    <p v-if="rows.length === 0" data-testid="no-stats">{{ $t('results.noStats') }}</p>

    <!-- Team medals: one column per pictogram (named in its header for screen readers) -->
    <div v-else-if="stat.kind === 'medals_teams'" class="overflow-x-auto lg:overflow-visible">
      <table class="w-full text-sm tabular-nums" data-testid="medal-table">
        <thead>
          <tr class="text-xs text-muted">
            <th scope="col" class="px-1 py-1 text-left">{{ $t('results.col.team') }}</th>
            <th v-for="code in teamCodes" :key="code" scope="col" class="px-1 py-1">
              <MedalShield :item="byCode.get(code)!" :size="36" :title="medalLabel(byCode.get(code)!, locale)" />
              <span class="sr-only">{{ medalLabel(byCode.get(code)!, locale) }}</span>
            </th>
            <th scope="col" class="px-1 py-1">{{ $t('medals.col.total') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.team!.id" class="border-t border-line" data-testid="medal-row">
            <td class="px-1 py-2"><ResultsTeamName :label="row.team!.label" :number="row.team!.number" :competition="competition" /></td>
            <td v-for="code in teamCodes" :key="code" class="px-1 py-2 text-center" :class="{ 'text-line': !row.medals?.[code] }">{{ row.medals?.[code] ?? '·' }}</td>
            <td class="px-1 py-2 text-center font-bold">{{ row.total }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Player and referee medals: one line per person, the medals as pills -->
    <ul v-else-if="stat.kind === 'medals_players' || stat.kind === 'medals_referees'" class="divide-y divide-line" data-testid="medal-people">
      <li v-for="(row, index) in rows" :key="index" class="flex flex-wrap items-center justify-between gap-2 py-2.5" data-testid="medal-row">
        <span>
          <b class="font-semibold">{{ name(stat.kind === 'medals_players' ? row : row.referee) }}</b>
          <small class="block text-muted">
            <template v-if="stat.kind === 'medals_players'">{{ row.team?.label }}<template v-if="row.number !== null">, {{ $t('results.stats.playerNumber', { number: row.number }) }}</template></template>
            <template v-else>{{ $t('medals.gamesOfficiated', { n: row.games }, Number(row.games)) }}</template>
          </small>
        </span>
        <span class="flex flex-wrap gap-1.5">
          <MedalPill v-for="[code, count] in medalsOf(row)" :key="code" :item="byCode.get(code)!" :count="count" />
        </span>
      </li>
    </ul>

    <!-- First goal wins: the figure of the perimeter, then by team -->
    <template v-else-if="stat.kind === 'opener_wins'">
      <div class="grid grid-cols-[auto_1fr] items-center gap-x-5 rounded-lg bg-navy px-5 py-4 text-white" data-testid="medal-lead">
        <span class="row-span-2 font-display text-7xl leading-none tabular-nums">{{ percent(summary.percent) }}</span>
        <b class="font-display text-2xl leading-tight">{{ $t('medals.openerLead', { won: summary.won, decided: summary.decided }) }}</b>
        <span class="text-sm text-white/80">{{ $t('medals.openerNote') }}</span>
      </div>
      <table class="w-full text-sm tabular-nums" data-testid="medal-table">
        <thead>
          <tr class="text-xs text-muted">
            <th scope="col" class="px-1 py-1 text-left">{{ $t('results.col.team') }}</th>
            <th scope="col" class="px-1 py-1 text-right">{{ $t('medals.col.opened') }}</th>
            <th scope="col" class="px-1 py-1 text-right">{{ $t('medals.col.won') }}</th>
            <th scope="col" class="px-1 py-1 text-right">%</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.team!.id" class="border-t border-line" data-testid="medal-row">
            <td class="px-1 py-2"><ResultsTeamName :label="row.team!.label" :number="row.team!.number" :competition="competition" /></td>
            <td class="px-1 py-2 text-right">{{ row.opened }}</td>
            <td class="px-1 py-2 text-right">{{ row.won }}</td>
            <td class="px-1 py-2 text-right font-bold">{{ percent(row.percent) }}</td>
          </tr>
        </tbody>
      </table>
    </template>

    <!-- Powerplay %: one bar per team -->
    <ol v-else-if="stat.kind === 'powerplay_rate'" class="divide-y divide-line" data-testid="medal-bars">
      <li v-for="row in rows" :key="row.team!.id" class="grid grid-cols-[minmax(0,1fr)_7.5rem] items-center gap-3 py-2.5 md:grid-cols-[minmax(0,1.1fr)_minmax(6rem,1fr)_8rem]" data-testid="medal-row">
        <ResultsTeamName :label="row.team!.label" :number="row.team!.number" :competition="competition" />
        <span class="hidden h-2.5 overflow-hidden rounded-full bg-wash md:block" aria-hidden="true">
          <span class="block h-full rounded-full bg-fam-superiorite" :style="{ width: `${Number(row.percent ?? 0)}%` }" />
        </span>
        <span class="text-right text-sm text-muted">
          <template v-if="row.percent === null">— <small>{{ $t('medals.noOpponentExclusion') }}</small></template>
          <template v-else>
            <b class="text-base text-ink">{{ percent(row.percent) }}</b><br>
            {{ $t('medals.powerplayDetail', { goals: row.powerplay_goals, exclusions: row.opponent_exclusions }) }}
          </template>
        </span>
      </li>
    </ol>

    <!-- Games officiated: sorted by games, never by cards (MED-55) -->
    <template v-else-if="stat.kind === 'referee_games'">
      <div class="overflow-x-auto lg:overflow-visible">
        <table class="w-full text-sm tabular-nums" data-testid="medal-table">
          <thead>
            <tr class="text-xs text-muted">
              <th scope="col" class="px-1 py-1 text-left">{{ $t('medals.col.referee') }}</th>
              <th v-for="key in ['games', 'principal', 'secondary', 'knockouts', 'overtimes']" :key="key" scope="col" class="px-1 py-1 text-right">{{ $t(`medals.col.${key}`) }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, index) in rows" :key="index" class="border-t border-line" data-testid="medal-row">
              <td class="px-1 py-2 font-semibold">{{ name(row.referee) }}</td>
              <td v-for="key in ['games', 'principal', 'secondary', 'knockouts', 'overtimes']" :key="key" class="px-1 py-2 text-right" :class="{ 'font-bold': key === 'games' }">{{ row[key] }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <p class="text-sm text-muted" data-testid="medal-referee-note">
        {{ $t('medals.refereeNote') }} {{ $t('medals.undesignated', { n: summary.undesignated_games ?? 0 }, Number(summary.undesignated_games ?? 0)) }}
      </p>
    </template>

    <!-- Any other published kind: a plain table of its columns -->
    <table v-else class="w-full text-sm tabular-nums" data-testid="medal-table">
      <tbody>
        <tr v-for="(row, index) in rows" :key="index" class="border-t border-line" data-testid="medal-row">
          <td class="px-1 py-2">{{ row.team?.label ?? name(row.referee ?? row) }}</td>
          <td v-for="column in stat.columns" :key="column.key" class="px-1 py-2 text-right">
            {{ column.type === 'percent' ? percent(row[column.key]) : row[column.key] ?? '—' }}
          </td>
        </tr>
      </tbody>
    </table>
  </section>
</template>
