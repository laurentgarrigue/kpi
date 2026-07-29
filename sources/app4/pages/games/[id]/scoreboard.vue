<script setup lang="ts">
/**
 * Full-screen scoreboard (spec §6.1) — replaces the legacy admin/scoreboard.php.
 *
 * Opened from the scoring console with window.open (same origin), it receives everything
 * through BroadcastChannel: no network, no API call, so it keeps working even when the
 * venue has no Internet. Remote screens use the video overlay + Mercure instead
 * (PAGE_INCRUSTATION.md).
 *
 * Read-only display: no interaction beyond the browser's own full-screen.
 */
definePageMeta({ layout: false, middleware: 'auth' })

const route = useRoute()
const matchId = Number(route.params.id)
const { state } = useScoringDisplay(matchId, 'scoreboard')

const { t } = useI18n()

const periodLabel = computed(() => {
  if (!state.period) return ''
  const n = overtimeIndex(state.period)
  return n !== null ? t('scoring.period.overtime', { n }) : t('scoring.period.' + state.period)
})

const penaltiesOf = (team: 'A' | 'B') =>
  state.penalties.filter(p => p.team === team).sort((a, b) => a.slot - b.slot)

const fmtPenalty = (ms: number): string => {
  const s = Math.ceil(ms / 1000)
  return `${String(Math.floor(s / 60)).padStart(2, '0')}:${String(s % 60).padStart(2, '0')}`
}
</script>

<template>
  <div class="min-h-screen bg-black text-white flex flex-col items-center justify-center gap-8 select-none">
    <!-- Period -->
    <div class="text-3xl uppercase tracking-[0.3em] text-white/70">{{ periodLabel }}</div>

    <!-- Teams + score -->
    <div class="flex items-center justify-center gap-12 w-full px-12">
      <div class="flex-1 text-right">
        <div class="text-4xl font-semibold truncate">{{ state.teamA }}</div>
        <div class="mt-2 flex justify-end gap-2 h-8">
          <div
            v-for="p in penaltiesOf('A')" :key="p.slot"
            class="px-2 rounded bg-red-700 text-xl font-mono tabular-nums"
          >{{ p.playerNumber ?? '?' }} · {{ fmtPenalty(p.remainingMs) }}</div>
        </div>
      </div>

      <div class="text-[12rem] leading-none font-mono font-bold tabular-nums">
        {{ state.scoreA }}<span class="mx-6 text-white/40">–</span>{{ state.scoreB }}
      </div>

      <div class="flex-1 text-left">
        <div class="text-4xl font-semibold truncate">{{ state.teamB }}</div>
        <div class="mt-2 flex gap-2 h-8">
          <div
            v-for="p in penaltiesOf('B')" :key="p.slot"
            class="px-2 rounded bg-red-700 text-xl font-mono tabular-nums"
          >{{ p.playerNumber ?? '?' }} · {{ fmtPenalty(p.remainingMs) }}</div>
        </div>
      </div>
    </div>

    <!-- Game clock + shotclock -->
    <div class="flex items-end gap-16">
      <div
        class="text-[10rem] leading-none font-mono font-bold tabular-nums"
        :class="state.timerRunning ? 'text-green-400' : 'text-white'"
      >
        {{ state.timer }}
      </div>
      <div class="text-center">
        <div class="text-xl uppercase tracking-widest text-white/60">{{ t('scoring.shotclock.title') }}</div>
        <div
          class="text-[7rem] leading-none font-mono font-bold tabular-nums"
          :class="state.shotclockState === 'RUNNING' ? 'text-green-400'
            : state.shotclockState === 'SUSPENDED' ? 'text-amber-400' : 'text-white/40'"
        >
          {{ state.shotclock }}
        </div>
      </div>
    </div>
  </div>
</template>
