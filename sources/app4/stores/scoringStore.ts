import { defineStore } from 'pinia'
import type {
  ScoringMatch,
  ScoringPlayer,
  ScoringEvent,
  Penalty,
  Period,
  MatchStatus,
  TeamSide,
  ScoringConfig,
  LiveClock
} from '~/types/scoring'

/**
 * Scoring store — live match console state (Phase 1: online, api2-backed).
 *
 * Loads a match via GET /admin/games/{id} and its players via
 * GET /admin/matches/{id}/players?teamCode=A|B (same endpoint as presence).
 * Mutating actions POST to api2 ScoringController (/admin/scoring/*) with optimistic
 * update + rollback on error. See DOC/specs/PAGE_SCORING.md.
 */

/**
 * Central match configuration defaults (spec §6.2 — «Configuration du match centralisée»).
 * Single source for every adjustable value; later hydrated from the competition settings
 * (plan lot 6) without touching any call site. Factory (not a shared const) so each store
 * instance mutates its own copy.
 */
function defaultScoringConfig(): ScoringConfig {
  return {
    // M1/M2 = 10 min; every overtime P{n} = 5 min (ICF & FFCK — spec §0.9); TB = 3 min
    periodDurations: { M1: 600, M2: 600, P: 300, TB: 180 },
    // Indicative inter-period breaks: 3' halftime, 3' before overtimes, 1' between them
    breakDurations: { halftime: 180, beforeOvertime: 180, betweenOvertimes: 60 },
    // 2027 rules applied from the start: 60 s engagement / 40 s offensive rebound
    shotclockDurations: { full: 60, offensiveRebound: 40 },
    shotclockOffensiveReboundEnabled: true,
    allowTimerAdjustWhileRunning: false,
    penaltyDuration: 120,
    overtimeUnlimited: true,
    shootoutEnabled: false,
    stopClockOnGoal: false,
    defaultCardReason: 'unknown'
  }
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
  clocks?: LiveClock[]
  /** Server seconds since midnight — drift basis for restoring running clocks */
  nowServer?: number
  /** Mercure subscribe URL + topic base of this match (addressing is server-owned) */
  mercureUrl?: string
  topicBase?: string
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
  config: ScoringConfig
  /** Live clocks snapshot from GET /admin/scoring/state (shotclock, penalties, break…) */
  liveClocks: LiveClock[]
  /** Mercure subscribe URL and topic base of the match (from GET /state) */
  mercureUrl: string
  topicBase: string
  /** Active writing source of the match (plan §4.1) */
  activeSource: 'MANUAL' | 'HARDWARE' | 'SCORE_ONLY' | 'IMPORT'
  /**
   * Epoch ms of the last LOCAL mutation. The live sync uses it to ignore the echo of
   * our own writes coming back on the Mercure channel (see useScoringLiveSync).
   */
  lastMutationAt: number
  loading: boolean
  initialized: boolean
}

/**
 * `useApi()` is a composable: it calls `useI18n()`, which throws
 * ("Must be called at the top of a `setup` function") outside a setup context.
 * Store actions fired from a callback (timer tick, keyboard shortcut, penalty
 * expiry…) are exactly that context, so the `apiInstance` fallback below could not
 * create one on the fly.
 *
 * The page calls `setApi()` once during setup; every action then reuses that
 * instance. Kept module-level (not in state) because it holds functions and has no
 * business being made reactive.
 */
let sharedApi: ReturnType<typeof useApi> | null = null

