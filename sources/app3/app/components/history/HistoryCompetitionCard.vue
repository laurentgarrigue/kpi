<script setup lang="ts">
import { competitionPath } from '~/utils/competitions'
import { splitPodium, type HistoryCompetition, type PodiumEntry } from '~/utils/history'

// One finished final competition of a season: podium with medals, other ranked teams folded (HIS-03, HIS-04).
const props = defineProps<{ competition: HistoryCompetition, season: string }>()
const localePath = useLocalePath()
const parts = computed(() => splitPodium(props.competition.podium))
const mark = (entry: PodiumEntry) => (entry.medal ? { kind: 'medal' as const, medal: entry.medal } : null)
</script>

<template>
  <article class="rounded border border-line p-4" :data-competition="competition.code">
    <h3 class="text-2xl">
      <NuxtLink :to="localePath(competitionPath(season, competition.code, 'ranking'))" class="text-kpi-blue-600 hover:underline" data-testid="history-ranking-link">
        {{ competition.display_title }}
      </NuxtLink>
    </h3>
    <p v-if="competition.soustitre2" class="text-sm text-ink/70">{{ competition.soustitre2 }}</p>
    <ol class="mt-3 space-y-1" data-testid="podium">
      <li v-for="entry in parts.top" :key="`${entry.rank}-${entry.team.label}`" class="flex items-center gap-2">
        <ResultsRankingMark :mark="mark(entry)" />
        <span class="w-6 text-right tabular-nums">{{ entry.rank }}</span>
        <ResultsTeamName :label="entry.team.label" :number="entry.team.number" :competition="competition.code" :season="season" strong />
      </li>
    </ol>
    <details v-if="parts.others.length" class="mt-2 text-sm">
      <summary class="cursor-pointer underline">{{ $t('history.fullRanking') }}</summary>
      <ol class="mt-2 space-y-1" data-testid="other-ranks">
        <li v-for="entry in parts.others" :key="`${entry.rank}-${entry.team.label}`" class="flex gap-2">
          <span class="w-14 text-right tabular-nums">{{ entry.rank }}</span>
          <ResultsTeamName :label="entry.team.label" :number="entry.team.number" :competition="competition.code" :season="season" />
        </li>
      </ol>
    </details>
  </article>
</template>
