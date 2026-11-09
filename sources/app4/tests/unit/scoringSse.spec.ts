import { describe, expect, it, vi } from 'vitest'
import { SCORING_SSE_TYPES, onScoringMessage } from '../../utils/scoringSse'

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
