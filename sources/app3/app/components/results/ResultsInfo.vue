<script setup lang="ts">
import { joinURL } from 'ufo'
import type { RouteLocationRaw } from 'vue-router'
import { groupGamedays } from '#kpi-layer/utils/results/gamedays'
import type { CompetitionHeader, CompetitionInfo, Gameday, RankingRow } from '#kpi-layer/utils/results/types'
import { formatEventDates } from '~/utils/events'
import { competitionIcsUrl, gamedayIcsUrl } from '~/utils/page-links'

/**
 * « Info » tab (CMP-08, CMP-23): dates and place first when every gameday shares them, officials as a grid, teams by
 * pool with their logos, schema; aside, the podium of a finished competition and the calendar subscription (CAL-06).
 * `subscriptionUrl` replaces the competition calendar, e.g. by the event's one (EVT-10).
 */
const props = defineProps<{
  info: CompetitionInfo
  competition: CompetitionHeader
  subscriptionUrl?: string
  podium: RankingRow[]
  rankingHref: RouteLocationRaw
}>()
const { legacyBaseUrl, api2BaseUrl } = useRuntimeConfig().public
const language = useLanguageTag()
const schema = computed(() => (props.info.schema ? joinURL(legacyBaseUrl, 'img', props.info.schema) : null))
const OFFICIALS = ['rc', 'r1', 'delegate', 'chief_referee'] as const
// Gamedays sharing every parameter (the phases of a cup) are one group, shown once (CMP-08).
const groups = computed(() => groupGamedays(props.info.gamedays))
const single = computed(() => (groups.value.length === 1 ? groups.value[0]!.gamedays[0]! : null))
const ics = computed(() => props.subscriptionUrl ?? competitionIcsUrl(api2BaseUrl, props.competition.season, props.competition.code))
const teamCount = computed(() => props.info.teams_by_pool.reduce((count, pool) => count + pool.teams.length, 0))

const place = (gameday: Gameday) => [gameday.place, gameday.department ? `(${gameday.department})` : null].filter(Boolean).join(' ')
const officials = (gameday: Gameday) => OFFICIALS.filter(official => gameday.officials[official])
</script>

