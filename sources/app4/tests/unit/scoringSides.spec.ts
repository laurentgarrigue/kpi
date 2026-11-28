import { describe, expect, it } from 'vitest'
import {
  sidesFor,
  teamOnSide,
  sideOfTeam,
  toggleSidesForPeriod,
  possessionConflict,
  sidesSettingOf,
  startForSides,
  type SidesStart,
} from '../../utils/scoringSides'
import type { Period } from '../../types/scoring'

/**
 * Côtés du terrain (PAGE_SCORING.md §0.18) : les côtés de chaque période se déduisent du côté de
 * départ, de la période et des inversions manuelles. La même table de vérité est testée côté api2
 * (ScoringRules::sidesFor, SIDE-18).
 */
describe('sidesFor — côtés d’une période (D3)', () => {
  const periods: Period[] = ['M1', 'M2', 'P1', 'P2', 'P3', 'P4']

  it('inverse les côtés à chaque nouvelle période, prolongations comprises (SIDE-03, SIDE-04)', () => {
    expect(periods.map(p => sidesFor('A_LEFT', p, []))).toEqual(['A_LEFT', 'A_RIGHT', 'A_LEFT', 'A_RIGHT', 'A_LEFT', 'A_RIGHT'])
    expect(periods.map(p => sidesFor('A_RIGHT', p, []))).toEqual(['A_RIGHT', 'A_LEFT', 'A_RIGHT', 'A_LEFT', 'A_RIGHT', 'A_LEFT'])
  })

  it('rend les côtés d’une période précédente quand on y revient (SIDE-05) : fonction pure de la période', () => {
    expect(sidesFor('A_LEFT', 'M1', [])).toBe(sidesFor('A_LEFT', 'M1', []))
  })

  it('applique une inversion manuelle à sa seule période (SIDE-06)', () => {
    expect(sidesFor('A_LEFT', 'M2', ['M2'])).toBe('A_LEFT')
    expect(sidesFor('A_LEFT', 'P1', ['M2'])).toBe('A_LEFT')
    expect(sidesFor('A_LEFT', 'M1', ['M2'])).toBe('A_LEFT')
  })

  it('garde A à gauche tant que les côtés ne sont pas vérifiés (D2)', () => {
    expect(sidesFor(null, 'M1', [])).toBe('A_LEFT')
    expect(sidesFor(null, 'M2', [])).toBe('A_RIGHT')
  })

  it('donne les côtés de départ aux tirs au but', () => {
    expect(sidesFor('A_RIGHT', 'TB', [])).toBe('A_RIGHT')
  })

  it('suit la table partagée avec api2 (SIDE-18)', () => {
    const table: Array<[SidesStart, Period, Period[], string]> = [
      ['A_LEFT', 'M1', [], 'A_LEFT'],
      ['A_LEFT', 'M2', [], 'A_RIGHT'],
      ['A_LEFT', 'P1', [], 'A_LEFT'],
      ['A_LEFT', 'P2', ['P2'], 'A_LEFT'],
      ['A_RIGHT', 'M1', ['M1'], 'A_LEFT'],
      ['A_RIGHT', 'P3', [], 'A_RIGHT'],
      ['A_RIGHT', 'P4', ['M2'], 'A_LEFT'],
    ]
    for (const [start, period, overrides, expected] of table) {
      expect(sidesFor(start, period, overrides), `${start} ${period} ${overrides.join()}`).toBe(expected)
    }
  })
})

describe('teamOnSide / sideOfTeam — gauche, droite et équipes A, B', () => {
  it('place A à gauche et B à droite quand A est à gauche', () => {
    expect([teamOnSide('A_LEFT', 'L'), teamOnSide('A_LEFT', 'R')]).toEqual(['A', 'B'])
    expect([sideOfTeam('A_LEFT', 'A'), sideOfTeam('A_LEFT', 'B')]).toEqual(['L', 'R'])
  })

  it('place B à gauche et A à droite quand A est à droite', () => {
    expect([teamOnSide('A_RIGHT', 'L'), teamOnSide('A_RIGHT', 'R')]).toEqual(['B', 'A'])
    expect([sideOfTeam('A_RIGHT', 'A'), sideOfTeam('A_RIGHT', 'B')]).toEqual(['R', 'L'])
  })
})

describe('toggleSidesForPeriod — inversion manuelle confirmée (D4)', () => {
  it('change le côté de départ tant que les côtés ne sont pas vérifiés', () => {
    expect(toggleSidesForPeriod({ start: null, overrides: [] }, 'M1')).toEqual({ start: 'A_RIGHT', overrides: [] })
  })

  it('inverse la seule période en cours une fois les côtés vérifiés', () => {
    expect(toggleSidesForPeriod({ start: 'A_LEFT', overrides: [] }, 'M2')).toEqual({ start: 'A_LEFT', overrides: ['M2'] })
  })

  it('annule l’inversion d’une période par une seconde inversion', () => {
    expect(toggleSidesForPeriod({ start: 'A_LEFT', overrides: ['M1', 'M2'] }, 'M2')).toEqual({ start: 'A_LEFT', overrides: ['M1'] })
  })
})

describe('possessionConflict — inversion probable détectée sur un but (D11)', () => {
  it('signale un but de A quand le dernier départ de la période donnait le ballon à B (SIDE-12)', () => {
    expect(possessionConflict({ team: 'B', period: 'M2' }, 'A', 'M2')).toBe(true)
  })

  it('ne signale rien quand le dernier départ donnait le ballon à l’équipe qui marque (SIDE-13)', () => {
    expect(possessionConflict({ team: 'A', period: 'M2' }, 'A', 'M2')).toBe(false)
  })

  it('ne compare pas un départ d’une autre période, ni l’absence de départ', () => {
    expect(possessionConflict({ team: 'B', period: 'M1' }, 'A', 'M2')).toBe(false)
    expect(possessionConflict(null, 'A', 'M2')).toBe(false)
  })
})

describe('sidesSettingOf — réglage des côtés lu dans l’état live', () => {
  it('lit le côté de départ et les périodes inversées', () => {
    expect(sidesSettingOf({ sidesStart: 'A_RIGHT', sidesOverrides: ['M2'] })).toEqual({ start: 'A_RIGHT', overrides: ['M2'] })
  })

  it('tient des côtés non vérifiés quand l’état ne les porte pas (match jamais touché, ancien serveur)', () => {
    expect(sidesSettingOf({})).toEqual({ start: null, overrides: [] })
    expect(sidesSettingOf({ sidesStart: 'LEFT' as never })).toEqual({ start: null, overrides: [] })
  })
})

describe('startForSides — côté de départ qui donne les côtés vérifiés (D15)', () => {
  it('enregistre tel quel les côtés vérifiés en première période', () => {
    expect(startForSides('A_RIGHT', 'M1', [])).toBe('A_RIGHT')
  })

  it('remonte au côté de départ quand la vérification se fait plus tard (statut passé En cours ailleurs, M2)', () => {
    expect(startForSides('A_RIGHT', 'M2', [])).toBe('A_LEFT')
    expect(sidesFor(startForSides('A_RIGHT', 'M2', []), 'M2', [])).toBe('A_RIGHT')
  })

  it('tient compte d’une inversion manuelle de la période', () => {
    expect(sidesFor(startForSides('A_LEFT', 'P1', ['P1']), 'P1', ['P1'])).toBe('A_LEFT')
  })
})
