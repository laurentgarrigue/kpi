// Suivi des Désignations (FEATURE_DESIGNATIONS.md § 4, SUI-01 à SUI-06) : règles d'affichage pures.
import { describe, expect, it } from 'vitest'
import { canAddHolder, canEditRole, countStatuses, gamedayDates, gamedayRows, holderLabel, isCompetitionEnded, isInherited, roleLabel, rowKindOf, scopeCells, scopeOfRow, statusClasses, visibleRoles, type CoverageCell, type CoverageHolder, type CoverageMatrix, type RoleDefinition } from '../../utils/designations'

const holder = (origin: CoverageHolder['origin'], club: string | null = 'C001'): CoverageHolder => ({ designationId: 1, matric: 1001, name: 'DUPONT Jean', club, origin, warnings: [] })
const cell = (status: CoverageCell['status'], holders: CoverageHolder[] = []): CoverageCell => ({ requirement: 'required', status, holders })
const role = (maxPerScope: number | null): RoleDefinition => ({ code: 'R1', label: 'R1', scopes: ['JOURNEE'], mandateProfile: 6, authorities: ['CNA'], maxPerScope, designableByMaxProfile: 3 })

describe('libellés des rôles (DES-12)', () => {
  const delegate: RoleDefinition = {
    ...role(1),
    code: 'DELEGUE_CNA',
    label: 'Représentant CNA',
    labels: { national: { fr: 'Représentant CNA', en: 'Chief Official' }, other: { fr: 'Officiel principal', en: 'Chief Official' } },
  }

  it('suit le niveau de la compétition et la langue', () => {
    expect(roleLabel(delegate, 'fr', true)).toBe('Représentant CNA')
    expect(roleLabel(delegate, 'fr', false)).toBe('Officiel principal')
    expect(roleLabel(delegate, 'en', false)).toBe('Chief Official')
  })

  it('niveau inconnu : les deux libellés quand ils diffèrent, un seul sinon', () => {
    expect(roleLabel(delegate, 'fr')).toBe('Représentant CNA / Officiel principal')
    expect(roleLabel(delegate, 'en')).toBe('Chief Official')
  })

  it('rôle inconnu ou sans libellés traduits : code, puis libellé de l\'API', () => {
    expect(roleLabel(undefined, 'fr', true, 'R1')).toBe('R1')
    expect(roleLabel({ ...role(1), label: 'R1' }, 'en', true)).toBe('R1')
  })
})

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
    // club étranger (code pays + numéro) : le pays seul
    expect(holderLabel(holder('JOURNEE', 'ITA01'))).toBe('DUPONT Jean (ITA)')
    expect(holderLabel(holder('JOURNEE', 'BEL00'))).toBe('DUPONT Jean (BEL)')
    // clubs et comités français inchangés
    expect(holderLabel(holder('JOURNEE', '4401'))).toBe('DUPONT Jean (4401)')
    expect(holderLabel(holder('JOURNEE', 'CD081'))).toBe('DUPONT Jean (CD081)')
  })

  it('compte les cases rouges et orange', () => {
    const matrix: CoverageMatrix = {
      season: '2998',
      gamedayRoles: ['R1'],
      competitions: [{
        code: 'NDES', label: 'N', type: 'CHPT', statut: 'ON', national: true, cells: { RC: cell('red') },
        gamedays: [{ id: 1, label: 'J1', place: '', organiser: { code: '', label: '' }, start: null, end: null, eventIds: [], cells: { R1: cell('orange'), JURY_APPEL: cell('empty') } }],
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

describe('phases d\'un tournoi (CP)', () => {
  const gameday = (id: number, start: string, place: string, cells: Record<string, CoverageCell>, organiser = { code: '', label: '' }) => ({ id, label: `Phase ${id}`, place, department: '', organiser, start, end: start, eventIds: [], cells })
  const own = (designationId: number, matric: number): CoverageHolder => ({ designationId, matric, name: `P${matric}`, club: null, origin: 'JOURNEE', warnings: [] })
  const fromCompetition: CoverageHolder = { designationId: 9, matric: 7, name: 'P7', club: null, origin: 'COMPETITION', warnings: [] }

  it('regroupe les phases de mêmes dates et lieu en une ligne', () => {
    const rows = gamedayRows({ type: 'CP', gamedays: [
      gameday(1, '2998-06-01', 'A', { R1: cell('green', [fromCompetition]) }),
      gameday(2, '2998-06-01', 'A', { R1: cell('green', [fromCompetition]) }),
      gameday(3, '2998-06-08', 'A', { R1: cell('red') }),
    ] })
    expect(rows.map(row => row.ids)).toEqual([[1, 2], [3]])
    expect(rows[0].cells.R1.holders).toEqual([{ ...fromCompetition, designationIds: [9] }])
  })

  it('garde le club organisateur du premier renseigné parmi les phases regroupées', () => {
    const saintOmer = { code: '6220', label: 'KAYAK CLUB SAINT-OMER' }
    const [row] = gamedayRows({ type: 'CP', gamedays: [gameday(1, '2998-06-01', 'A', {}), gameday(2, '2998-06-01', 'A', {}, saintOmer)] })
    expect(row.organiser).toEqual(saintOmer)
  })

  it('ne regroupe pas les journées d\'un championnat', () => {
    const rows = gamedayRows({ type: 'CHPT', gamedays: [gameday(1, '2998-06-01', 'A', {}), gameday(2, '2998-06-01', 'A', {})] })
    expect(rows.map(row => row.ids)).toEqual([[1], [2]])
  })

  it('l\'état d\'un groupe est le plus mauvais de ses phases ; une même personne désignée sur chaque phase est une seule fois', () => {
    const [row] = gamedayRows({ type: 'CP', gamedays: [
      gameday(1, '2998-06-01', 'A', { R1: cell('green', [own(11, 5)]), JUGE_ARBITRE: cell('empty') }),
      gameday(2, '2998-06-01', 'A', { R1: cell('red'), JUGE_ARBITRE: cell('green', [own(12, 6)]) }),
    ] })
    expect(row.cells.R1.status).toBe('red')
    expect(row.cells.JUGE_ARBITRE.status).toBe('empty')
    const both = gamedayRows({ type: 'CP', gamedays: [
      gameday(1, '2998-06-01', 'A', { R1: cell('green', [own(11, 5)]) }),
      gameday(2, '2998-06-01', 'A', { R1: cell('green', [own(13, 5)]) }),
    ] })[0]
    expect(both.cells.R1.holders).toHaveLength(1)
    expect(both.cells.R1.holders[0].designationIds).toEqual([11, 13])
  })

  it('un rôle se modifie selon le profil, sinon il se consulte seulement', () => {
    const rc = { ...role(null), code: 'RC', designableByMaxProfile: 2 }
    expect(canEditRole(rc, 2)).toBe(true)
    expect(canEditRole(rc, 3)).toBe(false)
    expect(canEditRole(undefined, 1)).toBe(false)
  })

  it('une compétition terminée est en lecture seule', () => {
    expect(isCompetitionEnded({ statut: 'END' })).toBe(true)
    expect(isCompetitionEnded({ statut: 'ON' })).toBe(false)
  })

  it('filtre les rôles affichés', () => {
    expect(visibleRoles(['DELEGUE_CNA', 'R1', 'JUGE_ARBITRE'], ['R1'])).toEqual(['DELEGUE_CNA', 'JUGE_ARBITRE'])
    expect(visibleRoles(['DELEGUE_CNA', 'R1'], [])).toEqual(['DELEGUE_CNA', 'R1'])
  })
})

describe('bloc « Désignations » d\'un périmètre', () => {
  const roles: RoleDefinition[] = [
    { code: 'RC', label: 'RC', scopes: ['COMPETITION', 'NIVEAU'], mandateProfile: 4, authorities: ['CNA'], maxPerScope: null, designableByMaxProfile: 2 },
    { code: 'R1', label: 'R1', scopes: ['EVENEMENT', 'COMPETITION', 'JOURNEE'], mandateProfile: 6, authorities: ['CNA'], maxPerScope: 1, designableByMaxProfile: 3 },
    { code: 'RESP_EQUIPE', label: 'Resp', scopes: ['EQUIPE'], mandateProfile: 7, authorities: ['CNA'], maxPerScope: null, designableByMaxProfile: 3 },
  ]

  it('liste, par rôle autorisé sur le périmètre, ses Désignations propres', () => {
    const cells = scopeCells('COMPETITION', roles, [{ id: 5, role: 'RC', matric: 10, lastName: 'DUPONT', firstName: 'Jean', club: 'C001' }])
    expect(Object.keys(cells)).toEqual(['RC', 'R1'])
    expect(cells.RC.status).toBe('green')
    expect(cells.RC.holders[0]).toMatchObject({ designationId: 5, name: 'DUPONT Jean', origin: 'COMPETITION' })
    expect(cells.R1.status).toBe('empty')
  })

  it('associe un périmètre à sa ligne de suivi', () => {
    expect(rowKindOf('JOURNEE')).toBe('gameday')
    expect(rowKindOf('EVENEMENT')).toBe('event')
    expect(rowKindOf('NIVEAU')).toBeNull()
  })
})
