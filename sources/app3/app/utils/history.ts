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

/** Final competition of a season: the first awarding a gold medal (HIS-06). */
export interface SeasonFinal {
  season: string
  competition: HistoryCompetition
}

export function seasonFinals(history: History): SeasonFinal[] {
  return history.seasons.flatMap(({ season, competitions }) => {
    const competition = competitions.find(item => item.podium.some(entry => entry.medal === 1))
    return competition ? [{ season, competition }] : []
  })
}

/** Holder of a medal of a competition. */
export function medalist(competition: HistoryCompetition, medal: 1 | 2 | 3): PodiumEntry | undefined {
  return competition.podium.find(entry => entry.medal === medal)
}

/** A team is the same across seasons by its number, else by its name (HIS-07). */
export function teamKey(team: PodiumEntry['team']): string {
  return team.number === null ? team.label : String(team.number)
}

/** The champion of the most recent season, its current streak and since when, its titles in all, the 2nd and 3rd (HIS-06). */
export function titleHolder(finals: readonly SeasonFinal[]): { season: string, champion: PodiumEntry, streak: number, since: string, titles: number, others: PodiumEntry[] } | null {
  const [latest] = finals
  const champion = latest ? medalist(latest.competition, 1) : undefined
  if (!latest || !champion) {
    return null
  }
  const wonBy = (final: SeasonFinal) => {
    const gold = medalist(final.competition, 1)
    return gold !== undefined && teamKey(gold.team) === teamKey(champion.team)
  }
  const broken = finals.findIndex(final => !wonBy(final))
  const streak = broken === -1 ? finals.length : broken
  return {
    season: latest.season,
    champion,
    streak,
    since: finals[streak - 1]!.season,
    titles: finals.filter(wonBy).length,
    others: latest.competition.podium.filter(entry => entry.medal === 2 || entry.medal === 3),
  }
}

const DECADE_LENGTH = 3

/** One anchor per decade, on its most recent season, seasons being in decreasing order (HIS-01). */
export function decadeAnchors(seasons: readonly string[]): { decade: string, season: string }[] {
  const decades = new Map<string, string>()
  for (const season of seasons) {
    const decade = `${season.slice(0, DECADE_LENGTH)}0`
    if (!decades.has(decade)) {
      decades.set(decade, season)
    }
  }
  return [...decades].map(([decade, season]) => ({ decade, season }))
}

/** Anchor of a season (`#saison-2019`). */
export function seasonAnchor(season: string): string {
  return `saison-${season}`
}
