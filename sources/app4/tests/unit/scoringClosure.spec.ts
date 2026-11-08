import { describe, expect, it } from 'vitest'
import { closureWarnings } from '../../utils/scoringClosure'

/**
 * Clore le match (PAGE_SCORING.md §0.15) : confirmation demandée quand la clôture semble
 * prématurée. Aucune n'est bloquante — l'arbitre peut arrêter un match à tout moment.
 */
const normal = { live: true, running: false, atZero: true, period: 'M2', type: 'C', scoreLevel: false } as const

describe('closureWarnings — pourquoi la clôture semble prématurée', () => {
  it('ne signale rien en fin de 2e période, chrono arrêté à zéro', () => {
    expect(closureWarnings(normal)).toEqual([])
  })

  it('signale un chrono en marche (et pas, en plus, un chrono non nul)', () => {
    expect(closureWarnings({ ...normal, running: true, atZero: false })).toEqual(['clock_running'])
  })

  it('signale un chrono arrêté qui n’est pas à zéro', () => {
    expect(closureWarnings({ ...normal, atZero: false })).toEqual(['clock_not_zero'])
  })

  it('signale la première période', () => {
    expect(closureWarnings({ ...normal, period: 'M1' })).toEqual(['first_period'])
  })

  it('signale une égalité en match éliminatoire, pas en match de classement', () => {
    expect(closureWarnings({ ...normal, type: 'E', scoreLevel: true })).toEqual(['tied_elimination'])
    expect(closureWarnings({ ...normal, type: 'C', scoreLevel: true })).toEqual([])
  })

  it('ignore les chronos en saisie post-match (aucun chrono réel)', () => {
    expect(closureWarnings({ ...normal, live: false, running: true, atZero: false })).toEqual([])
  })

  it('cumule les raisons', () => {
    expect(closureWarnings({ live: true, running: true, atZero: false, period: 'M1', type: 'E', scoreLevel: true }))
      .toEqual(['clock_running', 'first_period', 'tied_elimination'])
  })
})
