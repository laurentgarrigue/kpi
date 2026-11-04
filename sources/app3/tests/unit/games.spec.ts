import { describe, expect, it } from 'vitest'
import { drawSlotMessage, ordinal } from '../../app/utils/games'
import { teamLogoUrl } from '../../app/utils/page-links'

describe('ordinal', () => {
  it('CMP-20: « 1er », « 2e » in French; « 1st », « 2nd », « 3rd », « 11th » in English', () => {
    expect([1, 2, 3].map(n => ordinal(n, 'fr'))).toEqual(['1er', '2e', '3e'])
    expect([1, 2, 3, 4, 11, 22].map(n => ordinal(n, 'en'))).toEqual(['1st', '2nd', '3rd', '4th', '11th', '22nd'])
  })
})

describe('drawSlotMessage', () => {
  it('CMP-20: the message of each kind of slot, with its parameters', () => {
    expect(drawSlotMessage({ kind: 'pool', rank: 1, pool: 'B' }, 'fr')).toEqual({ key: 'results.draw.pool', params: { rank: '1er', pool: 'B' } })
    expect(drawSlotMessage({ kind: 'winner', game: 222 }, 'fr')).toEqual({ key: 'results.draw.winner', params: { number: 222 } })
    expect(drawSlotMessage({ kind: 'loser', game: 12 }, 'en')).toEqual({ key: 'results.draw.loser', params: { number: 12 } })
    expect(drawSlotMessage({ kind: 'team', game: 7 }, 'fr')).toEqual({ key: 'results.draw.team', params: { number: 7 } })
    expect(drawSlotMessage({ kind: 'text', text: 'T7' }, 'fr')).toEqual({ key: 'results.draw.text', params: { text: 'T7' } })
    expect(drawSlotMessage(null, 'fr')).toEqual({ key: 'results.draw.unknown', params: {} })
  })
})

describe('teamLogoUrl', () => {
  it('CMP-19: the logo of a team under /img/, none for the empty placeholder', () => {
    expect(teamLogoUrl('KIP/logo/5006-logo.png', 'https://kpi.localhost')).toBe('https://kpi.localhost/img/KIP/logo/5006-logo.png')
    expect(teamLogoUrl('KIP/logo/empty-logo.png', 'https://kpi.localhost')).toBeNull()
    expect(teamLogoUrl(null, 'https://kpi.localhost')).toBeNull()
  })
})
