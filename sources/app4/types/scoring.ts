/**
 * Types for Scoring (live match console)
 *
 * Replaces the legacy "feuille de marque" (FeuilleMarque2/3.php) and the standalone
 * app3 prototype. See DOC/specs/PAGE_SCORING.md.
 *
 * Naming convention:
 * - "Scoring"          = manual KPI console (this module)
 * - "Hardware Scoring" = live data captured from hardware (matériel propriétaire or equivalent), Phase 3
 */

/**
 * Match period. Overtimes are UNBOUNDED (spec §0.6 — golden goal: as many `P{n}` as
 * needed while the score stays level); the template literal keeps P1/P2 valid while
 * removing the legacy cap.
 */
export type Period = 'M1' | 'M2' | `P${number}` | 'TB'

/** Match status */
export type MatchStatus = 'ATT' | 'ON' | 'END'

/** Match type: C = classement (draw allowed), E = elimination (winner required) */
export type MatchType = 'C' | 'E'

/** Team side */
export type TeamSide = 'A' | 'B'

/**
 * Event code stored in kp_match_detail.Id_evt_match / scoring_live_event.code.
 * B = but (goal), V/J = green/yellow card, R = red card (cumul),
 * D = carton d'exclusion définitive — BLACK card under the 2027 rules ("Ejection card",
 * spec §0.9); the code stays D for storage compatibility, only labels/colors changed.
 */
export type ScoringEventCode = 'B' | 'V' | 'J' | 'R' | 'D'

/**
 * Match header — shape returned by GET /admin/games/{id} (AdminGamesController::get).
 * Mirrors the camelCase payload; only the fields used by the scoring console are typed here.
 */
export interface ScoringMatch {
  id: number
  idJournee: number
  numeroOrdre: number | null
  dateMatch: string
  heureMatch: string
  libelle: string | null
  terrain: string
  validation: string // 'O' = locked, else unlocked
  statut: MatchStatus
  type: MatchType
  periode: Period | null
  scoreA: string | null
  scoreB: string | null
  scoreDetailA: string | null
  scoreDetailB: string | null
  idEquipeA: number | null
  equipeA: string | null
  idEquipeB: number | null
  equipeB: string | null
  codeCompetition: string | null
  competitionStatut: 'ATT' | 'ON' | 'END'
  phase: string | null
}

/**
 * Player in the match composition.
 * Sourced from GET /admin/matches/{id}/players?teamCode=A|B (same endpoint as presence).
 */
export interface ScoringPlayer {
  matric: number
  nom: string
  prenom: string
  numero: number
  capitaine: '-' | 'C' | 'E' // - = player, C = captain, E = coach
  team: TeamSide
}

/**
 * Match event (goal or card) stored in kp_match_detail.
 */
export interface ScoringEvent {
  uid?: string // unique id (auto-generated server-side if absent)
  code: ScoringEventCode
  period: Period
  tpsJeu: string // game time "MM:SS"
  team: TeamSide
  player: string // licence number ("0" for a team-level event)
  number: number | null
  reason: string // card reason code (motif), '' if none
  nom?: string // player last name (enriched server-side when loading existing events)
  prenom?: string // player first name (enriched server-side)
}

/**
 * Penalty (exclusion) with countdown — UI/timer logic implemented in Phase 2.
 */
export interface Penalty {
  id: number
  team: TeamSide
  type: string // card type triggering the exclusion
  startTime: number // seconds, game-clock based
  duration: number // seconds
}

/**
 * Period durations in seconds. `P` is the duration shared by EVERY overtime P{n}
 * (spec §0.6 — 5 min in both ICF and FFCK rules, §0.9).
 */
export interface PeriodDurations {
  M1: number
  M2: number
  P: number
  TB: number
}

/** Inter-period break durations in seconds (spec §7.5 / plan §4.10 — indicative clocks). */
export interface BreakDurations {
  /** Between M1 and M2 (halftime) */
  halftime: number
  /** Between M2 and P1 (before overtimes) */
  beforeOvertime: number
  /** Between two overtimes (P{n} → P{n+1}) */
  betweenOvertimes: number
}

/** Shotclock durations in seconds (spec §6.5 — 2027 rules applied from the start). */
export interface ShotclockDurations {
  /** Start/reset at engagement (new possession) */
  full: number
  /** Start/reset after an offensive rebound */
  offensiveRebound: number
}

/**
 * One live clock row of scoring_live_clock, as served by GET /admin/scoring/state
 * (4-value model, plan §3.1: everything needed to recompute the display locally).
 */
export interface LiveClock {
  id: string
  kind: 'GAME' | 'SHOTCLOCK' | 'PENALTY' | 'BREAK'
  team: '' | TeamSide
  slot: number
  playerId: string | null
  cardCode: string | null
  initMs: number
  elapsedMs: number
  startedAt: string | null
  running: boolean
}

/**
 * Central match configuration (spec §6.2 «Configuration du match centralisée») — the
 * single place for every adjustable value. Held by scoringStore.config, initialized from
 * DEFAULT_SCORING_CONFIG; later hydrated from the competition settings (plan lot 6)
 * without changing any call site.
 */
export interface ScoringConfig {
  periodDurations: PeriodDurations
  breakDurations: BreakDurations
  shotclockDurations: ShotclockDurations
  /** 40 s offensive-rebound reset active (true by default — 2027 rules, spec §0.9) */
  shotclockOffensiveReboundEnabled: boolean
  /** Fine clock adjust allowed while running (hardware-sync use case only, spec §6.4) */
  allowTimerAdjustWhileRunning: boolean
  /** Card penalty duration in seconds (2 min) */
  penaltyDuration: number
  /** Unbounded golden-goal overtimes (regulation) */
  overtimeUnlimited: boolean
  /** Penalty shootout available (tournament option — competition setting, spec §7.5) */
  shootoutEnabled: boolean
  /** Automatic clock stop on goal (legacy option, off by default) */
  stopClockOnGoal: boolean
  /** Pre-selected card reason for fast entry (spec §0.9 — 'unknown' = Autre/Non précisé) */
  defaultCardReason: string
}
