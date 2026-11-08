<script setup lang="ts">
import type { MatchClosure, OfficialRole, Period, ScoringPlayer, ScoringEvent, ScoringEventCode, TeamSide } from '~/types/scoring'
import type { PenaltyClock } from '~/composables/usePenalties'
import { shortcutKeyLabel, type ShortcutAction } from '~/utils/scoringShortcuts'

definePageMeta({
  layout: 'admin',
  middleware: 'auth'
})

const route = useRoute()
const { t, locale } = useI18n()
const toast = useToast()
const scoringStore = useScoringStore()
// Build the api client here (setup context) and hand it to the store: most scoring
// actions fire from callbacks (clock tick, shortcut, penalty expiry) where the store
// cannot build one itself — useApi() calls useI18n(), which requires setup.
scoringStore.setApi(useApi())

const matchId = computed(() => parseInt(route.params.id as string))

// Permissions (experimentation phase: profile <= 2)
const { canView, canScore: canScoreBase, canValidate } = useScoringPermissions(
  computed(() => scoringStore.isLocked)
)
const canScore = computed(() => canScoreBase.value && !scoringStore.isCompetitionEnded)

// D = black ejection card under the 2027 rules (spec §0.9) — code kept for storage compat.
// shortKey: one-word label for the narrow fact keys (mobile bottom bar, validate button).
// shortcut: the keyboard action arming the fact (its key is shown on the button).
const eventCodes: { code: ScoringEventCode; labelKey: string; shortKey: string; color: string; shortcut: ShortcutAction }[] = [
  { code: 'B', labelKey: 'scoring.event.goal', shortKey: 'scoring.event_short.goal', color: 'primary', shortcut: 'factGoal' },
  { code: 'V', labelKey: 'scoring.event.card_green', shortKey: 'scoring.event_short.card_green', color: 'success', shortcut: 'factGreen' },
  { code: 'J', labelKey: 'scoring.event.card_yellow', shortKey: 'scoring.event_short.card_yellow', color: 'warning', shortcut: 'factYellow' },
  { code: 'R', labelKey: 'scoring.event.card_red', shortKey: 'scoring.event_short.card_red', color: 'error', shortcut: 'factRed' },
  { code: 'D', labelKey: 'scoring.event.card_black', shortKey: 'scoring.event_short.card_black', color: 'neutral', shortcut: 'factBlack' }
]
// Card reasons (motifs) reused from FMV3 — '' = none, 'unknown' pre-selected (spec §0.9)
const reasonCodes = ['', 'r_pad', 'r_kt', 'r_ht', 'r_p', 'r_o', 'r_un', 'r_rep', 'unknown']
// Reka UI's <SelectItem> refuses an empty-string value (it reserves '' to mean "cleared",
// and throws during setup — which aborted the whole page update). '' stays the stored
// value for "no reason": the sentinel below never leaves this component.
const NO_REASON = '__none__'
const reasonItems = computed(() => reasonCodes.map(c => ({
  label: c === '' ? t('scoring.reason.none') : t('scoring.reason.' + c),
  value: c === '' ? NO_REASON : c
})))

/** Human label of a period — P{n} share one parameterized i18n key (unbounded, spec §0.6). */
const periodLabel = (p: Period): string => {
  const n = overtimeIndex(p)
  return n !== null ? t('scoring.period.overtime', { n }) : t('scoring.period.' + p)
}

// Selected player for the next event
const selected = ref<{ team: TeamSide; player: ScoringPlayer } | null>(null)

const match = computed(() => scoringStore.match)
const loading = computed(() => scoringStore.loading)

// Level score conditions the E-type advance into (further) overtime (golden goal, §7.5).
const scoreLevel = computed(() => scoringStore.scoreA === scoringStore.scoreB)

// Periods offered in the event entry select: M1/M2, overtimes up to the furthest one
// in use +1 (type E — unbounded), TB when enabled or already used (post-match edits).
const periodItems = computed(() => {
  const list: Period[] = ['M1', 'M2']
  if (match.value?.type === 'E') {
    const fromEvents = scoringStore.events.reduce((m, e) => Math.max(m, overtimeIndex(e.period) ?? 0), 0)
    const maxN = Math.max(overtimeIndex(match.value?.periode ?? 'M1') ?? 0, fromEvents) + 1
    for (let n = 1; n <= maxN; n++) list.push(`P${n}`)
  }
  if (scoringStore.config.shootoutEnabled || match.value?.periode === 'TB'
    || scoringStore.events.some(e => e.period === 'TB')) list.push('TB')
  return list.map(p => ({ label: periodLabel(p), value: p }))
})

// ─── Direct vs post-match mode (spec §1.1) ───
// In post-match the live clock (run/stop/RAZ + current time) is hidden; only the per-event
// time field stays editable. Pre-positioned from match status (END → post-match).
type ScoringMode = 'live' | 'post'
const mode = ref<ScoringMode>('live')
const isLive = computed(() => mode.value === 'live')
// Clocks and periods only move once the match is under way (kick-off given, not closed).
const matchOn = computed(() => match.value?.statut === 'ON')
// Game facts are created, edited and deleted only while the match is in progress: before
// kick-off nothing has happened yet, and a closed match is corrected by reopening it
// (« Rouvrir le match… » → correct → « Clore le match… »), which re-consolidates kp_*.
const canEditFacts = computed(() => canScore.value && matchOn.value)

// ─── Two views: "Paramètres" (before kick-off) / "Déroulement" (spec §7.1) ───
const view = ref<'run' | 'settings'>('run')

// Officials edited in « Paramètres » live in that view's form until saved: leaving the view
// would drop them silently, so the switch asks first (the operator stays, then saves).
const officialsDirty = ref(false)
const leaveSettingsOpen = ref(false)
const switchView = (v: 'run' | 'settings') => {
  if (v === view.value) return
  if (view.value === 'settings' && officialsDirty.value) {
    leaveSettingsOpen.value = true
    return
  }
  view.value = v
}
const confirmLeaveSettings = () => {
  leaveSettingsOpen.value = false
  officialsDirty.value = false
  view.value = 'run'
}

// ─── Score-only mode (spec §3, plan §4.1 source SCORE_ONLY) ───
// Some tables only follow the score, without attributing goals to players (no roster, or
// no time for it). The console then shows score/period/status alone: same single entry
// door, same live state — only the level of detail changes. Goals recorded this way carry
// no player, so the history stays consistent with what was really captured.
const scoreOnly = ref(false)
watch(scoreOnly, (on) => {
  // Declare the active source so another writer (hardware relay) knows what is going on.
  void scoringStore.setSource(on ? 'SCORE_ONLY' : 'MANUAL')
})

// ─── Console mode menu: live / post-match / score-only in a single dropdown ───
// Score-only is a live capture (spec §3), so picking it also switches to live.
type ConsoleMode = 'live' | 'post' | 'scoreOnly'
const MODE_ICON: Record<ConsoleMode, string> = {
  live: 'i-heroicons-signal',
  post: 'i-heroicons-clipboard-document-check',
  scoreOnly: 'i-heroicons-hashtag'
}
const consoleMode = computed<ConsoleMode>(() => (scoreOnly.value ? 'scoreOnly' : mode.value))
const modeLabel = (m: ConsoleMode): string =>
  m === 'scoreOnly' ? t('scoring.score_only.label') : t('scoring.mode.' + m)
const setConsoleMode = (m: ConsoleMode) => {
  scoreOnly.value = m === 'scoreOnly'
  mode.value = m === 'post' ? 'post' : 'live'
}
const modeItems = computed(() => (['live', 'post', 'scoreOnly'] as ConsoleMode[]).map(m => ({
  type: 'checkbox' as const,
  label: modeLabel(m),
  icon: MODE_ICON[m],
  checked: m === consoleMode.value,
  disabled: m === 'scoreOnly' && !canScore.value,
  onSelect: () => setConsoleMode(m)
})))

/** Score-only goal: +1/−1 for a team, recorded as a team-level fact (player '0'). */
const bumpScore = async (team: TeamSide, delta: 1 | -1) => {
  if (!canEditFacts.value || !match.value || !acceptClick(`score${team}${delta}`)) return
  if (delta === 1) {
    try {
      await scoringStore.addEvent({
        code: 'B',
        period: eventPeriod.value,
        tpsJeu: eventTime.value,
        team,
        player: '0', // team-level fact — no player attribution in this mode
        number: null,
        reason: ''
      })
    } catch { /* toast handled */ }
    return
  }
  // −1 removes the most recent team-level goal of that team (correction of a mis-click).
  const last = [...scoringStore.events].reverse().find(e => e.code === 'B' && e.team === team)
  if (last) {
    try {
      await scoringStore.removeEvent(last)
    } catch { /* toast handled */ }
  }
}

// ─── Event time/reason entry (period + MM:SS) ───
// In live mode it is pre-filled from the clock; in post-match it is typed/edited by hand.
const eventPeriod = ref<Period>('M1')
const eventTime = ref('00:00')
const eventReason = ref('')
// Bridge the sentinel <-> '' so the select never sees an empty value while the rest of the
// page (and the API) keeps working with '' for "no reason".
const eventReasonSelect = computed({
  get: () => eventReason.value === '' ? NO_REASON : eventReason.value,
  set: (v: string) => { eventReason.value = v === NO_REASON ? '' : v }
})
// When set, the event buttons commit an UPDATE of this existing event instead of a new one.
const editingUid = ref<string | null>(null)

/**
 * Tab title: the operator often has several consoles open at once (one per pitch), so the
 * tab must say WHICH match it is without switching to it. Match **number** (Numero_ordre,
 * the one printed on the schedule) and pitch — never the internal id, which means nothing
 * to the scoring table. Falls back to the short id while the match is still loading.
 */
