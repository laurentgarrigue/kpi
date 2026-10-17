/** Formats of api2 `/clubs` and `/club/{code}` (API_PUBLIC_TRANSVERSE.md § 3.5). */

export interface ClubPosition {
  lat: number
  lng: number
}

export interface ClubSummary {
  code: string
  label: string
  department: { code: string | null, label: string | null }
  logo: string | null
  position: ClubPosition | null
}

export interface ClubSheet extends ClubSummary {
  region: { code: string | null, label: string | null }
  www: string | null
  email: string | null
  postal: string | null
  teams: { number: number, label: string }[]
}

export const CLUB_VIEWS = ['list', 'map'] as const
export type ClubView = typeof CLUB_VIEWS[number]

export function clubView(value: unknown): ClubView {
  return value === 'map' ? 'map' : 'list'
}

/** Website of a club as an absolute URL (some are stored without scheme), or `null`. */
export function clubWebsite(www: string | null): string | null {
  if (!www) {
    return null
  }
  return /^https?:\/\//i.test(www) ? www : `https://${www}`
}

/** Markers of the clubs having a position (CLB-02). */
export function clubMarkers(clubs: readonly ClubSummary[]): (ClubSummary & { position: ClubPosition })[] {
  return clubs.filter((club): club is ClubSummary & { position: ClubPosition } => club.position !== null)
}
