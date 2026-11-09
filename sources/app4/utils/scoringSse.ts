/**
 * Subscription to the scoring Mercure messages, typed or not.
 *
 * The api2 outbox publisher (ScoringOutboxPublisher) copies the payload's `type` into the SSE
 * `event:` field. The browser then dispatches the message under that name, and
 * `EventSource.onmessage` — which only receives untyped messages — never fires: the console
 * and the overlay stayed frozen until a reload. Every type is therefore listened to by name.
 *
 * Keep in sync with the `'type' =>` values deposited by ScoringLiveService and
 * ScoringProgramService (api2).
 */
export const SCORING_SSE_TYPES = ['state', 'source', 'fact', 'clock', 'program'] as const

/** Untyped messages (Mercure test bench) arrive under the default 'message' name. */
const UNTYPED = 'message'

/** Calls `handler` for every scoring message; returns the function that detaches it. */
export function onScoringMessage(
  source: EventSource,
  handler: (message: MessageEvent<string>) => void,
): () => void {
  const names = [UNTYPED, ...SCORING_SSE_TYPES]
  const listener = (event: Event) => handler(event as MessageEvent<string>)
  for (const name of names) source.addEventListener(name, listener)
  return () => {
    for (const name of names) source.removeEventListener(name, listener)
  }
}
