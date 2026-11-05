// Views of the Phases page (PAGE_JOURNEES_PHASES.md § 5.3): the gameday list, or the schema of
// the competition selected in the header. The active view is carried by the `view` query param.

export const GAMEDAYS_VIEWS = ['list', 'schema'] as const
export type GamedaysView = typeof GAMEDAYS_VIEWS[number]

const DEFAULT_GAMEDAYS_VIEW: GamedaysView = 'list'
const VIEW_QUERY_KEY = 'view'

export function parseGamedaysView(raw: unknown): GamedaysView {
  return (GAMEDAYS_VIEWS as readonly unknown[]).includes(raw) ? raw as GamedaysView : DEFAULT_GAMEDAYS_VIEW
}

// The default view leaves no trace in the URL; every other query param is kept.
export function gamedaysViewQuery<T extends Record<string, unknown>>(query: T, view: GamedaysView): Record<string, unknown> {
  const { [VIEW_QUERY_KEY]: _previous, ...rest } = query
  return view === DEFAULT_GAMEDAYS_VIEW ? rest : { ...rest, [VIEW_QUERY_KEY]: view }
}

// A schema is drawn for one competition: '' means "all competitions" in the header.
export function canShowSchemaView(competitionCode: string): boolean {
  return competitionCode !== ''
}
