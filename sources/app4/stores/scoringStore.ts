import { defineStore } from 'pinia'
import type {
  ScoringMatch,
  ScoringPlayer,
  ScoringEvent,
  Penalty,
  Period,
  MatchStatus,
  TeamSide,
  PeriodDurations
} from '~/types/scoring'

/**
 * Scoring store — live match console state (Phase 1: online, api2-backed).
 *
 * Loads a match via GET /admin/games/{id} and its players via
 * GET /admin/matches/{id}/players?teamCode=A|B (same endpoint as presence).
 * Mutating actions POST to api2 ScoringController (/admin/scoring/*) with optimistic
 * update + rollback on error. See DOC/specs/PAGE_SCORING.md.
 */

/** Default period durations in seconds */
const DEFAULT_PERIOD_DURATIONS: PeriodDurations = {
  M1: 600,
  M2: 600,
  P1: 180,
  P2: 180,
  TB: 180
}

/** Response shape of GET /admin/matches/{id}/players (subset we use) */
interface MatchPlayersResponse {
  players: Array<{
    matric: number
    nom: string
    prenom: string
    numero: number
    capitaine: '-' | 'C' | 'E'
  }>
}

/** Response shape of GET /admin/scoring/events/{id} */
interface ScoringEventsResponse {
  events: Array<{
    uid: string
    period: Period
    tpsJeu: string
    code: ScoringEvent['code']
    player: string
    number: number | null
    team: TeamSide
    reason: string | null
    nom: string | null
    prenom: string | null
  }>
}

/**
 * Response shape of GET /admin/scoring/state/{id} — canonical live state (subset we use).
 * Since the backend re-route (plan lot 1), the live status/period/scores of a match being
 * scored live in scoring_live_state, kp_match (served by /admin/games/{id}) only reflects
 * them after the end-of-match consolidation: the live state overlays the match on load.
 */
interface ScoringLiveStateResponse {
  exists: boolean
  tick?: number
  statut?: MatchStatus
  periode?: Period
  scoreA?: number
  scoreB?: number
  activeSource?: 'MANUAL' | 'HARDWARE' | 'SCORE_ONLY' | 'IMPORT'
}

/** Generate a uid in the same shape the backend produces (uniqid without dashes). */
function genUid(): string {
  return (Date.now().toString(16) + Math.random().toString(16).slice(2, 10)).replace(/-/g, '')
}

interface ScoringState {
  matchId: number | null
  match: ScoringMatch | null
  playersA: ScoringPlayer[]
  playersB: ScoringPlayer[]
  events: ScoringEvent[]
  penalties: Penalty[]
  periodDurations: PeriodDurations
  loading: boolean
  initialized: boolean
}

