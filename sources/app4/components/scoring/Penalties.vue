<script setup lang="ts">
import type { PenaltyClock } from '~/composables/usePenalties'
import { isPenaltyExpired } from '~/utils/scoringPenalties'

/**
 * Active penalties of ONE team (spec §7.4): countdowns with the card token, the sanctioned
 * player's number and a manual removal (correction). V/J = the player returns; R =
 * replacement at the end of the 2 minutes; D never appears here (no clock).
 *
 * A click on a chip (outside its cross) opens a fine adjust of its countdown (ScoringTimeAdjust,
 * the same popover as the game clock and the shot clock) — game clock stopped only (`canAdjust`).
 *
 * An expired penalty (§0.15) stays at 00:00 and blinks until the cross removes it: the cue to
 * signal the line judge and the player (or the replacement). Nothing left to correct on it.
 *
 * Rendered under the team's score, so it carries no team name; each penalty is a chip only
 * as wide as its content, so two of them sit side by side when there is room.
 */
const props = defineProps<{
  penalties: PenaltyClock[]
  canScore?: boolean
  canAdjust?: boolean
}>()

const emit = defineEmits<{
  remove: [id: string]
  adjust: [id: string, deltaSec: number]
}>()

const { t } = useI18n()

const fmt = (ms: number): string => {
  const s = Math.ceil(ms / 1000)
  return `${String(Math.floor(s / 60)).padStart(2, '0')}:${String(s % 60).padStart(2, '0')}`
}

const sorted = computed(() => [...props.penalties].sort((a, b) => a.slot - b.slot))
</script>

<template>
  <div v-if="sorted.length" class="flex flex-wrap gap-2" :aria-label="t('scoring.penalty.title')">
    <div
      v-for="p in sorted"
      :key="p.id"
      class="inline-flex items-center rounded border border-header-200 dark:border-header-700 text-sm"
    >
      <ScoringTimeAdjust
        :display="fmt(p.remainingMs)"
        :class="isPenaltyExpired(p) ? 'penalty-expired' : ''"
        :can-adjust="canAdjust && !isPenaltyExpired(p)"
        :locked-hint="t('scoring.timer.adjust_locked')"
        :label="t('scoring.penalty.adjust')"
        trigger-class="inline-flex items-center gap-2 pl-2 pr-1 py-0.5 rounded-l hover:bg-header-100 dark:hover:bg-header-800"
        @adjust="emit('adjust', p.id, $event)"
      >
        <ScoringEventIcon :code="p.cardCode" />
        <span class="scoring-digits">{{ p.playerNumber ?? '?' }}</span>
        <span
          class="scoring-digits"
          :class="p.running || isPenaltyExpired(p) ? 'text-error-600 dark:text-error-400' : 'text-amber-600 dark:text-amber-400'"
        >{{ fmt(p.remainingMs) }}</span>
      </ScoringTimeAdjust>
      <UButton
        size="xs"
        variant="ghost"
        icon="i-heroicons-x-mark"
        :disabled="!canScore"
        :aria-label="t('scoring.penalty.remove')"
        @click="emit('remove', p.id)"
      />
    </div>
  </div>
</template>

<style scoped>
/* Expired penalty: hard blink (not a soft pulse) — it must catch the table's eye. */
.penalty-expired {
  animation: penalty-blink 1s steps(2, start) infinite;
}
@keyframes penalty-blink {
  to { visibility: hidden; }
}
@media (prefers-reduced-motion: reduce) {
  .penalty-expired { animation: none; text-decoration: underline wavy; }
}
</style>
