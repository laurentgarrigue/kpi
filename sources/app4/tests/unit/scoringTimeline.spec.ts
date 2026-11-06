import { describe, expect, it } from 'vitest'
import type { ScoringEvent } from '../../types/scoring'
import { timelineOrder } from '../../utils/scoringTimeline'

/**
 * Ordre de la chronologie (PAGE_SCORING.md §7.1). Le temps d'un fait est le temps
 * RESTANT (le chrono est un compte à rebours) : dans une période, le fait le plus récent
 * est celui qui a le plus PETIT temps.
 */
const fact = (period: ScoringEvent['period'], tpsJeu: string, uid: string): ScoringEvent => ({
  uid, period, tpsJeu, code: 'B', team: 'A', player: '1', number: 1, reason: ''
})

const uids = (events: ScoringEvent[]) => timelineOrder(events).map(e => e.uid)

describe('timelineOrder — le plus récent en premier', () => {
  it('met en tête, dans une période, le fait au plus petit temps restant', () => {
    expect(uids([fact('M1', '08:30', 'early'), fact('M1', '02:10', 'late')])).toEqual(['late', 'early'])
  })

  it('met la période la plus avancée en tête', () => {
    expect(uids([fact('M2', '09:00', 'm2'), fact('M1', '00:30', 'm1')])).toEqual(['m2', 'm1'])
  })

  it('classe les prolongations au-delà de P2, et après la 2ᵉ période', () => {
    expect(uids([fact('P2', '01:00', 'p2'), fact('P3', '04:00', 'p3'), fact('M2', '00:10', 'm2'), fact('P10', '04:00', 'p10')]))
      .toEqual(['p10', 'p3', 'p2', 'm2'])
  })

  it('met les tirs au but après toutes les prolongations', () => {
    expect(uids([fact('P4', '00:01', 'p4'), fact('TB', '00:00', 'tb')])).toEqual(['tb', 'p4'])
  })

  it('accepte un temps stocké avec les heures (00:MM:SS)', () => {
    expect(uids([fact('M1', '00:08:30', 'early'), fact('M1', '00:02:10', 'late')])).toEqual(['late', 'early'])
  })

  it('ne modifie pas la liste reçue', () => {
    const events = [fact('M1', '08:30', 'early'), fact('M1', '02:10', 'late')]
    timelineOrder(events)
    expect(events.map(e => e.uid)).toEqual(['early', 'late'])
  })
})
