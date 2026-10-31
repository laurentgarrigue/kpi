<script setup lang="ts">
import type { GroupCompetition } from '#kpi-layer/utils/results/types'
import { competitionPath } from '~/utils/competitions'

// One competition of the list (CPL-04 to CPL-07): navy rule, title, written status (red while on), the five first
// teams of its ranking, then a link to the full ranking.
const SUMMARY_SIZE = 5
const props = defineProps<{ competition: GroupCompetition }>()
const localePath = useLocalePath()
const rankingPath = computed(() => localePath(competitionPath(props.competition.season, props.competition.code, 'ranking')))
</script>

<template>
  <article class="border-t-4 border-navy pt-3 dark:border-kpi-blue-500" :data-competition="competition.code">
    <h2 class="flex items-baseline justify-between gap-2.5 text-[1.75rem] leading-[1.05]">
      <NuxtLink :to="localePath(competitionPath(competition.season, competition.code))" class="text-navy hover:underline hover:decoration-1 dark:text-ink">
        {{ competition.display_title }}
      </NuxtLink>
      <small class="shrink-0 font-sans text-[0.8125rem] font-semibold" :class="competition.status === 'ON' ? 'text-live' : 'text-muted'">
        {{ $t(`results.competitionStatus.${competition.status}`) }}
      </small>
    </h2>
    <p v-if="competition.soustitre2 && competition.soustitre2 !== competition.display_title" class="text-sm text-muted">{{ competition.soustitre2 }}</p>
    <p v-if="competition.ranking.length === 0" class="mt-2 text-sm italic text-muted" data-testid="no-ranking">{{ $t('results.noRanking') }}</p>
    <template v-else>
      <div class="mt-2">
        <ResultsRankingTable
          :rows="competition.ranking"
          :type="competition.type"
          :competition="competition.code"
          :qualified="competition.qualified"
          :eliminated="competition.eliminated"
          :caption="competition.display_title"
          :to="SUMMARY_SIZE"
          compact
        />
      </div>
      <NuxtLink :to="rankingPath" class="mt-2 inline-block text-sm underline" data-testid="full-ranking">
        {{ $t('results.fullRankingCount', { n: competition.ranking.length }) }}
      </NuxtLink>
    </template>
  </article>
</template>