const pageTitle = computed(() => {
  const m = match.value
  if (!m) return t('scoring.title')
  const parts = [
    m.numeroOrdre ? `${t('games.field.game_number')}${m.numeroOrdre}` : `#${m.id}`,
    m.terrain ? `${t('scoring.pitch')} ${m.terrain}` : null,
    m.equipeA && m.equipeB ? `${m.equipeA} - ${m.equipeB}` : null
  ].filter(Boolean)
  return `${t('scoring.title')} — ${parts.join(' · ')}`
})
useHead({ title: pageTitle })

// ─── Buzzer (shotclock expiry, period end, end of break — decision §0.9) ───
const buzzer = useBuzzer()

// ─── Game clock (timestamp-based, like the shotclock and the penalties) ───
const { display: clockDisplay, gameTime, elapsed, elapsedPrecise, isRunning, maxTime: timerMaxTime, setPeriod: timerSetPeriod, start: timerStart, stop: timerStop, reset: timerReset, restoreFromServer } =
  useTimer({
    onTargetReached: () => {
      // Buzzer at period end; persist the stop server-side; then the break, if any.
      buzzer.beep()
      void scoringStore.setTimer('stop', { startTime: elapsedPrecise.value, runTime: 0, maxTime: scoringStore.currentPeriodDuration })
      startBreakAfterPeriod()
    }
  })

// The period this one leads to (spec §7.5): M1 → M2 always; M2 → P1 and P{n} → P{n+1} only
// for an elimination match still level. Null: nothing follows (the match can be closed).
const nextPeriod = computed<Period | null>(() => {
  const m = match.value
  if (!m?.periode) return null
  return nextPeriodFor(m.type, m.periode as Period, scoreLevel.value)
})

/**
 * Indicative break before the next period (spec §7.5): 3' at half-time, 3' before the first
 * overtime, 1' before each further one — only when a next period exists (an elimination
 * match goes to overtime only if level). Run when the game clock reaches 0:00, whether by
 * itself or brought there by the fine adjust (a clock corrected to zero = period over).
 */
const startBreakAfterPeriod = () => {
  if (!isLive.value || !nextPeriod.value) return
  const dur = breakDurationBefore(nextPeriod.value, scoringStore.config.breakDurations)
  if (dur) startBreak(dur)
}

// Game clock at 0:00 (stopped): the period is over — the period selector offers the next one.
const clockAtZero = computed(() => !isRunning.value && elapsed.value >= timerMaxTime.value)

// ─── Click debounce: a press held too long or too hard counts once ───
// Two tolerances: a start/stop toggle or a score key must count once per press (a double
// trigger would undo it), whereas ±1/±10 keys are meant to be clicked in quick bursts.
const TOGGLE_DEBOUNCE_MS = 250
const ADJUST_DEBOUNCE_MS = 40
const acceptClick = createClickGuard(TOGGLE_DEBOUNCE_MS)
const acceptAdjust = createClickGuard(ADJUST_DEBOUNCE_MS)

// ─── Adjustment keys (±1/±10 s): persisted once per burst ───
// Operators click them in bursts (+1 +1 +1). Each click applies locally at once, but only
// the final value is written, a moment after the last click: one write per burst means no
// out-of-order writes, and no echo of an intermediate value bouncing the display back.
const ADJUST_PERSIST_DELAY_MS = 500
const adjustTimers = new Map<string, ReturnType<typeof setTimeout>>()
const persistAfterBurst = (key: string, persist: () => void) => {
  // Mark the local change now: remote-change echoes are ignored while the burst lasts.
  scoringStore.lastMutationAt = Date.now()
  const pending = adjustTimers.get(key)
  if (pending) clearTimeout(pending)
  adjustTimers.set(key, setTimeout(() => {
    adjustTimers.delete(key)
    persist()
  }, ADJUST_PERSIST_DELAY_MS))
}
onUnmounted(() => {
  for (const pending of adjustTimers.values()) clearTimeout(pending)
})

// ─── Shotclock (chronomètre de tir) — 3 commands, auto-follows the game clock (§6.5) ───
const shotclock = useShotclock({ onExpired: () => buzzer.beep() })

const persistShotclock = (action: 'run' | 'stop' | 'RAZ') => {
  void scoringStore.setTimer(action, {
    kind: 'SHOTCLOCK',
    startTime: action === 'RAZ' ? 0 : shotclock.elapsedSeconds.value,
    runTime: 0,
    maxTime: shotclock.initSeconds.value
  })
}
const shotclockStart = (seconds: number) => {
  if (!canScore.value || !isLive.value || !matchOn.value) return
  shotclock.start(seconds)
  // The shotclock follows the game clock (§6.5): loading 60/40 while the game clock is
  // stopped must NOT make it count down — it waits, suspended, until the game restarts.
  // The auto-follow watcher below only sees game-clock *transitions*, so a start issued
  // while already stopped has to be suspended here.
  if (!isRunning.value) {
    shotclock.suspend()
    persistShotclock('stop')
    return
  }
  persistShotclock('run')
}
const shotclockStop = () => {
  if (!canScore.value || shotclock.state.value === 'IDLE') return
  shotclock.stopToIdle()
  persistShotclock('RAZ')
}
const shotclockAdjust = (delta: number) => {
  if (!canScore.value || shotclock.state.value !== 'SUSPENDED' || !matchOn.value || !acceptAdjust(`shotclock${delta}`)) return
  shotclock.adjust(delta)
  // Written with the state at the end of the burst (60/40 may have been pressed meanwhile).
  persistAfterBurst('shotclock', () => {
    const st = shotclock.state.value
    persistShotclock(st === 'RUNNING' ? 'run' : st === 'IDLE' ? 'RAZ' : 'stop')
  })
}
// ─── Penalties (spec §7.4 — rules corrected 2026-07-29) ───
// V/J/R start a 2-min clock; D is a dry definitive exclusion (no clock, no replacement).
// V/J lift early on a conceded goal (player returns); R always runs its full 2 minutes
// (replacement at the end only, even if goals are conceded meanwhile).
const penalties = usePenalties({
  onExpired: (p) => {
    buzzer.beep(400)
    void scoringStore.setTimer('RAZ', { kind: 'PENALTY', team: p.team, slot: p.slot })
    toast.add({
      title: t('scoring.penalty.expired_title'),
      description: t(
        p.cardCode === 'R' ? 'scoring.penalty.expired_replace' : 'scoring.penalty.expired_return',
        { number: p.playerNumber ?? '?' }
      ),
      color: 'info'
    })
  }
})

const persistPenalty = (p: PenaltyClock, action: 'run' | 'stop') => {
  void scoringStore.setTimer(action, {
    kind: 'PENALTY',
    team: p.team,
    slot: p.slot,
    playerId: p.playerId,
    cardCode: p.cardCode,
    startTime: Math.max(0, scoringStore.config.penaltyDuration - p.remainingMs / 1000),
    runTime: 0,
    maxTime: scoringStore.config.penaltyDuration
  })
}
const persistAllPenalties = () => {
  for (const p of penalties.penalties.value) persistPenalty(p, p.running ? 'run' : 'stop')
}

/**
 * Correct a running penalty's countdown (click on its chip — spec §7.4). Game clock stopped
 * only, like the main clock's fine adjust; persisted once per burst of clicks.
 */
const adjustPenalty = (id: string, deltaSec: number) => {
  if (!canScore.value || isRunning.value || !acceptAdjust(`penalty${id}${deltaSec}`)) return
  const clock = penalties.adjust(id, deltaSec * 1000, scoringStore.config.penaltyDuration * 1000)
  if (clock) persistAfterBurst(`penalty${id}`, () => persistPenalty(clock, clock.running ? 'run' : 'stop'))
}

const removePenalty = (id: string) => {
  if (!canScore.value) return
  const p = penalties.remove(id)
  if (p) void scoringStore.setTimer('RAZ', { kind: 'PENALTY', team: p.team, slot: p.slot })
}

// Conceded-goal early lift — asked to the operator (spec §7.4: after confirmation).
const liftPending = ref<PenaltyClock | null>(null)
const confirmLift = () => {
  const p = liftPending.value
  liftPending.value = null
  if (!p) return
  penalties.remove(p.id)
  void scoringStore.setTimer('RAZ', { kind: 'PENALTY', team: p.team, slot: p.slot })
}

// Excluded-player markers in the rosters (R = replacement at penalty end, D = out for the
// whole match, team short-handed to the end) — drawn as their card.
const excludedCard = computed(() => {
  const m = new Map<string, 'R' | 'D'>()
  for (const e of scoringStore.events) {
    if (e.code === 'D') m.set(e.player, 'D')
    else if (e.code === 'R' && m.get(e.player) !== 'D') m.set(e.player, 'R')
  }
  return m
})

// Auto-follow: game clock stop ⇒ suspension, restart ⇒ resume — the ONLY pause (§0.9).
// Penalty countdowns follow the game clock the same way.
watch(isRunning, (running) => {
  if (running) {
    if (shotclock.state.value === 'SUSPENDED') {
      shotclock.resume()
      persistShotclock('run')
    }
    penalties.resumeAll()
  } else {
    if (shotclock.state.value === 'RUNNING') {
      shotclock.suspend()
      persistShotclock('stop')
    }
    penalties.suspendAll()
  }
  persistAllPenalties()
})
// Legacy shotClockShow rule: masked when the game time remaining is below the shotclock.
const gameRemaining = computed(() => Math.max(0, scoringStore.currentPeriodDuration - elapsed.value))
const shotclockMasked = computed(() =>
  shotclock.state.value !== 'IDLE' && gameRemaining.value < shotclock.remainingMs.value / 1000
)

