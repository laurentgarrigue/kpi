<script setup lang="ts">
import { joinURL } from 'ufo'
import { groupGamedays } from '#kpi-layer/utils/results/gamedays'
import type { CompetitionHeader, CompetitionInfo } from '#kpi-layer/utils/results/types'
import { formatEventDates } from '~/utils/events'
import { competitionIcsUrl, gamedayIcsUrl, webcalUrl } from '~/utils/page-links'

// « Info » tab: gamedays with place and officials, teams by pool, schema (CMP-08); calendar subscription and
// .ics files, served by api2 (CAL-06).
const props = defineProps<{ info: CompetitionInfo, competition: CompetitionHeader }>()
const { legacyBaseUrl, api2BaseUrl } = useRuntimeConfig().public
const language = useLanguageTag()
const schema = computed(() => (props.info.schema ? joinURL(legacyBaseUrl, 'img', props.info.schema) : null))
const OFFICIALS = ['rc', 'r1', 'delegate', 'chief_referee'] as const
// Gamedays sharing every parameter (the phases of a cup) are one card, shown once (CMP-08).
const groups = computed(() => groupGamedays(props.info.gamedays))
const ics = computed(() => competitionIcsUrl(api2BaseUrl, props.competition.season, props.competition.code))
</script>

<template>
  <div class="space-y-10">
    <section aria-labelledby="gamedays-title">
      <h2 id="gamedays-title" class="mb-3 text-3xl text-kpi-blue-600">{{ $t('results.info.gamedays') }}</h2>
      <div v-if="info.gamedays.length" class="mb-4 flex flex-wrap items-center gap-3" data-testid="calendar-subscription">
        <a :href="webcalUrl(ics)" class="inline-flex items-center gap-1 rounded bg-kpi-blue-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-kpi-blue-700" data-testid="ics-subscribe">
          <UIcon name="i-heroicons-calendar-days" class="size-5" aria-hidden="true" />{{ $t('calendar.subscribe') }}
        </a>
        <a :href="ics" class="text-sm underline" data-testid="ics-download">{{ $t('calendar.download') }}</a>
        <p class="w-full text-xs text-ink/70">{{ $t('calendar.icsHelp') }}</p>
      </div>
      <p v-if="info.gamedays.length === 0">{{ $t('results.info.noGamedays') }}</p>
      <ul class="grid gap-4" :class="groups.length > 1 ? 'md:grid-cols-2' : ''">
        <li v-for="group in groups" :key="group.gamedays[0]!.id" class="rounded border border-line p-4" data-testid="gameday">
          <!-- A title only tells the groups apart: a single group needs none. -->
          <h3 v-if="groups.length > 1" class="text-xl">{{ group.phases.join(', ') }}</h3>
          <p class="text-sm">
            {{ formatEventDates(group.gamedays[0]!.start, group.gamedays[0]!.end, language) }}
            <template v-if="group.gamedays[0]!.place"> · {{ group.gamedays[0]!.place }}<template v-if="group.gamedays[0]!.department"> ({{ group.gamedays[0]!.department }})</template></template>
          </p>
          <dl class="mt-2 grid grid-cols-[auto_1fr] gap-x-3 text-sm">
            <template v-if="group.gamedays[0]!.organizer">
              <dt class="text-ink/70">{{ $t('results.info.organizer') }}</dt><dd>{{ group.gamedays[0]!.organizer }}</dd>
            </template>
            <template v-for="official in OFFICIALS" :key="official">
              <template v-if="group.gamedays[0]!.officials[official]">
                <dt class="text-ink/70">{{ $t(`results.info.${official}`) }}</dt><dd>{{ group.gamedays[0]!.officials[official] }}</dd>
              </template>
            </template>
          </dl>
          <!-- One file per gameday; the phases of a cup are covered by the competition subscription above. -->
          <a v-if="group.gamedays.length === 1" :href="gamedayIcsUrl(api2BaseUrl, group.gamedays[0]!.id)" class="mt-2 inline-block text-sm underline" data-testid="gameday-ics">{{ $t('calendar.addGameday') }}</a>
        </li>
      </ul>
    </section>

    <section v-if="info.teams_by_pool.length" aria-labelledby="teams-title" data-testid="teams-by-pool">
      <h2 id="teams-title" class="mb-3 text-3xl text-kpi-blue-600">{{ $t('results.info.teams') }}</h2>
      <div class="grid gap-6 md:grid-cols-3">
        <section v-for="pool in info.teams_by_pool" :key="pool.pool">
          <h3 v-if="pool.pool" class="mb-1 text-xl">{{ $t('results.info.pool', { pool: pool.pool }) }}</h3>
          <ul class="space-y-1">
            <li v-for="team in pool.teams" :key="team.id">
              <ResultsTeamName :label="team.label" :number="team.number" :competition="competition.code" />
            </li>
          </ul>
        </section>
      </div>
    </section>

    <section v-if="schema" aria-labelledby="schema-title">
      <h2 id="schema-title" class="mb-3 text-3xl text-kpi-blue-600">{{ $t('results.info.schema') }}</h2>
      <img :src="schema" :alt="$t('results.info.schemaAlt', { competition: competition.display_title })" loading="lazy" class="max-w-full" data-testid="schema">
    </section>
  </div>
</template>
