<script setup lang="ts">
import type { Period, MatchStatus, ScoringPlayer, ScoringEvent, ScoringEventCode, TeamSide } from '~/types/scoring'

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

// ─── Event time/reason entry (period + MM:SS) ───
// In live mode it is pre-filled from the clock; in post-match it is typed/edited by hand.
const eventPeriod = ref<Period>('M1')
const eventTime = ref('00:00')
const eventReason = ref('')
// When set, the event buttons commit an UPDATE of this existing event instead of a new one.
const editingUid = ref<string | null>(null)

// ─── Game clock (easytimer) ───
const { display: clockDisplay, gameTime, elapsed, isRunning, setPeriod: timerSetPeriod, start: timerStart, stop: timerStop, reset: timerReset, restoreFromServer } =
  useTimer({
    onTargetReached: () => {
      // Buzzer at period end; persist the stop server-side
      void scoringStore.setTimer('stop', { startTime: elapsed.value, runTime: 0, maxTime: scoringStore.currentPeriodDuration })
    }
  })

// In live mode keep the event-time field tracking the clock (unless editing a row).
watch(gameTime, (v) => {
  if (isLive.value && !editingUid.value) eventTime.value = v
})

onMounted(async () => {
  if (!canView.value) return
  try {
    await scoringStore.load(matchId.value)
    // Default mode from status, period field from the match's current period.
    mode.value = scoringStore.match?.statut === 'END' ? 'post' : 'live'
    eventPeriod.value = (scoringStore.match?.periode ?? 'M1') as Period
    // Card reason pre-selected for fast entry (spec §0.9 — Autre/Non précisé)
    eventReason.value = scoringStore.config.defaultCardReason
    // Restore the clock from kp_chrono if a state was persisted, else start fresh.
    const state = await scoringStore.loadTimerState()
    if (state && state.action) {
      restoreFromServer({
        action: state.action,
        maxTime: state.maxTime || scoringStore.currentPeriodDuration,
        // We persist the elapsed seconds in start_time (see store.setTimer)
        elapsed: state.startTime ?? 0,
        startTimeServer: state.startTimeServer ?? undefined,
        nowServer: state.nowServer
      })
    } else {
      timerSetPeriod(scoringStore.currentPeriodDuration)
    }
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
    // Golden goal (spec §7.5): a goal during overtime of a type-E match ends it — offer
    // the closure right away (Statut → END triggers the kp_* consolidation server-side).
    if (code === 'B' && goalEndsMatch(match.value.type, eventPeriod.value) && !scoreLevel.value) {
      goldenGoalOpen.value = true
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

      <!-- Score -->
      <div class="flex items-center justify-center gap-6 py-4 bg-header-50 rounded-lg">
        <div class="text-right flex-1">
          <div class="font-semibold">{{ match.equipeA }}</div>
        </div>
        <div class="text-4xl font-mono font-bold tabular-nums">
          {{ scoringStore.scoreA }} - {{ scoringStore.scoreB }}
        </div>
        <div class="text-left flex-1">
          <div class="font-semibold">{{ match.equipeB }}</div>
        </div>
      </div>

      <!-- Game clock + adjust (live mode only) -->
      <div v-if="isLive" class="flex flex-col items-center gap-2">
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

      <!-- Teams + events -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
              <UBadge v-if="p.capitaine === 'C'" size="xs" variant="soft">Cap.</UBadge>
              <UBadge v-else-if="p.capitaine === 'E'" size="xs" variant="soft" color="neutral">Coach</UBadge>
            </button>
          </div>
        </div>
      </div>

      <!-- Event entry zone: time/period/reason + buttons (present in live AND post-match) -->
      <div class="border-t pt-4 space-y-3">
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
    </div>

    <div v-else class="text-center text-header-900 py-12">
      {{ t('scoring.not_found') }}
    </div>
  </div>
</template>
