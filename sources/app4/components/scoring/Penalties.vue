<script setup lang="ts">
import type { PenaltyClock } from '~/composables/usePenalties'
import type { TeamSide } from '~/types/scoring'

/**
 * Active penalties of both teams (spec §7.4): per-team countdowns with the card token,
 * the sanctioned player's number and a manual removal (correction). V/J = the player
 * returns; R = replacement at the end of the 2 minutes; D never appears here (no clock).
 */
defineProps<{
  penalties: PenaltyClock[]
  teamAName?: string | null
  teamBName?: string | null
  canScore?: boolean
}>()

const emit = defineEmits<{ remove: [id: string] }>()

const { t } = useI18n()

const CARD_TOKEN: Record<string, string> = { V: '🟢', J: '🟡', R: '🔴' }

const fmt = (ms: number): string => {
  const s = Math.ceil(ms / 1000)
  return `${String(Math.floor(s / 60)).padStart(2, '0')}:${String(s % 60).padStart(2, '0')}`
}

const byTeam = (penalties: PenaltyClock[], team: TeamSide) =>
  penalties.filter(p => p.team === team).sort((a, b) => a.slot - b.slot)
</script>

<template>
  <div class="grid grid-cols-2 gap-4">
    <div v-for="team in (['A', 'B'] as TeamSide[])" :key="team">
      <div class="text-xs uppercase tracking-wide text-header-600 mb-1">
        {{ t('scoring.penalty.title') }} — {{ team === 'A' ? (teamAName ?? 'A') : (teamBName ?? 'B') }}
      </div>
      <div v-if="byTeam(penalties, team).length === 0" class="text-sm text-header-400">—</div>
      <div v-else class="space-y-1">
        <div
          v-for="p in byTeam(penalties, team)"
          :key="p.id"
          class="flex items-center gap-2 px-2 py-1 rounded border border-header-200 text-sm"
        >
          <span>{{ CARD_TOKEN[p.cardCode] }}</span>
          <span class="font-mono w-6 text-center">{{ p.playerNumber ?? '?' }}</span>
          <span
            class="font-mono font-bold tabular-nums flex-1"
            :class="p.running ? 'text-error-600' : 'text-amber-600'"
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
    </div>
  </div>
</template>
