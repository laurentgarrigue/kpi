<script setup lang="ts">
import type { Period, MatchStatus, ScoringPlayer, ScoringEvent, ScoringEventCode, TeamSide } from '~/types/scoring'
import type { PenaltyClock } from '~/composables/usePenalties'

definePageMeta({
  layout: 'admin',
  middleware: 'auth'
})

const route = useRoute()
const { t } = useI18n()
const toast = useToast()
const scoringStore = useScoringStore()

const matchId = computed(() => parseInt(route.params.id as string))

// Permissions (experimentation phase: profile <= 2)
const { canView, canScore: canScoreBase, canValidate } = useScoringPermissions(
  computed(() => scoringStore.isLocked)
)
const canScore = computed(() => canScoreBase.value && !scoringStore.isCompetitionEnded)

// D = black ejection card under the 2027 rules (spec §0.9) — code kept for storage compat.
const eventCodes: { code: ScoringEventCode; labelKey: string; color: string }[] = [
  { code: 'B', labelKey: 'scoring.event.goal', color: 'primary' },
  { code: 'V', labelKey: 'scoring.event.card_green', color: 'success' },
  { code: 'J', labelKey: 'scoring.event.card_yellow', color: 'warning' },
  { code: 'R', labelKey: 'scoring.event.card_red', color: 'error' },
  { code: 'D', labelKey: 'scoring.event.card_black', color: 'neutral' }
]
// Card reasons (motifs) reused from FMV3 — '' = none, 'unknown' pre-selected (spec §0.9)
const reasonCodes = ['', 'r_pad', 'r_kt', 'r_ht', 'r_p', 'r_o', 'r_un', 'r_rep', 'unknown']

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

// ─── Two views: "Paramètres" (before kick-off) / "Déroulement" (spec §7.1) ───
const view = ref<'run' | 'settings'>('run')

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

