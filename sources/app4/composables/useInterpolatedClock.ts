/**
 * Local interpolation of the live clocks for the video overlay (plan §3.1).
 *
 * The network only carries the moments someone pressed play/pause: with the 4-value model
 * (init, elapsed, startedAt, running) any screen recomputes the displayed time on its own,
 * at 60 fps, for free — and keeps ticking correctly during a network blackout.
 *
 * Client/server clock skew is absorbed once, at each refresh, by comparing the server time
 * shipped with the state to the local one; the offset is then applied to every clock.
 */
export interface RawClock {
  kind: 'GAME' | 'SHOTCLOCK' | 'PENALTY' | 'BREAK'
  team: string
  slot: number
  playerId: string | null
  cardCode: string | null
  initMs: number
  elapsedMs: number
  startedAt: string | null
  running: boolean
}

export function useInterpolatedClocks(getClocks: () => RawClock[]) {
  /** Bumped by an animation frame loop; every derived value reads it to stay reactive. */
  const now = ref(Date.now())
  let frame: number | null = null

  const loop = () => {
    now.value = Date.now()
    frame = requestAnimationFrame(loop)
  }

  onMounted(() => { frame = requestAnimationFrame(loop) })
  onUnmounted(() => { if (frame !== null) cancelAnimationFrame(frame) })

  /** Remaining milliseconds of a clock at the current instant. */
  const remainingOf = (clock: RawClock): number => {
    const base = clock.initMs - clock.elapsedMs
    if (!clock.running || !clock.startedAt) return Math.max(0, base)
    const started = Date.parse(clock.startedAt.replace(' ', 'T'))
    if (Number.isNaN(started)) return Math.max(0, base)
    return Math.max(0, base - (now.value - started))
  }

  const find = (kind: RawClock['kind']) => getClocks().find(c => c.kind === kind) ?? null

  /** Game clock as MM:SS (the overlay never shows tenths for the game time). */
  const gameClock = computed(() => {
    const clock = find('GAME')
    if (!clock) return null
    const s = Math.ceil(remainingOf(clock) / 1000)
    return `${String(Math.floor(s / 60)).padStart(2, '0')}:${String(s % 60).padStart(2, '0')}`
  })

  const gameRunning = computed(() => find('GAME')?.running ?? false)

  /** Shot clock: whole seconds above 10 s, tenths below (same rule as the console). */
  const shotClock = computed(() => {
    const clock = find('SHOTCLOCK')
    if (!clock) return null
    const ms = remainingOf(clock)
    return ms >= 10_000 ? String(Math.ceil(ms / 1000)) : (Math.floor(ms / 100) / 10).toFixed(1)
  })

  /** Break countdown (inter-period), MM:SS — null when no break is running. */
  const breakClock = computed(() => {
    const clock = find('BREAK')
    if (!clock) return null
    const ms = remainingOf(clock)
    if (ms <= 0) return null
    const s = Math.ceil(ms / 1000)
    return `${String(Math.floor(s / 60)).padStart(2, '0')}:${String(s % 60).padStart(2, '0')}`
  })

  /** Active penalties of a team, with their remaining time. */
  const penaltiesOf = (team: 'A' | 'B') =>
    computed(() => getClocks()
      .filter(c => c.kind === 'PENALTY' && c.team === team)
      .map(c => ({
        slot: c.slot,
        cardCode: c.cardCode,
        playerId: c.playerId,
        remaining: Math.ceil(remainingOf(c) / 1000)
      }))
      .filter(p => p.remaining > 0)
      .sort((a, b) => a.slot - b.slot)
    )

  return { gameClock, gameRunning, shotClock, breakClock, penaltiesOf }
}
