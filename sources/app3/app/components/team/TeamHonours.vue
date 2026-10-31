<script setup lang="ts">
import { competitionPath } from '~/utils/competitions'
import { ordinal } from '~/utils/games'
import { honoursBySeason, type TeamHonour } from '~/utils/teams'

// Honours (TEA-02, TEA-07): a season column, one row per competition; the rank written, preceded by the medal of the
// podiums of final rounds; intermediate rounds (qualifications, pools…) muted and named.
const props = defineProps<{ honours: TeamHonour[] }>()
const localePath = useLocalePath()
const language = useLanguageTag()
const seasons = computed(() => honoursBySeason(props.honours))
const DOTS = { 1: 'bg-medal-gold', 2: 'bg-medal-silver', 3: 'bg-medal-bronze' } as const
</script>

<template>
  <p v-if="honours.length === 0" data-testid="no-honours">{{ $t('teams.noHonours') }}</p>
  <div v-else class="grid gap-x-6 md:grid-cols-[5.625rem_minmax(0,1fr)]" data-testid="honours">
    <template v-for="group in seasons" :key="group.season">
      <p class="pt-3 pb-1 font-display text-[1.75rem] leading-none text-kpi-blue-700 md:border-t-2 md:border-navy md:py-3 dark:md:border-kpi-blue-500" :data-season="group.season">{{ group.season }}</p>
      <ul class="border-navy md:border-t-2 dark:border-kpi-blue-500">
        <li
          v-for="honour in group.honours"
          :key="honour.competition.code"
          class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3 border-t border-line py-2.5 first:border-t-0"
          :data-final="String(honour.final_round)"
          data-testid="honour"
        >
          <span>
            <NuxtLink
              :to="localePath(competitionPath(honour.season, honour.competition.code, 'ranking'))"
              class="hover:underline"
              :class="[honour.final_round ? '' : 'text-muted', { 'font-bold': honour.final_round && honour.medal }]"
            >{{ honour.competition.display_title }}</NuxtLink>
            <small v-if="!honour.final_round" class="ml-1.5 text-xs text-muted">{{ $t('teams.qualification') }}</small>
          </span>
          <span class="inline-flex items-center justify-end gap-1.5 font-display text-[1.375rem] leading-none tabular-nums" :class="{ 'text-muted': !honour.final_round }">
            <span v-if="honour.final_round && honour.medal" class="inline-flex items-center" :data-medal="honour.medal">
              <span class="size-2.5 rounded-full" :class="DOTS[honour.medal]" aria-hidden="true" />
              <span class="sr-only">{{ $t(`results.medal.${honour.medal}`) }}</span>
            </span>
            <span data-testid="honour-rank">{{ ordinal(honour.rank, language) }}</span>
          </span>
        </li>
      </ul>
    </template>
  </div>
</template>
