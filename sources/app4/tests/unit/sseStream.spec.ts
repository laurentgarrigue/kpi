import { describe, expect, it } from 'vitest'
import { createSseParser, parseTopicList, type SseEvent } from '../../utils/sseStream'

/**
 * Banc de test Mercure (Opérations → Mercure). L'abonnement passe par fetch, pas par EventSource :
 * le hub FrankenPHP 1.13 n'honore la reprise que via l'en-tête `Last-Event-ID` (il ignore le
 * paramètre `lastEventID`), et EventSource ne laisse pas poser d'en-tête. Il faut donc lire le flux
 * SSE soi-même — y compris les messages typés (`event: clock`) publiés par l'outbox du scoring.
 */
const collect = () => {
  const events: SseEvent[] = []
  const parser = createSseParser(e => events.push(e))
  return { events, parser }
}

describe('createSseParser', () => {
  it('livre un message avec son id, son type et ses données', () => {
    const { events, parser } = collect()
    parser.push('event: clock\nid: urn:kpi:scoring:592\ndata: {"a":1}\n\n')
    expect(events).toEqual([{ id: 'urn:kpi:scoring:592', type: 'clock', data: '{"a":1}' }])
  })

  it('livre un message sans type (champ event absent) avec type null', () => {
    const { events, parser } = collect()
    parser.push('id: urn:uuid:1\ndata: hello\n\n')
    expect(events).toEqual([{ id: 'urn:uuid:1', type: null, data: 'hello' }])
  })

  it('recolle un message coupé entre deux morceaux du flux', () => {
    const { events, parser } = collect()
    parser.push('event: sco')
    parser.push('re\nid: urn:kpi:scoring:7\nda')
    expect(events).toEqual([])
    parser.push('ta: x\n\n')
    expect(events).toEqual([{ id: 'urn:kpi:scoring:7', type: 'score', data: 'x' }])
  })

  it('ignore les commentaires (battements « : » du hub)', () => {
    const { events, parser } = collect()
    parser.push(':\n\n: keep-alive\n\n')
    expect(events).toEqual([])
  })

  it('joint plusieurs lignes data par un saut de ligne', () => {
    const { events, parser } = collect()
    parser.push('data: a\ndata: b\n\n')
    expect(events[0]?.data).toBe('a\nb')
  })

  it('accepte les fins de ligne CRLF', () => {
    const { events, parser } = collect()
    parser.push('event: fact\r\nid: 3\r\ndata: z\r\n\r\n')
    expect(events).toEqual([{ id: '3', type: 'fact', data: 'z' }])
  })

  it('livre plusieurs messages d\'un même morceau, dans l\'ordre', () => {
    const { events, parser } = collect()
    parser.push('id: 1\ndata: a\n\nid: 2\ndata: b\n\n')
    expect(events.map(e => e.id)).toEqual(['1', '2'])
  })
})

describe('parseTopicList', () => {
  it('sépare les topics par espaces, virgules ou retours à la ligne', () => {
    expect(parseTopicList(' /a/clock, /a/shotclock\n/a/fact ')).toEqual(['/a/clock', '/a/shotclock', '/a/fact'])
  })

  it('retire les doublons et les entrées vides', () => {
    expect(parseTopicList('/a,,/a ')).toEqual(['/a'])
  })
})
