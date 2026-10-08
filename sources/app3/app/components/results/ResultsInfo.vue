<script setup lang="ts">
import { joinURL } from 'ufo'
import type { CompetitionHeader, CompetitionInfo } from '#kpi-layer/utils/results/types'
import { formatEventDates } from '~/utils/events'

// « Info » tab: gamedays with place and officials, teams by pool, schema (CMP-08).
const props = defineProps<{ info: CompetitionInfo, competition: CompetitionHeader }>()
const { legacyBaseUrl } = useRuntimeConfig().public
const { locale, locales } = useI18n()
const language = computed(() => locales.value.find(item => item.code === locale.value)?.language ?? locale.value)
const schema = computed(() => (props.info.schema ? joinURL(legacyBaseUrl, 'img', props.info.schema) : null))
const OFFICIALS = ['rc', 'r1', 'delegate', 'chief_referee'] as const
</script>

<template>
  <div class="space-y-10">
    <section aria-labelledby="gamedays-title">
      <h2 id="gamedays-title" class="mb-3 text-3xl text-kpi-blue-600">{{ $t('results.info.gamedays') }}</h2>
      <p v-if="info.gamedays.length === 0">{{ $t('results.info.noGamedays') }}</p>
      <ul class="grid gap-4 md:grid-cols-2">
        <li v-for="gameday in info.gamedays" :key="gameday.id" class="rounded border border-line p-4" data-testid="gameday">
          <h3 class="text-xl">{{ gameday.phase || gameday.name }}</h3>
          <p class="text-sm">
            {{ formatEventDates(gameday.start, gameday.end, language) }}
            <template v-if="gameday.place"> · {{ gameday.place }}<template v-if="gameday.department"> ({{ gameday.department }})</template></template>
          </p>
          <dl class="mt-2 grid grid-cols-[auto_1fr] gap-x-3 text-sm">
            <template v-if="gameday.organizer">
              <dt class="text-ink/70">{{ $t('results.info.organizer') }}</dt><dd>{{ gameday.organizer }}</dd>
            </template>
            <template v-for="official in OFFICIALS" :key="official">
              <template v-if="gameday.officials[official]">
                <dt class="text-ink/70">{{ $t(`results.info.${official}`) }}</dt><dd>{{ gameday.officials[official] }}</dd>
              </template>
            </template>
          </dl>
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
