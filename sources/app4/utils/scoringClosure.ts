import type { MatchType } from '~/types/scoring'

/**
 * Closing a match (PAGE_SCORING.md §0.15): the reasons why closing now looks premature,
 * each one shown in a confirmation before the closure panel. None blocks — the referee may
 * stop a match at any time. Pure: the page feeds the clock and match state.
 *
 * Clock reasons only count in live mode: in post-match entry the console has no real clock.
 */
export type ClosureWarning = 'clock_running' | 'clock_not_zero' | 'first_period' | 'tied_elimination'

export function closureWarnings(input: {
  live: boolean
  running: boolean
  atZero: boolean
  period: string
  type: MatchType
  scoreLevel: boolean
}): ClosureWarning[] {
  const warnings: ClosureWarning[] = []
  if (input.live && input.running) warnings.push('clock_running')
  else if (input.live && !input.atZero) warnings.push('clock_not_zero')
  if (input.period === 'M1') warnings.push('first_period')
  if (input.type === 'E' && input.scoreLevel) warnings.push('tied_elimination')
  return warnings
}
