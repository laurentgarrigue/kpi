<script setup lang="ts">
import { competitionPath } from '~/utils/competitions'
import { honoursBySeason, type TeamHonour } from '~/utils/teams'

// Honours (TEA-02, TEA-07): one season cell per season, the medal in place of the rank for the podiums of final
// rounds, and the ranks of intermediate rounds (qualifications, pools…) in italic, explained by a legend.
const props = defineProps<{ honours: TeamHonour[], team: string }>()
const localePath = useLocalePath()
const seasons = computed(() => honoursBySeason(props.honours))
const hasIntermediate = computed(() => props.honours.some(honour => !honour.final_round))
</script>

<template>
  <p v-if="honours.length === 0" data-testid="no-honours">{{ $t('teams.noHonours') }}</p>
  <template v-else>
    <table class="w-full text-left text-sm" data-testid="honours">
      <caption class="sr-only">{{ $t('teams.honoursCaption', { team }) }}</caption>
      <thead>
        <tr class="border-b border-line">
          <th scope="col" class="py-1">{{ $t('teams.col.season') }}</th>
          <th scope="col" class="py-1">{{ $t('teams.col.competition') }}</th>
          <th scope="col" class="py-1 text-right">{{ $t('teams.col.rank') }}</th>
        </tr>
      </thead>
      <tbody v-for="group in seasons" :key="group.season" class="border-b border-line" :data-season="group.season">
        <tr
          v-for="(honour, index) in group.honours"
          :key="honour.competition.code"
          :class="{ 'italic text-ink/70': !honour.final_round }"
          :data-final="honour.final_round"
          data-testid="honour"
        >
          <th v-if="index === 0" scope="rowgroup" :rowspan="group.honours.length" class="py-1 pr-2 align-top font-normal tabular-nums not-italic text-ink">{{ group.season }}</th>
          <td class="py-1">
            <NuxtLink :to="localePath(competitionPath(honour.season, honour.competition.code, 'ranking'))" class="hover:underline">{{ honour.competition.display_title }}</NuxtLink>
            <span v-if="!honour.final_round" class="sr-only"> ({{ $t('teams.intermediateRound') }})</span>
          </td>
          <td class="py-1">
            <span class="flex justify-end">
              <!-- The medal replaces the rank (TEA-07). -->
              <ResultsRankingMark v-if="honour.medal" :mark="{ kind: 'medal', medal: honour.medal }" />
              <span v-else class="w-6 text-center tabular-nums" data-testid="honour-rank">{{ honour.rank }}</span>
            </span>
          </td>
        </tr>
      </tbody>
    </table>
    <p v-if="hasIntermediate" class="mt-2 text-xs italic text-ink/70" data-testid="honours-legend">{{ $t('teams.intermediateLegend') }}</p>
  </template>
</template>
