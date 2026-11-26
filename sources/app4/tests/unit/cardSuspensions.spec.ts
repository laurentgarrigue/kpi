// Page Suspensions (PAGE_SUSPENSIONS.md) et statut « Suspendu » (FEATURE_CARD_SUSPENSIONS.md S18).
import { describe, expect, it } from 'vitest'
import { canChangeStatus, canManageSuspensions, filterSuspensions, ruleLabel, suspensionText, type CardSuspension, type SuspensionCatalog } from '../../utils/cardSuspensions'

const suspension = (over: Partial<CardSuspension>): CardSuspension => ({
  id: 1, state: 'ACTIVE', matric: 9961, name: 'SUSPENDU Joueur', club: 'C001', section: 2,
  competition: { code: 'N1H', label: 'Nationale 1' }, gamedayId: 1, match: { id: 1, number: 2, date: '2027-03-07' },
  rule: 'BLACK_GAME', rules: ['BLACK_GAME'], catalogVersion: 1, tally: 1, matches: 2, gamedayTotal: 2, appealJury: true,
  detectedAt: '2027-03-07 18:00:00', notifiedAt: '2027-03-07 18:00:00', cancelledAt: null, liftedAt: null, liftedBy: null,
  reportId: 7, team: { id: 5, label: 'Un' }, ...over,
})

const catalog: SuspensionCatalog = {
  version: 1, firstSeason: '2027', sections: [2],
  rules: [{ code: 'BLACK_GAME', labelFr: '1 carton noir dans un match', labelEn: '1 black card in a game', cards: ['D'], scope: 'GAME',
    threshold: 1, repeat: 'EVERY_CARD_AFTER', distinctGames: false, gamedayTotal: true, matches: 2, appealJury: true }],
}

describe('suspensions', () => {
  it('PSU-01 : page et statut Suspendu réservés aux profils 1 et 2', () => {
    expect([1, 2, 3, 9].map(canManageSuspensions)).toEqual([true, true, false, false])
    expect(canChangeStatus(7, '-')).toBe(true)
    expect(canChangeStatus(7, 'S')).toBe(false)
    expect(canChangeStatus(2, 'S')).toBe(true)
  })

  it('PSU-02 : filtre par état et recherche par nom ou licence', () => {
    const items = [suspension({ id: 1 }), suspension({ id: 2, state: 'LIFTED', name: 'AUTRE Joueur', matric: 1234 }), suspension({ id: 3, state: 'CANCELLED' })]
    expect(filterSuspensions(items, 'ACTIVE', '').map(s => s.id)).toEqual([1])
    expect(filterSuspensions(items, 'ALL', '').map(s => s.id)).toEqual([1, 2, 3])
    expect(filterSuspensions(items, 'ALL', 'autre').map(s => s.id)).toEqual([2])
    expect(filterSuspensions(items, 'ALL', '123').map(s => s.id)).toEqual([2])
  })

  it('PSU-03 : ajout et total de la journée pour les cartons noirs', () => {
    expect(suspensionText({ matches: 1, gamedayTotal: null })).toEqual({ key: 'one', matches: 1, total: 1 })
    expect(suspensionText({ matches: 2, gamedayTotal: 2 })).toEqual({ key: 'many', matches: 2, total: 2 })
    expect(suspensionText({ matches: 1, gamedayTotal: 3 })).toEqual({ key: 'more', matches: 1, total: 3 })
    expect(suspensionText({ matches: 0, gamedayTotal: 4 })).toEqual({ key: 'none', matches: 0, total: 4 })
  })

  it('PSU-05 : libellé de règle tiré du catalogue, le code à défaut', () => {
    expect(ruleLabel(catalog, 'BLACK_GAME', 'fr')).toBe('1 carton noir dans un match')
    expect(ruleLabel(catalog, 'BLACK_GAME', 'en')).toBe('1 black card in a game')
    expect(ruleLabel(null, 'BLACK_GAME', 'fr')).toBe('BLACK_GAME')
  })
})
