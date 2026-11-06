import type { ScoringMatch, ScoringPlayer, TeamSide } from '~/types/scoring'

/**
 * Pre-kick-off checks of the game sheet (PAGE_SCORING.md §7.2). Warnings only: the console
 * never blocks the kick-off — the referee and the paper sheet stay the authority, and a
 * tournament may legitimately run with a short roster. Presentation aid, no api2 mirror.
 */

/** Roster bounds of a kayak-polo game sheet. */
export const ROSTER_LIMITS = {
  /** 5 players start on the water. */
  minPlayers: 5,
  /** 5 on the water + 3 substitutes. */
  maxPlayers: 8,
  /** Coaches / team officials listed on the sheet. */
  maxStaff: 3
} as const

/** Officials without whom the match cannot be run (line judges and shot-clock are optional). */
export const REQUIRED_OFFICIALS = ['arbitrePrincipal', 'arbitreSecondaire', 'secretaire', 'chronometre'] as const
export type RequiredOfficial = typeof REQUIRED_OFFICIALS[number]

export type ReadinessCode =
  | 'too_few_players'
  | 'too_many_players'
  | 'too_many_staff'
  | 'duplicate_numbers'
  | 'no_captain'
  | 'missing_officials'

export interface ReadinessIssue {
  code: ReadinessCode
  team?: TeamSide
  /** Head count behind a roster-size issue. */
  count?: number
  /** Duplicated jersey numbers, ascending. */
  numbers?: number[]
  /** Missing officials, in REQUIRED_OFFICIALS order. */
  roles?: RequiredOfficial[]
}

const STAFF = 'E'
const CAPTAIN = 'C'

function teamIssues(team: TeamSide, roster: ScoringPlayer[]): ReadinessIssue[] {
  const players = roster.filter(p => p.capitaine !== STAFF)
  const staff = roster.length - players.length
  const issues: ReadinessIssue[] = []

  if (players.length < ROSTER_LIMITS.minPlayers) issues.push({ code: 'too_few_players', team, count: players.length })
  if (players.length > ROSTER_LIMITS.maxPlayers) issues.push({ code: 'too_many_players', team, count: players.length })
  if (staff > ROSTER_LIMITS.maxStaff) issues.push({ code: 'too_many_staff', team, count: staff })

  const seen = new Set<number>()
  const duplicated = new Set<number>()
  for (const p of players) {
    if (seen.has(p.numero)) duplicated.add(p.numero)
    seen.add(p.numero)
  }
  if (duplicated.size) issues.push({ code: 'duplicate_numbers', team, numbers: [...duplicated].sort((x, y) => x - y) })

  if (players.length && !players.some(p => p.capitaine === CAPTAIN)) issues.push({ code: 'no_captain', team })

  return issues
}

export function matchReadiness(input: {
  playersA: ScoringPlayer[]
  playersB: ScoringPlayer[]
  officials: Partial<Pick<ScoringMatch, RequiredOfficial>>
}): ReadinessIssue[] {
  const missing = REQUIRED_OFFICIALS.filter(role => !(input.officials[role] ?? '').trim())
  return [
    ...teamIssues('A', input.playersA),
    ...teamIssues('B', input.playersB),
    ...(missing.length ? [{ code: 'missing_officials' as const, roles: [...missing] }] : [])
  ]
}