// ─── Inter-period break — indicative countdown, buzzer at its end (spec §7.5/§0.9) ───
const breakRemaining = ref<number | null>(null)
let breakInterval: ReturnType<typeof setInterval> | null = null
const stopBreak = (persist = true) => {
  if (breakInterval) {
    clearInterval(breakInterval)
    breakInterval = null
  }
  if (breakRemaining.value !== null && persist) void scoringStore.setTimer('RAZ', { kind: 'BREAK' })
  breakRemaining.value = null
}
const startBreak = (seconds: number, persist = true) => {
  stopBreak(false)
  breakRemaining.value = seconds
  if (persist) void scoringStore.setTimer('run', { kind: 'BREAK', startTime: 0, runTime: 0, maxTime: seconds })
  breakInterval = setInterval(() => {
    if (breakRemaining.value === null) return
    breakRemaining.value = Math.max(0, breakRemaining.value - 1)
    if (breakRemaining.value <= 0) {
      buzzer.beep() // end of break = resume signal
      stopBreak()
    }
  }, 1000)
}
const breakDisplay = computed(() => {
  const s = breakRemaining.value ?? 0
  return `${String(Math.floor(s / 60)).padStart(2, '0')}:${String(s % 60).padStart(2, '0')}`
})
onUnmounted(() => stopBreak(false))

// ─── Local broadcast to the full-screen displays (spec §6.5 — same origin, no network) ───
const broadcast = useScoringBroadcast(() => ({
  matchId: matchId.value,
  teamA: match.value?.equipeA ?? '',
  teamB: match.value?.equipeB ?? '',
  scoreA: scoringStore.scoreA,
  scoreB: scoringStore.scoreB,
  period: match.value?.periode ?? null,
  timer: clockDisplay.value, // same format as the console ("9:52", tenths under 10 s)
  timerRunning: isRunning.value,
  shotclock: shotclockMasked.value ? '--' : shotclock.display.value,
  shotclockState: shotclock.state.value,
  penalties: penalties.penalties.value
}))

// Push each change as it happens (cheap: BroadcastChannel is in-process messaging).
watch(clockDisplay, () => broadcast.timer())
watch(isRunning, () => broadcast.timerStatus())
watch(() => shotclock.display.value, () => broadcast.shotclock())
watch(() => shotclock.state.value, () => broadcast.shotclock())
watch(() => [scoringStore.scoreA, scoringStore.scoreB], () => broadcast.scores())
watch(() => match.value?.periode, () => broadcast.period())
watch(() => penalties.penalties.value.map(p => `${p.slot}${p.team}${Math.ceil(p.remainingMs / 1000)}${p.running}`).join(),
  () => broadcast.penalties())

// ─── Configurable keyboard shortcuts (spec §6.5, decision §0.9) ───
const shortcutsOpen = ref(false)

// ─── Closure (spec §7.6) and reopening — never a single tap: both go through a dialog ───
const closeOpen = ref(false)
const closing = ref(false)
const reopenOpen = ref(false)
const goals = computed(() => goalCounts(scoringStore.events))
// Only a match in progress can be closed (a pending one has not been played).
const openClose = () => {
  if (canScore.value && matchOn.value) closeOpen.value = true
}
const confirmClose = async (closure: MatchClosure) => {
  if (!canScore.value || closing.value) return
  closing.value = true
  try {
    // Freeze every live clock first: the match is over, nothing may keep counting.
    if (isLive.value && isRunning.value) timer('stop')
    if (shotclock.state.value !== 'IDLE') shotclockStop()
    stopBreak()
    await scoringStore.closeMatch(closure)
    closeOpen.value = false
    mode.value = 'post'
    toast.add({ title: t('scoring.close.done'), description: t('scoring.close.done_description'), color: 'success' })
  } catch { /* useApi already toasts */ } finally {
    closing.value = false
  }
}
const alignScore = async () => {
  try { await scoringStore.alignScoreOnGoals() } catch { /* useApi already toasts */ }
}
const confirmReopen = async () => {
  reopenOpen.value = false
  try {
    await scoringStore.reopenMatch()
    toast.add({ title: t('scoring.reopen.done'), color: 'info' })
  } catch { /* useApi already toasts */ }
}

// ─── Pre-kick-off checks of the game sheet (spec §7.2) — warnings, never a block ───
const readiness = computed(() => match.value
  ? matchReadiness({ playersA: scoringStore.playersA, playersB: scoringStore.playersB, officials: match.value })
  : [])

// ─── Roster markers: goals and current card of each player (spec §7.3) ───
const tallies = computed(() => playerTallies(scoringStore.events))

// ─── History: the latest facts during play, everything post-match (spec §7.1) ───
const HISTORY_RECENT_COUNT = 3

// ─── Connection loss: say it in words, not only with the sync badge colour ───
const online = ref(true)
const updateOnline = () => { online.value = navigator.onLine }
onMounted(() => {
  updateOnline()
  window.addEventListener('online', updateOnline)
  window.addEventListener('offline', updateOnline)
})
onUnmounted(() => {
  window.removeEventListener('online', updateOnline)
  window.removeEventListener('offline', updateOnline)
})
const connectionLost = computed(() => isLive.value && (!online.value || liveSync.lost.value))

// Occasional tools, grouped in the header's ⋯ menu: full-screen displays (live only — same
// origin, BroadcastChannel keeps them in sync) and the keyboard shortcuts.
const toolItems = computed(() => [
  ...(isLive.value
    ? [
        { label: t('scoring.display.scoreboard'), icon: 'i-heroicons-tv', onSelect: () => broadcast.openScoreboard(matchId.value) },
        { label: t('scoring.display.shotclock'), icon: 'i-heroicons-clock', onSelect: () => broadcast.openShotclock(matchId.value) }
      ]
    : []),
  { label: t('scoring.shortcuts.title'), icon: 'i-heroicons-command-line', onSelect: () => { shortcutsOpen.value = true } }
])
// Off while a dialog or the card-reason list owns the keyboard (keys there pick a reason
// — they must not also drive the clocks). Clock keys: live mode, match in
// progress only. Fact keys: Déroulement view with the detailed entry (live and post-match).
const shortcutsEnabled = computed(() => canScore.value && !shortcutsOpen.value
  && !closeOpen.value && !reopenOpen.value && !reasonOpen.value)
const clockKey = (handler: () => void) => () => { if (isLive.value && matchOn.value) handler() }
const factKey = (code: ScoringEventCode) => () => {
  if (view.value === 'run' && !scoreOnly.value) void stageEvent(code)
}
const { bindings, setBinding, resetDefaults } = useScoringShortcuts({
  gameClockToggle: clockKey(() => toggleClock()),
  shotclockStart60: clockKey(() => shotclockStart(scoringStore.config.shotclockDurations.full)),
  shotclockStart40: clockKey(() => {
    if (scoringStore.config.shotclockOffensiveReboundEnabled) {
      shotclockStart(scoringStore.config.shotclockDurations.offensiveRebound)
    }
  }),
  shotclockStop: clockKey(shotclockStop),
  factGoal: factKey('B'),
  factGreen: factKey('V'),
  factYellow: factKey('J'),
  factRed: factKey('R'),
  factBlack: factKey('D')
}, { enabled: shortcutsEnabled, locale })

// The fact time follows the clock until an entry starts: the moment a fact or a player is
// picked, it freezes — on some matches the clock keeps running after a goal, and the time
// to record is the moment of the fact, not of the click on "Valider". Deselecting resumes.
watch(gameTime, (v) => {
  if (isLive.value && !entryActive.value) eventTime.value = v
})

// ─── Live coherence between terminals (Mercure/SSE — plan lot 3) ───
// A change made on another terminal (or by the hardware relay) is signalled on the
// match's topics; the console refetches the canonical state instead of merging a diff.
const resyncFromServer = async (): Promise<boolean> => {
  try {
    await scoringStore.refreshLiveState()
    await applyServerClocks()
    broadcast.broadcastAll()
    return true
  } catch { /* useApi already toasts */ return false }
}
const liveSync = useScoringLiveSync({
  lastLocalWrite: () => scoringStore.lastMutationAt,
  onRemoteChange: async () => {
    if (await resyncFromServer()) flashRemoteSync()
  },
  // Back after a cut: re-read silently (nothing tells us something changed elsewhere).
  onReconnect: async () => { await resyncFromServer() },
})
// Brief visual feedback when the state was changed elsewhere: the operator must notice
// that the score/clock moved without them touching anything (failover, hardware source).
const remoteSynced = ref(false)
let remoteSyncTimeout: ReturnType<typeof setTimeout> | null = null
const flashRemoteSync = () => {
  remoteSynced.value = true
  if (remoteSyncTimeout) clearTimeout(remoteSyncTimeout)
  remoteSyncTimeout = setTimeout(() => { remoteSynced.value = false }, 2000)
}
onUnmounted(() => { if (remoteSyncTimeout) clearTimeout(remoteSyncTimeout) })

/**
 * Re-prime the local clocks from the canonical state — used on load AND when a remote
 * change is signalled. Everything is derived from the server, so a second terminal (or a
 * reload) always lands on the same displayed time (plan §3.1).
 *
 * The game clock goes through GET /gameTimer, which returns the server time alongside the
 * state: that is the only path that compensates the elapsed drift exactly (and handles
 * midnight). The shot clock and the break are re-primed from their persisted value; a
 * sub-second drift is possible right after a takeover, which is irrelevant at their scale.
 */
const applyServerClocks = async () => {
  if (!isLive.value) return

  const state = await scoringStore.loadTimerState()
  if (state && state.action) {
    restoreFromServer({
      action: state.action,
      maxTime: state.maxTime || scoringStore.currentPeriodDuration,
      elapsed: state.startTime ?? 0,
      startTimeServer: state.startTimeServer ?? undefined,
      nowServer: state.nowServer
    })
  } else {
    timerSetPeriod(scoringStore.currentPeriodDuration)
  }

  const sc = scoringStore.liveClocks.find(c => c.kind === 'SHOTCLOCK')
  if (sc) {
    shotclock.restore({ initMs: sc.initMs, elapsedMs: sc.elapsedMs, running: sc.running })
    // A shotclock can only run while the game clock runs: align after restore.
    if (!isRunning.value && shotclock.state.value === 'RUNNING') shotclock.suspend()
  } else if (shotclock.state.value !== 'IDLE') {
    shotclock.stopToIdle()
  }

  const br = scoringStore.liveClocks.find(c => c.kind === 'BREAK')
  const left = br ? Math.max(0, Math.round((br.initMs - br.elapsedMs) / 1000)) : 0
  if (left > 0) startBreak(left, false)
  else stopBreak(false)
}