/** Score-only goal: +1/−1 for a team, recorded as a team-level fact (player '0'). */
const bumpScore = async (team: TeamSide, delta: 1 | -1) => {
  if (!canScore.value || !match.value) return
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
// When set, the event buttons commit an UPDATE of this existing event instead of a new one.
const editingUid = ref<string | null>(null)

// The console is a PWA installed on the scoring table's tablet and stays open for days:
// make sure a new deployment is picked up immediately (spec §0.9).
usePwaUpdate()

// ─── Buzzer (shotclock expiry, period end, end of break — decision §0.9) ───
const buzzer = useBuzzer()

// ─── Game clock (easytimer) ───
const { display: clockDisplay, gameTime, elapsed, isRunning, setPeriod: timerSetPeriod, start: timerStart, stop: timerStop, reset: timerReset, restoreFromServer } =
  useTimer({
    onTargetReached: () => {
      // Buzzer at period end; persist the stop server-side; start the indicative
      // inter-period break countdown when one applies (spec §7.5 — 3'/3'/1').
      buzzer.beep()
      void scoringStore.setTimer('stop', { startTime: elapsed.value, runTime: 0, maxTime: scoringStore.currentPeriodDuration })
      const m = scoringStore.match
      if (m && isLive.value) {
        const next = nextPeriodFor(m.type, (m.periode ?? 'M1') as Period, scoreLevel.value, scoringStore.config.shootoutEnabled)
        const dur = next ? breakDurationBefore(next, scoringStore.config.breakDurations) : null
        if (dur) startBreak(dur)
      }
    }
  })

// ─── Shotclock (chronomètre de tir) — 3 commands, auto-follows the game clock (§6.5) ───
const shotclock = useShotclock({ onExpired: () => buzzer.beep() })

const persistShotclock = (action: 'run' | 'stop' | 'RAZ') => {
  void scoringStore.setTimer(action, {
    kind: 'SHOTCLOCK',
    startTime: action === 'RAZ' ? 0 : Math.round(shotclock.elapsedSeconds.value),
    runTime: 0,
    maxTime: shotclock.initSeconds.value
  })
}
const shotclockStart = (seconds: number) => {
  if (!canScore.value || !isLive.value) return
  shotclock.start(seconds)
  persistShotclock('run')
}
const shotclockStop = () => {
  if (!canScore.value || shotclock.state.value === 'IDLE') return
  shotclock.stopToIdle()
  persistShotclock('RAZ')
}
const shotclockAdjust = (delta: number) => {
  if (!canScore.value || shotclock.state.value !== 'SUSPENDED') return
  shotclock.adjust(delta)
  persistShotclock('stop')
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
    startTime: Math.max(0, Math.round(scoringStore.config.penaltyDuration - p.remainingMs / 1000)),
    runTime: 0,
    maxTime: scoringStore.config.penaltyDuration
  })
}
const persistAllPenalties = () => {
  for (const p of penalties.penalties.value) persistPenalty(p, p.running ? 'run' : 'stop')
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

// Excluded-player markers in the rosters (R = 🔴 replacement at penalty end, D = ⬛ out
// for the whole match, team short-handed to the end).
const excludedToken = computed(() => {
  const m = new Map<string, string>()
  for (const e of scoringStore.events) {
    if (e.code === 'D') m.set(e.player, '⬛')
    else if (e.code === 'R' && m.get(e.player) !== '⬛') m.set(e.player, '🔴')
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
  timer: gameTime.value,
  timerRunning: isRunning.value,
  shotclock: shotclockMasked.value ? '--' : shotclock.display.value,
  shotclockState: shotclock.state.value,
  penalties: penalties.penalties.value
}))

// Push each change as it happens (cheap: BroadcastChannel is in-process messaging).
watch(gameTime, () => broadcast.timer())
watch(isRunning, () => broadcast.timerStatus())
watch(() => shotclock.display.value, () => broadcast.shotclock())
watch(() => shotclock.state.value, () => broadcast.shotclock())
watch(() => [scoringStore.scoreA, scoringStore.scoreB], () => broadcast.scores())
watch(() => match.value?.periode, () => broadcast.period())
watch(() => penalties.penalties.value.map(p => `${p.slot}${p.team}${Math.ceil(p.remainingMs / 1000)}${p.running}`).join(),
  () => broadcast.penalties())

// ─── Configurable keyboard shortcuts (spec §6.5, decision §0.9) ───
const shortcutsOpen = ref(false)
const shortcutsEnabled = computed(() => isLive.value && canScore.value && !shortcutsOpen.value)
const { bindings, setBinding, resetDefaults } = useScoringShortcuts({
  gameClockToggle: () => timer(isRunning.value ? 'stop' : 'run'),
  shotclockStart60: () => shotclockStart(scoringStore.config.shotclockDurations.full),
  shotclockStart40: () => {
    if (scoringStore.config.shotclockOffensiveReboundEnabled) {
      shotclockStart(scoringStore.config.shotclockDurations.offensiveRebound)
    }
  },
  shotclockStop,
  shotclockPlus: () => shotclockAdjust(1),
  shotclockMinus: () => shotclockAdjust(-1)
}, { enabled: shortcutsEnabled })

// In live mode keep the event-time field tracking the clock (unless editing a row).
watch(gameTime, (v) => {
  if (isLive.value && !editingUid.value) eventTime.value = v
})

// ─── Live coherence between terminals (Mercure/SSE — plan lot 3) ───
// A change made on another terminal (or by the hardware relay) is signalled on the
// match's topics; the console refetches the canonical state instead of merging a diff.
const liveSync = useScoringLiveSync({
  lastLocalWrite: () => scoringStore.lastMutationAt,
  onRemoteChange: async () => {
    try {
      await scoringStore.refreshLiveState()
      await applyServerClocks()
      flashRemoteSync()
      broadcast.broadcastAll()
    } catch { /* useApi already toasts */ }
  }
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
    // Default mode from status, period field from the match's current period.
    mode.value = scoringStore.match?.statut === 'END' ? 'post' : 'live'
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

const selectPlayer = (team: TeamSide, player: ScoringPlayer) => {
  if (!canScore.value) return
  selected.value = { team, player }
}

// Card-progression warning modal (spec §7.4: alert, the operator stays sovereign) and
// golden-goal closing proposal (spec §7.5).
const cardWarning = ref<{ code: ScoringEventCode; violation: string } | null>(null)
const goldenGoalOpen = ref(false)

/** Commit the event buttons: add a new event, or update the one being edited. */
const commitEvent = async (code: ScoringEventCode) => {
  if (!canScore.value || !match.value) return

  if (editingUid.value) {
    // Editing an existing row: keep its team/player, change code/time/period/reason.
    try {
      await scoringStore.updateEvent(editingUid.value, {
        code,
        period: eventPeriod.value,
        tpsJeu: eventTime.value,
        reason: code === 'B' ? '' : eventReason.value
      })
      cancelEdit()
    } catch { /* toast handled */ }
    return
  }

  if (!selected.value) {
    toast.add({ title: t('common.error'), description: t('scoring.select_player_first'), color: 'warning' })
    return
  }

  // Card progression rule (spec §7.4, 2027): warn when the new card is identical/lower
  // than the player's previous one, or when the player is already out (R/D). The modal
  // lets the operator record it anyway (paper sheet stays the referee's authority).
  if (code !== 'B') {
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
    number: player.numero,
    reason: code === 'B' ? '' : eventReason.value,
    nom: player.nom,
    prenom: player.prenom
  }
  try {
    await scoringStore.addEvent(event)
    selected.value = null
    eventReason.value = scoringStore.config.defaultCardReason

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
          playerNumber: player.numero,
          cardCode: code,
          durationSec: scoringStore.config.penaltyDuration,
          running: isRunning.value
        })
        if (clock) persistPenalty(clock, clock.running ? 'run' : 'stop')
        else toast.add({ title: t('scoring.penalty.title'), description: t('scoring.penalty.full_slots'), color: 'warning' })
      }
    } else if (code === 'D') {
      // Black card: definitive exclusion, NO penalty clock, no replacement to the end.
      toast.add({ title: t('scoring.event.card_black'), description: t('scoring.penalty.black_no_penalty'), color: 'info' })
    }
  } catch { /* toast handled */ }
}

