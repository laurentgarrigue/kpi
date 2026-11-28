<script setup lang="ts">
import type { ShotclockState } from '~/utils/scoringRules'
import type { ShotclockDurations, TeamSide } from '~/types/scoring'
import type { PitchSide } from '~/utils/scoringSides'
import { shortcutKeyLabel } from '~/utils/scoringShortcuts'

/**
 * Shotclock (chronomètre de tir) commands of spec §6.5 and §0.18: start/reset 60 s with the ball
 * to the LEFT or to the RIGHT team (seen from the table — each button sits on its team's side and
 * carries its letter), start/reset 40 s (offensive rebound, 2027 rules), stop (back to "--").
 * Both 60 s have the same effect on the match; they only differ by the team given the ball.
 * The digits — and their fine adjust, suspended only (game clock stopped) — are on the console
 * board (ScoringBoard). Props down / events up: the page owns the useShotclock instance and the
 * persistence. Each command shows its keyboard key, dimmed by `kbdClass` when the keyboard does
 * not reach the console (spec §0.14).
 */
defineProps<{
  state: ShotclockState
  durations: ShotclockDurations
  offensiveReboundEnabled: boolean
  /** Teams on the left and right halves for the current period. */
  left: { team: TeamSide, name: string }
  right: { team: TeamSide, name: string }
  canScore?: boolean
  keys?: { start60Left: string, start60Right: string, start40: string, stop: string }
  kbdClass?: string
}>()

const emit = defineEmits<{
  start60: [side: PitchSide]
  start40: []
  stop: []
  testSound: []
}>()

const { t } = useI18n()
const KBD = 'rounded border border-current/40 px-1 text-sm font-mono leading-4'
</script>

<template>
  <!-- ◀ left team 60 · 40 · Arrêt · right team 60 ▶ would hide 40/stop in the middle: the two 60 s
       frame the group instead, each on its team's side, as on the 4-key pad (§0.18 D14). -->
  <div class="flex items-center gap-1" role="group" :aria-label="t('scoring.shotclock.title')">
    <UButton
      size="sm"
      data-side="L"
      :disabled="!canScore"
      :title="t('scoring.shotclock.start60_left', { team: `${left.team} — ${left.name}` })"
      @click="emit('start60', 'L')"
    >
      <span class="scoring-letter">{{ left.team }}</span>
      {{ durations.full }} ▶
      <kbd v-if="keys?.start60Left" :class="[KBD, kbdClass]">{{ shortcutKeyLabel(keys.start60Left) }}</kbd>
    </UButton>
    <UButton
      size="sm"
      data-side="R"
      :disabled="!canScore"
      :title="t('scoring.shotclock.start60_right', { team: `${right.team} — ${right.name}` })"
      @click="emit('start60', 'R')"
    >
      ◀ {{ durations.full }}
      <span class="scoring-letter">{{ right.team }}</span>
      <kbd v-if="keys?.start60Right" :class="[KBD, kbdClass]">{{ shortcutKeyLabel(keys.start60Right) }}</kbd>
    </UButton>
    <UButton
      v-if="offensiveReboundEnabled"
      size="sm"
      color="warning"
      :disabled="!canScore"
      @click="emit('start40')"
    >
      {{ durations.offensiveRebound }} s
      <kbd v-if="keys?.start40" :class="[KBD, kbdClass]">{{ shortcutKeyLabel(keys.start40) }}</kbd>
    </UButton>
    <UButton
      size="sm"
      variant="outline"
      :disabled="!canScore || state === 'IDLE'"
      @click="emit('stop')"
    >
      {{ t('scoring.shotclock.stop') }}
      <kbd v-if="keys?.stop" :class="[KBD, kbdClass]">{{ shortcutKeyLabel(keys.stop) }}</kbd>
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

<style scoped>
/* The team letter, as on the game sheet: A or B, boxed so it reads apart from the digits. */
.scoring-letter {
  display: inline-block;
  min-width: 1.25rem;
  padding: 0 0.25rem;
  border-radius: 0.25rem;
  border: 1px solid currentColor;
  font-weight: 700;
  line-height: 1.1rem;
  text-align: center;
}
</style>
