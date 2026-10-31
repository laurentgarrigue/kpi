<script setup lang="ts">
import { ordinal } from '~/utils/games'
import type { titleHolder } from '~/utils/history'

// The title holder opens the honours (HIS-06): logo, « Champion {season} », its current streak or number of titles,
// the 2nd and 3rd of that season.
defineProps<{ holder: NonNullable<ReturnType<typeof titleHolder>>, competition: string }>()
const language = useLanguageTag()
</script>

<template>
  <section
    class="grid grid-cols-[4.5rem_minmax(0,1fr)] items-center gap-x-7 gap-y-2 rounded-xl bg-navy p-4.5 text-white md:grid-cols-[auto_minmax(0,1fr)_auto] md:px-6.5 md:py-5.5"
    :aria-label="$t('history.holder')"
    data-testid="title-holder"
  >
    <ResultsTeamLogo :path="holder.champion.team.logo" size="holder" class="rounded-lg bg-white p-2" />
    <div>
      <small class="text-sm text-white/80">{{ $t('history.champion', { season: holder.season }) }}</small>
      <b class="my-1 block font-display text-[2.375rem] leading-none font-normal md:text-[3.375rem]">
        <ResultsTeamName :label="holder.champion.team.label" :number="holder.champion.team.number" :competition="competition" :season="holder.season" class="text-white" />
      </b>
      <span class="font-semibold text-kpi-gold-300" data-testid="holder-titles">
        {{ holder.streak > 1
          ? $t('history.streak', { n: holder.streak, since: holder.since })
          : $t('history.titles', { rank: ordinal(holder.titles, language) }) }}
      </span>
    </div>
    <ul class="col-span-2 text-sm leading-loose text-white/85 md:col-span-1 md:text-right" data-testid="holder-others">
      <li v-for="entry in holder.others" :key="entry.rank">
        <span class="mr-1.5 inline-block size-2.5 rounded-full" :class="entry.medal === 2 ? 'bg-medal-silver' : 'bg-medal-bronze'" aria-hidden="true" />{{ ordinal(entry.rank, language) }} {{ entry.team.label }}
      </li>
    </ul>
  </section>
</template>
