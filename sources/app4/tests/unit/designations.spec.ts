// Suivi des Désignations (FEATURE_DESIGNATIONS.md § 4, SUI-01 à SUI-06) : règles d'affichage pures.
import { describe, expect, it } from 'vitest'
import { canAddHolder, countStatuses, gamedayDates, holderLabel, isInherited, scopeOfRow, statusClasses, type CoverageCell, type CoverageHolder, type CoverageMatrix, type RoleDefinition } from '../../utils/designations'

const holder = (origin: CoverageHolder['origin'], club: string | null = 'C001'): CoverageHolder => ({ designationId: 1, matric: 1001, name: 'DUPONT Jean', club, origin, warnings: [] })
const cell = (status: CoverageCell['status'], holders: CoverageHolder[] = []): CoverageCell => ({ requirement: 'required', status, holders })
const role = (maxPerScope: number | null): RoleDefinition => ({ code: 'R1', label: 'R1', scopes: ['JOURNEE'], mandateProfile: 6, authorities: ['CNA'], maxPerScope })

describe('suivi des désignations', () => {
  it('SUI-01, SUI-04 : chaque état a sa couleur', () => {
    expect(statusClasses('red')).toContain('bg-red-100')
    expect(statusClasses('orange')).toContain('bg-orange-100')
    expect(statusClasses('green')).toContain('bg-green-100')
  })

  it('pose une Désignation sur le périmètre de la ligne', () => {
    expect(scopeOfRow('competition', 'N1H')).toEqual({ scopeType: 'COMPETITION', scopeId: 'N1H' })
    expect(scopeOfRow('gameday', 9801)).toEqual({ scopeType: 'JOURNEE', scopeId: '9801' })
    expect(scopeOfRow('team', 42)).toEqual({ scopeType: 'EQUIPE', scopeId: '42' })
  })

  it('SUI-03 : un titulaire hérité se distingue d\'un titulaire propre', () => {
    expect(isInherited(holder('EVENEMENT'), 'gameday')).toBe(true)
    expect(isInherited(holder('JOURNEE'), 'gameday')).toBe(false)
  })

  it('n\'offre l\'ajout que sous le nombre maximal de titulaires propres', () => {
    expect(canAddHolder(cell('red'), 'gameday', role(1))).toBe(true)
    expect(canAddHolder(cell('green', [holder('JOURNEE')]), 'gameday', role(1))).toBe(false)
    expect(canAddHolder(cell('green', [holder('EVENEMENT')]), 'gameday', role(1))).toBe(true)
    expect(canAddHolder(cell('green', [holder('JOURNEE')]), 'gameday', role(null))).toBe(true)
    expect(canAddHolder(cell('not_applicable'), 'gameday', role(1))).toBe(false)
  })

  it('affiche le club du titulaire', () => {
    expect(holderLabel(holder('JOURNEE'))).toBe('DUPONT Jean (C001)')
    expect(holderLabel(holder('JOURNEE', null))).toBe('DUPONT Jean')
  })

  it('compte les cases rouges et orange', () => {
    const matrix: CoverageMatrix = {
      season: '2998',
      gamedayRoles: ['R1'],
      competitions: [{
        code: 'NDES', label: 'N', national: true, cells: { RC: cell('red') },
        gamedays: [{ id: 1, label: 'J1', place: '', start: null, end: null, eventIds: [], cells: { R1: cell('orange'), JURY_APPEL: cell('empty') } }],
        teams: [{ id: 2, label: 'E', club: null, cells: { RESP_EQUIPE: cell('red') } }],
      }],
    }
    expect(countStatuses(matrix)).toEqual({ red: 2, orange: 1 })
  })

  it('date une journée', () => {
    expect(gamedayDates('2998-03-07', '2998-03-08')).toBe('07/03 – 08/03')
    expect(gamedayDates('2998-03-07', '2998-03-07')).toBe('07/03')
    expect(gamedayDates(null, null)).toBe('')
  })
})
