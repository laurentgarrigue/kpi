import { describe, expect, it } from 'vitest'
import { overlayCategoryLine, overlayTeamFormat, overlayTeamLabel } from '../../utils/overlayLabels'

// PAGE_INCRUSTATION.md §8 — score banner in two formats: "club" (full team names) and
// "inter" (nations: first 3 characters, as the legacy score.php did).
describe('overlay team format', () => {
  it('defaults to inter (nations)', () => {
    expect(overlayTeamFormat(undefined)).toBe('inter')
    expect(overlayTeamFormat('nations')).toBe('inter')
    expect(overlayTeamFormat('inter')).toBe('inter')
  })

  it('accepts club and clubs', () => {
    expect(overlayTeamFormat('club')).toBe('club')
    expect(overlayTeamFormat('clubs')).toBe('club')
  })
})

describe('overlay team label', () => {
  it('keeps the full name in club format', () => {
    expect(overlayTeamLabel('Acigné Kayak Polo', 'club')).toBe('Acigné Kayak Polo')
  })

  it('shows the first 3 characters, upper-cased, in inter format', () => {
    expect(overlayTeamLabel('France Women U21', 'inter')).toBe('FRA')
    expect(overlayTeamLabel('Netherlands', 'inter')).toBe('NET')
    expect(overlayTeamLabel('  ned ', 'inter')).toBe('NED')
  })

  it('is empty without a team', () => {
    expect(overlayTeamLabel(null, 'inter')).toBe('')
    expect(overlayTeamLabel(null, 'club')).toBe('')
  })
})

describe('overlay category line', () => {
  it('joins category and phase', () => {
    expect(overlayCategoryLine('Women U21', '5th place')).toBe('Women U21 - 5th place')
  })

  it('shows what exists when one is missing', () => {
    expect(overlayCategoryLine('Women U21', null)).toBe('Women U21')
    expect(overlayCategoryLine(null, 'Final')).toBe('Final')
    expect(overlayCategoryLine('', '  ')).toBe('')
  })
})
