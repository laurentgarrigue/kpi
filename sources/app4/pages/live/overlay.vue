<script setup lang="ts">
/**
 * Video overlay — the single, parameterized page that replaces the ~20 legacy PHP
 * incrustations and app_live (PAGE_INCRUSTATION.md).
 *
 * Autonomous by design (spec §2): everything comes from the URL and from the server, no
 * interaction is possible or expected — it is meant to be a browser source in OBS.
 * It boots on GET /program + /state, then follows Mercure, and interpolates the clocks
 * locally so it keeps ticking even during a network blackout.
 *
 * URL contract (§4):
 *   /live/overlay?event=236&pitch=2&blocks=score,clock,shotclock&skin=nations
 *                &style=…&variant=live|events|static|hd&bg=transparent|magenta|#rrggbb
 *                &lang=fr|en&debug
 */
definePageMeta({ layout: false })

const route = useRoute()
const config = useRuntimeConfig()

// ─── URL options (§4) ───
const event = Number(route.query.event ?? 0)
const pitch = String(route.query.pitch ?? '')
const blocks = computed(() => String(route.query.blocks ?? 'score,clock').split(',').map(b => b.trim()))
const has = (block: string) => blocks.value.includes(block)
const skin = computed(() => (route.query.skin === 'clubs' ? 'clubs' : 'nations'))
const variant = computed(() => String(route.query.variant ?? 'live'))
const debug = computed(() => route.query.debug !== undefined)

const { program, state, status } = useOverlayProgram(event, pitch, config.public.api2BaseUrl)

const settings = computed(() => program.value?.settings ?? null)

// Background: URL wins over the event/pitch setting (a director may need to override on
// the spot); 'transparent' relies on OBS' alpha, anything else is a chroma-key fill.
const background = computed(() => {
  const value = String(route.query.bg ?? settings.value?.background ?? 'transparent')
  return value === 'transparent' ? 'transparent' : value
})

const clocks = useInterpolatedClocks(() => state.value?.clocks ?? [])
const penaltiesA = clocks.penaltiesOf('A')
const penaltiesB = clocks.penaltiesOf('B')

const current = computed(() => program.value?.current ?? null)
const next = computed(() => program.value?.next ?? null)

// ─── Display sequence (§7): what the screen shows right now ───
// Driven by SERVER facts (period end, status END, new program) plus the configured delay,
// never by a local clock alone — so two overlays of the same pitch stay identical.
type Phase = 'idle' | 'match' | 'halftime' | 'final' | 'next'
const phase = ref<Phase>('idle')
let phaseTimer: ReturnType<typeof setTimeout> | null = null

const setPhase = (value: Phase, afterMs = 0) => {
  if (phaseTimer) clearTimeout(phaseTimer)
  if (afterMs <= 0) {
    phase.value = value
    return
  }
  phaseTimer = setTimeout(() => { phase.value = value }, afterMs)
}
onUnmounted(() => { if (phaseTimer) clearTimeout(phaseTimer) })

watch(() => [current.value?.statut, state.value?.statut, clocks.breakClock.value], () => {
  const matchStatus = state.value?.statut ?? current.value?.statut
  const s = settings.value
  if (!s || !current.value) {
    setPhase('idle')
    return
  }
  if (matchStatus === 'END') {
    // Final score, then the presentation of the next match.
    setPhase('final', s.finalScoreDelay * 1000)
    if (next.value) {
      const wait = (s.finalScoreDelay + s.finalScoreDuration + s.nextGameDelay) * 1000
      phaseTimer = setTimeout(() => { phase.value = 'next' }, wait)
    }
    return
  }
  if (clocks.breakClock.value) {
    // A break is running: show the score of the period that just ended.
    setPhase('halftime', s.halftimeScoreDelay * 1000)
    return
  }
  setPhase(matchStatus === 'ON' ? 'match' : 'next')
}, { immediate: true })

// Team labels follow the skin: club names as-is, nations derived from the club code
// (spec §4 — the logo comes first, the derived flag is only a fallback).
const teamA = computed(() => current.value?.equipeA ?? '')
const teamB = computed(() => current.value?.equipeB ?? '')

const periodLabel = computed(() => state.value?.periode ?? '')

/** Facts to display (goals/cards), most recent first — used by the `fact` block. */
const facts = computed(() => {
  const list = state.value?.events ?? []
  // `static` variant freezes the whole list; `live` keeps the last few.
  return variant.value === 'static' ? list : list.slice(0, 6)
})

const CARD_COLOUR: Record<string, string> = {
  V: '#22c55e', J: '#eab308', R: '#dc2626', D: '#000000'
}
</script>

