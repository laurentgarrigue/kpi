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

// Periods available depend on match type (C = no overtime unless needed)
const periods: Period[] = ['M1', 'M2', 'P1', 'P2', 'TB']
const eventCodes: { code: ScoringEventCode; labelKey: string; color: string }[] = [
  { code: 'B', labelKey: 'scoring.event.goal', color: 'primary' },
  { code: 'V', labelKey: 'scoring.event.card_green', color: 'success' },
  { code: 'J', labelKey: 'scoring.event.card_yellow', color: 'warning' },
  { code: 'R', labelKey: 'scoring.event.card_red', color: 'error' },
  { code: 'D', labelKey: 'scoring.event.card_red_def', color: 'error' }
]
// Card reasons (motifs) reused from FMV3 — '' = none
const reasonCodes = ['', 'r_pad', 'r_kt', 'r_ht', 'r_p', 'r_o', 'r_un', 'r_rep', 'unknown']

// Selected player for the next event
const selected = ref<{ team: TeamSide; player: ScoringPlayer } | null>(null)

const match = computed(() => scoringStore.match)
const loading = computed(() => scoringStore.loading)

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
        reason: eventReason.value
      })
      cancelEdit()
    } catch { /* toast handled */ }
    return
  }

  if (!selected.value) {
    toast.add({ title: t('common.error'), description: t('scoring.select_player_first'), color: 'warning' })
    return
  }
  const { team, player } = selected.value
  const event: ScoringEvent = {
    code,
    period: eventPeriod.value,
    tpsJeu: eventTime.value,
    team,
    player: String(player.matric),
    number: player.numero,
    reason: eventReason.value,
    nom: player.nom,
    prenom: player.prenom
  }
  try {
    await scoringStore.addEvent(event)
    selected.value = null
    eventReason.value = ''
  } catch { /* toast handled */ }
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
  eventReason.value = ''
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
  if (isLive.value) timerSetPeriod(scoringStore.periodDurations[p])
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
            <USelect v-model="eventPeriod" :items="periods" :disabled="!canScore" size="sm" class="w-28" />
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
