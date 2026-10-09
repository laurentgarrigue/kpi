<script setup lang="ts">
import { competitionPath } from '~/utils/competitions'
import type { TeamHonour } from '~/utils/teams'

// Honours: season, competition, rank, medals for the podiums of final rounds (TEA-02).
defineProps<{ honours: TeamHonour[], team: string }>()
const localePath = useLocalePath()
const mark = (honour: TeamHonour) => (honour.medal ? { kind: 'medal' as const, medal: honour.medal } : null)
</script>

<template>
  <p v-if="honours.length === 0" data-testid="no-honours">{{ $t('teams.noHonours') }}</p>
  <table v-else class="w-full text-left text-sm" data-testid="honours">
    <caption class="sr-only">{{ $t('teams.honoursCaption', { team }) }}</caption>
    <thead>
      <tr class="border-b border-line">
        <th scope="col" class="py-1">{{ $t('teams.col.season') }}</th>
        <th scope="col" class="py-1">{{ $t('teams.col.competition') }}</th>
        <th scope="col" class="py-1 text-right">{{ $t('teams.col.rank') }}</th>
      </tr>
    </thead>
    <tbody>
      <tr v-for="honour in honours" :key="`${honour.season}-${honour.competition.code}`" class="border-b border-line/60">
        <td class="py-1 tabular-nums">{{ honour.season }}</td>
        <td class="py-1">
          <NuxtLink :to="localePath(competitionPath(honour.season, honour.competition.code, 'ranking'))" class="hover:underline">{{ honour.competition.display_title }}</NuxtLink>
        </td>
        <td class="py-1">
          <span class="flex items-center justify-end gap-2"><ResultsRankingMark :mark="mark(honour)" /><span class="tabular-nums">{{ honour.rank }}</span></span>
        </td>
      </tr>
    </tbody>
  </table>
</template>
