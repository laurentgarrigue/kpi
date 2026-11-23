// Suivi des Désignations (DOC/specs/FEATURE_DESIGNATIONS.md § 4, SUI-01 à SUI-06) : règles d'affichage pures.
// Les états, exigences et titulaires viennent de api2 (GET /admin/designations/coverage) : rien n'est recalculé ici.

export type ScopeType = 'NIVEAU' | 'COMPETITION' | 'EQUIPE' | 'EVENEMENT' | 'JOURNEE'
export type CoverageStatus = 'red' | 'orange' | 'green' | 'empty' | 'not_applicable'

export interface CoverageHolder {
  designationId: number
  matric: number
  name: string
  club: string | null
  origin: ScopeType
  warnings: string[]
}

export interface CoverageCell {
  requirement: 'required' | 'optional' | 'not_applicable'
  status: CoverageStatus
  holders: CoverageHolder[]
}

export interface CoverageGameday {
  id: number
  label: string
  place: string
  start: string | null
  end: string | null
  eventIds: number[]
  cells: Record<string, CoverageCell>
}

export interface CoverageTeam {
  id: number
  label: string
  club: string | null
  cells: Record<string, CoverageCell>
}

export interface CoverageCompetition {
  code: string
  label: string
  national: boolean
  cells: Record<string, CoverageCell>
  gamedays: CoverageGameday[]
  teams: CoverageTeam[]
}

export interface CoverageMatrix {
  season: string
  gamedayRoles: string[]
  competitions: CoverageCompetition[]
}

export interface RoleDefinition {
  code: string
  label: string
  scopes: ScopeType[]
  mandateProfile: number | null
  authorities: string[]
  maxPerScope: number | null
}

export type RowKind = 'competition' | 'gameday' | 'team'

const STATUS_CLASSES: Record<CoverageStatus, string> = {
  red: 'bg-red-100 text-red-900 dark:bg-red-900/40 dark:text-red-100',
  orange: 'bg-orange-100 text-orange-900 dark:bg-orange-900/40 dark:text-orange-100',
  green: 'bg-green-100 text-green-900 dark:bg-green-900/40 dark:text-green-100',
  empty: 'bg-white text-header-700 dark:bg-header-900 dark:text-header-200',
  not_applicable: 'bg-header-100 text-header-400 dark:bg-header-800 dark:text-header-500',
}

export const statusClasses = (status: CoverageStatus): string => STATUS_CLASSES[status]

/** Périmètre d'une Désignation posée depuis une ligne de la matrice. */
export const scopeOfRow = (kind: RowKind, id: string | number): { scopeType: ScopeType, scopeId: string } => ({
  scopeType: ({ competition: 'COMPETITION', gameday: 'JOURNEE', team: 'EQUIPE' } as const)[kind],
  scopeId: String(id),
})

/** Un titulaire hérité (SUI-03) se supprime depuis la ligne où il a été désigné, pas depuis la journée. */
export const isInherited = (holder: CoverageHolder, kind: RowKind): boolean => holder.origin !== scopeOfRow(kind, '').scopeType

/** On peut encore désigner sur ce périmètre tant que le nombre maximal de titulaires propres n'est pas atteint. */
export const canAddHolder = (cell: CoverageCell, kind: RowKind, role: RoleDefinition | undefined): boolean => {
  if (!role || cell.status === 'not_applicable') {
    return false
  }
  const own = cell.holders.filter(holder => !isInherited(holder, kind)).length
  return role.maxPerScope === null || own < role.maxPerScope
}

export const holderLabel = (holder: CoverageHolder): string => (holder.club ? `${holder.name} (${holder.club})` : holder.name)

/** Nombre de cases rouges et orange de la matrice, pour l'en-tête. */
export const countStatuses = (matrix: CoverageMatrix): Record<'red' | 'orange', number> => {
  const counts = { red: 0, orange: 0 }
  const tally = (cells: Record<string, CoverageCell>) => {
    for (const cell of Object.values(cells)) {
      if (cell.status === 'red' || cell.status === 'orange') {
        counts[cell.status]++
      }
    }
  }
  for (const competition of matrix.competitions) {
    tally(competition.cells)
    competition.gamedays.forEach(gameday => tally(gameday.cells))
    competition.teams.forEach(team => tally(team.cells))
  }
  return counts
}

/** Date d'une journée, « 07/03 » ou « 07/03 – 08/03 ». */
export const gamedayDates = (start: string | null, end: string | null): string => {
  const short = (date: string | null) => (date ? `${date.slice(8, 10)}/${date.slice(5, 7)}` : '')
  return end && end !== start ? `${short(start)} – ${short(end)}` : short(start)
}
