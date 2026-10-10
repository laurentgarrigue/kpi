/** Formats of api2 `/teams`, `/team/{n}` and `/team/{n}/roster/…` (API_PUBLIC_TRANSVERSE.md § 3.4). */

export interface TeamSummary {
  number: number
  label: string
  club: { code: string | null, label: string | null }
}

export interface TeamImage {
  image: string
  season: string | null
}

export interface TeamHonour {
  season: string
  competition: { code: string, display_title: string, group: string | null }
  rank: number
  medal: 1 | 2 | 3 | null
  /** Final round (`Code_tour` = 10); otherwise the rank of an intermediate round (qualification, pool…). */
  final_round: boolean
}

/** Honours grouped by season, in api2 order (most recent season first): one season heading per group (TEA-07). */
export function honoursBySeason(honours: readonly TeamHonour[]): { season: string, honours: TeamHonour[] }[] {
  const groups: { season: string, honours: TeamHonour[] }[] = []
  for (const honour of honours) {
    const last = groups.at(-1)
    if (last?.season === honour.season) {
      last.honours.push(honour)
    }
    else {
      groups.push({ season: honour.season, honours: [honour] })
    }
  }
  return groups
}

export interface TeamSeason {
  season: string
  competitions: { code: string, display_title: string }[]
}

export interface TeamSheet extends TeamSummary {
  logo: string | null
  colors: TeamImage | null
  photo: TeamImage | null
  honours: TeamHonour[]
  seasons: TeamSeason[]
}

export type TeamRole = 'captain' | 'coach'

export interface RosterPlayer {
  first_name: string | null
  last_name: string | null
  number: number | null
  category: string | null
  role: TeamRole | null
  goals: number
  green: number
  yellow: number
  red: number
  red_final: number
}

export interface Roster {
  players: RosterPlayer[]
}

/** Value of the roster selector: `2026|N1H`. */
export function rosterKey(season: string, competition: string): string {
  return `${season}|${competition}`
}

/**
 * Competition whose roster is shown (TEA-03): the requested one if the team played it — `?season=&competition=`
 * (links from results pages) or `?roster=season|code` (selector) —, otherwise the most recent.
 */
export function selectedRoster(seasons: readonly TeamSeason[], query: Record<string, unknown>): { season: string, competition: string } | null {
  const [season, competition] = typeof query.roster === 'string' ? query.roster.split('|') : [query.season, query.competition]
  const requested = seasons.find(item => item.season === season)?.competitions.find(item => item.code === competition)
  if (requested && typeof season === 'string') {
    return { season, competition: requested.code }
  }
  const latest = seasons[0]
  const first = latest?.competitions[0]
  return latest && first ? { season: latest.season, competition: first.code } : null
}

/** « NOM Prénom », as on the legacy pages. */
export function playerName(player: Pick<RosterPlayer, 'first_name' | 'last_name'>): string {
  return [player.last_name, player.first_name].filter(Boolean).join(' ')
}
