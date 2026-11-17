import { describe, expect, it, vi } from 'vitest'
import { SCORING_SSE_TYPES, onScoringMessage, scoringSubscribeUrl } from '../../utils/scoringSse'

/** Minimal EventSource stand-in: dispatches by SSE `event:` name, like the browser does. */
function fakeSource() {
  const listeners = new Map<string, Array<(e: MessageEvent<string>) => void>>()
  return {
    addEventListener(type: string, fn: (e: MessageEvent<string>) => void) {
      listeners.set(type, [...(listeners.get(type) ?? []), fn])
    },
    removeEventListener(type: string, fn: (e: MessageEvent<string>) => void) {
      listeners.set(type, (listeners.get(type) ?? []).filter(l => l !== fn))
    },
    /** A message without `event:` is dispatched as 'message'. */
    emit(type: string | null, data: string) {
      for (const fn of listeners.get(type ?? 'message') ?? []) fn({ data } as MessageEvent<string>)
    },
  }
}

// PAGE_INCRUSTATION.md §5 / plan lot 3: the worker publishes TYPED SSE events (`event: clock`…).
// EventSource.onmessage only receives untyped ones, so overlay and console never saw a change.
describe('scoring SSE subscription', () => {
  it.each(['state', 'source', 'fact', 'clock', 'program'])('reacts to a typed `%s` message', (type) => {
    const source = fakeSource()
    const handler = vi.fn()
    onScoringMessage(source as unknown as EventSource, handler)

    source.emit(type, JSON.stringify({ type }))

    expect(handler).toHaveBeenCalledOnce()
    expect(handler.mock.calls[0][0].data).toBe(JSON.stringify({ type }))
  })

  it('still reacts to an untyped message (Mercure test bench, older publishers)', () => {
    const source = fakeSource()
    const handler = vi.fn()
    onScoringMessage(source as unknown as EventSource, handler)

    source.emit(null, '{}')

    expect(handler).toHaveBeenCalledOnce()
  })

  it('covers every block type the api2 outbox deposits', () => {
    expect(SCORING_SSE_TYPES).toEqual(expect.arrayContaining(['state', 'source', 'fact', 'clock', 'program']))
  })

  it('returns a function that detaches every listener', () => {
    const source = fakeSource()
    const handler = vi.fn()
    const off = onScoringMessage(source as unknown as EventSource, handler)

    off()
    source.emit('clock', '{}')
    source.emit(null, '{}')

    expect(handler).not.toHaveBeenCalled()
  })
})

describe('scoringSubscribeUrl', () => {
  const hub = 'https://preprod.kayak-polo.info/api2/.well-known/mercure'

  it('subscribes to every block of the topic base (URI template)', () => {
    const url = new URL(scoringSubscribeUrl(hub, '/scoring/event/77/pitch/1'))
    expect(url.searchParams.get('topic')).toBe('/scoring/event/77/pitch/1/{type}')
  })

  // Preprod/prod hubs refuse anonymous subscribers: without the JWT the console showed
  // « Connexion perdue » for good. EventSource cannot send a header, hence the query parameter.
  it('passes the subscriber JWT as the authorization query parameter', () => {
    const url = new URL(scoringSubscribeUrl(hub, '/scoring/match/9404', 'a.b.c'))
    expect(url.searchParams.get('authorization')).toBe('a.b.c')
  })

  it('subscribes anonymously when there is no JWT (dev hub)', () => {
    expect(new URL(scoringSubscribeUrl(hub, '/scoring/match/9404', '')).searchParams.has('authorization')).toBe(false)
    expect(new URL(scoringSubscribeUrl(hub, '/scoring/match/9404')).searchParams.has('authorization')).toBe(false)
  })
})