const confirmCardWarning = async () => {
  const code = cardWarning.value?.code
  cardWarning.value = null
  if (code) await pushEvent(code)
}

/** Load an existing event into the entry zone for editing. */
const startEdit = (e: ScoringEvent) => {
  if (!canScore.value || !e.uid) return
  editingUid.value = e.uid
  eventPeriod.value = e.period
  eventTime.value = e.tpsJeu
  eventReason.value = e.reason
}
const cancelEdit = () => {
  editingUid.value = null
  eventReason.value = scoringStore.config.defaultCardReason
  if (isLive.value) eventTime.value = gameTime.value
}
const removeEvent = async (e: ScoringEvent) => {
  if (!canScore.value) return
  try {
    await scoringStore.removeEvent(e)
    if (editingUid.value === e.uid) cancelEdit()
  } catch { /* toast handled */ }
}

// ─── Event time fine-adjust (±60/±10/±1 s) — works in live and post-match ───
const adjustEventTime = (deltaSec: number) => {
  const [m, s] = eventTime.value.split(':').map(Number)
  const total = Math.max(0, (m || 0) * 60 + (s || 0) + deltaSec)
  eventTime.value = `${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`
}

const setPeriod = (p: Period) => {
  if (!canScore.value) return
  scoringStore.setPeriod(p)
  eventPeriod.value = p
  // The break ends when the next period is set up; the shotclock goes back to "--"
  // (it only starts on the first possession of the new period — spec §6.5).
  stopBreak()
  if (shotclock.state.value !== 'IDLE') shotclockStop()
  // Reconfigure the clock to the new period duration (fresh countdown)
  if (isLive.value) timerSetPeriod(periodDurationOf(p, scoringStore.config.periodDurations))
}
const setStatus = (s: MatchStatus) => { if (canScore.value) scoringStore.setStatus(s) }

