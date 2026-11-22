import { describe, expect, it } from 'vitest'
import { CARD_REASONS } from '../../utils/scoringCardReasons'

const codes = CARD_REASONS.map(r => r.code)

describe('card reasons order', () => {
  it('puts Remplacement above Antijeu, and Contestation right below it', () => {
    expect(codes.indexOf('r_rep')).toBeLessThan(codes.indexOf('r_un'))
    expect(codes.indexOf('r_ct')).toBe(codes.indexOf('r_un') + 1)
  })

  it('keeps « Autre / Non précisé » as the last choice, each reason has a pictogram', () => {
    expect(codes.at(-1)).toBe('unknown')
    expect(CARD_REASONS.every(r => r.icon.length > 0)).toBe(true)
  })
})
