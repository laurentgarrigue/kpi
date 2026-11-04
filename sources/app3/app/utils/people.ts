/** api2 stores « -1 » when a game has no referee at that position. */
const NO_PERSON = '-1'

const ENTITIES: Record<string, string> = { amp: '&', lt: '<', gt: '>', quot: '"', apos: "'" }
const ENTITY = /&(#\d+|#x[\da-f]+|[a-z]+);/gi

/** Text stored HTML-encoded by the legacy (« Coupe d&#039;Europe ») read as plain text. */
export function decodeEntities(value: string): string {
  return value.replace(ENTITY, (entity, code: string) => {
    if (code.startsWith('#')) {
      const point = code[1]?.toLowerCase() === 'x' ? Number.parseInt(code.slice(2), 16) : Number(code.slice(1))
      return String.fromCodePoint(point)
    }
    return ENTITIES[code.toLowerCase()] ?? entity
  })
}

/**
 * « NOM Prénom (123456) » → « NOM Prénom »: what is between parentheses is never published (minimisation).
 * The « -1 » placeholder (no referee) is nobody.
 */
export function personName(value: string | null | undefined): string | null {
  const name = value ? decodeEntities(value).split(' (')[0]!.trim() : ''
  return name && name !== NO_PERSON ? name : null
}
