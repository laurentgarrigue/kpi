<script setup lang="ts">
import { displayOptionsFromQuery } from '~/utils/scoringDisplayOptions'
import { isPenaltyExpired } from '~/utils/scoringPenalties'

/**
 * Full-screen scoreboard (spec §6.1) — replaces the legacy admin/scoreboard.php.
 *
 * Like the shot-clock page, it targets **any** screen shape. Layout (§0.15, 2026-11-08): a
 * two-column grid — each team's name, penalties and score in its own half of the width; the
 * current period above the clocks, centred. Landscape: game clock | shot clock side by
 * side; portrait: stacked. The shot clock keeps full contrast whatever its state (stopped
 * included): at distance, a dimmed number reads as "off". Rows have fixed shares of the height and the numbers sit in
 * absolutely positioned boxes, so the digits never size their own box (the score used to
 * shrink to a few pixels in portrait). Maximum contrast, no chrome.
 *
 * Opened from the scoring console with window.open (same origin), it receives everything
 * through BroadcastChannel: no network, no API call, so it keeps working even when the
 * venue has no Internet. Remote screens use the video overlay + Mercure instead
 * (PAGE_INCRUSTATION.md).
 *
 * URL options, chosen in the console's options dialog (utils/scoringDisplayOptions):
 * ?theme=light (black on white) · ?shotclock=0 (hide the shot clock).
 */
definePageMeta({ layout: false, middleware: 'auth' })

const route = useRoute()
const matchId = Number(route.params.id)
const { state } = useScoringDisplay(matchId, 'scoreboard')

const { t } = useI18n()

const options = computed(() => displayOptionsFromQuery('scoreboard', route.query))
const light = computed(() => options.value.theme === 'light')
const showShotclock = computed(() => options.value.shotclock)

const colours = computed(() => (light.value ? 'bg-white text-black' : 'bg-black text-white'))

const periodLabel = computed(() => {
  if (!state.period) return ''
  const n = overtimeIndex(state.period)
  return n !== null ? t('scoring.period.overtime', { n }) : t('scoring.period.' + state.period)
})

// ─── Adaptive layout: portrait (or narrow) screens stack the clocks ───
const root = ref<HTMLElement | null>(null)
const portrait = ref(false)
let observer: ResizeObserver | null = null
onMounted(() => {
  const update = () => {
    const el = root.value
    if (el) portrait.value = el.clientWidth / Math.max(1, el.clientHeight) < 1.2
  }
  update()
  if (root.value && typeof ResizeObserver !== 'undefined') {
    observer = new ResizeObserver(update)
    observer.observe(root.value)
  }
})
onUnmounted(() => observer?.disconnect())

/** Row heights: names · penalties · scores · period · clocks (· shot clock, portrait). */
const gridRows = computed(() => {
  if (!portrait.value) return '13% 8% minmax(0, 1fr) 6% 30%'
  return showShotclock.value
    ? '12% 7% minmax(0, 1fr) 5% 20% 14%'
    : '12% 7% minmax(0, 1fr) 5% 26%'
})
/** Landscape with the shot clock: game clock in the left half, shot clock right. */
const clocksSideBySide = computed(() => !portrait.value && showShotclock.value)

// ─── Auto-fit: both scores share the same candidates, hence the same size ───
const scoreCandidates = () => ['88', String(state.scoreA), String(state.scoreB)]
const scoreBoxA = ref<HTMLElement | null>(null)
const scoreBoxB = ref<HTMLElement | null>(null)
const { fontSize: scoreSizeA } = useFitText(scoreBoxA, scoreCandidates)
const { fontSize: scoreSizeB } = useFitText(scoreBoxB, scoreCandidates)
const scoreSize = computed(() => Math.min(scoreSizeA.value, scoreSizeB.value))

const clockBox = ref<HTMLElement | null>(null)
const { fontSize: clockSize } = useFitText(clockBox, () => ['00:00'])

const shotBox = ref<HTMLElement | null>(null)
const { fontSize: shotSize } = useFitText(shotBox, () => ['60', '8.8'], { fill: 0.85 })

const penaltiesOf = (team: 'A' | 'B') =>
  state.penalties.filter(p => p.team === team).sort((a, b) => a.slot - b.slot)

