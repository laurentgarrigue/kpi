<script setup lang="ts">
import type { PenaltyClock } from '~/composables/usePenalties'

/**
 * Active penalties of ONE team (spec §7.4): countdowns with the card token, the sanctioned
 * player's number and a manual removal (correction). V/J = the player returns; R =
 * replacement at the end of the 2 minutes; D never appears here (no clock).
 *
 * A click on a chip (outside its cross) opens a fine adjust of its countdown — game clock
 * stopped only (`canAdjust`), like the main clock's ±1/±10.
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

const ADJUST_STEPS = [-10, -1, 1, 10] as const

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
      <UPopover>
        <button
          type="button"
          class="inline-flex items-center gap-2 pl-2 pr-1 py-0.5 rounded-l hover:bg-header-100 dark:hover:bg-header-800 focus-visible:outline-2 focus-visible:outline-primary-500"
          :title="t('scoring.penalty.adjust')"
        >
          <ScoringEventIcon :code="p.cardCode" />
          <span class="scoring-digits">{{ p.playerNumber ?? '?' }}</span>
          <span
            class="scoring-digits"
            :class="p.running ? 'text-error-600 dark:text-error-400' : 'text-amber-600 dark:text-amber-400'"
          >{{ fmt(p.remainingMs) }}</span>
        </button>
        <template #content>
          <div class="p-2 space-y-1">
            <div class="flex items-center gap-1">
              <UButton
                v-for="d in ADJUST_STEPS" :key="d"
                size="xs"
                variant="ghost"
                :disabled="!canAdjust"
                @click="emit('adjust', p.id, d)"
              >{{ d > 0 ? `+${d}` : `−${-d}` }}</UButton>
            </div>
            <p v-if="!canAdjust" class="text-xs text-header-600 dark:text-header-300">{{ t('scoring.timer.adjust_locked') }}</p>
          </div>
        </template>
      </UPopover>
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