export const useScoringStore = defineStore('scoring', {
  state: (): ScoringState => ({
    matchId: null,
    match: null,
    playersA: [],
    playersB: [],
    events: [],
    penalties: [],
    config: defaultScoringConfig(),
    liveClocks: [],
    mercureUrl: '',
    topicBase: '',
    activeSource: 'MANUAL',
    lastMutationAt: 0,
    loading: false,
    initialized: false
  }),

  getters: {
    hasMatch: (state): boolean => state.match !== null,

    /** Locked when the match is validated (Validation === 'O') */
    isLocked: (state): boolean => state.match?.validation === 'O',

    /** Competition is ended — no modifications allowed */
    isCompetitionEnded: (state): boolean => state.match?.competitionStatut === 'END',

    /** Duration (seconds) of the currently selected period (P{n} share config.periodDurations.P) */
    currentPeriodDuration: (state): number => {
      const p = state.match?.periode as Period | null | undefined
      return periodDurationOf(p ?? 'M1', state.config.periodDurations)
    },

    scoreA: (state): number => Number(state.match?.scoreA ?? 0),
    scoreB: (state): number => Number(state.match?.scoreB ?? 0)
  },

  actions: {
    /**
     * Hand the store an api instance built during component setup, so actions called
     * later from callbacks (outside setup) can still reach the API. Call it once, from
     * the page's setup.
     */
    setApi(api: ReturnType<typeof useApi>) {
      sharedApi = api
    },

    /** Load match header + both team rosters */
    async load(matchId: number, apiInstance?: ReturnType<typeof useApi>) {
      const api = apiInstance ?? sharedApi ?? useApi()
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
          if (typeof liveState.scoreA === 'number') {
            // The console keeps the displayed score (scoreA/B) aligned with the live goal
            // count during the match; the official score is only frozen at validation.
            match.scoreDetailA = String(liveState.scoreA)
            match.scoreA = String(liveState.scoreA)
          }
          if (typeof liveState.scoreB === 'number') {
            match.scoreDetailB = String(liveState.scoreB)
            match.scoreB = String(liveState.scoreB)
          }
          this.liveClocks = liveState.clocks ?? []
          if (liveState.activeSource) this.activeSource = liveState.activeSource
        }
        this.mercureUrl = liveState.mercureUrl ?? ''
        this.topicBase = liveState.topicBase ?? ''

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
      this.lastMutationAt = Date.now()
      const api = apiInstance ?? sharedApi ?? useApi()
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
      this.lastMutationAt = Date.now()
      const api = apiInstance ?? sharedApi ?? useApi()
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
      this.lastMutationAt = Date.now()
      const api = apiInstance ?? sharedApi ?? useApi()
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
      this.lastMutationAt = Date.now()
      const api = apiInstance ?? sharedApi ?? useApi()
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

    /**
     * Re-read the canonical live state and re-apply it on top of the local one.
     * Used when Mercure signals a change made on ANOTHER terminal (plan lot 3): the
     * console refetches instead of merging a diff, so it can never drift from the
     * server. Cheap thanks to the ETag (304 when nothing moved).
     */
    async refreshLiveState(apiInstance?: ReturnType<typeof useApi>) {
      if (!this.match) return
      const api = apiInstance ?? sharedApi ?? useApi()
      const [liveState, resEvents] = await Promise.all([
        api.get<ScoringLiveStateResponse>(`/admin/scoring/state/${this.match.id}`),
        api.get<ScoringEventsResponse>(`/admin/scoring/events/${this.match.id}`)
      ])

      if (liveState.exists && this.match) {
        if (liveState.statut) this.match.statut = liveState.statut
        if (liveState.periode) this.match.periode = liveState.periode
        if (typeof liveState.scoreA === 'number') {
          this.match.scoreDetailA = String(liveState.scoreA)
          this.match.scoreA = String(liveState.scoreA)
        }
        if (typeof liveState.scoreB === 'number') {
          this.match.scoreDetailB = String(liveState.scoreB)
          this.match.scoreB = String(liveState.scoreB)
        }
        this.liveClocks = liveState.clocks ?? []
        if (liveState.activeSource) this.activeSource = liveState.activeSource
      }

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
    },

    /**
     * Update a player's jersey number and/or captain status (spec §7.2/§7.8).
     * Reuses the presence endpoint (same kp_match_joueur row, already journalized) —
     * no duplicate write path for the same data.
     */
    async updatePlayer(
      team: TeamSide,
      matric: number,
      patch: { numero?: number, capitaine?: '-' | 'C' | 'E' },
      apiInstance?: ReturnType<typeof useApi>
    ) {
      if (!this.match) return
      this.lastMutationAt = Date.now()
      const api = apiInstance ?? sharedApi ?? useApi()
      const list = team === 'A' ? this.playersA : this.playersB
      const idx = list.findIndex(p => p.matric === matric)
      if (idx === -1) return
      const previous = { ...list[idx] }
      list[idx] = { ...list[idx], ...patch }
      try {
        await api.patch(`/admin/matches/${this.match.id}/players/${matric}`, { teamCode: team, ...patch })
      } catch (error) {
        list[idx] = previous
        throw error
      }
    },

    /** Remove a player from the match composition (spec §7.2). */
    async removePlayer(team: TeamSide, matric: number, apiInstance?: ReturnType<typeof useApi>) {
      if (!this.match) return
      this.lastMutationAt = Date.now()
      const api = apiInstance ?? sharedApi ?? useApi()
      const list = team === 'A' ? this.playersA : this.playersB
      const idx = list.findIndex(p => p.matric === matric)
      if (idx === -1) return
      const [removed] = list.splice(idx, 1)
      try {
        await api.del(`/admin/matches/${this.match.id}/players`, {
          teamCode: team,
          matricIds: [matric]
        })
      } catch (error) {
        list.splice(idx, 0, removed)
        throw error
      }
    },

    /**
     * Reload a team's composition from the presence sheet (« Recharger les joueurs
     * présents », spec §7.2) — replaces the current list, then refetches it.
     */
    async reloadPresentPlayers(team: TeamSide, apiInstance?: ReturnType<typeof useApi>) {
      if (!this.match) return
      this.lastMutationAt = Date.now()
      const api = apiInstance ?? sharedApi ?? useApi()
      await api.post(`/admin/matches/${this.match.id}/players/initialize`, { teamCode: team })
      const res = await api.get<MatchPlayersResponse>(
        `/admin/matches/${this.match.id}/players`, { teamCode: team }
      )
      const players = res.players.map(p => ({ ...p, team }))
      if (team === 'A') this.playersA = players
      else this.playersB = players
    },

    /**
     * Promote the active writing source of the match (plan §4.1). Used when the console
     * switches to score-only, or hands over to the hardware relay: a single source writes
     * at any time, the others are journalized but rejected (409).
     */
    async setSource(
      source: 'MANUAL' | 'HARDWARE' | 'SCORE_ONLY' | 'IMPORT',
      apiInstance?: ReturnType<typeof useApi>
    ) {
      if (!this.match) return
      this.lastMutationAt = Date.now()
      const api = apiInstance ?? sharedApi ?? useApi()
      await api.put(`/admin/scoring/source/${this.match.id}`, { source })
      this.activeSource = source
    },

    /** Update the match officials (referees, secretary, timekeepers, line judges). */
    async setOfficials(officials: Record<string, string | number | null>, apiInstance?: ReturnType<typeof useApi>) {
      if (!this.match) return
      this.lastMutationAt = Date.now()
      const api = apiInstance ?? sharedApi ?? useApi()
      await api.put(`/admin/scoring/officials/${this.match.id}`, { officials })
    },

    /**
     * Resolve a short game number (Numero_ordre) into a match id, relative to the
     * current match (same gameday → same competition → same event). Returns null when
     * unknown or ambiguous.
     */
    async resolveShortNumber(number: number, apiInstance?: ReturnType<typeof useApi>): Promise<number | null> {
      if (!this.match) return null
      const api = apiInstance ?? sharedApi ?? useApi()
      try {
        const res = await api.get<{ id: number }>(`/admin/scoring/resolve/${this.match.id}/${number}`)
        return res.id ?? null
      } catch {
        return null
      }
    },

    /** Read the persisted timer state (for clock restore on reload) */
    async loadTimerState(apiInstance?: ReturnType<typeof useApi>) {
      if (!this.match) return null
      const api = apiInstance ?? sharedApi ?? useApi()
      return api.get<{
        action: 'run' | 'stop' | null
        startTime?: number
        startTimeServer?: number | null
        runTime?: number
        maxTime?: number
        nowServer: number
      }>(`/admin/scoring/gameTimer/${this.match.id}`)
    },

    /**
     * Control one match clock — persisted to scoring_live_clock. `kind` defaults to the
     * main game clock; the same endpoint drives the shotclock, penalties and inter-period
     * breaks (spec §0.5 — N clocks per match). Seconds in the payload, ms server-side.
     */
    async setTimer(
      action: 'run' | 'stop' | 'RAZ',
      params: {
        startTime?: number
        runTime?: number
        maxTime?: number
        kind?: LiveClock['kind']
        team?: '' | TeamSide
        slot?: number
        playerId?: string | null
        cardCode?: string | null
      } = {},
      apiInstance?: ReturnType<typeof useApi>
    ) {
      if (!this.match) return
      this.lastMutationAt = Date.now()
      const api = apiInstance ?? sharedApi ?? useApi()
      await api.put(`/admin/scoring/gameTimer/${this.match.id}`, { params: { action, ...params } })
    },

    /** Validate / lock toggle (reuses AdminGames endpoint) */
    async toggleValidation(apiInstance?: ReturnType<typeof useApi>) {
      if (!this.match) return
      this.lastMutationAt = Date.now()
      const api = apiInstance ?? sharedApi ?? useApi()
      const res = await api.patch<{ validation: string }>(
        `/admin/games/${this.match.id}/validation`
      )
      this.match.validation = res.validation
    }
  }
})
