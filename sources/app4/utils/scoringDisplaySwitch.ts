/**
 * The full-screen displays follow their console (PAGE_SCORING.md §0.16): when the console's
 * tab opens another match (Paramètres → load a match, or the Scoring button of the Games
 * page, which reuses the console tab), it announces `{ from, to }` on the local channel and
 * the displays of the match it left move to the new one. Displays of another match — a
 * second console on the same workstation — stay put. Pure.
 */
export interface MatchSwitch {
  from: number
  to: number
}

/** The switch the console announces when its tab goes from `previous` to `current`. */
export function consoleMatchSwitch(previous: number | null, current: number): MatchSwitch | null {
  return previous !== null && previous !== current ? { from: previous, to: current } : null
}

/** The match a display showing `displayed` must move to, or null when it is not concerned. */
export function matchSwitchTarget(value: unknown, displayed: number): number | null {
  const v = value as Partial<MatchSwitch> | null
  if (!v || typeof v.from !== 'number' || typeof v.to !== 'number') return null
  return v.from === displayed ? v.to : null
}