onMounted(async () => {
  if (!canView.value) return
  try {
    await scoringStore.load(matchId.value)
    await loadEmptyCompositions()
    // Default mode from status, period field from the match's current period.
    mode.value = scoringStore.match?.statut === 'END' ? 'post' : 'live'
    // A pending match opens on « Paramètres »: officials and compositions are what the
    // scoring table checks before kick-off (spec §7.1).
    view.value = scoringStore.match?.statut === 'ATT' ? 'settings' : 'run'
    eventPeriod.value = (scoringStore.match?.periode ?? 'M1') as Period
    // Card reason pre-selected for fast entry (spec §0.9 — Autre/Non précisé)
    eventReason.value = scoringStore.config.defaultCardReason
    // Restore every clock from the canonical server state (chrono, shotclock, break).
    if (isLive.value) await applyServerClocks()
    else timerSetPeriod(scoringStore.currentPeriodDuration)

    const penClocks = scoringStore.liveClocks.filter(c => c.kind === 'PENALTY')
    if (penClocks.length && isLive.value) {
      penalties.restore(
        penClocks.map(c => ({
          id: c.id,
          team: c.team as TeamSide,
          slot: c.slot,
          playerId: c.playerId,
          cardCode: c.cardCode,
          initMs: c.initMs,
          elapsedMs: c.elapsedMs,
          running: c.running
        })),
        isRunning.value
      )
      // Enrich the jersey numbers from the rosters (not persisted on the clock).
      for (const p of penalties.penalties.value) {
        const roster = p.team === 'A' ? scoringStore.playersA : scoringStore.playersB
        p.playerNumber = roster.find(pl => String(pl.matric) === p.playerId)?.numero ?? null
      }
    }
    // Local channel for the scoreboard / shotclock windows, then a first full snapshot.
    broadcast.init()
    broadcast.broadcastAll()

    // Subscribe to this match's topics: another terminal (or the hardware relay) taking
    // over is reflected here without a reload.
    liveSync.connect(scoringStore.mercureUrl, scoringStore.topicBase)
  } catch {
    // useApi already shows a toast
  }
})

/**
 * An empty composition is filled from the presence sheet straight away (spec §7.2): an
 * empty game sheet is never what the scoring table wants, and reloading by hand was the
 * first gesture of every match. Unlocked match only; a team without presence stays empty.
 */
const loadEmptyCompositions = async () => {
  if (!canScore.value || scoringStore.isLocked) return
  for (const team of ['A', 'B'] as TeamSide[]) {
    const roster = team === 'A' ? scoringStore.playersA : scoringStore.playersB
    if (roster.length) continue
    try {
      await scoringStore.reloadPresentPlayers(team)
      const loaded = team === 'A' ? scoringStore.playersA : scoringStore.playersB
      if (loaded.length) {
        toast.add({
          title: t('scoring.settings.compo_loaded', { team: (team === 'A' ? match.value?.equipeA : match.value?.equipeB) ?? team }),
          color: 'info'
        })
      }
    } catch { /* useApi already toasts */ }
  }
}

/** Pick the player of the entry; once the fact is known too, the time gets the focus. */
const selectPlayer = (team: TeamSide, player: ScoringPlayer) => {
  if (!canEditFacts.value) return
  selected.value = { team, player }
  playerQuery.value = ''
  playerQueryTeam.value = null
  if (pendingCode.value) void focusTimeField()
}

// Mobile: both rosters side by side, names truncated; tapping a team name widens that side
// for a few seconds, then both columns share the width again.
const WIDE_TEAM_MS = 5000
const wideTeam = ref<TeamSide | null>(null)
let wideTeamTimer: ReturnType<typeof setTimeout> | null = null
const widenTeam = (team: TeamSide) => {
  wideTeam.value = team
  if (wideTeamTimer) clearTimeout(wideTeamTimer)
  wideTeamTimer = setTimeout(() => { wideTeam.value = null }, WIDE_TEAM_MS)
}
onUnmounted(() => { if (wideTeamTimer) clearTimeout(wideTeamTimer) })

/** Who the next fact goes to, as shown in the entry panel. */
const selectedLabel = computed(() => {
  const p = selected.value?.player
  if (!p) return ''
  if (isTeamFact(p)) return t('scoring.team_fact')
  return `${p.capitaine === 'E' ? t('scoring.settings.status_E') : '#' + p.numero} ${p.nom.toUpperCase()} ${p.prenom}`
})

// Team-level fact (player '0', as in score-only mode): the only way to record anything
// when a team has no player on the game sheet — otherwise every fact button stays disabled.
const TEAM_FACT_MATRIC = 0
const isTeamFact = (player: ScoringPlayer) => player.matric === TEAM_FACT_MATRIC
const selectTeamFact = (team: TeamSide) => selectPlayer(team, {
  matric: TEAM_FACT_MATRIC, nom: '', prenom: '', numero: 0, capitaine: '-', team
})

// Card-progression warning modal (spec §7.4: alert, the operator stays sovereign) and
// golden-goal closing proposal (spec §7.5).
const cardWarning = ref<{ code: ScoringEventCode; violation: string } | null>(null)
const goldenGoalOpen = ref(false)

/**
 * Fact entry (spec §7.3, order revised 2026-11-06 — §0.12). The fact and the player are
 * picked in ANY order with the mouse; on the keyboard the fact comes first (its letter),
 * then the player's number, since digits are also shot-clock shortcuts outside a field.
 * Nothing is recorded before an explicit validation of the time (`Entrée` or the button):
 * the fact almost always happened a few seconds before it is entered (legacy workflow,
 * restored 2026-07-31). `Échap` or "Désélectionner" drops the whole entry.
 *
 *   fact key ──► (card: reason list opens) ──► player (click, or number + ←/→ + Entrée)
 *       └─────────── or the player first ──────────┘ ──► time (focused) ──► Entrée
 */
const pendingCode = ref<ScoringEventCode | null>(null)
// The reason only applies to cards: its list opens as soon as a card is armed.
const pendingIsCard = computed(() => pendingCode.value !== null && pendingCode.value !== 'B')
const reasonOpen = ref(false)
const timeInput = ref<{ $el?: HTMLElement } | HTMLElement | null>(null)
const playerInput = ref<{ $el?: HTMLElement } | null>(null)

/** An entry is in progress: the fact time stops following the clock (see watch(gameTime)). */
const entryActive = computed(() => pendingCode.value !== null || selected.value !== null || editingUid.value !== null)

// ─── Player by number (keyboard): digits, ←/→ to choose the team when both have it ───
const playerQuery = ref('')
const playerQueryTeam = ref<TeamSide | null>(null)
const playerCandidates = computed(() => playerQuery.value.trim()
  ? findPlayersByNumber(`${playerQueryTeam.value ?? ''}${playerQuery.value}`, scoringStore.playersA, scoringStore.playersB)
  : [])
/** Matrics to highlight in each roster while a number is being typed. */
const candidateMatrics = computed(() => new Set(playerCandidates.value.map(p => p.matric)))
const onPlayerQueryKey = (event: KeyboardEvent) => {
  if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
    event.preventDefault()
    playerQueryTeam.value = event.key === 'ArrowLeft' ? 'A' : 'B'
  } else if (event.key === 'Enter') {
    event.preventDefault()
    const only = playerCandidates.value.length === 1 ? playerCandidates.value[0] : null
    if (only) selectPlayer(only.team, only)
  } else if (event.key === 'Escape') {
    event.preventDefault()
    clearEntry()
  }
}
watch(playerQuery, () => { playerQueryTeam.value = null })

const inputOf = (el: { $el?: HTMLElement } | HTMLElement | null): HTMLInputElement | null => {
  const root = (el as { $el?: HTMLElement })?.$el ?? (el as HTMLElement | null)
  return root?.tagName === 'INPUT' ? root as HTMLInputElement : root?.querySelector('input') ?? null
}

/** Focus + select the time field so typing overwrites it right away. */
const focusTimeField = async () => {
  await nextTick()
  const input = inputOf(timeInput.value)
  input?.focus()
  input?.select()
}

/**
 * Focus the player-number field — only with a fine pointer (keyboard + mouse): on a tablet,
 * focusing it would pop the on-screen keyboard over the rosters the operator taps.
 */
const focusPlayerField = async () => {
  if (!window.matchMedia('(pointer: fine)').matches) return
  await nextTick()
  inputOf(playerInput.value)?.focus()
}

/** Next step of the entry once the fact (and its reason, for a card) is set. */
const advanceEntry = () => {
  if (selected.value || editingUid.value) void focusTimeField()
  else void focusPlayerField()
}

/** Arm a fact (button or letter key). A card opens its reason list first. */
const stageEvent = async (code: ScoringEventCode) => {
  if (!canEditFacts.value || !match.value) return
  pendingCode.value = code
  if (code !== 'B') reasonOpen.value = true
  else advanceEntry()
}

/** The reason list closed (picked, or Échap to keep the default): carry on with the entry. */
const onReasonOpen = (open: boolean) => {
  reasonOpen.value = open
  // After the list has handed the focus back to its trigger.
  if (!open && pendingCode.value) setTimeout(advanceEntry, 0)
}

/** Drop the whole entry (button "Désélectionner", `Échap`): the time follows the clock again. */
const clearEntry = () => {
  pendingCode.value = null
  selected.value = null
  editingUid.value = null
  reasonOpen.value = false
  playerQuery.value = ''
  eventReason.value = scoringStore.config.defaultCardReason
  if (isLive.value) {
    eventTime.value = gameTime.value
    eventPeriod.value = (match.value?.periode ?? eventPeriod.value) as Period
  }
}

watch(matchOn, (on) => { if (!on) clearEntry() })

// Deleting the fact being edited (entry panel): confirmed, a goal changes the score.
const deleteEditedOpen = ref(false)
const confirmDeleteEdited = async () => {
  deleteEditedOpen.value = false
  const edited = scoringStore.events.find(e => e.uid === editingUid.value)
  if (edited) await removeEvent(edited)
}

