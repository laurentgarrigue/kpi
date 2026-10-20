<script setup lang="ts">
import type { PenaltyClock } from '~/composables/usePenalties'

/**
 * Active penalties of ONE team (spec §7.4): countdowns with the card token, the sanctioned
 * player's number and a manual removal (correction). V/J = the player returns; R =
 * replacement at the end of the 2 minutes; D never appears here (no clock).
 *
 * Rendered inside the team's column, so it carries no team name; each penalty is a chip
 * only as wide as its content, so two of them sit side by side when there is room.
 */
const props = defineProps<{
  penalties: PenaltyClock[]
  canScore?: boolean
}>()

const emit = defineEmits<{ remove: [id: string] }>()

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
      class="inline-flex items-center gap-2 pl-2 rounded border border-header-200 dark:border-header-700 text-sm"
    >
      <ScoringEventIcon :code="p.cardCode" />
      <span class="scoring-digits">{{ p.playerNumber ?? '?' }}</span>
      <span
        class="scoring-digits"
        :class="p.running ? 'text-error-600 dark:text-error-400' : 'text-amber-600 dark:text-amber-400'"
      >{{ fmt(p.remainingMs) }}</span>
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
