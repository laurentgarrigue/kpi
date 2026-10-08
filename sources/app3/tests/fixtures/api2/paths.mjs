// api2 requests whose real responses are kept as fixtures (scripts/capture-api2-fixtures.mjs).
const COMPETITIONS = ['RCH', 'RCP', 'RMU', 'RAT']
const TABS = ['games', 'charts', 'ranking', 'info', 'stats', 'stats/scorers?limit=20', 'stats/scorers?limit=100']

export const FIXTURE_PATHS = [
  '/events/all',
  '/seasons',
  '/groups/2999',
  '/group/2999/TSTRES/competitions',
  '/group/2999/TSTRES/games',
  '/group/2999/NOPE/competitions',
  '/event/77/competitions',
  '/event/77/games',
  '/event/78/competitions',
  '/competition/2999/NOPE',
  ...COMPETITIONS.flatMap(code => [`/competition/2999/${code}`, ...TABS.map(tab => `/competition/2999/${code}/${tab}`)]),
]

/** File name of a request: `/competition/2999/RCH/stats/scorers?limit=20` → `competition_2999_RCH_stats_scorers_limit=20.json`. */
export function fixtureFile(path) {
  return `${path.replace(/^\//, '').replace(/[/?&]/g, '_')}.json`
}
