import type { TeamSide } from '~/types/scoring'

/**
 * Expired penalties (PAGE_SCORING.md §0.15). A penalty that reaches 0 is NOT removed: it
 * stays on the boards at 00:00, blinking, until the table removes it (cross) — the cue to
 * signal the line judge and the player (or the replacement) that they may come back. It no
 * longer counts as an exclusion: its slot is free for a new card and a conceded goal never
 * lifts it. Pure — the countdown lives in composables/usePenalties.
 */
interface PenaltyLike {
  team: TeamSide
  slot: number
  remainingMs: number
  running: boolean
}

/** Reached 0 and stopped there. */
export function isPenaltyExpired(p: PenaltyLike): boolean {
  return p.remainingMs <= 0 && !p.running
}

/** Slots held by the team's penalties still in progress (expired ones release theirs). */
export function busyPenaltySlots(penalties: PenaltyLike[], team: TeamSide): number[] {
  return penalties.filter(p => p.team === team && !isPenaltyExpired(p)).map(p => p.slot)
}
