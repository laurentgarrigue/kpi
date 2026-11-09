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
 *   /live/overlay?event=236&pitch=2&token=…&blocks=score,clock,shotclock&skin=inter|club
 *                &style=…&variant=live|events|static|hd&bg=transparent|magenta|#rrggbb
 *                &lang=fr|en&debug
 *
 * `token` is the **display token** (scoped to the event/pitch, expiring, revocable): it
 * authorizes the public read routes and yields the Mercure subscriber JWT. It is set once
 * in the OBS source URL — see PAGE_INCRUSTATION.md §11bis.
 */
definePageMeta({ layout: false })

const route = useRoute()
const config = useRuntimeConfig()

// ─── URL options (§4) ───
const event = Number(route.query.event ?? 0)
const pitch = String(route.query.pitch ?? '')
const token = String(route.query.token ?? '')
const blocks = computed(() => String(route.query.blocks ?? 'score,clock').split(',').map(b => b.trim()))
const has = (block: string) => blocks.value.includes(block)
/** Score-banner format: `club` (full names) or `inter` (3-letter nations), from `skin`. */
const teamFormat = computed(() => overlayTeamFormat(route.query.skin))
const variant = computed(() => String(route.query.variant ?? 'live'))
const debug = computed(() => route.query.debug !== undefined)
/** Fixed labels follow `lang` (§4, default fr): overtimes read PRL{n} in French, OVT{n} in English. */
const OVERTIME_PREFIX: Record<string, string> = { fr: 'PRL', en: 'OVT' }
const overtimePrefix = computed(() => OVERTIME_PREFIX[String(route.query.lang ?? 'fr')] ?? OVERTIME_PREFIX.fr)
/** Period as shown to viewers (M1/M2 → P1/P2, P{n} → PRL{n}/OVT{n}); storage codes never shown. */
const showPeriod = (period: string) => periodDisplayCode(period, overtimePrefix.value)

const { program, state, status } = useOverlayProgram(event, pitch, config.public.api2BaseUrl, token)

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

// ─── Stage fitting: 1920×1080 scaled to the browser source (see utils/overlayStage) ───
const scale = ref(1)
const fitStage = () => { scale.value = stageScale(window.innerWidth, window.innerHeight) }
onMounted(() => {
  fitStage()
  window.addEventListener('resize', fitStage)
})
onUnmounted(() => window.removeEventListener('resize', fitStage))

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

// Team labels follow the banner format (§8): full club names, or 3-letter nations.
const teamA = computed(() => overlayTeamLabel(current.value?.equipeA ?? null, teamFormat.value))
const teamB = computed(() => overlayTeamLabel(current.value?.equipeB ?? null, teamFormat.value))

const periodLabel = computed(() => showPeriod(state.value?.periode ?? ''))
/** Bottom-left caption, "Category - Phase" as in the legacy overlays. */
const categoryLine = computed(() => overlayCategoryLine(current.value?.categorie ?? null, current.value?.phase ?? null))

/** The banner is on air from kick-off to the end of the final-score display. */
const scoreOnAir = computed(() => phase.value === 'match' || phase.value === 'halftime' || phase.value === 'final')
/** Second line under the banner: period + game clock + shot clock (only those requested). */
const showClockBar = computed(() => scoreOnAir.value && (has('clock') || has('shotclock')))

// Facts (last goals/cards, `fact` block) are deliberately NOT rendered for now: the list was
// too big and heavy for an overlay; its layout is to be redesigned (lot 4, PAGE_INCRUSTATION §8).

const CARD_COLOUR: Record<string, string> = {
  V: '#22c55e', J: '#eab308', R: '#dc2626', D: '#000000'
}
</script>

<template>
  <!-- 1920×1080 stage, scaled to the browser source: the overlay is authored once at
       broadcast resolution and adapts if OBS captures at another size. -->
  <div class="overlay-root" :style="{ background }">
    <div class="overlay-stage" :style="{ '--stage-scale': scale }">
      <!-- Top centre: score banner, then period / clock / shot clock underneath -->
      <div class="top">
        <div v-if="has('score') && scoreOnAir" class="score-band" :class="`format-${teamFormat}`">
          <div class="team team-a">{{ teamA }}</div>
          <div class="score">{{ state?.scoreA ?? 0 }}<span class="sep">-</span>{{ state?.scoreB ?? 0 }}</div>
          <div class="team team-b">{{ teamB }}</div>
        </div>

        <div v-if="showClockBar" class="clock-bar">
          <template v-if="phase === 'match'">
            <span v-if="periodLabel" class="period">{{ periodLabel }}</span>
            <span v-if="has('clock')" class="clock" :class="{ running: clocks.gameRunning.value }">
              {{ clocks.gameClock.value ?? '--:--' }}
            </span>
            <span v-if="has('shotclock')" class="shotclock">{{ clocks.shotClock.value ?? '--' }}</span>
          </template>
          <span v-else-if="phase === 'halftime'" class="phase-label">{{ periodLabel }}</span>
          <span v-else class="phase-label">FINAL</span>
        </div>

        <!-- Penalties, each team under its own side of the banner -->
        <div v-if="has('penalty') && phase === 'match'" class="penalties">
          <div class="pen-side left">
            <span
              v-for="p in penaltiesA"
              :key="'a' + p.slot"
              class="pen"
              :style="{ background: CARD_COLOUR[p.cardCode ?? 'V'] }"
            >{{ p.remaining }}</span>
          </div>
          <div class="pen-side right">
            <span
              v-for="p in penaltiesB"
              :key="'b' + p.slot"
              class="pen"
              :style="{ background: CARD_COLOUR[p.cardCode ?? 'V'] }"
            >{{ p.remaining }}</span>
          </div>
        </div>
      </div>

      <!-- Bottom left: category + phase -->
      <div v-if="categoryLine && scoreOnAir" class="category-line">{{ categoryLine }}</div>

      <!-- Next match presentation -->
      <div v-if="has('next') && phase === 'next' && next" class="next-game">
        <div class="next-title">MATCH {{ next.numero }} · {{ next.heure }}<template v-if="next.categorie"> · {{ next.categorie }}</template></div>
        <div class="next-teams">{{ next.equipeA }} <span class="sep">vs</span> {{ next.equipeB }}</div>
      </div>

      <!-- Token refused: a silent empty overlay would look like a data problem -->
      <div v-if="status === 'denied'" class="denied">
        Jeton d'affichage invalide, expiré ou révoqué
      </div>

      <!-- Diagnostics — never in production, only with ?debug -->
      <div v-if="debug" class="debug">
        event={{ event }} pitch={{ pitch }} · sse={{ status }} · phase={{ phase }} ·
        match={{ current?.id ?? '—' }} tick={{ state?.tick ?? '—' }} · format={{ teamFormat }} variant={{ variant }}
      </div>
    </div>
  </div>
