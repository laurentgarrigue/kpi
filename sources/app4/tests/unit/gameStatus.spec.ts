import { describe, expect, it } from 'vitest'
import { gameDisplayStatus, periodLabel } from '../../utils/gameStatus'

describe('game status and period (PAGE_COMPETITION.md CMP-17, as app2)', () => {
  it('a finished game not locked yet is provisional', () => {
    expect(gameDisplayStatus('ATT', '')).toBe('ATT')
    expect(gameDisplayStatus('ON', '')).toBe('ON')
    expect(gameDisplayStatus('END', 'O')).toBe('END')
    expect(gameDisplayStatus('END', 'N')).toBe('PROVISIONAL')
    expect(gameDisplayStatus('END', null)).toBe('PROVISIONAL')
  })

  it('periods are regular time, overtime or shoot-out, in full or abbreviated', () => {
    expect(periodLabel('M2', false)).toEqual({ key: 'regular', n: 2 })
    expect(periodLabel('M2', true)).toEqual({ key: 'regularShort', n: 2 })
    expect(periodLabel('P1', true)).toEqual({ key: 'overtimeShort', n: 1 })
    expect(periodLabel('TB', false)).toEqual({ key: 'shootout', n: null })
    expect(periodLabel(null, true)).toBeNull()
  })
})
