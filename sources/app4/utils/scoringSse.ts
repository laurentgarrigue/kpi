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

/**
 * Subscribe URL of the hub for every block of a topic base (URI template `{base}/{type}`).
 * The subscriber JWT goes in the `authorization` query parameter — EventSource cannot set a
 * header. Required in preprod/prod where the hub refuses anonymous subscribers; omitted when
 * empty (dev hub in anonymous mode).
 */
export function scoringSubscribeUrl(hubUrl: string, topicBase: string, jwt?: string): string {
  const url = new URL(hubUrl)
  url.searchParams.append('topic', `${topicBase}/{type}`)
  if (jwt) url.searchParams.append('authorization', jwt)
  return url.toString()
}
