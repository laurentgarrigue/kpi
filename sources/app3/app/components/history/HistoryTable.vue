<script setup lang="ts">
import { competitionPath } from '~/utils/competitions'
import { medalist, teamKey, type History } from '~/utils/history'

// Every season (HIS-02 to HIS-04, HIS-07): one row per finished final competition, its medals; touching a team
// highlights its podiums (`highlight`).
defineProps<{ history: History, highlighted: string | null, anchor: (season: string) => string }>()
const emit = defineEmits<{ highlight: [string] }>()
const localePath = useLocalePath()
const MEDALS = [1, 2, 3] as const
const DOTS = { 1: 'bg-medal-gold', 2: 'bg-medal-silver', 3: 'bg-medal-bronze' } as const
</script>

<template>
  <div class="overflow-x-auto pb-10 lg:overflow-visible">
    <table class="w-full" data-testid="history-table">
      <thead class="text-xs font-semibold text-muted">
        <tr>
          <th scope="col" class="pr-2.5 pb-1.5 text-left">{{ $t('history.col.season') }}</th>
          <th scope="col" class="hidden pr-2.5 pb-1.5 text-left md:table-cell">{{ $t('history.col.competition') }}</th>
          <th scope="col" class="pr-2.5 pb-1.5 text-left">{{ $t('history.col.champion') }}</th>
          <th scope="col" class="pr-2.5 pb-1.5 text-left">{{ $t('history.col.second') }}</th>
          <th scope="col" class="pr-2.5 pb-1.5 text-left">{{ $t('history.col.third') }}</th>
        </tr>
      </thead>
      <tbody>
        <template v-for="item in history.seasons" :key="item.season">
          <tr v-for="(competition, index) in item.competitions" :key="competition.code" class="border-t border-line align-top" :data-competition="competition.code">
            <td
              v-if="index === 0"
              :id="anchor(item.season)"
              :rowspan="item.competitions.length"
              class="w-16 scroll-mt-3 py-2.5 pr-2.5 font-display text-2xl leading-none text-kpi-blue-700"
              data-testid="history-season"
            >{{ item.season }}</td>
            <td class="hidden w-36 py-2.5 pr-2.5 text-[0.8125rem] text-muted md:table-cell">
              <NuxtLink :to="localePath(competitionPath(item.season, competition.code, 'ranking'))" class="hover:underline" data-testid="history-ranking-link">
                {{ competition.soustitre2 || competition.display_title }}
              </NuxtLink>
            </td>
            <td v-for="medal in MEDALS" :key="medal" class="py-2.5 pr-2.5">
              <span
                v-if="medalist(competition, medal)"
                class="inline-flex items-center gap-2 rounded"
                :class="{ 'bg-sky/20': highlighted === teamKey(medalist(competition, medal)!.team) }"
                :data-highlighted="highlighted === teamKey(medalist(competition, medal)!.team) ? '' : undefined"
                :data-medal="medal"
              >
                <span class="size-2.5 shrink-0 rounded-full" :class="DOTS[medal]" aria-hidden="true" />
                <span class="sr-only">{{ $t(`results.medal.${medal}`) }}</span>
                <button
                  type="button"
                  class="text-left hover:underline"
                  :class="{ 'font-bold text-kpi-blue-700': highlighted === teamKey(medalist(competition, medal)!.team) }"
                  :aria-pressed="highlighted === teamKey(medalist(competition, medal)!.team)"
                  :data-team="teamKey(medalist(competition, medal)!.team)"
                  @click="emit('highlight', teamKey(medalist(competition, medal)!.team))"
                >
                  {{ medalist(competition, medal)!.team.label }}
                </button>
              </span>
            </td>
          </tr>
        </template>
      </tbody>
    </table>
  </div>
</template>
