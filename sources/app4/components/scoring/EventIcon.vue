<script setup lang="ts">
import type { ScoringEventCode } from '~/types/scoring'

/**
 * Event marker shared by the fact buttons, the penalties and the timeline: a goal is a black
 * disc with a white ring (same as the app2 timeline, GameEventIcon), a card is a plain
 * rectangle of its colour. D = black ejection card (2027 rules, spec §0.9): white outline so
 * it stays visible on the dark theme.
 */
defineProps<{ code: ScoringEventCode }>()

const CARD_CLASS: Record<Exclude<ScoringEventCode, 'B'>, string> = {
  V: 'bg-green-500',
  J: 'bg-yellow-400',
  R: 'bg-red-500',
  D: 'bg-black ring-1 ring-white'
}
</script>

<template>
  <span
    v-if="code === 'B'"
    class="inline-flex size-4 shrink-0 items-center justify-center rounded-full bg-black"
    aria-hidden="true"
  >
    <span class="size-2.5 rounded-full border-2 border-white" />
  </span>
  <span
    v-else
    class="inline-block h-4 w-3 shrink-0 rounded-[2px]"
    :class="CARD_CLASS[code]"
    aria-hidden="true"
  />
</template>
