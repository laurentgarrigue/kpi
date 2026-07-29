<script setup lang="ts">
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
 * URL options (the display is autonomous — no interaction, cf. PAGE_INCRUSTATION spirit):
 *   ?theme=light   black on white (default: white on black)
 *   ?clock=0       hide the small game clock shown underneath (default: shown)
 */
definePageMeta({ layout: false, middleware: 'auth' })

const route = useRoute()
const matchId = Number(route.params.id)
const { state } = useScoringDisplay(matchId, 'shotclock')

// ─── URL options ───
const light = computed(() => route.query.theme === 'light')
const showClock = computed(() => route.query.clock !== '0')

// ─── Auto-fit ───
// Candidates, not just the current value: '60' (widest 2-digit) and '8.8' (widest tenths)
// are measured together so the digits keep a STABLE size when the display switches to
// tenths at 10 s, instead of jumping.
const mainBox = ref<HTMLElement | null>(null)
const { fontSize: mainSize } = useFitText(mainBox, () => ['60', '8.8', state.shotclock])

const clockBox = ref<HTMLElement | null>(null)
const { fontSize: clockSize } = useFitText(clockBox, () => ['00:00'], { fill: 0.8 })

// Colours: full black/white only — maximum contrast for LED panels and daylight.
const colours = computed(() => (light.value ? 'bg-white text-black' : 'bg-black text-white'))
// The suspended state (game clock stopped) is the one thing worth signalling, and it is
// done by dimming rather than by a colour, which would break the contrast requirement.
const dimmed = computed(() => state.shotclockState === 'IDLE' || state.shotclockState === 'SUSPENDED')
</script>

<template>
  <div class="fixed inset-0 flex flex-col overflow-hidden select-none scoring-digits" :class="colours">
    <!-- Shot clock: takes all the space left by the optional game clock -->
    <div ref="mainBox" class="flex-1 min-h-0 flex items-center justify-center">
      <span
        class="leading-none whitespace-nowrap transition-opacity duration-150"
        :class="dimmed ? 'opacity-40' : 'opacity-100'"
        :style="{ fontSize: mainSize + 'px' }"
      >{{ state.shotclock }}</span>
    </div>

    <!-- Game clock, smaller, underneath — optional (?clock=0) -->
    <div v-if="showClock" ref="clockBox" class="h-[18%] shrink-0 flex items-center justify-center">
      <span
        class="leading-none whitespace-nowrap opacity-70"
        :style="{ fontSize: clockSize + 'px' }"
      >{{ state.timer }}</span>
    </div>
  </div>
</template>

<style>
/**
 * Digit legibility at distance (spec: "police adaptée pour une excellente lisibilité des
 * chiffres"): a heavy neo-grotesque with **tabular figures**, so every digit keeps the
 * same advance width and the number never wobbles as it counts down. No web font is
 * loaded on purpose — the display must start instantly and work offline (PWA); an
 * embedded font can be swapped in here later without touching the layout.
 */
.scoring-digits {
  font-family: 'Helvetica Neue', Helvetica, Arial, 'Liberation Sans', system-ui, sans-serif;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
  font-feature-settings: 'tnum' 1, 'lnum' 1;
  letter-spacing: 0;
}
</style>
