import { describe, expect, it } from 'vitest'
import { formatGameClock, snapShotclockAdjust } from '../../utils/scoringDisplay'

/**
 * Affichage des chronos de la console et du tableau d'affichage (PAGE_SCORING.md §6.5).
 * Pas de miroir api2 : c'est de la présentation, pas une règle de jeu.
 */
describe('formatGameClock — chrono de match', () => {
  it('n’affiche pas le zéro de tête des minutes', () => {
    expect(formatGameClock(9 * 60_000 + 52_000)).toBe('9:52')
  })

  it('garde deux chiffres aux secondes', () => {
    expect(formatGameClock(5 * 60_000 + 7_000)).toBe('5:07')
  })

  it('affiche la période pleine telle quelle', () => {
    expect(formatGameClock(10 * 60_000)).toBe('10:00')
  })

  it('arrondit à la seconde supérieure au-dessus de 10 s (un décompte n’affiche jamais 0 trop tôt)', () => {
    expect(formatGameClock(9 * 60_000 + 51_400)).toBe('9:52')
    expect(formatGameClock(10_001)).toBe('0:11')
  })

  it('passe aux dixièmes sous 10 secondes restantes', () => {
    expect(formatGameClock(9_999)).toBe('9.9')
    expect(formatGameClock(4_350)).toBe('4.3')
  })

  it('affiche 10 s pile sans dixièmes', () => {
    expect(formatGameClock(10_000)).toBe('0:10')
  })

  it('termine à 0.0', () => {
    expect(formatGameClock(0)).toBe('0.0')
    expect(formatGameClock(-200)).toBe('0.0')
  })
})

describe('snapShotclockAdjust — ajustement ±1 s du chrono de tir', () => {
  it('part de la seconde affichée au-dessus de 10 s (36,4 s s’affiche 37 → −1 donne 36)', () => {
    expect(snapShotclockAdjust(36_400, -1, 60_000)).toBe(36_000)
    expect(snapShotclockAdjust(36_400, 1, 60_000)).toBe(38_000)
  })

  it('arrondit à la seconde la plus proche sous 10 s', () => {
    expect(snapShotclockAdjust(8_300, -1, 60_000)).toBe(7_000)
    expect(snapShotclockAdjust(8_800, -1, 60_000)).toBe(8_000)
  })

  it('ne dépasse ni zéro ni la durée chargée', () => {
    expect(snapShotclockAdjust(400, -1, 60_000)).toBe(0)
    expect(snapShotclockAdjust(59_500, 1, 60_000)).toBe(60_000)
  })
})