export const useScoringStore = defineStore('scoring', {
  state: (): ScoringState => ({
    matchId: null,
    match: null,
    playersA: [],
    playersB: [],
    events: [],
    penalties: [],
    periodDurations: { ...DEFAULT_PERIOD_DURATIONS },
    loading: false,
    initialized: false
  }),

  getters: {
    hasMatch: (state): boolean => state.match !== null,

    /** Locked when the match is validated (Validation === 'O') */
    isLocked: (state): boolean => state.match?.validation === 'O',

    /** Competition is ended — no modifications allowed */
    isCompetitionEnded: (state): boolean => state.match?.competitionStatut === 'END',

    /** Duration (seconds) of the currently selected period */
    currentPeriodDuration: (state): number => {
      const p = state.match?.periode as Period | null | undefined
      return p ? state.periodDurations[p] : state.periodDurations.M1
    },

    scoreA: (state): number => Number(state.match?.scoreA ?? 0),
    scoreB: (state): number => Number(state.match?.scoreB ?? 0)
  },

  actions: {
    /** Load match header + both team rosters */
    async load(matchId: number, apiInstance?: ReturnType<typeof useApi>) {
      const api = apiInstance ?? useApi()
      this.matchId = matchId
      this.loading = true
      this.initialized = false

      try {
        const match = await api.get<ScoringMatch>(`/admin/games/${matchId}`)
        const [resA, resB, resEvents, liveState] = await Promise.all([
          api.get<MatchPlayersResponse>(`/admin/matches/${matchId}/players`, { teamCode: 'A' }),
          api.get<MatchPlayersResponse>(`/admin/matches/${matchId}/players`, { teamCode: 'B' }),
          api.get<ScoringEventsResponse>(`/admin/scoring/events/${matchId}`),
          api.get<ScoringLiveStateResponse>(`/admin/scoring/state/${matchId}`)
        ])

        // Overlay the canonical live state on top of the kp_match snapshot: during a match
        // kp_* is only written at consolidation (Statut → END), so the live values win.
        if (liveState.exists) {
          if (liveState.statut) match.statut = liveState.statut
          if (liveState.periode) match.periode = liveState.periode
          if (typeof liveState.scoreA === 'number') match.scoreDetailA = String(liveState.scoreA)
          if (typeof liveState.scoreB === 'number') match.scoreDetailB = String(liveState.scoreB)
        }

        this.match = match
        this.playersA = resA.players.map(p => ({ ...p, team: 'A' as TeamSide }))
        this.playersB = resB.players.map(p => ({ ...p, team: 'B' as TeamSide }))
        this.events = resEvents.events.map(e => ({
          uid: e.uid,
          code: e.code,
          period: e.period,
          tpsJeu: e.tpsJeu,
          team: e.team,
          player: e.player,
          number: e.number,
          reason: e.reason ?? '',
          nom: e.nom ?? undefined,
          prenom: e.prenom ?? undefined
        }))
        this.initialized = true
      } catch (error) {
        console.error('Failed to load scoring match:', error)
        throw error
      } finally {
        this.loading = false
      }
    },

    /** Update a match parameter (score/status/period) via api2, optimistic + rollback */
    async setParam(param: string, value: string, apiInstance?: ReturnType<typeof useApi>) {
      if (!this.match) return
      const api = apiInstance ?? useApi()
      const previous = (this.match as Record<string, unknown>)[
        param.charAt(0).toLowerCase() + param.slice(1)
      ]
      // Optimistic local update for the mapped field
      this.applyParamLocally(param, value)
      try {
        await api.put(`/admin/scoring/gameParam/${this.match.id}`, { param, value })
      } catch (error) {
        // rollback
        if (previous !== undefined) this.applyParamLocally(param, String(previous))
        throw error
      }
    },

    /** Maps a backend param name to the local ScoringMatch field and assigns it */
    applyParamLocally(param: string, value: string) {
      if (!this.match) return
      switch (param) {
        case 'ScoreA': this.match.scoreA = value; break
        case 'ScoreB': this.match.scoreB = value; break
        case 'Statut': this.match.statut = value as MatchStatus; break
        case 'Periode': this.match.periode = value as Period; break
      }
    },

    setStatus(status: MatchStatus, api?: ReturnType<typeof useApi>) {
      return this.setParam('Statut', status, api)
    },

    setPeriod(period: Period, api?: ReturnType<typeof useApi>) {
      return this.setParam('Periode', period, api)
    },

    /** Add a match event (goal/card); score auto-incremented for goals */
    async addEvent(event: ScoringEvent, apiInstance?: ReturnType<typeof useApi>) {
      if (!this.match) return
      const api = apiInstance ?? useApi()
      // Assign a uid up front so optimistic state, server row and later edits all agree.
      if (!event.uid) event.uid = genUid()
      this.events.push(event)
      const wasGoal = event.code === 'B'
      const scoreField = event.team === 'A' ? 'scoreA' : 'scoreB'
      const prevScore = this.match[scoreField]
      if (wasGoal) {
        this.match[scoreField] = String(Number(prevScore ?? 0) + 1)
      }
      try {
        await api.put(`/admin/scoring/gameEvent/${this.match.id}`, {
          params: {
            action: 'add',
            uid: event.uid,
            period: event.period,
            tpsJeu: event.tpsJeu,
            code: event.code,
            player: event.player,
            number: event.number,
            team: event.team,
            reason: event.reason
          }
        })
        // Persist score increment server-side too
        if (wasGoal) {
          await this.setParam(event.team === 'A' ? 'ScoreA' : 'ScoreB', this.match[scoreField] ?? '0', api)
        }
      } catch (error) {
        // rollback
        this.events.pop()
        if (wasGoal) this.match[scoreField] = prevScore
        throw error
      }
    },

    /** Remove an event (by uid when known, else by period/player/code) */
    async removeEvent(event: ScoringEvent, apiInstance?: ReturnType<typeof useApi>) {
      if (!this.match) return
      const api = apiInstance ?? useApi()
      const idx = event.uid
        ? this.events.findIndex(e => e.uid === event.uid)
        : this.events.findIndex(
            e => e.period === event.period && e.player === event.player && e.code === event.code
          )
      const removed = idx >= 0 ? this.events.splice(idx, 1)[0] : null
      const wasGoal = event.code === 'B'
      const scoreField = event.team === 'A' ? 'scoreA' : 'scoreB'
      const prevScore = this.match[scoreField]
      if (wasGoal) {
        this.match[scoreField] = String(Math.max(0, Number(prevScore ?? 0) - 1))
      }
      try {
        await api.put(`/admin/scoring/gameEvent/${this.match.id}`, {
          params: {
            action: 'remove',
            uid: event.uid,
            period: event.period,
            player: event.player,
            code: event.code
          }
        })
        if (wasGoal) {
          await this.setParam(event.team === 'A' ? 'ScoreA' : 'ScoreB', this.match[scoreField] ?? '0', api)
        }
      } catch (error) {
        if (removed && idx >= 0) this.events.splice(idx, 0, removed)
        if (wasGoal) this.match[scoreField] = prevScore
        throw error
      }
    },

    /**
     * Edit an existing event in place (period/time/player/number/code/team/reason).
     * Recomputes both teams' scores from goal events afterwards (a goal may have been
     * added/removed/moved between teams). Central to post-match correction (spec §7.3).
     */
    async updateEvent(uid: string, patch: Partial<ScoringEvent>, apiInstance?: ReturnType<typeof useApi>) {
      if (!this.match) return
      const api = apiInstance ?? useApi()
      const idx = this.events.findIndex(e => e.uid === uid)
      if (idx < 0) return
      const previous = { ...this.events[idx] }
      const updated: ScoringEvent = { ...previous, ...patch, uid }
      this.events[idx] = updated
      this.recomputeScoresFromEvents()
      try {
        await api.put(`/admin/scoring/gameEvent/${this.match.id}`, {
          params: {
            action: 'update',
            uid,
            period: updated.period,
            tpsJeu: updated.tpsJeu,
            code: updated.code,
            player: updated.player,
            number: updated.number,
            team: updated.team,
            reason: updated.reason
          }
        })
        // Persist both scores (either side may have changed).
        await this.setParam('ScoreA', String(this.scoreA), api)
        await this.setParam('ScoreB', String(this.scoreB), api)
      } catch (error) {
        this.events[idx] = previous
        this.recomputeScoresFromEvents()
        throw error
      }
    },

    /** Recompute scoreA/scoreB from the current goal events (code 'B'). */
    recomputeScoresFromEvents() {
      if (!this.match) return
      let a = 0
      let b = 0
      for (const e of this.events) {
        if (e.code !== 'B') continue
        if (e.team === 'A') a++
        else if (e.team === 'B') b++
      }
      this.match.scoreA = String(a)
      this.match.scoreB = String(b)
    },

    /** Read the persisted timer state (for clock restore on reload) */
    async loadTimerState(apiInstance?: ReturnType<typeof useApi>) {
      if (!this.match) return null
      const api = apiInstance ?? useApi()
      return api.get<{
        action: 'run' | 'stop' | null
        startTime?: number
        startTimeServer?: number | null
        runTime?: number
        maxTime?: number
        nowServer: number
      }>(`/admin/scoring/gameTimer/${this.match.id}`)
    },

    /** Control the match timer (run/stop/RAZ) — persisted to kp_chrono */
    async setTimer(
      action: 'run' | 'stop' | 'RAZ',
      params: { startTime?: number; runTime?: number; maxTime?: number } = {},
      apiInstance?: ReturnType<typeof useApi>
    ) {
      if (!this.match) return
      const api = apiInstance ?? useApi()
      await api.put(`/admin/scoring/gameTimer/${this.match.id}`, { params: { action, ...params } })
    },

    /** Validate / lock toggle (reuses AdminGames endpoint) */
    async toggleValidation(apiInstance?: ReturnType<typeof useApi>) {
      if (!this.match) return
      const api = apiInstance ?? useApi()
      const res = await api.patch<{ validation: string }>(
        `/admin/games/${this.match.id}/validation`
      )
      this.match.validation = res.validation
    }
  }
})