</template>

<style scoped>
/* The overlay is authored at 1920×1080 and scaled by the viewport, so a browser source
   captured at another resolution keeps the exact same composition.
   Look inspired by the legacy live/score.php: light banner with blue team names, dark
   clock bar underneath. Colours are tokens so a future style (§10) only overrides them. */
.overlay-root {
  position: fixed;
  inset: 0;
  overflow: hidden;
}
.overlay-stage {
  --ov-band-bg: linear-gradient(180deg, #eef6fd 0%, #d6e8f8 100%);
  --ov-band-border: #4a7fd0;
  --ov-team: #3d8fd6;
  --ov-score: #12304f;
  --ov-bar-bg: linear-gradient(180deg, #1d4566 0%, #0d2338 100%);
  --ov-bar-text: #4fa3e6;
  --ov-running: #7cc7ff;
  position: absolute;
  top: 50%;
  left: 50%;
  width: 1920px;
  height: 1080px;
  transform: translate(-50%, -50%) scale(var(--stage-scale, 1));
  font-family: 'Helvetica Neue', Helvetica, Arial, 'Liberation Sans', system-ui, sans-serif;
  font-variant-numeric: tabular-nums;
  font-weight: 700;
}

.top {
  position: absolute;
  top: 66px;
  left: 50%;
  transform: translateX(-50%);
  display: flex;
  flex-direction: column;
  align-items: center;
}

.score-band {
  display: flex;
  align-items: center;
  gap: 22px;
  padding: 10px 26px;
  background: var(--ov-band-bg);
  border: 3px solid var(--ov-band-border);
  border-radius: 16px;
  box-shadow: 0 3px 10px rgba(0, 0, 0, .35);
  font-size: 38px;
  line-height: 1.15;
  white-space: nowrap;
}
.team { color: var(--ov-team); }
.format-inter .team { min-width: 84px; text-align: center; letter-spacing: .02em; }
.format-club .team { max-width: 560px; overflow: hidden; text-overflow: ellipsis; }
.format-club .team-a { text-align: right; }
.score { color: var(--ov-score); font-size: 40px; }
.sep { margin: 0 14px; }

/* Dark bar tucked under the banner (legacy: chrono + period). */
.clock-bar {
  display: flex;
  align-items: center;
  gap: 34px;
  min-width: 220px;
  justify-content: center;
  padding: 6px 24px 8px;
  background: var(--ov-bar-bg);
  border-radius: 0 0 10px 10px;
  box-shadow: 0 3px 8px rgba(0, 0, 0, .35);
  color: var(--ov-bar-text);
  font-size: 34px;
  line-height: 1.1;
}
.clock.running { color: var(--ov-running); }
.shotclock { color: #fbbf24; min-width: 44px; text-align: center; }
.phase-label { letter-spacing: .12em; }

.penalties {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 24px;
  width: 720px;
  margin-top: 10px;
}
.pen-side { display: flex; gap: 8px; }
.pen-side.left { justify-content: flex-end; }
.pen-side.right { justify-content: flex-start; }
.pen {
  min-width: 48px;
  padding: 2px 10px;
  border-radius: 6px;
  color: #fff;
  font-size: 26px;
  text-align: center;
  text-shadow: 0 1px 3px rgba(0, 0, 0, .6);
}

.category-line {
  position: absolute;
  left: 52px;
  bottom: 52px;
  padding: 8px 18px;
  background: var(--ov-band-bg);
  border: 2px solid var(--ov-band-border);
  border-radius: 6px;
  color: var(--ov-team);
  font-size: 22px;
  font-style: italic;
}

.next-game {
  position: absolute;
  left: 50%;
  bottom: 120px;
  transform: translateX(-50%);
  text-align: center;
  padding: 24px 48px;
  background: rgba(10, 12, 20, .82);
  border-radius: 14px;
  color: #fff;
}
.next-title { font-size: 30px; letter-spacing: .18em; opacity: .8; }
.next-teams { font-size: 54px; font-weight: 800; margin-top: 10px; }
.next-teams .sep { opacity: .45; }

.denied {
  position: absolute;
  top: 40px;
  left: 50%;
  transform: translateX(-50%);
  font-size: 28px;
  color: #fff;
  background: rgba(153, 27, 27, .9);
  padding: 12px 24px;
  border-radius: 8px;
}
.debug {
  position: absolute;
  top: 12px;
  left: 12px;
  font-size: 18px;
  font-weight: 400;
  font-family: ui-monospace, monospace;
  color: #fff;
  background: rgba(0, 0, 0, .7);
  padding: 6px 10px;
  border-radius: 6px;
}
</style>
