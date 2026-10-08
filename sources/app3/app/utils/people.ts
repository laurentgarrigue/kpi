/** « NOM Prénom (123456) » → « NOM Prénom »: what is between parentheses is never published (minimisation). */
export function personName(value: string | null | undefined): string | null {
  return value ? value.split(' (')[0]!.trim() || null : null
}