/** A penalty chip takes its card's colour. */
const PENALTY_COLOURS: Record<'V' | 'J' | 'R', string> = {
  V: 'bg-green-600 text-white',
  J: 'bg-yellow-400 text-black',
  R: 'bg-red-600 text-white'
}

const fmtPenalty = (ms: number): string => {
  const s = Math.ceil(ms / 1000)
  return `${String(Math.floor(s / 60)).padStart(2, '0')}:${String(s % 60).padStart(2, '0')}`
}
</script>

<template>
  <div
    ref="root"
    class="fixed inset-0 grid grid-cols-2 gap-x-[3vmin] px-[3vmin] pb-[2vmin] overflow-hidden select-none scoring-digits"
    :class="colours"
    :style="{ gridTemplateRows: gridRows }"
  >
    <!-- Team names: half the width each -->
    <div
      v-for="(name, i) in [state.teamA, state.teamB]" :key="'name' + i"
      class="min-w-0 flex items-end justify-center text-center font-semibold"
      :style="{ fontSize: portrait ? '5.5vmin' : '7vmin' }"
    >
      <span class="w-full leading-tight" :class="portrait ? 'line-clamp-2 break-words' : 'truncate'">{{ name }}</span>
    </div>

    <!-- Penalties: card colour, 00:00 blinking once expired -->
    <div
      v-for="team in (['A', 'B'] as const)" :key="'pen' + team"
      class="min-w-0 flex flex-wrap items-center justify-center gap-[1vmin]"
    >
      <span
        v-for="p in penaltiesOf(team)" :key="p.slot"
        class="px-[1vmin] rounded tabular-nums font-semibold"
        :class="[PENALTY_COLOURS[p.cardCode], isPenaltyExpired(p) ? 'penalty-expired' : '']"
        style="font-size: 4vmin"
      >{{ p.playerNumber ?? '?' }} · {{ fmtPenalty(p.remainingMs) }}</span>
    </div>

    <!-- Scores: each in its half, same size -->
    <div ref="scoreBoxA" class="relative min-w-0 min-h-0">
      <span class="absolute inset-0 flex items-center justify-center leading-none whitespace-nowrap" :style="{ fontSize: scoreSize + 'px' }">{{ state.scoreA }}</span>
    </div>
    <div ref="scoreBoxB" class="relative min-w-0 min-h-0">
      <span class="absolute inset-0 flex items-center justify-center leading-none whitespace-nowrap" :style="{ fontSize: scoreSize + 'px' }">{{ state.scoreB }}</span>
    </div>

    <!-- Current period, above the clocks, centred on the screen -->
    <div class="col-span-2 min-w-0 flex items-end justify-center text-center">
      <span class="uppercase tracking-[0.3em] opacity-60 truncate" style="font-size: 3.5vmin">{{ periodLabel }}</span>
    </div>

    <!-- Game clock -->
    <div ref="clockBox" class="relative min-w-0 min-h-0" :class="clocksSideBySide ? '' : 'col-span-2'">
      <span
        class="absolute inset-0 flex items-center justify-center leading-none whitespace-nowrap"
        :class="state.timerRunning ? (light ? 'text-green-700' : 'text-green-400') : ''"
        :style="{ fontSize: clockSize + 'px' }"
      >{{ state.timer }}</span>
    </div>

    <!-- Shot clock: beside the game clock (landscape), under it (portrait) -->
    <div v-if="showShotclock" ref="shotBox" class="relative min-w-0 min-h-0" :class="clocksSideBySide ? '' : 'col-span-2'">
      <span
        class="absolute inset-0 flex items-center justify-center leading-none whitespace-nowrap"
        :style="{ fontSize: shotSize + 'px' }"
      >{{ state.shotclock }}</span>
    </div>
  </div>
</template>

<style scoped>
/* Expired penalty (§0.15): hard blink until the table removes it. */
.penalty-expired {
  animation: penalty-blink 1s steps(2, start) infinite;
}
@keyframes penalty-blink {
  to { visibility: hidden; }
}
@media (prefers-reduced-motion: reduce) {
  .penalty-expired { animation: none; text-decoration: underline wavy; }
}
</style>
