/**
 * Data plumbing of the video overlay (PAGE_INCRUSTATION.md §5/§6/§9).
 *
 * Boot: GET /scoring/program/{event}/{pitch} then GET /scoring/state/{matchId} — both
 * public and ETag-cached. Then: Mercure/SSE on `{topicBase}/{type}`, which carries both
 * the match blocks (score/clock/…) and the pitch **program** (match switching).
 *
 * Deliberate choices, straight from the spec:
 *  - **no polling**: the program arrives by push (P4 — the server decides what is played);
 *  - **self-healing** (P2): any message triggers a refetch of the canonical state rather
 *    than a diff merge, and a long blackout, a tab wake-up or an SSE error forces a full
 *    reload of program + state. A missed message can never leave a wrong score on air;
 *  - **no interaction** (P1): everything comes from the URL and the server.
 */
export interface OverlayProgramMatch {
  id: number
  numero: number | null
  heure: string | null
  statut: 'ATT' | 'ON' | 'END'
  type: string
  equipeA: string | null
  equipeB: string | null
}

export interface OverlaySettings {
  halftimeScoreDelay: number
  finalScoreDelay: number
  finalScoreDuration: number
  nextGameDelay: number
  nextGameDuration: number
  background: string
  styleId: string
}

export interface OverlayState {
  exists: boolean
  tick: number
  statut: 'ATT' | 'ON' | 'END'
  periode: string
  scoreA: number
  scoreB: number
  clocks: Array<{
    kind: 'GAME' | 'SHOTCLOCK' | 'PENALTY' | 'BREAK'
    team: string
    slot: number
    playerId: string | null
    cardCode: string | null
    initMs: number
    elapsedMs: number
    startedAt: string | null
    running: boolean
  }>
  events: Array<{
    uid: string
    code: string
    period: string
    tpsJeu: string
    team: string
    numero: string | null
    nom: string | null
    prenom: string | null
  }>
}

export function useOverlayProgram(event: number, pitch: string, apiBase: string) {
  const program = ref<{
    current: OverlayProgramMatch | null
    next: OverlayProgramMatch | null
    settings: OverlaySettings
    topicBase: string
    mercureUrl: string
  } | null>(null)

  const state = ref<OverlayState | null>(null)
  /** 'boot' | 'live' | 'blind' — surfaced only in ?debug mode. */
  const status = ref<'boot' | 'live' | 'blind'>('boot')

  let source: EventSource | null = null
  let debounce: ReturnType<typeof setTimeout> | null = null
  let currentMatchId: number | null = null

  const fetchProgram = async () => {
    const res = await fetch(`${apiBase}/scoring/program/${event}/${encodeURIComponent(pitch)}`)
    if (!res.ok) return
    program.value = await res.json()
  }

  const fetchState = async () => {
    const id = program.value?.current?.id
    if (!id) {
      state.value = null
      currentMatchId = null
      return
    }
    const res = await fetch(`${apiBase}/scoring/state/${id}`)
    // 404 = the match exists but was never touched live yet: keep an empty board.
    state.value = res.ok ? await res.json() : null
    currentMatchId = id
  }

  /** Full resynchronisation — the safety net of P2. */
  const reload = async () => {
    await fetchProgram()
    await fetchState()
    // The match may have changed: re-point the subscription if needed.
    subscribe()
  }

  const scheduleRefresh = (fn: () => Promise<void>) => {
    if (debounce !== null) clearTimeout(debounce)
    debounce = setTimeout(() => {
      debounce = null
      void fn()
    }, 200)
  }

  const subscribe = () => {
    const hub = program.value?.mercureUrl
    const base = program.value?.topicBase
    if (!hub || !base || typeof window === 'undefined') return

    // Already subscribed to the right pitch: the topic covers every match of the pitch,
    // so a match change needs no re-subscription.
    if (source && source.readyState !== EventSource.CLOSED) return

    const url = new URL(hub)
    url.searchParams.append('topic', `${base}/{type}`)

    try {
      source = new EventSource(url.toString())
    } catch {
      status.value = 'blind'
      return
    }

    source.onopen = () => { status.value = 'live' }

    source.onmessage = (message: MessageEvent<string>) => {
      let payload: { type?: string } = {}
      try {
        payload = JSON.parse(message.data)
      } catch { /* non-JSON payload: fall through to a plain refresh */ }

      if (payload.type === 'program') {
        // The pitch moved on to another match (or the settings changed).
        scheduleRefresh(reload)
        return
      }
      scheduleRefresh(fetchState)
    }

    source.onerror = () => {
      // EventSource retries on its own and replays from Last-Event-ID; we only note that
      // the overlay is momentarily blind (interpolated clocks keep running meanwhile).
      status.value = 'blind'
    }
  }

  /** Wake-ups and returns from a blackout: never trust the local copy, refetch. */
  const onVisible = () => {
    if (document.visibilityState === 'visible') void reload()
  }

  onMounted(async () => {
    await reload()
    document.addEventListener('visibilitychange', onVisible)
    // Long safety net: if SSE dies silently (proxy, sleeping wall), a full reload every
    // 5 min guarantees the screen can never stay wrong for long.
    const interval = setInterval(() => void reload(), 5 * 60 * 1000)
    onUnmounted(() => clearInterval(interval))
  })

  onUnmounted(() => {
    source?.close()
    source = null
    if (debounce !== null) clearTimeout(debounce)
    document.removeEventListener('visibilitychange', onVisible)
  })

  return { program, state, status, reload, currentMatchId: () => currentMatchId }
}
