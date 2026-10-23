<script setup lang="ts">
import { medalTally, type TeamHonour } from '~/utils/teams'

// What the team won (TEA-08): titles, second and third places of final rounds, each number with its label.
const props = defineProps<{ honours: TeamHonour[] }>()
const tally = computed(() => medalTally(props.honours))
const MEDALS = [1, 2, 3] as const
const COLOURS = { 1: 'text-medal-gold', 2: 'text-medal-silver', 3: 'text-medal-bronze' } as const
</script>

<template>
  <section :aria-label="$t('teams.medals')" class="flex flex-wrap gap-x-10 gap-y-3.5 py-6.5" data-testid="medal-tally">
    <div v-for="medal in MEDALS" :key="medal" class="grid grid-cols-[auto_auto] items-center gap-x-3" :data-tally="medal">
      <span class="row-span-2 font-display text-[3.375rem] leading-[0.85] tabular-nums md:text-[4.5rem]" :class="COLOURS[medal]" data-testid="tally-count">{{ tally[medal] }}</span>
      <b class="text-[0.9375rem]">{{ $t(`teams.tally.${medal}.label`) }}</b>
      <small class="text-[0.8125rem] text-muted">{{ $t(`teams.tally.${medal}.detail`) }}</small>
    </div>
  </section>
</template>
