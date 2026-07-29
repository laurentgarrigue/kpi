<script setup lang="ts">
/**
 * Full-screen scoreboard (spec §6.1) — replaces the legacy admin/scoreboard.php.
 *
 * Like the shot-clock page, it targets **any** screen shape: the score is measured and
 * scaled to the surface (useFitText), the layout switches to a stacked arrangement on
 * portrait/narrow displays, and every secondary block is sized in viewport units so an
 * outdoor LED panel or a 21:9 strip stays readable. Maximum contrast, no chrome.
 *
 * Opened from the scoring console with window.open (same origin), it receives everything
 * through BroadcastChannel: no network, no API call, so it keeps working even when the
 * venue has no Internet. Remote screens use the video overlay + Mercure instead
 * (PAGE_INCRUSTATION.md).
 *
 * URL options: ?theme=light (black on white) · ?shotclock=0 (hide the shot clock).
 */
definePageMeta({ layout: false, middleware: 'auth' })

const route = useRoute()
const matchId = Number(route.params.id)
const { state } = useScoringDisplay(matchId, 'scoreboard')

const { t } = useI18n()

const light = computed(() => route.query.theme === 'light')
const showShotclock = computed(() => route.query.shotclock !== '0')

const colours = computed(() => (light.value ? 'bg-white text-black' : 'bg-black text-white'))

const periodLabel = computed(() => {
  if (!state.period) return ''
  const n = overtimeIndex(state.period)
  return n !== null ? t('scoring.period.overtime', { n }) : t('scoring.period.' + state.period)
})

// ─── Adaptive layout: stack the blocks when the screen is narrow or portrait ───
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

// ─── Auto-fit for the two dominant numbers ───
const scoreBox = ref<HTMLElement | null>(null)
const { fontSize: scoreSize } = useFitText(
  scoreBox,
  // Sized against a wide reference so the score never resizes when it goes from 9 to 10.
  () => ['88–88', `${state.scoreA}–${state.scoreB}`]
)

const clockBox = ref<HTMLElement | null>(null)
const { fontSize: clockSize } = useFitText(clockBox, () => ['00:00'])

const shotBox = ref<HTMLElement | null>(null)
const { fontSize: shotSize } = useFitText(shotBox, () => ['60', '8.8'], { fill: 0.85 })

const penaltiesOf = (team: 'A' | 'B') =>
  state.penalties.filter(p => p.team === team).sort((a, b) => a.slot - b.slot)

const fmtPenalty = (ms: number): string => {
  const s = Math.ceil(ms / 1000)
  return `${String(Math.floor(s / 60)).padStart(2, '0')}:${String(s % 60).padStart(2, '0')}`
}
</script>

<template>
  <div
    ref="root"
    class="fixed inset-0 flex flex-col overflow-hidden select-none scoring-digits"
    :class="colours"
  >
    <!-- Period — small header band -->
    <div class="h-[8%] shrink-0 flex items-center justify-center">
      <span class="uppercase tracking-[0.3em] opacity-60" style="font-size: 3.5vmin">{{ periodLabel }}</span>
    </div>

    <!-- Teams + score: side by side on wide screens, stacked on portrait ones -->
    <div
      class="flex-[3] min-h-0 flex items-center justify-center gap-[2vmin] px-[3vmin]"
      :class="portrait ? 'flex-col' : 'flex-row'"
    >
      <div class="flex-1 min-w-0" :class="portrait ? 'text-center w-full' : 'text-right'">
        <div class="truncate font-semibold" style="font-size: 6vmin">{{ state.teamA }}</div>
        <div class="mt-[1vmin] flex gap-[1vmin]" :class="portrait ? 'justify-center' : 'justify-end'">
          <span
            v-for="p in penaltiesOf('A')" :key="p.slot"
            class="px-[1vmin] rounded tabular-nums"
            :class="light ? 'bg-red-600 text-white' : 'bg-red-700 text-white'"
            style="font-size: 3.5vmin"
          >{{ p.playerNumber ?? '?' }} · {{ fmtPenalty(p.remainingMs) }}</span>
        </div>
      </div>

      <div ref="scoreBox" class="flex-[2] min-w-0 h-full flex items-center justify-center">
        <span class="leading-none whitespace-nowrap" :style="{ fontSize: scoreSize + 'px' }">
          {{ state.scoreA }}<span class="opacity-40">–</span>{{ state.scoreB }}
        </span>
      </div>

      <div class="flex-1 min-w-0" :class="portrait ? 'text-center w-full' : 'text-left'">
        <div class="truncate font-semibold" style="font-size: 6vmin">{{ state.teamB }}</div>
        <div class="mt-[1vmin] flex gap-[1vmin]" :class="portrait ? 'justify-center' : 'justify-start'">
          <span
            v-for="p in penaltiesOf('B')" :key="p.slot"
            class="px-[1vmin] rounded tabular-nums"
            :class="light ? 'bg-red-600 text-white' : 'bg-red-700 text-white'"
            style="font-size: 3.5vmin"
          >{{ p.playerNumber ?? '?' }} · {{ fmtPenalty(p.remainingMs) }}</span>
        </div>
      </div>
    </div>

    <!-- Game clock + shot clock -->
    <div class="flex-[2] min-h-0 flex items-stretch justify-center gap-[6vmin] pb-[2vmin]">
      <div
        ref="clockBox"
        class="flex items-center justify-center"
        :class="showShotclock ? 'flex-[2]' : 'flex-1'"
      >
        <span
          class="leading-none whitespace-nowrap"
          :class="state.timerRunning ? (light ? 'text-green-700' : 'text-green-400') : ''"
          :style="{ fontSize: clockSize + 'px' }"
        >{{ state.timer }}</span>
      </div>

      <div v-if="showShotclock" ref="shotBox" class="flex-1 flex items-center justify-center">
        <span
          class="leading-none whitespace-nowrap"
          :class="state.shotclockState === 'RUNNING' ? '' : 'opacity-40'"
          :style="{ fontSize: shotSize + 'px' }"
        >{{ state.shotclock }}</span>
      </div>
    </div>
  </div>
</template>

<style>
/* Shared with the shot-clock page: heavy neo-grotesque with tabular figures so digits
   never wobble; no web font loaded (instant start, works offline). */
.scoring-digits {
  font-family: 'Helvetica Neue', Helvetica, Arial, 'Liberation Sans', system-ui, sans-serif;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
  font-feature-settings: 'tnum' 1, 'lnum' 1;
  letter-spacing: 0;
}
</style>
