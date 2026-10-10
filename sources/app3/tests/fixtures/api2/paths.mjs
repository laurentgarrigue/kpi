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
  // Phase 3 (API_PUBLIC_TRANSVERSE.md): requests of the calendar, history, team, club and search pages tested.
  '/calendar?start=2999-04-01&end=2999-05-05',
  '/calendar?start=2999-04-01&end=2999-05-05&section=2&group=TSTRES',
  '/calendar?start=2999-04-01&end=2999-05-05&section=2',
  '/calendar/groups',
  '/calendar?start=2999-04-29&end=2999-06-02&group=TSTRES',
  '/calendar?start=2999-06-01&end=3000-05-31&group=TSTRES',
  '/history',
  '/history/TSTRES',
  '/history/NOPE',
  '/teams?q=alpha',
  '/teams?q=zzz',
  '/team/101',
  '/team/9999',
  '/team/101/roster/2999/RCH',
  '/team/101/roster/2998/RCP',
  '/clubs',
  '/clubs?q=lacville',
  '/clubs?q=zzz',
  '/club/C001',
  '/club/NOPE',
  '/search?q=resultats',
  '/search?q=alpha',
  '/search?q=zzz',
]

/** File name of a request: `/competition/2999/RCH/stats/scorers?limit=20` → `competition_2999_RCH_stats_scorers_limit=20.json`. */
export function fixtureFile(path) {
  return `${path.replace(/^\//, '').replace(/[/?&]/g, '_')}.json`
}
