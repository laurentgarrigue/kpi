/**
 * Lecture d'un flux SSE (text/event-stream) hors EventSource.
 *
 * Utilisé par le banc de test Mercure : le hub FrankenPHP 1.13 n'honore la reprise
 * d'historique que via l'en-tête `Last-Event-ID` — il ignore le paramètre d'URL
 * `lastEventID` — et EventSource ne permet pas de poser cet en-tête. On lit donc le flux
 * avec fetch et on le découpe ici (format : https://html.spec.whatwg.org/#event-stream-interpretation).
 */

export interface SseEvent {
  id: string | null
  // Champ `event:` ; null pour un message non typé (EventSource l'appellerait « message »).
  type: string | null
  data: string
}

const LINE_BREAK = /\r\n|\r|\n/

export const createSseParser = (onEvent: (event: SseEvent) => void) => {
  let buffer = ''
  let id: string | null = null
  let type: string | null = null
  let dataLines: string[] = []

  const dispatch = () => {
    if (dataLines.length > 0) {
      onEvent({ id, type, data: dataLines.join('\n') })
    }
    id = null
    type = null
    dataLines = []
  }

  const processLine = (line: string) => {
    if (line === '') return dispatch()
    if (line.startsWith(':')) return // commentaire / battement

    const colon = line.indexOf(':')
    const field = colon === -1 ? line : line.slice(0, colon)
    let value = colon === -1 ? '' : line.slice(colon + 1)
    if (value.startsWith(' ')) value = value.slice(1)

    if (field === 'data') dataLines.push(value)
    else if (field === 'id') id = value
    else if (field === 'event') type = value
  }

  return {
    push(chunk: string) {
      buffer += chunk
      const lines = buffer.split(LINE_BREAK)
      // La dernière ligne peut être incomplète : elle attend le morceau suivant.
      buffer = lines.pop() ?? ''
      lines.forEach(processLine)
    }
  }
}

/** Topics saisis à la main, séparés par des espaces, virgules ou retours à la ligne. */
export const parseTopicList = (input: string): string[] =>
  [...new Set(input.split(/[\s,]+/).filter(topic => topic !== ''))]