// ─── Timer controls (UI + server persistence to kp_chrono) ───
const timer = (action: 'run' | 'stop' | 'RAZ') => {
  if (!canScore.value) return
  const maxTime = scoringStore.currentPeriodDuration
  if (action === 'run') {
    timerStart()
  } else if (action === 'stop') {
    timerStop()
  } else {
    timerReset()
  }
  // Persist: elapsed seconds in the current period + period duration
  void scoringStore.setTimer(action, {
    startTime: action === 'RAZ' ? 0 : elapsed.value,
    runTime: 0,
    maxTime
  })
}

// Fine-adjust the live clock (±1/±10 s). Only meaningful in live mode.
const adjustClock = (deltaSec: number) => {
  if (!canScore.value) return
  const wasRunning = isRunning.value
  const next = Math.max(0, elapsed.value + deltaSec)
  timerSetPeriod(scoringStore.currentPeriodDuration, next) // re-primes the clock (paused)
  if (wasRunning) timerStart() // keep it running if it was
  void scoringStore.setTimer(wasRunning ? 'run' : 'stop', {
    startTime: next,
    runTime: 0,
    maxTime: scoringStore.currentPeriodDuration
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

const toggleLock = async () => {
  if (!canValidate.value) return
  try {
    await scoringStore.toggleValidation()
  } catch { /* toast handled */ }
}
</script>

<template>
  <div class="p-4">
    <div v-if="!canView" class="text-center text-header-900 py-12">
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

      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-xl font-bold">{{ t('scoring.title') }} — #{{ match.id }}</h1>
          <p class="text-sm text-header-900">
            {{ match.codeCompetition }} · {{ match.phase }} · {{ t('scoring.field') }} {{ match.terrain }}
          </p>
        </div>
        <div class="flex items-center gap-2">
          <!-- Settings / run views (spec §7.1) -->
          <UButton
            size="xs"
            variant="outline"
            :icon="view === 'run' ? 'i-heroicons-adjustments-horizontal' : 'i-heroicons-play'"
            @click="view = view === 'run' ? 'settings' : 'run'"
          >{{ view === 'run' ? t('scoring.settings.title') : t('scoring.run.title') }}</UButton>

          <!-- Direct / post-match mode -->
          <div class="flex gap-1">
            <UButton
              size="xs"
              :variant="mode === 'live' ? 'solid' : 'outline'"
              @click="mode = 'live'"
            >{{ t('scoring.mode.live') }}</UButton>
            <UButton
              size="xs"
              :variant="mode === 'post' ? 'solid' : 'outline'"
              @click="mode = 'post'"
            >{{ t('scoring.mode.post') }}</UButton>
          </div>

          <!-- Score-only: minimal capture, no player attribution (spec §3) -->
          <UButton
            size="xs"
            :variant="scoreOnly ? 'solid' : 'outline'"
            :color="scoreOnly ? 'warning' : 'neutral'"
            :disabled="!canScore"
            :title="t('scoring.score_only.hint')"
            @click="scoreOnly = !scoreOnly"
          >{{ t('scoring.score_only.label') }}</UButton>
          <!-- Live sync indicator: the console follows this match's Mercure topics, so a
               change made on another terminal (or by the hardware relay) lands here. -->
          <UBadge
            v-if="isLive"
            size="xs"
            variant="soft"
            :color="liveSync.status.value === 'connected' ? 'success' : liveSync.status.value === 'error' ? 'warning' : 'neutral'"
            :title="remoteSynced ? t('scoring.sync.remote_change') : t('scoring.sync.' + liveSync.status.value)"
            :class="remoteSynced ? 'animate-pulse ring-2 ring-primary-500' : ''"
          >
            <UIcon :name="liveSync.status.value === 'connected' ? 'i-heroicons-signal' : 'i-heroicons-signal-slash'" />
          </UBadge>

          <!-- Full-screen displays (same origin → BroadcastChannel keeps them in sync) -->
          <UButton
            v-if="isLive"
            size="xs"
            variant="ghost"
            icon="i-heroicons-tv"
            :aria-label="t('scoring.display.scoreboard')"
            @click="broadcast.openScoreboard(matchId)"
          />
          <UButton
            v-if="isLive"
            size="xs"
            variant="ghost"
            icon="i-heroicons-clock"
            :aria-label="t('scoring.display.shotclock')"
            @click="broadcast.openShotclock(matchId)"
          />
          <UButton
            size="xs"
            variant="ghost"
            icon="i-heroicons-cog-6-tooth"
            :aria-label="t('scoring.shortcuts.title')"
            @click="shortcutsOpen = true"
          />
          <UBadge :color="scoringStore.isLocked ? 'error' : 'success'">
            {{ scoringStore.isLocked ? t('scoring.locked') : t('scoring.status.' + match.statut) }}
          </UBadge>
          <UButton
            v-if="canValidate"
            :icon="scoringStore.isLocked ? 'i-heroicons-lock-closed' : 'i-heroicons-lock-open'"
            :color="scoringStore.isLocked ? 'error' : 'neutral'"
            variant="soft"
            @click="toggleLock"
          />
        </div>
      </div>

      <!-- ══ Paramètres view (before kick-off / control) ══ -->
      <ScoringSettingsPanel
        v-if="view === 'settings'"
        :match="match"
        :players-a="scoringStore.playersA"
        :players-b="scoringStore.playersB"
        :can-score="canScore"
        :can-manage-players="canScore"
        @update-player="updatePlayer"
        @remove-player="removePlayer"
        @reload-players="reloadPlayers"
        @save-officials="saveOfficials"
        @load-match="loadOtherMatch"
      />

      <!-- ══ Déroulement view ══ -->
      <template v-else>
      <!-- Score — in score-only mode, ±1 buttons make it the whole entry surface -->
      <div class="flex items-center justify-center gap-6 py-4 bg-header-50 rounded-lg">
        <div class="text-right flex-1">
          <div class="font-semibold">{{ match.equipeA }}</div>
          <div v-if="scoreOnly" class="flex gap-1 justify-end mt-1">
            <UButton size="xs" variant="outline" :disabled="!canScore" @click="bumpScore('A', -1)">−1</UButton>
            <UButton size="xs" :disabled="!canScore" @click="bumpScore('A', 1)">+1</UButton>
          </div>
        </div>
        <div class="text-4xl font-mono font-bold tabular-nums">
          {{ scoringStore.scoreA }} - {{ scoringStore.scoreB }}
        </div>
        <div class="text-left flex-1">
          <div class="font-semibold">{{ match.equipeB }}</div>
          <div v-if="scoreOnly" class="flex gap-1 mt-1">
            <UButton size="xs" :disabled="!canScore" @click="bumpScore('B', 1)">+1</UButton>
            <UButton size="xs" variant="outline" :disabled="!canScore" @click="bumpScore('B', -1)">−1</UButton>
          </div>
        </div>
      </div>

      <!-- Game clock + shotclock + break (live mode only) -->
      <div v-if="isLive" class="flex flex-wrap items-start justify-center gap-10">
        <div class="flex flex-col items-center gap-2">
          <div
            class="text-5xl font-mono font-bold tabular-nums px-6 py-2 rounded-lg"
            :class="isRunning ? 'text-success-600' : 'text-header-900'"
          >
            {{ clockDisplay }}
          </div>
          <div class="flex gap-1">
            <UButton size="xs" variant="ghost" :disabled="!canScore" @click="adjustClock(-10)">−10</UButton>
            <UButton size="xs" variant="ghost" :disabled="!canScore" @click="adjustClock(-1)">−1</UButton>
            <UButton size="xs" variant="ghost" :disabled="!canScore" @click="adjustClock(1)">+1</UButton>
            <UButton size="xs" variant="ghost" :disabled="!canScore" @click="adjustClock(10)">+10</UButton>
          </div>
        </div>

        <!-- Shotclock (chronomètre de tir): 3 commands — 60 s / 40 s / arrêt (spec §6.5) -->
        <ScoringShotclock
          :display="shotclock.display.value"
          :state="shotclock.state.value"
          :durations="scoringStore.config.shotclockDurations"
          :offensive-rebound-enabled="scoringStore.config.shotclockOffensiveReboundEnabled"
          :masked="shotclockMasked"
          :can-score="canScore"
          @start="shotclockStart"
          @stop="shotclockStop"
          @adjust="shotclockAdjust"
          @test-sound="buzzer.test()"
        />

        <!-- Inter-period break — indicative countdown (spec §7.5) -->
        <div v-if="breakRemaining !== null" class="flex flex-col items-center gap-1">
          <div class="text-xs uppercase tracking-wide text-header-600">{{ t('scoring.break.title') }}</div>
          <div class="text-4xl font-mono font-bold tabular-nums text-amber-600">{{ breakDisplay }}</div>
          <UButton size="xs" variant="ghost" :disabled="!canScore" @click="stopBreak()">
            {{ t('scoring.break.skip') }}
          </UButton>
        </div>
      </div>

      <!-- Active penalties A/B (spec §7.4) — live mode, shown when at least one runs -->
      <ScoringPenalties
        v-if="isLive && penalties.penalties.value.length"
        :penalties="penalties.penalties.value"
        :team-a-name="match.equipeA"
        :team-b-name="match.equipeB"
        :can-score="canScore"
        @remove="removePenalty"
      />

      <!-- Status (cyclic badge) + Period (advance/direct) + Timer -->
      <div class="flex flex-wrap items-center justify-between gap-2">
        <ScoringStatusBadge
          :status="match.statut"
          :can-cycle="canScore"
          @change="setStatus"
        />
        <ScoringPeriodSelector
          :period="match.periode"
          :type="match.type"
          :can-change="canScore"
          :score-level="scoreLevel"
          :shootout-enabled="scoringStore.config.shootoutEnabled"
          @change="setPeriod"
        />
        <div v-if="isLive" class="flex gap-1">
          <UButton size="xs" icon="i-heroicons-play" :disabled="!canScore" @click="timer('run')">{{ t('scoring.timer.start') }}</UButton>
          <UButton size="xs" icon="i-heroicons-pause" :disabled="!canScore" @click="timer('stop')">{{ t('scoring.timer.pause') }}</UButton>
          <UButton size="xs" icon="i-heroicons-arrow-uturn-left" variant="outline" :disabled="!canScore" @click="timer('RAZ')">{{ t('scoring.timer.reset') }}</UButton>
        </div>
      </div>

      <!-- Teams + events — hidden in score-only mode (no player attribution, spec §3) -->
      <div v-if="!scoreOnly" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div v-for="(players, team) in { A: scoringStore.playersA, B: scoringStore.playersB }" :key="team">
          <h2 class="font-semibold mb-2">{{ team === 'A' ? match.equipeA : match.equipeB }}</h2>
          <div class="space-y-1">
            <button
              v-for="p in players" :key="p.matric"
              type="button"
              class="w-full flex items-center gap-2 px-2 py-1 rounded text-left text-sm border"
              :class="selected?.player.matric === p.matric
                ? 'border-primary-500 bg-primary-50'
                : 'border-header-200 hover:bg-header-50'"
              :disabled="!canScore"
              @click="selectPlayer(team as TeamSide, p)"
            >
              <span class="font-mono w-6 text-center">{{ p.numero }}</span>
              <span class="flex-1">{{ p.nom.toUpperCase() }} {{ p.prenom }}</span>
              <!-- Exclusion markers: 🔴 = replacement at penalty end, ⬛ = out to the end -->
              <span v-if="excludedToken.get(String(p.matric))">{{ excludedToken.get(String(p.matric)) }}</span>
              <UBadge v-if="p.capitaine === 'C'" size="xs" variant="soft">Cap.</UBadge>
              <UBadge v-else-if="p.capitaine === 'E'" size="xs" variant="soft" color="neutral">Coach</UBadge>
            </button>
          </div>
        </div>
      </div>

      <!-- Event entry zone: time/period/reason + buttons (present in live AND post-match) -->
      <div v-if="!scoreOnly" class="border-t pt-4 space-y-3">
        <div v-if="editingUid" class="flex items-center gap-2 text-sm text-primary-600">
          <UIcon name="i-heroicons-pencil-square" />
          {{ t('scoring.edit.title') }}
          <UButton size="xs" variant="ghost" @click="cancelEdit">{{ t('scoring.edit.cancel') }}</UButton>
        </div>

        <div class="flex flex-wrap items-end gap-3 justify-center">
          <!-- Period -->
          <div>
            <label class="block text-xs text-header-600 mb-1">{{ t('scoring.time.period') }}</label>
            <USelect v-model="eventPeriod" :items="periodItems" value-key="value" :disabled="!canScore" size="sm" class="w-36" />
          </div>
          <!-- Event time -->
          <div>
            <label class="block text-xs text-header-600 mb-1">{{ t('scoring.time.label') }}</label>
            <div class="flex items-center gap-1">
              <UButton size="xs" variant="ghost" :disabled="!canScore" @click="adjustEventTime(-60)">−60</UButton>
              <UButton size="xs" variant="ghost" :disabled="!canScore" @click="adjustEventTime(-10)">−10</UButton>
              <UButton size="xs" variant="ghost" :disabled="!canScore" @click="adjustEventTime(-1)">−1</UButton>
              <UInput v-model="eventTime" :disabled="!canScore" size="sm" class="w-20 font-mono text-center" />
              <UButton size="xs" variant="ghost" :disabled="!canScore" @click="adjustEventTime(1)">+1</UButton>
              <UButton size="xs" variant="ghost" :disabled="!canScore" @click="adjustEventTime(10)">+10</UButton>
              <UButton size="xs" variant="ghost" :disabled="!canScore" @click="adjustEventTime(60)">+60</UButton>
            </div>
          </div>
          <!-- Reason (cards) -->
          <div>
            <label class="block text-xs text-header-600 mb-1">{{ t('scoring.reason.label') }}</label>
            <USelect
              v-model="eventReason"
              :items="reasonCodes.map(c => ({ label: c === '' ? t('scoring.reason.none') : t('scoring.reason.' + c), value: c }))"
              :disabled="!canScore"
              size="sm"
              class="w-48"
            />
          </div>
        </div>

        <!-- Event buttons -->
        <div class="flex flex-wrap gap-2 justify-center">
          <UButton
            v-for="evt in eventCodes" :key="evt.code"
            :color="evt.color as any"
            :disabled="!canScore || (!selected && !editingUid)"
            @click="commitEvent(evt.code)"
          >{{ t(evt.labelKey) }}</UButton>
        </div>
      </div>

      <!-- Card progression alert (spec §7.4) — the operator can record anyway -->
      <AdminConfirmModal
        :open="cardWarning !== null"
        variant="warning"
        :title="t('scoring.card_progression.title')"
        :message="t('scoring.card_progression.' + (cardWarning?.violation ?? 'card_not_higher'))
          + ' ' + t('scoring.card_progression.confirm')"
        :confirm-text="t('scoring.edit.save')"
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
        @confirm="() => { goldenGoalOpen = false; setStatus('END') }"
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

      <!-- Keyboard shortcuts settings (per-device preference) -->
      <ScoringShortcutsModal
        :open="shortcutsOpen"
        :bindings="bindings"
        @close="shortcutsOpen = false"
        @set="setBinding"
        @reset="resetDefaults"
      />

      <!-- Events history (editable) — symmetric A | time | B on wide screens, list on mobile -->
      <ScoringEventHistory
        v-if="scoringStore.events.length"
        class="border-t pt-4"
        :events="scoringStore.events"
        :team-a-name="match.equipeA"
        :team-b-name="match.equipeB"
        :editing-uid="editingUid"
        :can-score="canScore"
        @edit="startEdit"
        @remove="removeEvent"
      />
      </template>
    </div>

    <div v-else class="text-center text-header-900 py-12">
      {{ t('scoring.not_found') }}
    </div>
  </div>
</template>
