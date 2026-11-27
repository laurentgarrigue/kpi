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
  /** Toutes les Désignations regroupées sous ce titulaire (phases d'un tournoi) ; à défaut, designationId. */
  designationIds?: number[]
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
  department: string
  /** Club organisateur, identifié comme dans le classement national des clubs (code saisi, sinon libellé). */
  organiser: { code: string, label: string }
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
  /** Code_typeclt : CP = tournoi, dont les phases de mêmes dates et lieu sont regroupées. */
  type: string
  /** ATT / ON / END : une compétition terminée (END) est en lecture seule. */
  statut: string
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

/** Libellé d'un rôle par langue ; il dépend du niveau de la compétition (DES-12). */
export interface RoleLabels {
  fr: string
  en: string
}

export interface RoleDefinition {
  code: string
  /** Libellé français des compétitions nationales (repli quand `labels` manque). */
  label: string
  labels?: { national: RoleLabels, other: RoleLabels }
  scopes: ScopeType[]
  mandateProfile: number | null
  authorities: string[]
  maxPerScope: number | null
  /** Profil le moins élevé (numéro le plus grand) qui peut désigner ce rôle. */
  designableByMaxProfile: number
}

export type RowKind = 'competition' | 'gameday' | 'team' | 'event'

const STATUS_CLASSES: Record<CoverageStatus, string> = {
  red: 'bg-red-100 text-red-900 dark:bg-red-900/40 dark:text-red-100',
  orange: 'bg-orange-100 text-orange-900 dark:bg-orange-900/40 dark:text-orange-100',
  green: 'bg-green-100 text-green-900 dark:bg-green-900/40 dark:text-green-100',
  empty: 'bg-white text-header-700 dark:bg-header-900 dark:text-header-200',
  not_applicable: 'bg-header-100 text-header-400 dark:bg-header-800 dark:text-header-500',
}

/** Compétition terminée : ses désignations et ses clubs organisateurs ne se modifient plus (verrou de l'api2). */
export const isCompetitionEnded = (competition: Pick<CoverageCompetition, 'statut'>): boolean => competition.statut === 'END'

/** Couleurs du statut d'une compétition, celles de la page Compétitions. */
export const COMPETITION_STATUS_CLASSES: Record<string, string> = {
  ATT: 'bg-header-600 text-header-50 italic',
  ON: 'bg-success-500 text-success-50 italic',
  END: 'bg-primary-900 text-primary-50 italic',
}

export const statusClasses = (status: CoverageStatus): string => STATUS_CLASSES[status]