/** Validate the entry (`Entrée` in the time field, or the confirm button). */
const validatePending = async () => {
  const code = pendingCode.value
  if (!code) return
  if (!selected.value && !editingUid.value) {
    toast.add({ title: t('scoring.entry.player_missing'), color: 'warning' })
    await focusPlayerField()
    return
  }
  pendingCode.value = null
  await commitEvent(code)
}

/** Commit the event buttons: add a new event, or update the one being edited. */
const commitEvent = async (code: ScoringEventCode) => {
  if (!canEditFacts.value || !match.value) return
  // `Entrée` validates without blurring: format the digits typed by the operator here too.
  normalizeEventTime()

  if (editingUid.value) {
    // Editing an existing row: keep its team/player, change code/time/period/reason.
    try {
      await scoringStore.updateEvent(editingUid.value, {
        code,
        period: eventPeriod.value,
        tpsJeu: eventTime.value,
        reason: code === 'B' ? '' : eventReason.value
      })
      clearEntry()
    } catch { /* toast handled */ }
    return
  }

  if (!selected.value) {
    toast.add({ title: t('scoring.entry.player_missing'), color: 'warning' })
    return
  }

  // Card progression rule (spec §7.4, 2027): warn when the new card is identical/lower
  // than the player's previous one, or when the player is already out (R/D). The modal
  // lets the operator record it anyway (paper sheet stays the referee's authority).
  if (code !== 'B' && !isTeamFact(selected.value.player)) {
    const previous = scoringStore.events
      .filter(e => e.code !== 'B' && e.player === String(selected.value?.player.matric))
      .map(e => e.code)
    const verdict = validateCardProgression(previous, code)
    if (verdict !== true) {
      cardWarning.value = { code, violation: verdict }
      return
    }
  }

  await pushEvent(code)
}

/** Actually record the event (after the progression check or its override). */
const pushEvent = async (code: ScoringEventCode) => {
  if (!selected.value || !match.value) return
  const { team, player } = selected.value
  const event: ScoringEvent = {
    code,
    period: eventPeriod.value,
    tpsJeu: eventTime.value,
    team,
    player: String(player.matric),
    number: isTeamFact(player) ? null : player.numero,
    reason: code === 'B' ? '' : eventReason.value,
    nom: player.nom,
    prenom: player.prenom
  }
  // Captured before the selection is cleared: the undo toast names who and when.
  const recordedLabel = `${t(eventCodes.find(e => e.code === code)!.shortKey)} · ${selectedLabel.value} · ${periodDisplayCode(event.period, t('scoring.period.overtime_short'))} ${event.tpsJeu}`
  let createdPenaltyId: string | null = null
  try {
    await scoringStore.addEvent(event)
    clearEntry()

    if (code === 'B') {
      // Conceded goal: propose the early lift of the conceding team's oldest liftable
      // (V/J) penalty — an R runs its full 2 minutes whatever happens (spec §7.4).
      const conceding: TeamSide = team === 'A' ? 'B' : 'A'
      const lift = penalties.liftCandidate(conceding)
      if (lift) liftPending.value = lift
      // Golden goal (spec §7.5): a goal during overtime of a type-E match ends it — offer
      // the closure right away (Statut → END triggers the kp_* consolidation server-side).
      if (goalEndsMatch(match.value.type, eventPeriod.value) && !scoreLevel.value) {
        goldenGoalOpen.value = true
      }
    } else if (cardCreatesPenaltyClock(code)) {
      // V/J/R: start the 2-min penalty clock (follows the game clock) — in live mode only.
      if (isLive.value) {
        const clock = penalties.add({
          team,
          playerId: String(player.matric),
          playerNumber: isTeamFact(player) ? null : player.numero,
          cardCode: code,
          durationSec: scoringStore.config.penaltyDuration,
          running: isRunning.value
        })
        if (clock) {
          createdPenaltyId = clock.id
          persistPenalty(clock, clock.running ? 'run' : 'stop')
        } else toast.add({ title: t('scoring.penalty.title'), description: t('scoring.penalty.full_slots'), color: 'warning' })
      }
    } else if (code === 'D') {
      // Black card: definitive exclusion, NO penalty clock, no replacement to the end.
      toast.add({ title: t('scoring.event.card_black'), description: t('scoring.penalty.black_no_penalty'), color: 'info' })
    }
    offerUndo(event, recordedLabel, createdPenaltyId)
  } catch { /* toast handled */ }
}

/**
 * Undo of the fact just recorded, offered for a few seconds (cheaper than finding the row
 * in the history). Removes what the fact created along with it: its penalty clock, and the
 * goal's follow-up dialogs (early lift, golden goal).
 */
const UNDO_TOAST_MS = 6000
const offerUndo = (event: ScoringEvent, label: string, penaltyId: string | null) => {
  toast.add({
    title: t('scoring.undo.recorded'),
    description: label,
    color: 'success',
    duration: UNDO_TOAST_MS,
    actions: [{
      label: t('scoring.undo.action'),
      icon: 'i-heroicons-arrow-uturn-left',
      color: 'neutral',
      variant: 'outline',
      onClick: () => { void undoEvent(event, penaltyId) }
    }]
  })
}
const undoEvent = async (event: ScoringEvent, penaltyId: string | null) => {
  if (!canEditFacts.value || !scoringStore.events.some(e => e.uid === event.uid)) return
  try {
    await scoringStore.removeEvent(event)
    if (penaltyId) removePenalty(penaltyId)
    if (event.code === 'B') {
      liftPending.value = null
      goldenGoalOpen.value = false
    }
    toast.add({ title: t('scoring.undo.done'), color: 'info' })
  } catch { /* toast handled */ }
}

const confirmCardWarning = async () => {
  const code = cardWarning.value?.code
  cardWarning.value = null
  if (code) await pushEvent(code)
}

/**
 * Load an existing fact into the entry zone for editing: re-select its player (so the
 * rosters show who it belongs to), re-stage its code (the confirm button then commits the
 * change) and focus the time field — same gestures as a fresh entry.
 */
const startEdit = async (e: ScoringEvent) => {
  if (!canEditFacts.value || !e.uid) return
  editingUid.value = e.uid
  eventPeriod.value = e.period
  eventTime.value = e.tpsJeu
  eventReason.value = e.reason
  pendingCode.value = e.code
  // Re-select the player the fact belongs to (team-level facts, player '0', have none).
  const roster = e.team === 'A' ? scoringStore.playersA : scoringStore.playersB
  const player = roster.find(p => String(p.matric) === e.player)
  selected.value = player ? { team: e.team, player } : null
  await focusTimeField()
}
const removeEvent = async (e: ScoringEvent) => {
  if (!canEditFacts.value) return
  try {
    await scoringStore.removeEvent(e)
    if (editingUid.value === e.uid) clearEntry()
  } catch { /* toast handled */ }
}

// ─── Event time fine-adjust (±60/±10/±1 s) — works in live and post-match ───
const adjustEventTime = (deltaSec: number) => {
  if (!acceptAdjust(`eventTime${deltaSec}`)) return
  const [m, s] = eventTime.value.split(':').map(Number)
  const total = Math.max(0, (m || 0) * 60 + (s || 0) + deltaSec)
  eventTime.value = `${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`
}

/**
 * The operator types digits only ("750", "0800"): the ':' is inserted for them. Done on
 * blur and just before recording — never on each keystroke, which would fight the caret
 * while the value is still half-typed.
 */
const normalizeEventTime = () => {
  eventTime.value = normalizeGameTime(eventTime.value)
}

/**
 * Select the whole time on focus so a click is enough to retype it — the operator corrects
 * the time far more often than they edit one digit of it, and a second click (or a manual
 * selection) at that moment costs seconds during play. On `focus` rather than `click`: it
 * also covers tab-in, and it leaves a deliberate second click free to place the caret.
 */
const selectEventTime = (e: FocusEvent) => {
  (e.target as HTMLInputElement | null)?.select()
}

const setPeriod = (p: Period) => {
  if (!canScore.value) return
  scoringStore.setPeriod(p)
  eventPeriod.value = p
  // The break countdown deliberately SURVIVES the period change (spec §7.5): the operator
  // sets up the next period while the teams are still resting, and needs to keep seeing the
  // time left. Only the game clock starting ends it (see `timer('run')`).
  // The shotclock, however, goes back to "--": it only starts on the first possession of
  // the new period (spec §6.5).
  if (shotclock.state.value !== 'IDLE') shotclockStop()
  // Reconfigure the clock to the new period duration (fresh countdown)
  if (isLive.value) timerSetPeriod(periodDurationOf(p, scoringStore.config.periodDurations))
}
/**
 * Kick-off (ATT → ON), the only one-tap status change. With no period chosen yet the match
 * starts on the first period — no confirmation, there is no clock to reset yet. Closing
 * and reopening have their own dialogs (see confirmClose / confirmReopen).
 */
const kickoff = () => {
  if (!canScore.value) return
  void scoringStore.setStatus('ON').catch(() => { /* useApi already toasts */ })
  if (!match.value?.periode) setPeriod('M1')
}

// In live mode the entry period follows the match period (local change, remote terminal or
// kick-off): the entry zone then needs no period select of its own outside edits.
watch(() => match.value?.periode, (p) => {
  if (p && isLive.value && !editingUid.value) eventPeriod.value = p as Period
})

// ─── Timer controls (UI + server persistence to scoring_live_clock) ───
const timer = (action: 'run' | 'stop' | 'RAZ') => {
  if (!canScore.value) return
  const maxTime = scoringStore.currentPeriodDuration
  if (action === 'run') {
    // Starting the game clock is the ONLY thing that ends the inter-period break: play has
    // resumed, so the rest countdown no longer means anything (spec §7.5).
    stopBreak()
    timerStart()
  } else if (action === 'stop') {
    timerStop()
  } else {
    timerReset()
  }
  // Persist: elapsed time in the current period (at the tenth) + period duration
  void scoringStore.setTimer(action, {
    startTime: action === 'RAZ' ? 0 : elapsedPrecise.value,
    runTime: 0,
    maxTime
  })
}

