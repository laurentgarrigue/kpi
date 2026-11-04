<script setup lang="ts">
import { medalist, teamKey, type SeasonFinal } from '~/utils/history'

// Champions of the last ten seasons, newest first (HIS-07); the followed team highlighted.
const LAST_SEASONS = 10
const props = defineProps<{ finals: SeasonFinal[], highlighted: string | null }>()
const champions = computed(() => props.finals.slice(0, LAST_SEASONS).map(final => ({ final, champion: medalist(final.competition, 1)! })))
</script>

<template>
  <ol
    :aria-label="$t('history.lastSeasons')"
    class="flex snap-x gap-2 overflow-x-auto pb-1 lg:grid lg:grid-cols-10 lg:overflow-visible"
    data-testid="champions-strip"
  >
    <li
      v-for="{ final, champion } in champions"
      :key="final.season"
      class="grid min-w-0 flex-[0_0_5.75rem] snap-start justify-items-center gap-1.5 rounded-lg border px-1 py-2.5 text-center"
      :class="highlighted === teamKey(champion.team) ? 'border-navy bg-sky/20' : 'border-line'"
      :data-highlighted="highlighted === teamKey(champion.team) ? '' : undefined"
    >
      <span class="font-display text-[1.375rem] leading-none text-kpi-blue-700" data-testid="strip-season">{{ final.season }}</span>
      <ResultsTeamLogo :path="champion.team.logo" size="strip" />
      <ResultsTeamName :label="champion.team.label" :number="champion.team.number" :competition="final.competition.code" :season="final.season" class="text-xs leading-tight font-semibold" />
    </li>
  </ol>
</template>
