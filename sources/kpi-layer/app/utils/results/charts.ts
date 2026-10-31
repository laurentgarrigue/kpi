import { winnerSide } from './games'
import type { ChartPhase, ChartTeam, CompetitionChart, Gameday, ResultsGame, Side } from './types'

export interface RoundView {
  round: number
  phases: ChartPhase[]
}

/** Rounds in order, phases in api2 order (horizontal view, kpchart.php). */
export function chartRounds(chart: CompetitionChart): RoundView[] {
  return Object.entries(chart.rounds)
    .map(([round, { phases }]) => ({ round: Number(round), phases: Object.values(phases) }))
    .sort((a, b) => a.round - b.round)
}

/** Every phase by decreasing level (vertical view, kpphases.php). */
export function phasesByLevel(chart: CompetitionChart): ChartPhase[] {
  return chartRounds(chart).flatMap(round => round.phases).sort((a, b) => b.level - a.level)
}

export interface GamedayStanding {
  gameday: Gameday
  /** The gameday as a pool phase holding its own rows only, for `poolStandings`. */
  phase: ChartPhase
}

/**
 * Standings of each gameday of a championship (CMP-25), in the given order. api2 merges the rows of the gamedays
 * sharing a phase name; they are split back by their `d_id`. A gameday without rows has an empty standing.
 */
export function standingsByGameday(chart: CompetitionChart, gamedays: readonly Gameday[]): GamedayStanding[] {
  const rows = chartRounds(chart).flatMap(round => round.phases).flatMap(phase => phase.teams ?? [])
  return gamedays.map((gameday) => {
    const teams = rows.filter(row => row.d_id === gameday.id)
    return {
      gameday,
      phase: { type: 'C', libelle: gameday.phase ?? gameday.name ?? '', level: 0, t_count: teams.length, d_id: gameday.id, teams, games: null },
    }
  })
}

export type PoolRow = (ChartTeam & { placeholder?: true }) | { empty: true }

const byNumber = (a: ChartTeam, b: ChartTeam) => (a.t_number ?? 0) - (b.t_number ?? 0)
const byLabel = (a: ChartTeam, b: ChartTeam) => (a.t_label ?? '').localeCompare(b.t_label ?? '')
const byStanding = (a: ChartTeam, b: ChartTeam) =>
  (a.t_clt ?? 0) - (b.t_clt ?? 0) || (b.t_pts ?? 0) - (a.t_pts ?? 0) || (b.t_diff ?? 0) - (a.t_diff ?? 0)

/**
 * Rows of a pool table: standings once games are played (draw order before), then waiting labels
 * and empty slots up to the pool size.
 */
export function poolStandings(phase: ChartPhase): PoolRow[] {
  const teams = phase.teams ?? []
  const played = teams.some(team => (team.t_pld ?? 0) > 0)
  const sorted = [...teams].sort((a, b) => (played ? byStanding(a, b) : byNumber(a, b)) || byLabel(a, b))
  if (!sorted.some(team => team.t_id !== null)) {
    return sorted
  }

  const rows: PoolRow[] = sorted.filter(team => team.t_id !== null)
  const placeholders = sorted.filter(team => team.t_id === null)
  while (rows.length < phase.t_count) {
    const placeholder = placeholders.shift()
    rows.push(placeholder ? { ...placeholder, placeholder: true } : { empty: true })
  }
  return rows
}

export interface KnockoutSide {
  side: Side
  teamId: number | null
  label: string | null
  score: string | null
  winner: boolean
}

/** Both teams of a knockout game, the winner first. */
export function knockoutSides(game: ResultsGame): KnockoutSide[] {
  const winner = winnerSide(game)
  const sides: KnockoutSide[] = [
    { side: 'A', teamId: game.t_a_id, label: game.t_a_label, score: game.g_score_a, winner: winner === 'A' },
    { side: 'B', teamId: game.t_b_id, label: game.t_b_label, score: game.g_score_b, winner: winner === 'B' },
  ]
  return winner === 'B' ? sides.reverse() : sides
}
