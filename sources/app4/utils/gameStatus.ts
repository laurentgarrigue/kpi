/**
 * Game status and period as shown to users, same rules as app2 and the public site (PAGE_COMPETITION.md CMP-17):
 * a finished game not locked yet is « provisional »; periods `Mx` (regular time), `Px` (overtime), `TB` (shoot-out),
 * written in full or abbreviated (`P1`, `OVT 1`).
 */

export type GameDisplayStatus = 'ATT' | 'ON' | 'PROVISIONAL' | 'END'

const LOCKED = 'O'
const SHOOTOUT = 'TB'
const PERIOD_PATTERN = /^([MP])(\d)$/

export function gameDisplayStatus(statut: string, validation: string | null | undefined): GameDisplayStatus {
  if (statut === 'END') {
    return validation === LOCKED ? 'END' : 'PROVISIONAL'
  }
  return statut === 'ON' ? 'ON' : 'ATT'
}

/** i18n key (under `games.period_label.`) and number of a period, or `null` when unknown. */
export function periodLabel(period: string | null | undefined, short: boolean): { key: string, n: number | null } | null {
  const suffix = short ? 'Short' : ''
  if (period === SHOOTOUT) {
    return { key: `shootout${suffix}`, n: null }
  }
  const match = PERIOD_PATTERN.exec(period ?? '')
  return match ? { key: `${match[1] === 'M' ? 'regular' : 'overtime'}${suffix}`, n: Number(match[2]) } : null
}
