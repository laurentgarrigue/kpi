<script setup lang="ts">
import type { ScoringShot } from '~/types/scoring'
import { shotTotals } from '~/utils/scoringShots'

/**
 * Shot log of the scoring console (PAGE_SCORING.md §0.19 T6): totals per team, then the last shots and
 * saves entered with T / A, newest first, the one just entered highlighted. While a number may still be
 * typed after the key (T5), its line shows the « n° ? » prompt. Each line has its bin — no confirmation,
 * these are unofficial statistics, never on the game sheet. Props down / events up: the page owns the
 * entry and the persistence.
 */
const props = withDefaults(defineProps<{
  shots: ScoringShot[]
  teamA: string
  teamB: string
  canScore?: boolean
  /** Line just entered (highlighted). */
  highlightUid?: string | null
  /** Number being typed after T / A: digits so far, or the unknown number just rejected. */
  numberPrompt?: { uid: string, digits: string, unknown: number | null } | null
  /** Lines shown under the totals. */
  recentCount?: number
}>(), { canScore: false, highlightUid: null, numberPrompt: null, recentCount: 5 })

const emit = defineEmits<{ remove: [uid: string] }>()

const { t } = useI18n()

const totals = computed(() => shotTotals(props.shots))
const recent = computed(() => props.shots.slice(0, props.recentCount))
const teamName = (team: 'A' | 'B') => (team === 'A' ? props.teamA : props.teamB)
</script>

<template>
  <section class="space-y-2 text-sm" :aria-label="t('scoring.shots.title')">
    <div class="flex items-center justify-between gap-2">
      <h3 class="font-semibold">{{ t('scoring.shots.title') }}</h3>
      <span class="text-xs text-header-500 dark:text-header-400">{{ t('scoring.shots.unofficial') }}</span>
    </div>

    <div class="grid grid-cols-2 gap-2">
      <div
        v-for="team in (['A', 'B'] as const)" :key="team"
        :data-totals="team"
        class="rounded-md bg-header-100 dark:bg-header-800 px-2 py-1"
      >
        <div class="flex items-center gap-1 font-semibold min-w-0">
          <span class="shot-letter">{{ team }}</span>
          <span class="truncate">{{ teamName(team) }}</span>
        </div>
        <div class="tabular-nums">
          {{ t('scoring.shots.shots_total', { n: totals[team].shots }) }} · {{ t('scoring.shots.saves_total', { n: totals[team].saves }) }}
        </div>
      </div>
    </div>
    <p v-if="totals.unknown" class="text-xs text-amber-700 dark:text-amber-300">{{ t('scoring.shots.unknown_count', { n: totals.unknown }) }}</p>

    <p v-if="!shots.length" class="text-xs text-header-500 dark:text-header-400">{{ t('scoring.shots.empty') }}</p>
    <ul v-else class="space-y-1">
      <li
        v-for="s in recent" :key="s.uid"
        :data-highlight="s.uid === highlightUid ? 'true' : undefined"
        class="flex items-center gap-2 rounded-md px-2 py-1 border"
        :class="s.uid === highlightUid ? 'border-primary-500 bg-primary-50 dark:bg-primary-950/40' : 'border-header-200 dark:border-header-700'"
      >
        <span class="w-16 shrink-0 tabular-nums text-header-600 dark:text-header-300">{{ s.period }} {{ s.tpsJeu }}</span>
        <span class="font-semibold">{{ s.kind === 'SHOT' ? t('scoring.shots.shot') : t('scoring.shots.save') }}</span>
        <span v-if="s.team" class="shot-letter">{{ s.team }}</span>
        <span v-else class="text-amber-700 dark:text-amber-300">{{ t('scoring.shots.unknown_team') }}</span>
        <span v-if="s.number !== null" class="tabular-nums">n° {{ s.number }}</span>
        <span
          v-if="numberPrompt?.uid === s.uid"
          data-number-prompt
          class="rounded bg-amber-400 px-1.5 font-semibold text-black"
        >
          <template v-if="numberPrompt.unknown !== null">{{ t('scoring.shots.number_unknown', { n: numberPrompt.unknown }) }}</template>
          <template v-else>{{ t('scoring.shots.number_prompt') }} {{ numberPrompt.digits }}</template>
        </span>
        <span class="flex-1" />
        <UButton
          v-if="canScore"
          size="xs"
          variant="ghost"
          color="neutral"
          icon="i-heroicons-trash"
          :aria-label="t('scoring.shots.remove')"
          :title="t('scoring.shots.remove')"
          @click="emit('remove', s.uid)"
        />
      </li>
    </ul>
    <p v-if="numberPrompt && numberPrompt.unknown === null" class="text-xs text-header-500 dark:text-header-400">{{ t('scoring.shots.number_hint') }}</p>
  </section>
</template>

<style scoped>
.shot-letter {
  flex-shrink: 0;
  min-width: 1.25rem;
  padding: 0 0.25rem;
  border: 1.5px solid currentColor;
  border-radius: 0.25rem;
  font-weight: 700;
  text-align: center;
  line-height: 1.1rem;
}
</style>
