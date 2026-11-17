<script setup lang="ts">
import type { ShotclockState } from '~/utils/scoringRules'
import type { ShotclockDurations } from '~/types/scoring'
import { shortcutKeyLabel } from '~/utils/scoringShortcuts'

/**
 * Shotclock (chronomètre de tir) commands of spec §6.5: start/reset 60 s, start/reset 40 s
 * (offensive rebound, 2027 rules), stop (back to "--"). The digits — and their fine adjust,
 * suspended only (game clock stopped) — are on the console board (ScoringBoard).
 * Props down / events up: the page owns the useShotclock instance and the persistence.
 * Each command shows its keyboard key, dimmed by `kbdClass` when the keyboard does not
 * reach the console (spec §0.14).
 */
defineProps<{
  state: ShotclockState
  durations: ShotclockDurations
  offensiveReboundEnabled: boolean
  canScore?: boolean
  keys?: { start60: string, start40: string, stop: string }
  kbdClass?: string
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
      <kbd v-if="keys?.start60" class="rounded border border-current/40 px-1 text-xs font-mono leading-4" :class="kbdClass">{{ shortcutKeyLabel(keys.start60) }}</kbd>
    </UButton>
    <UButton
      v-if="offensiveReboundEnabled"
      size="sm"
      color="warning"
      :disabled="!canScore"
      @click="emit('start', durations.offensiveRebound)"
    >
      {{ durations.offensiveRebound }} s
      <kbd v-if="keys?.start40" class="rounded border border-current/40 px-1 text-xs font-mono leading-4" :class="kbdClass">{{ shortcutKeyLabel(keys.start40) }}</kbd>
    </UButton>
    <UButton
      size="sm"
      variant="outline"
      :disabled="!canScore || state === 'IDLE'"
      @click="emit('stop')"
    >
      {{ t('scoring.shotclock.stop') }}
      <kbd v-if="keys?.stop" class="rounded border border-current/40 px-1 text-xs font-mono leading-4" :class="kbdClass">{{ shortcutKeyLabel(keys.stop) }}</kbd>
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
