import type { TeamSide, Period } from '~/types/scoring'
import type { PenaltyClock } from '~/composables/usePenalties'
import type { ShotclockState } from '~/utils/scoringRules'

/**
 * Local broadcast between the console and the full-screen displays opened from it
 * (scoreboard / shotclock windows) — spec §6.5, decision §0.8.
 *
 * BroadcastChannel is **same-origin** and needs no network at all: the displays plugged
 * into the scoring workstation stay in sync even without Internet. Remote screens and
 * video overlays do NOT use this channel — they consume Mercure (plan §3.3).
 *
 * Port of app3/composables/useBroadcast.ts on the new model: the channel name
 * ('kpi_channel') and the message contract (timer / timer_status / shotclock / period /
 * teams / scores / penA / penB) are kept so the legacy scoreboard.php markup could still
 * be plugged during the transition. Additions: `matchId` on every message (a workstation
 * may run two matches on two screens) and `ready` handshake replies.
 */

export interface BroadcastState {
  matchId: number | null
  teamA: string
  teamB: string
  scoreA: number
  scoreB: number
  period: Period | null
  timer: string
  timerRunning: boolean
  shotclock: string
  shotclockState: ShotclockState
  penalties: PenaltyClock[]
}

type ChannelMessage = { type: string, value: unknown, matchId?: number | null }

export function useScoringBroadcast(getState: () => BroadcastState) {
  let channel: BroadcastChannel | null = null

  const post = (type: string, value: unknown) => {
    if (!channel) return
    channel.postMessage({ type, value, matchId: getState().matchId } satisfies ChannelMessage)
  }

  const timer = () => post('timer', getState().timer)
  const timerStatus = () => post('timer_status', getState().timerRunning ? 'run' : 'stop')
  const shotclock = () => {
    const s = getState()
    post('shotclock', s.shotclock)
    post('shotclock_state', s.shotclockState)
  }
  const period = () => post('period', getState().period)
  const teams = () => {
    const s = getState()
    post('teams', { teamA: s.teamA, teamB: s.teamB })
  }
  const scores = () => {
    const s = getState()
    post('scores', { scoreA: s.scoreA, scoreB: s.scoreB })
  }
  /** Penalties are sent per team (legacy contract penA/penB), as a compact array. */
  const penalties = () => {
    const s = getState()
    for (const side of ['A', 'B'] as TeamSide[]) {
      post(`pen${side}`, s.penalties
        .filter(p => p.team === side)
        .map(p => ({
          slot: p.slot,
          number: p.playerNumber,
          card: p.cardCode,
          remaining: Math.ceil(p.remainingMs / 1000),
          running: p.running
        })))
    }
  }

  /** Full snapshot — sent on open and whenever a display announces itself ready. */
  const broadcastAll = () => {
    teams()
    scores()
    period()
    timer()
    timerStatus()
    shotclock()
    penalties()
  }

  const init = () => {
    if (typeof window === 'undefined' || channel) return
    try {
      channel = new BroadcastChannel('kpi_channel')
      channel.onmessage = (event: MessageEvent<ChannelMessage>) => {
        const message = event.data
        // A display just opened and asks for the current state (handshake).
        if (message?.value === 'ready' && (message.type === 'scoreboard' || message.type === 'shotclock')) {
          broadcastAll()
        }
      }
    } catch {
      // BroadcastChannel unsupported (very old browser): displays simply won't sync.
    }
  }

  const close = () => {
    channel?.close()
    channel = null
  }

  /** Open the full-screen displays (same origin → the channel works). */
  const openScoreboard = (matchId: number) => {
    window.open(`/games/${matchId}/scoreboard`, 'kpi_scoreboard', 'width=1280,height=720')
  }
  const openShotclock = (matchId: number) => {
    window.open(`/games/${matchId}/shotclock`, 'kpi_shotclock', 'width=640,height=480')
  }

  onUnmounted(close)

  return {
    init,
    close,
    broadcastAll,
    timer,
    timerStatus,
    shotclock,
    period,
    teams,
    scores,
    penalties,
    openScoreboard,
    openShotclock
  }
}

/**
 * Receiving side, used by the full-screen display pages: subscribes to the channel,
 * announces itself ready (so the console pushes a full snapshot) and exposes the
 * incoming state. Filters on `matchId` when the page knows which match it displays.
 */
export function useScoringDisplay(matchId: number, role: 'scoreboard' | 'shotclock') {
  const state = reactive<BroadcastState>({
    matchId,
    teamA: '',
    teamB: '',
    scoreA: 0,
    scoreB: 0,
    period: null,
    timer: '00:00',
    timerRunning: false,
    shotclock: '--',
    shotclockState: 'IDLE',
    penalties: []
  })

  let channel: BroadcastChannel | null = null

  const apply = (message: ChannelMessage) => {
    // Ignore messages of another match displayed on the same workstation.
    if (message.matchId != null && message.matchId !== matchId) return
    switch (message.type) {
      case 'timer': state.timer = String(message.value); break
      case 'timer_status': state.timerRunning = message.value === 'run'; break
      case 'shotclock': state.shotclock = String(message.value); break
      case 'shotclock_state': state.shotclockState = message.value as ShotclockState; break
      case 'period': state.period = message.value as Period | null; break
      case 'teams': {
        const v = message.value as { teamA: string, teamB: string }
        state.teamA = v.teamA
        state.teamB = v.teamB
        break
      }
      case 'scores': {
        const v = message.value as { scoreA: number, scoreB: number }
        state.scoreA = v.scoreA
        state.scoreB = v.scoreB
        break
      }
      case 'penA':
      case 'penB': {
        const team: TeamSide = message.type === 'penA' ? 'A' : 'B'
        const incoming = (message.value as Array<{
          slot: number, number: number | null, card: string, remaining: number, running: boolean
        }>).map(p => ({
          id: `${team}${p.slot}`,
          team,
          slot: p.slot,
          playerId: '',
          playerNumber: p.number,
          cardCode: p.card as 'V' | 'J' | 'R',
          remainingMs: p.remaining * 1000,
          running: p.running
        }))
        state.penalties = [...state.penalties.filter(p => p.team !== team), ...incoming]
        break
      }
    }
  }

  onMounted(() => {
    try {
      channel = new BroadcastChannel('kpi_channel')
      channel.onmessage = (event: MessageEvent<ChannelMessage>) => apply(event.data)
      // Handshake: ask the console for a full snapshot.
      channel.postMessage({ type: role, value: 'ready', matchId })
    } catch {
      // Unsupported: the page stays on its initial state.
    }
  })

  onUnmounted(() => {
    channel?.close()
    channel = null
  })

  return { state }
}
