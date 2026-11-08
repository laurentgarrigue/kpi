<script setup lang="ts">
import { displayOptionsFromQuery } from '~/utils/scoringDisplayOptions'

/**
 * Full-screen shot-clock display (spec §6.1) — replaces the legacy admin/shotclock.php.
 *
 * Meant for **any** screen: poolside satellite, outdoor LED panel, portrait monitor,
 * ultra-wide strip. Nothing is sized in fixed units — the digits are measured and scaled
 * to the largest size the surface can hold (useFitText), and recomputed on every resize
 * or rotation. Maximum contrast, no decoration: at distance, only the number matters.
 *
 * Fed by BroadcastChannel only (same origin, no network), like the scoreboard.
 *
 * URL options (the display is autonomous — no interaction, cf. PAGE_INCRUSTATION spirit),
 * chosen in the console's options dialog before opening (§0.15, utils/scoringDisplayOptions):
 *   ?theme=light   black on white (default: white on black)
 *   ?clock=0       hide the small game clock shown underneath (default: shown)
 */
definePageMeta({ layout: false, middleware: 'auth' })

const route = useRoute()
const matchId = Number(route.params.id)
const { state } = useScoringDisplay(matchId, 'shotclock')

// ─── URL options ───
const options = computed(() => displayOptionsFromQuery('shotclock', route.query))
const light = computed(() => options.value.theme === 'light')
const showClock = computed(() => options.value.clock)

// ─── Auto-fit ───
// Candidates, not just the current value: '60' (widest 2-digit) and '8.8' (widest tenths)
// are measured together so the digits keep a STABLE size when the display switches to
// tenths at 10 s, instead of jumping.
const mainBox = ref<HTMLElement | null>(null)
const { fontSize: mainSize } = useFitText(mainBox, () => ['60', '8.8', state.shotclock])

const clockBox = ref<HTMLElement | null>(null)
const { fontSize: clockSize } = useFitText(clockBox, () => ['00:00'], { fill: 0.8 })

// Colours: full black/white only — maximum contrast for LED panels and daylight, whatever
// the state (stopped or suspended included, 2026-11-08): a dimmed number reads as "off".
const colours = computed(() => (light.value ? 'bg-white text-black' : 'bg-black text-white'))
</script>

<template>
  <div class="fixed inset-0 flex flex-col overflow-hidden select-none scoring-digits" :class="colours">
    <!-- Shot clock: takes all the space left by the optional game clock -->
    <div ref="mainBox" class="flex-1 min-h-0 flex items-center justify-center">
      <span
        class="leading-none whitespace-nowrap"
        :style="{ fontSize: mainSize + 'px' }"
      >{{ state.shotclock }}</span>
    </div>

    <!-- Game clock, smaller, underneath — optional (?clock=0); full contrast too -->
    <div v-if="showClock" ref="clockBox" class="h-[18%] shrink-0 flex items-center justify-center">
      <span
        class="leading-none whitespace-nowrap"
        :style="{ fontSize: clockSize + 'px' }"
      >{{ state.timer }}</span>
    </div>
  </div>
</template>
