<script setup lang="ts">
import type { ShotclockState } from '~/utils/scoringRules'
import type { ShotclockDurations } from '~/types/scoring'

/**
 * Shotclock (chronomètre de tir) commands of spec §6.5: start/reset 60 s, start/reset 40 s
 * (offensive rebound, 2027 rules), stop (back to "--"). The digits — and their fine adjust,
 * suspended only (game clock stopped) — are on the console board (ScoringBoard).
 * Props down / events up: the page owns the useShotclock instance and the persistence.
 */
defineProps<{
  state: ShotclockState
  durations: ShotclockDurations
  offensiveReboundEnabled: boolean
  canScore?: boolean
}>()

const emit = defineEmits<{
  start: [seconds: number]
  stop: []
  testSound: []
}>()

const { t } = useI18n()
</script>

<template>
  <!-- 60 s / 40 s / Arrêt. The fine adjust is a click on the shot clock digits of the board
       (ScoringTimeAdjust, like the game clock and the penalties). -->
  <div class="flex items-center gap-1" role="group" :aria-label="t('scoring.shotclock.title')">
    <UButton size="sm" :disabled="!canScore" @click="emit('start', durations.full)">
      {{ durations.full }} s
    </UButton>
    <UButton
      v-if="offensiveReboundEnabled"
      size="sm"
      color="warning"
      :disabled="!canScore"
      @click="emit('start', durations.offensiveRebound)"
    >
      {{ durations.offensiveRebound }} s
    </UButton>
    <UButton
      size="sm"
      variant="outline"
      :disabled="!canScore || state === 'IDLE'"
      @click="emit('stop')"
    >
      {{ t('scoring.shotclock.stop') }}
    </UButton>
    <UButton
      size="sm"
      variant="ghost"
      icon="i-heroicons-speaker-wave"
      :aria-label="t('scoring.sound_test')"
      :title="t('scoring.sound_test')"
      @click="emit('testSound')"
    />
  </div>
</template>
