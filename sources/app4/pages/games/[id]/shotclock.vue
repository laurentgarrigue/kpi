<script setup lang="ts">
/**
 * Full-screen shot-clock display (spec §6.1) — replaces the legacy admin/shotclock.php.
 *
 * Same principle as the scoreboard: opened from the console (same origin), fed by
 * BroadcastChannel only — no network. Meant for the satellite screen at the pool side.
 */
definePageMeta({ layout: false, middleware: 'auth' })

const route = useRoute()
const matchId = Number(route.params.id)
const { state } = useScoringDisplay(matchId, 'shotclock')

const digitClass = computed(() => {
  if (state.shotclockState === 'RUNNING') return 'text-green-400'
  if (state.shotclockState === 'SUSPENDED') return 'text-amber-400'
  return 'text-white/40'
})
</script>

<template>
  <div class="min-h-screen bg-black text-white flex flex-col items-center justify-center select-none">
    <div class="text-[22rem] leading-none font-mono font-bold tabular-nums" :class="digitClass">
      {{ state.shotclock }}
    </div>
    <!-- Game clock as a small reminder, so the pool-side screen stays self-sufficient -->
    <div class="text-5xl font-mono tabular-nums text-white/60 mt-4">{{ state.timer }}</div>
  </div>
</template>
