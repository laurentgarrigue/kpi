/** api2 stores « -1 » when a game has no referee at that position. */
const NO_PERSON = '-1'

/**
 * « NOM Prénom (123456) » → « NOM Prénom »: what is between parentheses is never published (minimisation).
 * The « -1 » placeholder (no referee) is nobody.
 */
export function personName(value: string | null | undefined): string | null {
  const name = value ? value.split(' (')[0]!.trim() : ''
  return name && name !== NO_PERSON ? name : null
}
