import { describe, expect, it } from 'vitest'
import { addMinutesToTime, continuationOf } from '../../utils/gameFormDefaults'

describe('création de match : suite du match précédent', () => {
  it('ajoute l\'intervalle à l\'heure, sur 24 h', () => {
    expect(addMinutesToTime('09:30', 40)).toBe('10:10')
    expect(addMinutesToTime('23:50:00', 40)).toBe('00:30')
    expect(addMinutesToTime(null, 40)).toBe('')
  })

  it('reprend journée, date, terrain, type, N° + 1 et heure + intervalle', () => {
    expect(continuationOf({ idJournee: 7, dateMatch: '2026-05-02', heureMatch: '10:00:00', numeroOrdre: 12, terrain: '2', type: 'E' }))
      .toEqual({ idJournee: 7, dateMatch: '2026-05-02', heureMatch: '10:40', numeroOrdre: 13, terrain: '2', type: 'E', intervalle: 40 })
  })

  it('tolère les champs absents', () => {
    expect(continuationOf({ idJournee: 7, dateMatch: null, heureMatch: null, numeroOrdre: null, terrain: null, type: null }, 30))
      .toEqual({ idJournee: 7, dateMatch: '', heureMatch: '', numeroOrdre: null, terrain: '', type: 'C', intervalle: 30 })
  })
})
