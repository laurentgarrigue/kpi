import type { GroupSection } from '#kpi-layer/utils/results/types'

/** Formats of api2 `/history` and `/history/{group}` (API_PUBLIC_TRANSVERSE.md § 3.3). */

export interface HistoryGroups {
  sections: GroupSection[]
}

export interface PodiumEntry {
  rank: number
  team: { number: number | null, label: string, logo: string | null }
  medal: 1 | 2 | 3 | null
}

export interface HistoryCompetition {
  code: string
  display_title: string
  soustitre2: string | null
  type: string
  podium: PodiumEntry[]
}

export interface History {
  group: { code: string, libelle: string, libelle_en: string | null }
  seasons: { season: string, competitions: HistoryCompetition[] }[]
}

const PODIUM_SIZE = 3

/** Podium (ranks 1 to 3) shown first, the other ranked teams folded (HIS-03). */
export function splitPodium(podium: readonly PodiumEntry[]): { top: PodiumEntry[], others: PodiumEntry[] } {
  return { top: podium.filter(entry => entry.rank <= PODIUM_SIZE), others: podium.filter(entry => entry.rank > PODIUM_SIZE) }
}

/** Anchor of a season section (`#saison-2019`). */
export function seasonAnchor(season: string): string {
  return `saison-${season}`
}
