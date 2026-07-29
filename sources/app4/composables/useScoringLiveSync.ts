/**
 * Keeps the console coherent with the other terminals of the same match (plan lot 3).
 *
 * The console subscribes, over Mercure/SSE, to the topics of **its** match — the server
 * gives both the hub URL and the topic base in `GET /admin/scoring/state` (plan §3.3:
 * addressing is server-owned, the client never builds a topic itself). A URI template
 * `{base}/{type}` subscribes to every block at once (score, clock, shotclock, penalty,
 * fact…), which is exactly what a supervising client wants.
 *
 * What it does with a message — deliberately **not** a diff merge:
 *   the payload only carries the change, so instead of re-implementing a merge that would
 *   drift from the server logic, an incoming tick simply triggers a (debounced) refetch of
 *   the canonical state. It is cheap (ETag → 304 when unchanged) and self-healing: a
 *   missed message or an out-of-order one can never leave the console on a wrong state.
 *
 * Echo suppression: the console is itself a writer, so its own writes come back on the
 * channel. Rather than tagging every payload with an emitter id (which would change the
 * write contract), messages are ignored for a short window after a local mutation. The
 * consequence is bounded and safe: at worst a remote change made in that same window is
 * applied a bit later, on the next message or on the next local refresh.
 */
export function useScoringLiveSync(options: {
  /** Called when a remote change was detected — the page refetches and re-primes clocks. */
  onRemoteChange: () => void | Promise<void>
  /** Epoch ms of the last local write (store.lastMutationAt) — echo suppression. */
  lastLocalWrite: () => number
  /** Milliseconds during which our own echo is ignored after a local write. */
  echoWindowMs?: number
  /** Debounce before reacting to a burst of messages. */
  debounceMs?: number
}) {
  const { echoWindowMs = 2000, debounceMs = 400 } = options

  /** 'idle' | 'connected' | 'error' — surfaced in the UI as a small indicator. */
  const status = ref<'idle' | 'connected' | 'error'>('idle')

  let source: EventSource | null = null
  let debounce: ReturnType<typeof setTimeout> | null = null

  const disconnect = () => {
    source?.close()
    source = null
    if (debounce !== null) {
      clearTimeout(debounce)
      debounce = null
    }
    status.value = 'idle'
  }

  /**
   * @param hubUrl    browser-facing Mercure URL (from GET /state)
   * @param topicBase e.g. /scoring/event/236/pitch/2 (from GET /state)
   */
  const connect = (hubUrl: string, topicBase: string) => {
    if (!hubUrl || !topicBase || typeof window === 'undefined') return
    disconnect()

    const url = new URL(hubUrl)
    // URI template: every block of this match, and this match only.
    url.searchParams.append('topic', `${topicBase}/{type}`)

    try {
      source = new EventSource(url.toString())
    } catch {
      status.value = 'error'
      return
    }

    source.onopen = () => { status.value = 'connected' }

    source.onmessage = () => {
      // Our own echo: nothing to do, the local state is already the freshest.
      if (Date.now() - options.lastLocalWrite() < echoWindowMs) return
      if (debounce !== null) clearTimeout(debounce)
      debounce = setTimeout(() => {
        debounce = null
        void options.onRemoteChange()
      }, debounceMs)
    }

    // EventSource reconnects on its own (and replays from Last-Event-ID); we only
    // reflect the state so the operator sees the console is momentarily blind.
    source.onerror = () => { status.value = 'error' }
  }

  onUnmounted(disconnect)

  return { status, connect, disconnect }
}
