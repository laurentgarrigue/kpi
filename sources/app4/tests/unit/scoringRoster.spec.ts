import { describe, expect, it } from 'vitest'
import type { ScoringEvent, ScoringPlayer } from '../../types/scoring'
import { findPlayersByNumber, goalCounts, playerTallies } from '../../utils/scoringRoster'

/**
 * Lecture des effectifs de la console (PAGE_SCORING.md §7.3 — marqueurs joueur, sélection
 * au clavier par numéro). Présentation et aide à la saisie : pas de miroir api2.
 */
const player = (team: 'A' | 'B', matric: number, numero: number, capitaine: '-' | 'C' | 'E' = '-'): ScoringPlayer => ({
  matric, numero, capitaine, team, nom: `NOM${matric}`, prenom: 'P'
})

const fact = (code: ScoringEvent['code'], team: 'A' | 'B', playerId: string): ScoringEvent => ({
  code, team, player: playerId, period: 'M1', tpsJeu: '05:00', number: null, reason: ''
})

describe('playerTallies — buts et cartons de chaque joueur', () => {
  it('compte les buts de chaque joueur', () => {
    const tallies = playerTallies([fact('B', 'A', '11'), fact('B', 'A', '11'), fact('B', 'B', '22')])
    expect(tallies.get('11')?.goals).toBe(2)
    expect(tallies.get('22')?.goals).toBe(1)
  })

  it('garde les cartons dans l’ordre de saisie', () => {
    const tallies = playerTallies([fact('V', 'A', '11'), fact('J', 'A', '11')])
    expect(tallies.get('11')?.cards).toEqual(['V', 'J'])
    expect(tallies.get('11')?.goals).toBe(0)
  })

  it('ignore les faits d’équipe (joueur 0)', () => {
    expect(playerTallies([fact('B', 'A', '0')]).has('0')).toBe(false)
  })

  it('n’a pas d’entrée pour un joueur sans fait', () => {
    expect(playerTallies([]).get('11')).toBeUndefined()
  })
})

describe('goalCounts — score calculé (nombre de buts)', () => {
  it('compte les buts par équipe, faits d’équipe compris', () => {
    expect(goalCounts([fact('B', 'A', '11'), fact('B', 'A', '0'), fact('B', 'B', '22'), fact('J', 'B', '22')]))
      .toEqual({ A: 2, B: 1 })
  })

  it('vaut 0-0 sans but', () => {
    expect(goalCounts([])).toEqual({ A: 0, B: 0 })
  })
})

describe('findPlayersByNumber — sélection du joueur au clavier', () => {
  const a = [player('A', 11, 7, 'C'), player('A', 12, 3), player('A', 13, 0, 'E')]
  const b = [player('B', 21, 7), player('B', 22, 10)]

  it('trouve le joueur quand le numéro n’existe que dans une équipe', () => {
    expect(findPlayersByNumber('10', a, b).map(p => p.matric)).toEqual([22])
  })

  it('propose les deux joueurs quand le numéro existe des deux côtés', () => {
    expect(findPlayersByNumber('7', a, b).map(p => p.matric)).toEqual([11, 21])
  })

  it('départage par la lettre d’équipe, avant ou après le numéro', () => {
    expect(findPlayersByNumber('a7', a, b).map(p => p.matric)).toEqual([11])
    expect(findPlayersByNumber('7B', a, b).map(p => p.matric)).toEqual([21])
    expect(findPlayersByNumber('b 7', a, b).map(p => p.matric)).toEqual([21])
  })

  it('ne sélectionne jamais un entraîneur (son numéro n’a pas de sens)', () => {
    expect(findPlayersByNumber('0', a, b)).toEqual([])
  })

  it('ne renvoie rien pour une saisie vide ou sans chiffre', () => {
    expect(findPlayersByNumber('', a, b)).toEqual([])
    expect(findPlayersByNumber('a', a, b)).toEqual([])
  })

  it('ne renvoie rien pour un numéro absent', () => {
    expect(findPlayersByNumber('99', a, b)).toEqual([])
  })
})