/** Périmètre d'une Désignation posée depuis une ligne de la matrice. */
export const scopeOfRow = (kind: RowKind, id: string | number): { scopeType: ScopeType, scopeId: string } => ({
  scopeType: ({ competition: 'COMPETITION', gameday: 'JOURNEE', team: 'EQUIPE', event: 'EVENEMENT' } as const)[kind],
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

/** Un rôle se modifie (ajout, retrait) si le profil de l'utilisateur le permet ; sinon il se consulte seulement. */
export const canEditRole = (role: RoleDefinition | undefined, profile: number): boolean => !!role && profile <= role.designableByMaxProfile

/**
 * Libellé d'un rôle (DES-12) dans la langue de l'interface : celui des nationales ou des autres sections ; niveau
 * inconnu (en-têtes communs à tous les niveaux) = les deux, « Représentant CNA / Officiel principal ».
 */
export const roleLabel = (role: RoleDefinition | undefined, locale: string, national?: boolean, fallback = ''): string => {
  if (!role) return fallback
  if (!role.labels) return role.label
  const lang: keyof RoleLabels = locale === 'en' ? 'en' : 'fr'
  const nationalLabel = role.labels.national[lang]
  const otherLabel = role.labels.other[lang]
  if (national === undefined) {
    return nationalLabel === otherLabel ? nationalLabel : `${nationalLabel} / ${otherLabel}`
  }
  return national ? nationalLabel : otherLabel
}

/** Club étranger : code pays sur trois lettres suivi d'un numéro (ITA01) ; les clubs et comités français n'en ont pas. */
const FOREIGN_CLUB = /^([A-Z]{3})\d+$/

/** Club d'un officiel tel qu'affiché : le pays seul pour un club étranger (ITA01 → ITA), le code sinon. */
export const clubLabel = (club: string): string => FOREIGN_CLUB.exec(club)?.[1] ?? club

export const holderLabel = (holder: CoverageHolder): string => (holder.club ? `${holder.name} (${clubLabel(holder.club)})` : holder.name)

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

/** Une ligne du tableau des journées : une journée, ou les phases d'un tournoi (CP) de mêmes dates et lieu. */
export interface GamedayRow {
  key: string
  ids: number[]
  labels: string[]
  place: string
  department: string
  organiser: { code: string, label: string }
  start: string | null
  end: string | null
  cells: Record<string, CoverageCell>
}

/** Du pire au meilleur : l'état d'un groupe de phases est le pire de ses phases. */
const STATUS_ORDER: CoverageStatus[] = ['red', 'orange', 'empty', 'green', 'not_applicable']

const mergeCells = (cells: CoverageCell[]): CoverageCell => {
  const holders = new Map<string, CoverageHolder>()
  for (const cell of cells) {
    for (const holder of cell.holders) {
      // une Désignation héritée est commune aux phases ; une Désignation propre se répète sur chaque phase
      const key = holder.origin === 'JOURNEE' ? `own-${holder.matric}` : `inherited-${holder.designationId}`
      const known = holders.get(key)
      const ids = [...(known?.designationIds ?? []), ...(holder.designationIds ?? [holder.designationId])]
      holders.set(key, { ...(known ?? holder), designationIds: [...new Set(ids)] })
    }
  }
  return {
    requirement: cells[0]?.requirement ?? 'optional',
    status: STATUS_ORDER.find(status => cells.some(cell => cell.status === status)) ?? 'empty',
    holders: [...holders.values()],
  }
}

export const gamedayRows = (competition: Pick<CoverageCompetition, 'type' | 'gamedays'>): GamedayRow[] => {
  const groups = new Map<string, CoverageGameday[]>()
  for (const gameday of competition.gamedays) {
    const key = competition.type === 'CP' ? `${gameday.start}|${gameday.end}|${gameday.place}` : String(gameday.id)
    groups.set(key, [...(groups.get(key) ?? []), gameday])
  }
  return [...groups.entries()].map(([key, gamedays]) => {
    const roles = [...new Set(gamedays.flatMap(gameday => Object.keys(gameday.cells)))]
    return {
      key,
      ids: gamedays.map(gameday => gameday.id),
      labels: gamedays.map(gameday => gameday.label),
      place: gamedays[0].place,
      department: gamedays[0].department,
      // les phases d'un tournoi ont le même organisateur ; on garde le premier renseigné
      organiser: gamedays.map(gameday => gameday.organiser).find(organiser => organiser.label !== '') ?? gamedays[0].organiser,
      start: gamedays[0].start,
      end: gamedays[0].end,
      cells: Object.fromEntries(roles.map(role => [role, mergeCells(gamedays.map(gameday => gameday.cells[role]).filter(Boolean))])),
    }
  })
}

/** Rôles affichés : tous sauf ceux masqués par le menu Affichage, dans l'ordre de l'API. */
export const visibleRoles = (roles: string[], hidden: string[]): string[] => roles.filter(role => !hidden.includes(role))

/** Identifiants à supprimer pour retirer un titulaire (toutes ses Désignations regroupées). */
export const designationIdsOf = (holder: CoverageHolder): number[] => holder.designationIds ?? [holder.designationId]

/** Cible de la fenêtre « Désigner » : une ligne du suivi (kind + ids) ou un périmètre direct (scopeType + scopeIds). */
export interface DesignationTarget {
  role: string
  label: string
  /** Niveau de la compétition, pour le libellé du rôle (DES-12) ; absent = inconnu. */
  national?: boolean
  kind?: RowKind
  ids?: (string | number)[]
  scopeType?: ScopeType
  scopeIds?: string[]
}

/**
 * Cases d'un périmètre autre qu'une journée (compétition, événement, équipe) : ses Désignations propres, par rôle
 * autorisé sur ce périmètre. Pas d'exigence ici : le suivi de couverture la porte.
 */
export const scopeCells = (
  scopeType: ScopeType,
  roles: RoleDefinition[],
  items: { id: number, role: string, matric: number, lastName: string | null, firstName: string | null, club: string | null }[],
): Record<string, CoverageCell> => Object.fromEntries(roles
  .filter(role => role.scopes.includes(scopeType))
  .map((role) => {
    const holders: CoverageHolder[] = items.filter(item => item.role === role.code).map(item => ({
      designationId: item.id,
      matric: item.matric,
      name: `${item.lastName ?? ''} ${item.firstName ?? ''}`.trim(),
      club: item.club,
      origin: scopeType,
      warnings: [],
    }))
    return [role.code, { requirement: 'optional', status: holders.length ? 'green' : 'empty', holders }]
  }))

/** Ligne de suivi correspondant à un périmètre (pour savoir quels titulaires sont hérités). */
export const rowKindOf = (scopeType: ScopeType): RowKind | null =>
  ({ COMPETITION: 'competition', JOURNEE: 'gameday', EQUIPE: 'team', EVENEMENT: 'event' } as Partial<Record<ScopeType, RowKind>>)[scopeType] ?? null

/** Périmètre ouvert dans la fenêtre « Désignations » d'une page compétitions, événements ou équipes. */
export interface ScopeModalTarget { season: string, scopeType: ScopeType, scopeId: string | number, label: string }
