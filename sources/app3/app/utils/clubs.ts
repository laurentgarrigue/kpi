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

/** Capitalised words of a name: articles back to lower case, elided ones too, acronyms in capitals (CLB-07). */
const WORD_START = /(^|[\s'’(-])(\p{L})/gu
const ELIDED_ARTICLE = / ([DL])(['’])/g
const LOWER_WORD = / (De|Du|Des|La|Le|Les|Et|Sur|En|Aux|Au)\b/g
const ACRONYM = /\b(Ck|Cd|Cr|Ffck|Asl|Us|As|Ckc|Mjc)\b/g

/** A name stored in capitals, in title case (« CANOE CLUB D'AVRANCHES » → « Canoe Club d'Avranches »); a name already in mixed case is kept. */
export function titleCase(value: string | null | undefined): string {
  const name = (value ?? '').trim()
  if (name !== name.toUpperCase()) {
    return name
  }
  return name.toLowerCase()
    .replace(WORD_START, (_, before: string, letter: string) => before + letter.toUpperCase())
    .replace(ELIDED_ARTICLE, (_, article: string, quote: string) => ` ${article.toLowerCase()}${quote}`)
    .replace(LOWER_WORD, word => word.toLowerCase())
    .replace(ACRONYM, word => word.toUpperCase())
}

const COMMITTEE_PREFIX = /^COMIT[EÉ] (D[EÉ]PARTEMENTAL|R[EÉ]GIONAL) (CK )?(DE LA |DE L'|DU |DES |DE )?/i
const COMMITTEE_SUFFIX = / CK$/i

/** A departmental or regional committee by its place (« COMITE DEPARTEMENTAL CK DE LA MANCHE » → « Manche »). */
export function committeeName(value: string | null | undefined): string {
  return titleCase((value ?? '').replace(COMMITTEE_PREFIX, '').replace(COMMITTEE_SUFFIX, ''))
}

/** Teams that tournaments create in clubs while waiting for the draw (« (1er Poule A) », « Vainqueur match 21 »). */
const PLACEHOLDER_TEAM = /^\(|\bpoule\b|^\d+(er|e|ème)\b|^(vainqueur|perdant|winner|loser)\b/i

export function isPlaceholderTeam(label: string): boolean {
  return PLACEHOLDER_TEAM.test(label.trim())
}

const POSTAL_CITY = /\d{5}\s+(.+)$/

/** City of a postal address (« … 50300 AVRANCHES » → « Avranches »), or an empty string. */
export function clubCity(postal: string | null): string {
  const city = postal?.match(POSTAL_CITY)?.[1]
  return city ? titleCase(city) : ''
}

/** Directions to a club on OpenStreetMap (a link: nothing is loaded before the visitor follows it). */
export function directionsUrl(position: ClubPosition): string {
  return `https://www.openstreetmap.org/directions?to=${encodeURIComponent(`${position.lat},${position.lng}`)}`
}