<template>
  <div class="grid gap-7 lg:grid-cols-[minmax(0,1fr)_21.25rem] lg:gap-10">
    <section v-if="single" :aria-label="$t('results.info.when')" class="lg:col-span-2" data-testid="info-when">
      <p class="font-display text-[2.375rem] leading-none text-navy md:text-[2.875rem] dark:text-ink">
        {{ formatEventDates(single.start, single.end, language) }}
      </p>
      <p class="mt-1.5 text-[1.0625rem]">
        {{ place(single) }}
        <span v-if="single.organizer" class="text-muted">{{ $t('results.info.organizedBy', { organizer: single.organizer }) }}</span>
      </p>
    </section>

    <div class="space-y-9">
      <p v-if="info.gamedays.length === 0">{{ $t('results.info.noGamedays') }}</p>
      <section v-if="single" aria-labelledby="officials-title" data-testid="gameday">
        <template v-if="officials(single).length">
          <h2 id="officials-title" class="mb-3 text-[1.75rem] leading-tight text-kpi-blue-700">{{ $t('results.info.officials') }}</h2>
          <dl class="grid grid-cols-[repeat(auto-fill,minmax(12.5rem,1fr))] gap-x-6 gap-y-3.5">
            <div v-for="official in officials(single)" :key="official" class="border-t-2 border-line pt-2" data-testid="official">
              <dt class="text-[0.8125rem] text-muted">{{ $t(`results.info.${official}`) }}</dt>
              <dd class="mt-0.5 font-semibold">{{ single.officials[official] }}</dd>
            </div>
          </dl>
        </template>
        <a v-if="groups[0]!.gamedays.length === 1" :href="gamedayIcsUrl(api2BaseUrl, single.id)" class="mt-3 inline-block text-sm underline" data-testid="gameday-ics">{{ $t('calendar.addGameday') }}</a>
      </section>
      <section v-else-if="groups.length" aria-labelledby="gamedays-title">
        <h2 id="gamedays-title" class="mb-3 text-[1.75rem] leading-tight text-kpi-blue-700">{{ $t('results.info.gamedays') }}</h2>
        <ul class="grid gap-4 md:grid-cols-2">
          <li v-for="group in groups" :key="group.gamedays[0]!.id" class="rounded-lg border border-line p-4" data-testid="gameday">
            <h3 class="font-sans text-base font-bold">{{ group.phases.join(', ') }}</h3>
            <p class="font-display text-2xl leading-tight text-navy dark:text-ink">{{ formatEventDates(group.gamedays[0]!.start, group.gamedays[0]!.end, language) }}</p>
            <p class="text-sm">
              {{ place(group.gamedays[0]!) }}
              <span v-if="group.gamedays[0]!.organizer" class="text-muted">{{ $t('results.info.organizedBy', { organizer: group.gamedays[0]!.organizer }) }}</span>
            </p>
            <dl class="mt-3 grid grid-cols-[repeat(auto-fill,minmax(10rem,1fr))] gap-x-4 gap-y-2.5">
              <div v-for="official in officials(group.gamedays[0]!)" :key="official" class="border-t-2 border-line pt-1.5" data-testid="official">
                <dt class="text-xs text-muted">{{ $t(`results.info.${official}`) }}</dt>
                <dd class="text-sm font-semibold">{{ group.gamedays[0]!.officials[official] }}</dd>
              </div>
            </dl>
            <!-- One file per gameday; the phases of a cup are covered by the competition subscription. -->
            <a v-if="group.gamedays.length === 1" :href="gamedayIcsUrl(api2BaseUrl, group.gamedays[0]!.id)" class="mt-2 inline-block text-sm underline" data-testid="gameday-ics">{{ $t('calendar.addGameday') }}</a>
          </li>
        </ul>
      </section>

      <section v-if="info.teams_by_pool.length" aria-labelledby="teams-title" data-testid="teams-by-pool">
        <div class="mb-3 flex flex-wrap items-baseline gap-x-3.5">
          <h2 id="teams-title" class="text-[1.75rem] leading-tight text-kpi-blue-700">{{ $t('results.info.teams') }}</h2>
          <span class="text-[0.8125rem] text-muted">
            {{ $t('results.info.teamsSummary', { teams: teamCount }, teamCount) }}<template v-if="info.teams_by_pool.length > 1">, {{ $t('results.info.poolsSummary', { pools: info.teams_by_pool.length }, info.teams_by_pool.length) }}</template>
          </span>
        </div>
        <div class="grid grid-cols-[repeat(auto-fill,minmax(11.875rem,1fr))] gap-x-6 gap-y-5">
          <section v-for="pool in info.teams_by_pool" :key="pool.pool">
            <h3 v-if="pool.pool" class="mb-1.5 font-sans text-sm font-bold">{{ $t('results.info.pool', { pool: pool.pool }) }}</h3>
            <ul>
              <li v-for="team in pool.teams" :key="team.id" class="flex items-center gap-2.5 border-t border-line py-1.5">
                <ResultsTeamLogo :path="team.logo" />
                <ResultsTeamName :label="team.label" :number="team.number" :competition="competition.code" />
              </li>
            </ul>
          </section>
        </div>
      </section>

      <section v-if="schema" aria-labelledby="schema-title">
        <h2 id="schema-title" class="mb-3 text-[1.75rem] leading-tight text-kpi-blue-700">{{ $t('results.info.schema') }}</h2>
        <img :src="schema" :alt="$t('results.info.schemaAlt', { competition: competition.display_title })" loading="lazy" class="kpi-logo max-w-full" data-testid="schema">
      </section>
    </div>

    <aside class="-order-1 grid content-start gap-5.5 md:grid-cols-[repeat(auto-fit,minmax(16.25rem,1fr))] lg:order-none lg:grid-cols-1">
      <section v-if="podium.length" class="rounded-lg bg-wash p-4.5" aria-labelledby="info-podium-title" data-testid="info-podium">
        <h2 id="info-podium-title" class="mb-3 text-[1.75rem] leading-tight text-kpi-blue-700">{{ $t('results.podium') }}</h2>
        <ol class="mb-2.5">
          <li v-for="row in podium" :key="row.team.id" class="flex items-center gap-2.5 py-1.5" :class="{ 'text-lg font-bold': row.medal === 1 }">
            <span class="w-4.5 font-display leading-none" :class="row.medal === 1 ? 'text-3xl text-medal-gold' : 'text-[1.375rem] text-muted'">{{ row.rank }}<span class="sr-only">, {{ $t(`results.medal.${row.medal}`) }} </span></span>
            <ResultsTeamLogo :path="row.team.logo" :size="row.medal === 1 ? 'lg' : 'md'" />
            <ResultsTeamName :label="row.team.label" :number="row.team.number" :competition="competition.code" />
          </li>
        </ol>
        <NuxtLink :to="rankingHref" class="text-sm underline" data-testid="info-ranking-link">{{ $t('results.fullRanking') }}</NuxtLink>
      </section>
      <CalendarSubscribe v-if="info.gamedays.length" :url="ics" />
    </aside>
  </div>
</template>