/** Start/pause toggle — debounced: a double contact must not start then stop the clock. */
const toggleClock = () => {
  if (!matchOn.value || !acceptClick('clockToggle')) return
  timer(isRunning.value ? 'stop' : 'run')
}

// Fine-adjust the live clock (±1/±10 s — ScoringTimeAdjust popover on the board digits).
// `deltaSec` is expressed on the DISPLAYED time (a countdown): +1 adds a second to the time
// left, so it SUBTRACTS a second from the elapsed time. Getting this backwards inverted the
// buttons (fixed 2026-08-02).
// A mis-tap next to Start/Pause must not shift a running clock: the fine adjust is a
// correction made clock stopped (spec §6.4), unless the competition allows it (hardware sync).
const clockAdjustable = computed(() => !isRunning.value || scoringStore.config.allowTimerAdjustWhileRunning)
const adjustClock = (deltaSec: number) => {
  if (!canScore.value || !clockAdjustable.value || !matchOn.value || !acceptAdjust(`clock${deltaSec}`)) return
  const wasRunning = isRunning.value
  // Clamped at both ends: never below 0, never past the period duration (which would show
  // a negative remaining time).
  const next = Math.min(
    scoringStore.currentPeriodDuration,
    Math.max(0, elapsed.value - deltaSec)
  )
  const reachesZero = next >= scoringStore.currentPeriodDuration && elapsed.value < scoringStore.currentPeriodDuration
  timerSetPeriod(scoringStore.currentPeriodDuration, next) // re-primes the clock (paused)
  if (wasRunning) timerStart() // keep it running if it was
  if (reachesZero) startBreakAfterPeriod()
  // Persisted when the burst ends, with the elapsed time AT THAT MOMENT (it may be running).
  persistAfterBurst('clock', () => {
    void scoringStore.setTimer(isRunning.value ? 'run' : 'stop', {
      startTime: elapsedPrecise.value,
      runTime: 0,
      maxTime: scoringStore.currentPeriodDuration
    })
  })
}

// ─── "Paramètres" view handlers (spec §7.2/§7.8) ───
const updatePlayer = async (team: TeamSide, matric: number, patch: { numero?: number, capitaine?: '-' | 'C' | 'E' }) => {
  try { await scoringStore.updatePlayer(team, matric, patch) } catch { /* toast handled */ }
}
const removePlayer = async (team: TeamSide, matric: number) => {
  try { await scoringStore.removePlayer(team, matric) } catch { /* toast handled */ }
}
const reloadPlayers = async (team: TeamSide) => {
  try { await scoringStore.reloadPresentPlayers(team) } catch { /* toast handled */ }
}
const saveOfficials = async (officials: Record<string, string>) => {
  try {
    await scoringStore.setOfficials(officials)
    if (scoringStore.match) Object.assign(scoringStore.match, officials)
    toast.add({ title: t('scoring.settings.officials_saved'), color: 'success' })
  } catch { /* toast handled */ }
}

/**
 * Load another match from the "Paramètres" view: a full id navigates straight away, a
 * short number is resolved server-side against the current match (same gameday, then
 * competition, then event — spec §7.8).
 */
const loadOtherMatch = async (input: string) => {
  const value = Number(String(input).trim())
  if (!Number.isFinite(value) || value <= 0) return
  // Short numbers are ≤ 5 digits, ids are 8-9 (legacy convention).
  const target = String(value).length <= 5 ? await scoringStore.resolveShortNumber(value) : value
  if (!target) {
    toast.add({ title: t('common.error'), description: t('scoring.settings.game_not_found'), color: 'warning' })
    return
  }
  await navigateTo(`/games/${target}/scoring`)
}

/** Chain the pitch: open the next match on the same pitch (same day, whole event). */
const loadNextOnPitch = async () => {
  try {
    const next = await scoringStore.nextOnPitch()
    if (next) await navigateTo(`/games/${next}/scoring`)
    else toast.add({ title: t('scoring.settings.no_next_game'), color: 'info' })
  } catch { /* useApi already toasts */ }
}

const suggestOfficials = (role: OfficialRole, q: string) => scoringStore.suggestOfficials(role, q)

const toggleLock = async () => {
  if (!canValidate.value) return
  try {
    await scoringStore.toggleValidation()
  } catch { /* toast handled */ }
}
</script>