<template>
  <!-- 1920×1080 stage, scaled to the browser source: the overlay is authored once at
       broadcast resolution and adapts if OBS captures at another size. -->
  <div class="overlay-root" :style="{ background }">
    <div class="overlay-stage">
      <!-- Score banner -->
      <div v-if="has('score') && (phase === 'match' || phase === 'halftime' || phase === 'final')" class="score-band">
        <div class="team team-a">{{ teamA }}</div>
        <div class="score">{{ state?.scoreA ?? 0 }}<span class="sep">–</span>{{ state?.scoreB ?? 0 }}</div>
        <div class="team team-b">{{ teamB }}</div>

        <div v-if="has('clock') && phase === 'match'" class="clock" :class="{ running: clocks.gameRunning.value }">
          {{ clocks.gameClock.value ?? '--:--' }}
        </div>
        <div v-if="has('shotclock') && phase === 'match'" class="shotclock">
          {{ clocks.shotClock.value ?? '--' }}
        </div>
        <div v-if="phase === 'halftime'" class="phase-label">{{ periodLabel }}</div>
        <div v-if="phase === 'final'" class="phase-label">FINAL</div>
      </div>

      <!-- Penalties -->
      <div v-if="has('penalty') && phase === 'match'" class="penalties">
        <div class="pen-side">
          <span
            v-for="p in penaltiesA.value"
            :key="'a' + p.slot"
            class="pen"
            :style="{ background: CARD_COLOUR[p.cardCode ?? 'V'] }"
          >{{ p.remaining }}</span>
        </div>
        <div class="pen-side right">
          <span
            v-for="p in penaltiesB.value"
            :key="'b' + p.slot"
            class="pen"
            :style="{ background: CARD_COLOUR[p.cardCode ?? 'V'] }"
          >{{ p.remaining }}</span>
        </div>
      </div>

      <!-- Facts (goals / cards) -->
      <div v-if="has('fact') && phase !== 'next'" class="facts">
        <div v-for="f in facts" :key="f.uid" class="fact">
          <span class="fact-token" :style="{ background: f.code === 'B' ? '#1e40af' : CARD_COLOUR[f.code] }" />
          <span class="fact-time">{{ f.period }} {{ f.tpsJeu }}</span>
          <span class="fact-name">{{ f.numero }} {{ f.nom }}</span>
        </div>
      </div>

      <!-- Next match presentation -->
      <div v-if="has('next') && phase === 'next' && next" class="next-game">
        <div class="next-title">MATCH {{ next.numero }} · {{ next.heure }}</div>
        <div class="next-teams">{{ next.equipeA }} <span class="sep">vs</span> {{ next.equipeB }}</div>
      </div>

      <!-- Diagnostics — never in production, only with ?debug -->
      <div v-if="debug" class="debug">
        event={{ event }} pitch={{ pitch }} · sse={{ status }} · phase={{ phase }} ·
        match={{ current?.id ?? '—' }} tick={{ state?.tick ?? '—' }} · skin={{ skin }} variant={{ variant }}
      </div>
    </div>
  </div>
</template>

<style scoped>
/* The overlay is authored at 1920×1080 and scaled by the viewport, so a browser source
   captured at another resolution keeps the exact same composition. */
.overlay-root {
  position: fixed;
  inset: 0;
  overflow: hidden;
}
.overlay-stage {
  position: absolute;
  top: 50%;
  left: 50%;
  width: 1920px;
  height: 1080px;
  transform: translate(-50%, -50%) scale(min(100vw / 1920, 100vh / 1080));
  font-family: 'Helvetica Neue', Helvetica, Arial, 'Liberation Sans', system-ui, sans-serif;
  font-variant-numeric: tabular-nums;
  color: #fff;
  text-shadow: 0 2px 6px rgba(0, 0, 0, .6);
}

.score-band {
  position: absolute;
  left: 60px;
  bottom: 80px;
  display: flex;
  align-items: center;
  gap: 24px;
  padding: 16px 28px;
  background: rgba(10, 12, 20, .82);
  border-radius: 12px;
  font-size: 44px;
  font-weight: 800;
}
.team { min-width: 320px; }
.team-a { text-align: right; }
.team-b { text-align: left; }
.score { font-size: 64px; }
.sep { opacity: .45; margin: 0 12px; }
.clock { font-size: 52px; margin-left: 24px; color: #e5e7eb; }
.clock.running { color: #4ade80; }
.shotclock { font-size: 52px; color: #fbbf24; min-width: 90px; text-align: center; }
.phase-label { font-size: 32px; letter-spacing: .2em; opacity: .8; margin-left: 16px; }

.penalties {
  position: absolute;
  left: 60px;
  bottom: 190px;
  width: 900px;
  display: flex;
  justify-content: space-between;
}
.pen-side { display: flex; gap: 8px; }
.pen-side.right { justify-content: flex-end; }
.pen {
  min-width: 54px;
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 30px;
  font-weight: 800;
  text-align: center;
}

.facts {
  position: absolute;
  right: 60px;
  bottom: 80px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.fact {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px 16px;
  background: rgba(10, 12, 20, .78);
  border-radius: 8px;
  font-size: 28px;
}
.fact-token { width: 18px; height: 18px; border-radius: 4px; display: inline-block; }
.fact-time { opacity: .7; font-size: 24px; }

.next-game {
  position: absolute;
  left: 50%;
  bottom: 120px;
  transform: translateX(-50%);
  text-align: center;
  padding: 24px 48px;
  background: rgba(10, 12, 20, .82);
  border-radius: 14px;
}
.next-title { font-size: 30px; letter-spacing: .18em; opacity: .8; }
.next-teams { font-size: 54px; font-weight: 800; margin-top: 10px; }

.debug {
  position: absolute;
  top: 12px;
  left: 12px;
  font-size: 18px;
  font-family: ui-monospace, monospace;
  background: rgba(0, 0, 0, .7);
  padding: 6px 10px;
  border-radius: 6px;
}
</style>
