import type { CompetitionStatus, CompetitionGroup, GroupSection } from '#kpi-layer/utils/results/types'

/** Tabs of a competition page (PAGE_COMPETITION.md): « phases » is the vertical view of « progress ». */
export const COMPETITION_TABS = ['info', 'games', 'pitches', 'progress', 'ranking', 'stats'] as const
export type CompetitionTab = typeof COMPETITION_TABS[number]

/** Tabs of the event and group views (PAGE_EVENT_GROUP.md). */
export const AGGREGATE_TABS = ['games', 'pitches'] as const satisfies readonly CompetitionTab[]
export type AggregateTab = typeof AGGREGATE_TABS[number]

export const PROGRESS_VIEWS = ['horizontal', 'vertical'] as const
export type ProgressView = typeof PROGRESS_VIEWS[number]

/** Section « Compétitions nationales » of `GET /groups/{season}`. */
const NATIONAL_SECTION = 2

/** Requested group if it exists for the season, otherwise the first national group, otherwise the first one. */
export function defaultGroup(sections: readonly GroupSection[], requested: string | undefined): string | null {
  const groups = sections.flatMap(section => section.groups)
  if (requested && groups.some(group => group.code === requested)) {
    return requested
  }
  const national = sections.find(section => section.section === NATIONAL_SECTION)?.groups[0]
  return (national ?? groups[0])?.code ?? null
}

export function groupLabel(group: CompetitionGroup, locale: string): string {
  return locale === 'en' && group.libelle_en ? group.libelle_en : group.libelle
}

/** A finished competition opens on its ranking, the others on their games. */
export function defaultTab(status: CompetitionStatus): CompetitionTab {
  return status === 'END' ? 'ranking' : 'games'
}

export function progressView(value: unknown): ProgressView {
  return value === 'vertical' ? 'vertical' : 'horizontal'
}

/** Path of a competition page (without language prefix), keeping the event context. */
export function competitionPath(season: string, code: string, tab?: CompetitionTab, event?: number): string {
  const path = ['/competitions', season, code, tab].filter(Boolean).join('/')
  return event === undefined ? path : `${path}?event=${event}`
}

/** Path of an event view tab (without language prefix), optionally restricted to one competition (EVT-03, EVT-07). */
export function eventTabPath(event: number, tab: CompetitionTab, competition?: string): string {
  const path = `/events/${event}/${tab}`
  return competition ? `${path}?competition=${encodeURIComponent(competition)}` : path
}

/**
 * Competition shown by the event view: the one asked for in the query when the event has it; otherwise none
 * (the whole event), or the first one on tabs that need a competition.
 */
export function selectedCompetition<T extends { code: string }>(competitions: readonly T[], requested: unknown, required: boolean): T | undefined {
  const asked = typeof requested === 'string' ? competitions.find(item => item.code === requested) : undefined
  return asked ?? (required ? competitions[0] : undefined)
}