<template>
  <div class="p-4">
    <div v-if="!canView" class="text-center text-header-900 dark:text-header-50 py-12">
      {{ t('scoring.no_access') }}
    </div>

    <div v-else-if="loading" class="text-center py-12">
      <UIcon name="i-heroicons-arrow-path" class="w-8 h-8 animate-spin" />
    </div>

    <div v-else-if="match" class="space-y-4">
      <!-- Competition ended banner -->
      <UAlert
        v-if="scoringStore.isCompetitionEnded"
        icon="i-heroicons-lock-closed"
        color="warning"
        variant="soft"
        :title="t('competition.ended_title')"
        :description="t('competition.ended_description')"
      />

      <!-- Connection lost: the sync badge colour alone went unnoticed during play -->
      <UAlert
        v-if="connectionLost"
        icon="i-heroicons-signal-slash"
        color="error"
        variant="soft"
        :title="t('scoring.offline.title')"
        :description="t('scoring.offline.message')"
      />

      <!-- Header: which match, then the console switches (view, mode, tools, lock) -->
      <header class="flex flex-wrap items-center gap-x-6 gap-y-3">
        <!-- Full width on mobile, so the switches wrap under it instead of squeezing it -->
        <div class="min-w-0 w-full md:w-auto md:flex-1">
          <h1 class="text-2xl leading-tight">
            {{ t('scoring.title') }} —
            <template v-if="match.numeroOrdre">{{ t('games.field.game_number') }}{{ match.numeroOrdre }}</template>
            <template v-else>#{{ match.id }}</template>
          </h1>
          <p class="text-sm text-header-700 dark:text-header-200 truncate">
            {{ match.codeCompetition }} · {{ match.phase }} · {{ t('scoring.pitch') }} {{ match.terrain }}
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <!-- Two views of the same match (spec §7.1): before kick-off / during the match -->
          <div class="inline-flex rounded-lg border border-header-200 dark:border-header-700 p-0.5" role="tablist">
            <UButton
              v-for="v in (['settings', 'run'] as const)" :key="v"
              size="sm"
              role="tab"
              :aria-selected="view === v"
              :variant="view === v ? 'solid' : 'ghost'"
              :color="view === v ? 'primary' : 'neutral'"
              :icon="v === 'settings' ? 'i-heroicons-adjustments-horizontal' : 'i-heroicons-play-circle'"
              @click="switchView(v)"
            >{{ t('scoring.view.' + v) }}</UButton>
          </div>

          <!-- Console mode: live / post-match / score only — one button, the menu changes it -->
          <UDropdownMenu :items="modeItems">
            <UButton
              size="sm"
              variant="outline"
              :color="scoreOnly ? 'warning' : 'neutral'"
              :icon="MODE_ICON[consoleMode]"
              trailing-icon="i-heroicons-chevron-down"
              :title="t('scoring.mode.label')"
            >{{ modeLabel(consoleMode) }}</UButton>
          </UDropdownMenu>

          <!-- Live sync indicator: the console follows this match's Mercure topics, so a
               change made on another terminal (or by the hardware relay) lands here. -->
          <UBadge
            v-if="isLive"
            variant="soft"
            :color="liveSync.status.value === 'connected' ? 'success' : liveSync.status.value === 'error' ? 'warning' : 'neutral'"
            :title="remoteSynced ? t('scoring.sync.remote_change') : t('scoring.sync.' + liveSync.status.value)"
            :class="remoteSynced ? 'animate-pulse ring-2 ring-primary-500' : ''"
          >
            <UIcon :name="liveSync.status.value === 'connected' ? 'i-heroicons-signal' : 'i-heroicons-signal-slash'" class="size-4" />
          </UBadge>

          <!-- Occasional tools: full-screen displays + keyboard shortcuts -->
          <UDropdownMenu :items="toolItems">
            <UButton size="sm" variant="ghost" color="neutral" icon="i-heroicons-ellipsis-horizontal" :aria-label="t('scoring.tools')" :title="t('scoring.tools')" />
          </UDropdownMenu>

          <!-- Lock state only: the match status has its own (clickable) control on the board. -->
          <UBadge v-if="scoringStore.isLocked" color="error">
            {{ t('scoring.locked') }}
          </UBadge>
          <UButton
            v-if="canValidate"
            size="sm"
            :icon="scoringStore.isLocked ? 'i-heroicons-lock-closed' : 'i-heroicons-lock-open'"
            :color="scoringStore.isLocked ? 'error' : 'neutral'"
            variant="soft"
            :aria-label="t('scoring.lock_toggle')"
            :title="t('scoring.lock_toggle')"
            @click="toggleLock"
          />
        </div>
      </header>

      <!-- ══ Paramètres view (before kick-off / control) ══ -->
      <ScoringReadiness
        v-if="view === 'settings'"
        :issues="readiness"
        :team-a="match.equipeA ?? 'A'"
        :team-b="match.equipeB ?? 'B'"
      />
      <ScoringSettingsPanel
        v-if="view === 'settings'"
        :match="match"
        :players-a="scoringStore.playersA"
        :players-b="scoringStore.playersB"
        :can-score="canScore"
        :can-manage-players="canScore"
        :suggest-officials="suggestOfficials"
        @update-player="updatePlayer"
        @remove-player="removePlayer"
        @reload-players="reloadPlayers"
        @save-officials="saveOfficials"
        @load-match="loadOtherMatch"
        @load-next="loadNextOnPitch"
        @officials-dirty="officialsDirty = $event"
      />

      <!-- ══ Déroulement view ══ -->
      <template v-else>
      <!-- Before kick-off: what still needs checking on the game sheet, one tap away -->
      <ScoringReadiness
        v-if="match.statut === 'ATT' && canScore"
        compact
        :issues="readiness"
        :team-a="match.equipeA ?? 'A'"
        :team-b="match.equipeB ?? 'B'"
        @open="view = 'settings'"
      />

      <!-- The console: the board (what the public sees) with the deck of clock keys under it -->
      <div>
        <ScoringBoard
          :class="isLive && matchOn ? '' : 'rounded-b-xl'"
          :team-a="match.equipeA ?? 'A'"
          :team-b="match.equipeB ?? 'B'"
          :score-a="scoringStore.scoreA"
          :score-b="scoringStore.scoreB"
          :clock="isLive ? clockDisplay : null"
          :clock-running="isRunning"
          :shotclock="isLive ? (shotclockMasked ? '--' : shotclock.display.value) : null"
          :shotclock-state="shotclock.state.value"
          :break-display="breakRemaining !== null ? breakDisplay : null"
          :penalties-a="isLive ? penalties.penalties.value.filter(p => p.team === 'A') : []"
          :penalties-b="isLive ? penalties.penalties.value.filter(p => p.team === 'B') : []"
          :score-only="scoreOnly"
          :can-score="canEditFacts"
          :can-adjust-penalties="canScore && !isRunning"
          :can-open-clock-adjust="isLive && matchOn && canScore"
          :clock-adjustable="clockAdjustable"
          :shotclock-adjustable="shotclock.state.value === 'SUSPENDED'"
          @bump="bumpScore"
          @remove-penalty="removePenalty"
          @adjust-penalty="adjustPenalty"
          @adjust-clock="adjustClock"
          @adjust-shotclock="shotclockAdjust"
        >
          <template #state>
            <ScoringStatusBadge
              :status="match.statut"
              :can-kickoff="canScore"
              @kickoff="kickoff"
            />
            <!-- Closing and reopening: deliberate, through their own dialog (spec §7.6) -->
            <UButton
              v-if="match.statut === 'ON' && canScore"
              size="xs"
              variant="outline"
              color="neutral"
              icon="i-heroicons-flag"
              @click="openClose"
            >{{ t('scoring.close.action') }}</UButton>
            <UButton
              v-else-if="match.statut === 'END' && canScore"
              size="xs"
              variant="ghost"
              color="neutral"
              icon="i-heroicons-arrow-uturn-left"
              @click="reopenOpen = true"
            >{{ t('scoring.reopen.action') }}</UButton>
            <ScoringPeriodSelector
              v-if="matchOn"
              :period="match.periode"
              :type="match.type"
              :can-change="canScore"
              :score-level="scoreLevel"
              :shootout-enabled="scoringStore.config.shootoutEnabled"
              :period-over="isLive && clockAtZero"
              @change="setPeriod"
            />
          </template>
        </ScoringBoard>

        <!-- Deck (live, match in progress only): game clock keys | shot clock keys, under the
             digits they drive. Before kick-off and after the closure there is nothing to run. -->
        <div
          v-if="isLive && matchOn"
          class="rounded-b-xl border border-t-0 border-header-200 dark:border-header-700 bg-header-50 dark:bg-header-900 px-3 py-3 flex flex-wrap items-center justify-center gap-x-8 gap-y-3"
        >
          <div class="flex items-center gap-1" role="group" :aria-label="t('scoring.timer.group')">
            <!-- One start/pause toggle (fine adjust = click on the board digits) -->
            <UButton
              class="min-w-32 justify-center"
              :color="isRunning ? 'warning' : 'success'"
              :icon="isRunning ? 'i-heroicons-pause' : 'i-heroicons-play'"
              :disabled="!canScore"
              @click="toggleClock"
            >{{ isRunning ? t('scoring.timer.pause') : t('scoring.timer.start') }}</UButton>
            <UButton
              size="sm"
              icon="i-heroicons-arrow-uturn-left"
              variant="ghost"
              color="neutral"
              :disabled="!canScore || isRunning"
              :aria-label="t('scoring.timer.reset')"
              :title="t('scoring.timer.reset')"
              @click="timer('RAZ')"
            />
          </div>

          <!-- Shotclock (chronomètre de tir): 3 commands — 60 s / 40 s / arrêt (spec §6.5) -->
          <ScoringShotclock
            :state="shotclock.state.value"
            :durations="scoringStore.config.shotclockDurations"
            :offensive-rebound-enabled="scoringStore.config.shotclockOffensiveReboundEnabled"
            :can-score="canScore"
            @start="shotclockStart"
            @stop="shotclockStop"
            @test-sound="buzzer.test()"
          />

          <UButton v-if="breakRemaining !== null" size="sm" variant="ghost" :disabled="!canScore" @click="stopBreak()">
            {{ t('scoring.break.skip') }}
          </UButton>
        </div>
      </div>

      <!-- Work area: roster A | entry panel | roster B (lg) — rosters side by side with the
           entry below (md and mobile, where rosters show numbers only and the entry bar is
           pinned at the bottom, within thumb reach). Pick the player, then the fact. -->
      <div
        v-if="!scoreOnly"
        class="grid grid-cols-2 gap-3 md:gap-4 lg:grid-cols-[minmax(0,1fr)_21rem_minmax(0,1fr)] lg:items-start
               transition-[grid-template-columns] duration-300 motion-reduce:transition-none"
        :class="wideTeam === 'A' ? 'max-md:grid-cols-[minmax(0,2.2fr)_minmax(0,1fr)]'
          : wideTeam === 'B' ? 'max-md:grid-cols-[minmax(0,1fr)_minmax(0,2.2fr)]' : ''"
      >
        <ScoringTeamColumn
          v-for="side in (['A', 'B'] as TeamSide[])" :key="side"
          :class="side === 'A' ? 'lg:order-1' : 'lg:order-3'"
          :team="side"
          :team-name="(side === 'A' ? match.equipeA : match.equipeB) ?? side"
          :players="side === 'A' ? scoringStore.playersA : scoringStore.playersB"
          :tallies="tallies"
          :selected-matric="selected?.team === side ? selected.player.matric : null"
          :candidate-matrics="candidateMatrics"
          :excluded-card="excludedCard"
          :expanded="wideTeam === side"
          :show-roster="true"
          :can-score="canEditFacts"
          @select="selectPlayer(side, $event)"
          @select-team-fact="selectTeamFact(side)"
          @reload="reloadPlayers(side)"
          @expand="widenTeam(side)"
        />

        <!-- Entry panel (live AND post-match), in the order of the entry: fact → (reason) →
             player (or the player first, with the mouse) → time → validation. -->
        <section
          class="entry-panel order-last col-span-2 lg:col-span-1 lg:order-2 lg:sticky lg:top-24
                 space-y-3 rounded-xl border border-header-200 dark:border-header-700 bg-header-50 dark:bg-header-900 p-3
                 max-md:sticky max-md:bottom-0 max-md:z-20 max-md:-mx-4 max-md:rounded-none max-md:border-x-0 max-md:border-b-0 max-md:shadow-[0_-4px_12px_rgb(0_0_0/0.15)]"
          :aria-label="t('scoring.entry.title')"
        >
          <!-- Not in progress: nothing to enter — say why and what to do -->
          <p v-if="!matchOn" class="flex items-start gap-2 text-sm text-header-600 dark:text-header-300">
            <UIcon name="i-heroicons-lock-closed" class="size-4 mt-0.5 shrink-0" />
            {{ match.statut === 'END' ? t('scoring.entry.locked_end') : t('scoring.entry.locked_pending') }}
          </p>
          <template v-else>
          <!-- What is armed so far, and the way out -->
          <div class="flex items-center gap-2 min-h-7 text-sm">
            <template v-if="entryActive">
              <UIcon v-if="editingUid" name="i-heroicons-pencil-square" class="size-4 shrink-0 text-primary-600 dark:text-primary-300" />
              <span class="flex-1 min-w-0 truncate">
                <template v-if="editingUid"><span class="text-primary-700 dark:text-primary-200">{{ t('scoring.edit.title') }}</span> · </template>
                <strong v-if="pendingCode">{{ t(eventCodes.find(e => e.code === pendingCode)!.shortKey) }}</strong>
                <template v-if="pendingCode && selected"> · </template>
                <template v-if="selected">
                  <span class="text-header-500 dark:text-header-400">{{ selected.team === 'A' ? match.equipeA : match.equipeB }}</span>
                  <strong class="ml-1">{{ selectedLabel }}</strong>
                </template>
              </span>
              <UButton size="xs" variant="soft" color="neutral" icon="i-heroicons-x-mark" @click="clearEntry">
                {{ t('scoring.entry.clear') }}
              </UButton>
            </template>
            <template v-else-if="canScore">
              <span class="flex-1 text-header-500 dark:text-header-400">{{ t('scoring.entry.start_hint') }}</span>
            </template>
          </div>

          <!-- 1. The fact: arms it, records nothing -->
          <div class="grid grid-cols-5 lg:grid-cols-1 gap-2">
            <UButton
              v-for="evt in eventCodes" :key="evt.code"
              block
              class="fact-key max-sm:flex-col max-sm:gap-1 max-sm:px-1 max-sm:text-xs lg:justify-start"
              :color="evt.color as any"
              :variant="pendingCode === evt.code ? 'solid' : 'outline'"
              :disabled="!canEditFacts"
              :aria-keyshortcuts="bindings[evt.shortcut] || undefined"
              @click="stageEvent(evt.code)"
            >
              <template #leading><ScoringEventIcon :code="evt.code" /></template>
              <span class="sm:hidden">{{ t(evt.shortKey) }}</span>
              <span class="max-sm:hidden flex-1 text-left">{{ t(evt.labelKey) }}</span>
              <!-- Its keyboard key: on the right, under the label on a phone -->
              <kbd
                v-if="bindings[evt.shortcut]"
                class="fact-kbd shrink-0 rounded border border-current/40 px-1.5 text-xs font-mono leading-5 opacity-80"
                :title="t('scoring.entry.keys_hint')"
              >{{ shortcutKeyLabel(bindings[evt.shortcut]) }}</kbd>
            </UButton>
          </div>

          <!-- 2. A card's reason: the list opens by itself (optional — default "Autre") -->
          <div v-if="pendingIsCard">
            <label class="block text-center text-xs text-header-600 dark:text-header-300 mb-1">{{ t('scoring.reason.label') }}</label>
            <USelect
              v-model="eventReasonSelect"
              :open="reasonOpen"
              :items="reasonItems"
              :disabled="!canScore"
              size="sm"
              class="w-full"
              @update:open="onReasonOpen"
            />
          </div>

          <!-- 3. The player by number (keyboard): ←/→ when both teams have it, then Entrée -->
          <div v-if="pendingCode && !selected && !editingUid" class="max-md:hidden">
            <label for="scoring-player-number" class="block text-center text-xs text-header-600 dark:text-header-300 mb-1">{{ t('scoring.entry.player_number') }}</label>
            <UInput
              id="scoring-player-number"
              ref="playerInput"
              v-model="playerQuery"
              size="md"
              class="w-full scoring-digits"
              :ui="{ base: 'text-center' }"
              inputmode="numeric"
              autocomplete="off"
              :placeholder="t('scoring.entry.player_number_placeholder')"
              @keydown="onPlayerQueryKey"
            />
            <p class="mt-1 text-center text-xs text-header-600 dark:text-header-300">
              <template v-if="playerCandidates.length > 1">{{ t('scoring.entry.player_ambiguous') }}</template>
              <template v-else-if="playerCandidates.length === 1">
                {{ playerCandidates[0]!.team }} · {{ playerCandidates[0]!.nom.toUpperCase() }} — {{ t('scoring.entry.player_enter') }}
              </template>
              <template v-else-if="playerQuery.trim()">{{ t('scoring.entry.player_none') }}</template>
              <template v-else>{{ t('scoring.entry.player_hint') }}</template>
            </p>
          </div>

          <!-- 4. Time (+ period in post-match/edit), frozen since the entry started.
               On mobile it only opens once something is armed, to keep the bottom bar short. -->
          <div class="space-y-3" :class="entryActive ? '' : 'max-md:hidden'">
            <div v-if="!isLive || editingUid">
              <label class="block text-center text-xs text-header-600 dark:text-header-300 mb-1">{{ t('scoring.time.period') }}</label>
              <USelect v-model="eventPeriod" :items="periodItems" value-key="value" :disabled="!canScore" size="sm" class="w-full" />
            </div>
            <div>
              <label class="block text-center text-xs text-header-600 dark:text-header-300 mb-1">{{ t('scoring.time.label') }}</label>
              <div class="flex items-center justify-center gap-0.5">
                <UButton size="xs" variant="ghost" :disabled="!canScore" @click="adjustEventTime(-60)">−60</UButton>
                <UButton size="xs" variant="ghost" :disabled="!canScore" @click="adjustEventTime(-10)">−10</UButton>
                <UButton size="xs" variant="ghost" :disabled="!canScore" @click="adjustEventTime(-1)">−1</UButton>
                <UInput
                  ref="timeInput"
                  v-model="eventTime"
                  :disabled="!canScore"
                  size="md"
                  class="w-20 scoring-digits"
                  :ui="{ base: 'text-center' }"
                  :class="pendingCode && (selected || editingUid) ? 'ring-2 ring-primary-500 rounded-md' : ''"
                  inputmode="numeric"
                  maxlength="5"
                  @focus="selectEventTime"
                  @blur="normalizeEventTime"
                  @keydown.enter.prevent="validatePending"
                  @keydown.esc.prevent="clearEntry"
                />
                <UButton size="xs" variant="ghost" :disabled="!canScore" @click="adjustEventTime(1)">+1</UButton>
                <UButton size="xs" variant="ghost" :disabled="!canScore" @click="adjustEventTime(10)">+10</UButton>
                <UButton size="xs" variant="ghost" :disabled="!canScore" @click="adjustEventTime(60)">+60</UButton>
              </div>
              <p v-if="isLive && entryActive && !editingUid" class="mt-1 text-center text-xs text-header-500 dark:text-header-400">
                {{ t('scoring.entry.time_frozen') }}
              </p>
            </div>

            <!-- 5. Explicit validation (spec §7.3: focus the time, then Entrée); an edited fact
                 can also be deleted; « Annuler » drops the entry like « Désélectionner ». -->
            <div v-if="pendingCode" class="space-y-2">
              <UButton
                block
                color="primary"
                icon="i-heroicons-check"
                :disabled="!canEditFacts || (!selected && !editingUid)"
                @click="validatePending"
              >
                {{ editingUid ? t('scoring.edit.save') : t('scoring.pending.validate_event', { event: t(eventCodes.find(e => e.code === pendingCode)!.shortKey) }) }}
              </UButton>
              <div class="flex gap-2">
                <UButton
                  v-if="editingUid"
                  class="flex-1 justify-center"
                  color="error"
                  variant="soft"
                  icon="i-heroicons-trash"
                  :disabled="!canEditFacts"
                  @click="deleteEditedOpen = true"
                >{{ t('scoring.edit.delete') }}</UButton>
                <UButton class="flex-1 justify-center" color="neutral" variant="outline" @click="clearEntry">
                  {{ t('scoring.edit.cancel') }}
                </UButton>
              </div>
            </div>
          </div>
          </template>
        </section>
      </div>

      <!-- Delete the fact being edited (entry panel) -->
      <AdminConfirmModal
        :open="deleteEditedOpen"
        variant="danger"
        :title="t('scoring.edit.delete_title')"
        :message="t('scoring.edit.delete_message')"
        :confirm-text="t('scoring.edit.delete')"
        :cancel-text="t('scoring.edit.cancel')"
        @confirm="confirmDeleteEdited"
        @close="deleteEditedOpen = false"
      />

      <!-- Card progression alert (spec §7.4) — the operator can record anyway -->
      <AdminConfirmModal
        :open="cardWarning !== null"
        variant="warning"
        :title="t('scoring.card_progression.title')"
        :message="t('scoring.card_progression.' + (cardWarning?.violation ?? 'card_not_higher'))
          + ' ' + t('scoring.card_progression.confirm')"
        :confirm-text="t('scoring.card_progression.record_anyway')"
        :cancel-text="t('scoring.edit.cancel')"
        @confirm="confirmCardWarning"
        @close="cardWarning = null"
      />

      <!-- Golden goal (spec §7.5): offer to close the match right after the winning goal -->
      <AdminConfirmModal
        :open="goldenGoalOpen"
        variant="warning"
        :title="t('scoring.golden_goal.title')"
        :message="t('scoring.golden_goal.message', { period: periodLabel(eventPeriod) })"
        :confirm-text="t('scoring.golden_goal.close_match')"
        :cancel-text="t('scoring.edit.cancel')"
        @confirm="() => { goldenGoalOpen = false; openClose() }"
        @close="goldenGoalOpen = false"
      />

      <!-- Conceded-goal early lift of a V/J penalty (spec §7.4 — operator confirms) -->
      <AdminConfirmModal
        :open="liftPending !== null"
        variant="warning"
        :title="t('scoring.penalty.lift_title')"
        :message="t('scoring.penalty.lift_message', { number: liftPending?.playerNumber ?? '?' })"
        :confirm-text="t('scoring.penalty.lift_confirm')"
        :cancel-text="t('scoring.edit.cancel')"
        @confirm="confirmLift"
        @close="liftPending = null"
      />

      <!-- Events history (editable) — symmetric A | time | B on wide screens, list on mobile -->
      <ScoringEventHistory
        v-if="scoringStore.events.length"
        class="border-t pt-4"
        :events="scoringStore.events"
        :editing-uid="editingUid"
        :can-score="canEditFacts"
        :recent-count="isLive ? HISTORY_RECENT_COUNT : 0"
        :team-a="match.equipeA ?? 'A'"
        :team-b="match.equipeB ?? 'B'"
        @edit="startEdit"
        @remove="removeEvent"
      />
      </template>

      <!-- Leaving « Paramètres » with officials not saved -->
      <AdminConfirmModal
        :open="leaveSettingsOpen"
        variant="warning"
        :title="t('scoring.settings.officials_unsaved_title')"
        :message="t('scoring.settings.officials_unsaved_message')"
        :confirm-text="t('scoring.settings.officials_unsaved_leave')"
        :cancel-text="t('scoring.settings.officials_unsaved_stay')"
        @confirm="confirmLeaveSettings"
        @close="leaveSettingsOpen = false"
      />

      <!-- Closure (spec §7.6): score check, end time, comments, lock -->
      <ScoringCloseMatchModal
        :open="closeOpen"
        :team-a="match.equipeA ?? 'A'"
        :team-b="match.equipeB ?? 'B'"
        :score="{ A: scoringStore.scoreA, B: scoringStore.scoreB }"
        :goals="goals"
        :comments="match.commentairesOfficiels ?? ''"
        :recorded-end-time="match.heureFin"
        :can-lock="canValidate"
        :loading="closing"
        @confirm="confirmClose"
        @align="alignScore"
        @close="closeOpen = false"
      />
      <AdminConfirmModal
        :open="reopenOpen"
        variant="warning"
        :title="t('scoring.reopen.title')"
        :message="t('scoring.reopen.message')"
        :confirm-text="t('scoring.reopen.confirm')"
        :cancel-text="t('scoring.edit.cancel')"
        @confirm="confirmReopen"
        @close="reopenOpen = false"
      />

      <!-- Keyboard shortcuts settings (per-device preference) -->
      <ScoringShortcutsModal
        :open="shortcutsOpen"
        :bindings="bindings"
        @close="shortcutsOpen = false"
        @set="setBinding"
        @reset="resetDefaults"
      />
    </div>

    <div v-else class="text-center text-header-900 dark:text-header-50 py-12">
      {{ t('scoring.not_found') }}
    </div>
  </div>
</template>
