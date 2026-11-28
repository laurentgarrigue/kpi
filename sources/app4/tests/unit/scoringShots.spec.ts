import { describe, expect, it } from 'vitest'
import { possessionTeam, shotTeam, appendShotDigit, playerByNumber, shotTotals, SHOT_NUMBER_MAX_DIGITS } from '../../utils/scoringShots'
import type { ScoringPlayer, ScoringShot } from '../../types/scoring'

/**
 * Tirs et arrêts en deux touches (PAGE_SCORING.md §0.19) : l'équipe vient de la possession déclarée par
 * le chronomètre de tir, le numéro du tireur ou du gardien est facultatif.
 */
describe('possessionTeam — équipe en attaque d’après le dernier départ du chronomètre de tir (T4)', () => {
  it('donne l’équipe du dernier départ de la période (SHOT-01, SHOT-03)', () => {
    expect(possessionTeam({ team: 'A', period: 'M1' }, 'M1')).toBe('A')
  })

  it('ne sait pas quand le dernier départ est d’une autre période, ou qu’il n’y en a pas (SHOT-04)', () => {
    expect(possessionTeam({ team: 'A', period: 'M1' }, 'M2')).toBeNull()
    expect(possessionTeam(null, 'M1')).toBeNull()
  })
})

describe('shotTeam — équipe d’un tir ou d’un arrêt (T4)', () => {
  it('donne le tir à l’équipe en possession (SHOT-01)', () => {
    expect(shotTeam('SHOT', 'A')).toBe('A')
  })

  it('donne l’arrêt à l’autre équipe, celle du gardien (SHOT-02)', () => {
    expect(shotTeam('SAVE', 'A')).toBe('B')
    expect(shotTeam('SAVE', 'B')).toBe('A')
  })

  it('ne donne aucune équipe quand la possession est inconnue (SHOT-04)', () => {
    expect(shotTeam('SHOT', null)).toBe('')
    expect(shotTeam('SAVE', null)).toBe('')
  })
})

describe('appendShotDigit — numéro tapé après la touche (T5)', () => {
  it('ajoute les chiffres tapés (SHOT-05, SHOT-08)', () => {
    expect(appendShotDigit('', '2')).toBe('2')
    expect(appendShotDigit('2', '3')).toBe('23')
  })

  it('ignore ce qui n’est pas un chiffre', () => {
    expect(appendShotDigit('2', 'a')).toBeNull()
    expect(appendShotDigit('2', 'Enter')).toBeNull()
  })

  it('borne la longueur du numéro', () => {
    const full = '9'.repeat(SHOT_NUMBER_MAX_DIGITS)
    expect(appendShotDigit(full, '1')).toBe(full)
  })
})

describe('playerByNumber — joueur désigné par son numéro (T5)', () => {
  const player = (overrides: Partial<ScoringPlayer>): ScoringPlayer => ({
    matric: 1, nom: 'X', prenom: 'Y', numero: 7, capitaine: '-', team: 'A', ...overrides,
  })
  const roster = [player({ matric: 101, numero: 7 }), player({ matric: 102, numero: 1, capitaine: 'C' }), player({ matric: 103, numero: 0, capitaine: 'E' })]

  it('trouve le joueur de l’effectif qui porte ce numéro (SHOT-05, SHOT-06)', () => {
    expect(playerByNumber(roster, 7)?.matric).toBe(101)
    expect(playerByNumber(roster, 1)?.matric).toBe(102)
  })

  it('ne trouve personne pour un numéro absent, ni l’entraîneur (SHOT-08)', () => {
    expect(playerByNumber(roster, 23)).toBeNull()
    expect(playerByNumber(roster, 0)).toBeNull()
  })
})

describe('shotTotals — totaux par équipe du journal des tirs (SHOT-10)', () => {
  const shot = (kind: ScoringShot['kind'], team: ScoringShot['team']): ScoringShot => ({
    uid: Math.random().toString(16), kind, team, period: 'M1', tpsJeu: '05:00', player: null, number: null,
  })

  it('compte les tirs et les arrêts de chaque équipe, à part ceux sans équipe', () => {
    const totals = shotTotals([shot('SHOT', 'A'), shot('SHOT', 'A'), shot('SHOT', 'A'), shot('SAVE', 'B'), shot('SHOT', '')])
    expect(totals).toEqual({ A: { shots: 3, saves: 0 }, B: { shots: 0, saves: 1 }, unknown: 1 })
  })
})
