import type { TeamSide } from '~/types/scoring'

/**
 * Penalty clocks of the console (spec §7.4, rules corrected 2026-07-29):
 *
 *  - only V/J/R cards start a 2-minute clock (the black D card is a dry, definitive
 *    exclusion: no clock, no replacement until the end of the match);
 *  - at most 2 concurrent penalties per team (slots 1|2 — a team never drops below 3);
 *  - the clocks follow the game clock (suspendAll/resumeAll, like the shotclock);
 *  - on a conceded goal, the OLDEST liftable (V/J) penalty ends early — the player
 *    returns; an R penalty always runs its full 2 minutes (replacement at its end).
 *
 * The composable owns the countdown only; persistence (scoring_live_clock kind PENALTY)
 * and confirmations live in the page.
 */

export interface PenaltyClock {
  /** Emitter-side uuid — matches scoring_live_clock.id (idempotence, offline-ready). */
  id: string
  team: TeamSide
  slot: number
  playerId: string
  playerNumber: number | null
  cardCode: 'V' | 'J' | 'R'
  remainingMs: number
  running: boolean
}

const uuid = (): string =>
  (typeof crypto !== 'undefined' && 'randomUUID' in crypto)
    ? crypto.randomUUID()
    : `${Date.now().toString(16)}-${Math.random().toString(16).slice(2)}`

export function usePenalties(options: { onExpired?: (p: PenaltyClock) => void } = {}) {
  const penalties = ref<PenaltyClock[]>([])

  // Epoch instant of expiry per running clock (timestamp-based — no drift).
  const endAt = new Map<string, number>()
  let intervalId: ReturnType<typeof setInterval> | null = null

  const ensureTick = () => {
    intervalId ??= setInterval(tick, 200)
  }
  const clearTickIfIdle = () => {
    if (intervalId !== null && !penalties.value.some(p => p.running)) {
      clearInterval(intervalId)
      intervalId = null
    }
  }

  const tick = () => {
    const now = Date.now()
    const expired: PenaltyClock[] = []
    for (const p of penalties.value) {
      if (!p.running) continue
      const end = endAt.get(p.id)
      if (end === undefined) continue
      p.remainingMs = Math.max(0, end - now)
      if (p.remainingMs <= 0) expired.push(p)
    }
    for (const p of expired) {
      remove(p.id)
      options.onExpired?.(p)
    }
  }

  /**
   * Start a penalty for a team. Returns the created clock, or null when the team already
   * has 2 concurrent exclusions (spec: the operator is warned, nothing is created).
   */
  const add = (input: {
    team: TeamSide
    playerId: string
    playerNumber: number | null
    cardCode: 'V' | 'J' | 'R'
    durationSec: number
    running: boolean
  }): PenaltyClock | null => {
    const busy = penalties.value.filter(p => p.team === input.team).map(p => p.slot)
    const slot = freePenaltySlot(busy)
    if (slot === null) return null

    const clock: PenaltyClock = {
      id: uuid(),
      team: input.team,
      slot,
      playerId: input.playerId,
      playerNumber: input.playerNumber,
      cardCode: input.cardCode,
      remainingMs: input.durationSec * 1000,
      running: input.running
    }
    penalties.value.push(clock)
    if (input.running) {
      endAt.set(clock.id, Date.now() + clock.remainingMs)
      ensureTick()
    }
    return clock
  }

  /** Remove a clock (expiry, early lift, manual correction). Returns it, or null. */
  const remove = (id: string): PenaltyClock | null => {
    const idx = penalties.value.findIndex(p => p.id === id)
    if (idx === -1) return null
    const [clock] = penalties.value.splice(idx, 1)
    endAt.delete(id)
    clearTickIfIdle()
    return clock
  }

  /** Game clock stopped → freeze every running penalty (the countdowns follow the game). */
  const suspendAll = () => {
    const now = Date.now()
    for (const p of penalties.value) {
      if (!p.running) continue
      const end = endAt.get(p.id)
      if (end !== undefined) p.remainingMs = Math.max(0, end - now)
      p.running = false
      endAt.delete(p.id)
    }
    clearTickIfIdle()
  }

  /** Game clock restarted → resume every frozen penalty. */
  const resumeAll = () => {
    const now = Date.now()
    for (const p of penalties.value) {
      if (p.running || p.remainingMs <= 0) continue
      p.running = true
      endAt.set(p.id, now + p.remainingMs)
    }
    if (penalties.value.some(p => p.running)) ensureTick()
  }

  /**
   * The penalty that a conceded goal would lift for `team` (oldest liftable V/J — an R
   * runs its full term). Pure lookup: removal + persistence stay with the caller.
   */
  const liftCandidate = (team: TeamSide): PenaltyClock | null => {
    const candidates = penalties.value.filter(
      p => p.team === team && penaltyLiftableOnGoal(p.cardCode)
    )
    if (candidates.length === 0) return null
    // Slot order follows creation order here (clocks are pushed sequentially).
    return [...candidates].sort((a, b) => a.slot - b.slot)[0]
  }

  /** Rebuild from the persisted live clocks (GET /state → kind PENALTY). */
  const restore = (clocks: Array<{
    id: string
    team: TeamSide
    slot: number
    playerId: string | null
    cardCode: string | null
    initMs: number
    elapsedMs: number
    running: boolean
  }>, gameClockRunning: boolean) => {
    for (const c of clocks) {
      if (!cardCreatesPenaltyClock(c.cardCode ?? '')) continue
      const left = Math.max(0, c.initMs - c.elapsedMs)
      if (left <= 0) continue
      const clock: PenaltyClock = {
        id: c.id,
        team: c.team,
        slot: c.slot,
        playerId: c.playerId ?? '',
        playerNumber: null,
        cardCode: (c.cardCode ?? 'V') as 'V' | 'J' | 'R',
        remainingMs: left,
        running: c.running && gameClockRunning
      }
      penalties.value.push(clock)
      if (clock.running) endAt.set(clock.id, Date.now() + left)
    }
    if (penalties.value.some(p => p.running)) ensureTick()
  }

  onUnmounted(() => {
    if (intervalId !== null) clearInterval(intervalId)
  })

  return { penalties, add, remove, suspendAll, resumeAll, liftCandidate, restore }
}
