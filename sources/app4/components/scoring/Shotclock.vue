<script setup lang="ts">
import type { ShotclockState } from '~/utils/scoringRules'
import type { ShotclockDurations } from '~/types/scoring'

/**
 * Shotclock (chronomètre de tir) — display + the 3 commands of spec §6.5:
 * start/reset 60 s, start/reset 40 s (offensive rebound, 2027 rules), stop (back to "--").
 * ±1 s fine adjust only while suspended (game clock stopped). Props down / events up:
 * the page owns the useShotclock instance and the persistence.
 */
const props = defineProps<{
  display: string
  state: ShotclockState
  durations: ShotclockDurations
  offensiveReboundEnabled: boolean
  /** Masked = game time remaining below the shotclock (legacy shotClockShow rule). */
  masked?: boolean
  canScore?: boolean
}>()

const emit = defineEmits<{
  start: [seconds: number]
  stop: []
  adjust: [delta: number]
  testSound: []
}>()

const { t } = useI18n()

const shown = computed(() => (props.masked ? '--' : props.display))
const digitClass = computed(() => {
  if (props.state === 'RUNNING' && !props.masked) return 'text-success-600'
  if (props.state === 'SUSPENDED') return 'text-amber-600'
  return 'text-header-400'
})
</script>

<template>
  <div class="flex flex-col items-center gap-1">
    <div class="text-xs uppercase tracking-wide text-header-600">{{ t('scoring.shotclock.title') }}</div>
    <div class="text-4xl font-mono font-bold tabular-nums px-4" :class="digitClass">
      {{ shown }}
    </div>
    <div class="flex items-center gap-1">
      <UButton size="xs" :disabled="!canScore" @click="emit('start', durations.full)">
        {{ durations.full }} s
      </UButton>
      <UButton
        v-if="offensiveReboundEnabled"
        size="xs"
        color="warning"
        :disabled="!canScore"
        @click="emit('start', durations.offensiveRebound)"
      >
        {{ durations.offensiveRebound }} s
      </UButton>
      <UButton
        size="xs"
        variant="outline"
        :disabled="!canScore || state === 'IDLE'"
        @click="emit('stop')"
      >
        {{ t('scoring.shotclock.stop') }}
      </UButton>
      <!-- ±1 s: only while suspended (spec: adjust only when the game clock is stopped) -->
      <UButton size="xs" variant="ghost" :disabled="!canScore || state !== 'SUSPENDED'" @click="emit('adjust', -1)">−1</UButton>
      <UButton size="xs" variant="ghost" :disabled="!canScore || state !== 'SUSPENDED'" @click="emit('adjust', 1)">+1</UButton>
      <UButton
        size="xs"
        variant="ghost"
        icon="i-heroicons-speaker-wave"
        :aria-label="t('scoring.sound_test')"
        @click="emit('testSound')"
      />
    </div>
  </div>
</template>
