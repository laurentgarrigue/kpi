import { describe, expect, it } from 'vitest'
import { busyPenaltySlots, isPenaltyExpired } from '../../utils/scoringPenalties'

/**
 * Pénalités échues (PAGE_SCORING.md §0.15) : une pénalité arrivée à 0 reste affichée,
 * clignotante, jusqu'à ce que la table la retire — mais elle ne compte plus comme exclusion.
 */
const pen = (team: 'A' | 'B', slot: number, remainingMs: number, running = false) =>
  ({ team, slot, remainingMs, running })

describe('isPenaltyExpired — pénalité arrivée à zéro', () => {
  it('est échue à 0, arrêtée', () => {
    expect(isPenaltyExpired(pen('A', 1, 0))).toBe(true)
  })

  it('n’est pas échue tant qu’il reste du temps, même suspendue', () => {
    expect(isPenaltyExpired(pen('A', 1, 1))).toBe(false)
    expect(isPenaltyExpired(pen('A', 1, 90_000, true))).toBe(false)
  })
})

describe('busyPenaltySlots — emplacements réellement occupés', () => {
  it('ne compte que les pénalités en cours de l’équipe', () => {
    const list = [pen('A', 1, 0), pen('A', 2, 30_000, true), pen('B', 1, 60_000)]
    expect(busyPenaltySlots(list, 'A')).toEqual([2])
    expect(busyPenaltySlots(list, 'B')).toEqual([1])
  })

  it('libère les deux emplacements quand les deux pénalités sont échues', () => {
    expect(busyPenaltySlots([pen('A', 1, 0), pen('A', 2, 0)], 'A')).toEqual([])
  })
})
