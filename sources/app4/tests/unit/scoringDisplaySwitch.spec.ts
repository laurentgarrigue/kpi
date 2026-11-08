import { describe, expect, it } from 'vitest'
import { matchSwitchTarget, consoleMatchSwitch } from '../../utils/scoringDisplaySwitch'

/**
 * Changement de match sur la console (PAGE_SCORING.md §0.16) : les affichages plein écran
 * du match quitté passent au nouveau match ; ceux d'un autre match ne bougent pas.
 */
describe('consoleMatchSwitch — la console annonce-t-elle un changement de match ?', () => {
  it('annonce le passage du match précédent de l’onglet au nouveau', () => {
    expect(consoleMatchSwitch(12, 34)).toEqual({ from: 12, to: 34 })
  })

  it('n’annonce rien à la première ouverture ni au rechargement du même match', () => {
    expect(consoleMatchSwitch(null, 34)).toBeNull()
    expect(consoleMatchSwitch(34, 34)).toBeNull()
  })
})

describe('matchSwitchTarget — un affichage doit-il changer de match ?', () => {
  it('suit la console qui quitte le match affiché', () => {
    expect(matchSwitchTarget({ from: 12, to: 34 }, 12)).toBe(34)
  })

  it('ignore le changement d’une autre console (autre match affiché)', () => {
    expect(matchSwitchTarget({ from: 56, to: 34 }, 12)).toBeNull()
  })

  it('ignore un message mal formé', () => {
    expect(matchSwitchTarget({ from: '12' }, 12)).toBeNull()
    expect(matchSwitchTarget(null, 12)).toBeNull()
  })
})
