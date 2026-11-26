/**
 * Page Suspensions (DOC/specs/PAGE_SUSPENSIONS.md) et statut « Suspendu » de la feuille de présence
 * (FEATURE_CARD_SUSPENSIONS.md S16 à S19). Fonctions pures, testées par Vitest ; les règles viennent du catalogue
 * d'api2 (ADR-0004), jamais codées ici.
 */

export const SUSPENDED_STATUS = 'S'
/** Seuls les profils 1 et 2 donnent ou retirent le statut « Suspendu », et voient la page (S18). */
const SUSPENSION_MANAGER_MAX_PROFILE = 2

export type SuspensionState = 'ACTIVE' | 'LIFTED' | 'CANCELLED'
export type SuspensionFilter = SuspensionState | 'ALL'

export interface SuspensionRuleDefinition {
  code: string
  labelFr: string
  labelEn: string
  cards: string[]
  scope: 'GAME' | 'GAMEDAY' | 'SEASON'
  threshold: number
  repeat: string
  distinctGames: boolean
  gamedayTotal: boolean
  matches: number
  appealJury: boolean
}

export interface SuspensionCatalog {
  version: number
  firstSeason: string
  sections: number[]
  rules: SuspensionRuleDefinition[]
}

export interface CardSuspension {
  id: number
  state: SuspensionState
  matric: number
  name: string
  club: string | null
  section: number
  competition: { code: string, label: string | null }
  gamedayId: number
  match: { id: number, number: string | number | null, date: string | null }
  rule: string
  rules: string[]
  catalogVersion: number
  tally: number
  matches: number
  gamedayTotal: number | null
  appealJury: boolean
  detectedAt: string
  notifiedAt: string | null
  cancelledAt: string | null
  liftedAt: string | null
  liftedBy: string | null
  reportId: number | null
  team: { id: number, label: string | null } | null
}

export interface CardSuspensionsResponse {
  season: string
  catalog: SuspensionCatalog | null
  suspensions: CardSuspension[]
}

export function canManageSuspensions(profile: number): boolean {
  return profile <= SUSPENSION_MANAGER_MAX_PROFILE
}

/** Un joueur suspendu ne change de statut que par un profil 1–2, qui seul peut aussi le suspendre (S18). */
export function canChangeStatus(profile: number, current: string): boolean {
  return current !== SUSPENDED_STATUS || canManageSuspensions(profile)
}

/** PSU-02 : filtre par état, puis recherche sur le nom ou la licence. */
export function filterSuspensions(items: CardSuspension[], state: SuspensionFilter, query: string): CardSuspension[] {
  const needle = query.trim().toLowerCase()
  return items.filter(item => (state === 'ALL' || item.state === state)
    && (needle === '' || item.name.toLowerCase().includes(needle) || String(item.matric).includes(needle)))
}

/** PSU-05 : libellé de la règle, depuis le catalogue reçu d'api2. */
export function ruleLabel(catalog: SuspensionCatalog | null, code: string, locale: string): string {
  const rule = catalog?.rules.find(r => r.code === code)
  if (!rule) return code
  return locale === 'en' ? rule.labelEn : rule.labelFr
}

/**
 * PSU-03 : ce que dit la colonne Suspension, sous forme de clé i18n et de paramètres.
 * `more` = des matchs en plus d'un total de journée (S15), `none` = aucun match de plus.
 */
export function suspensionText(item: Pick<CardSuspension, 'matches' | 'gamedayTotal'>): { key: 'one' | 'many' | 'more' | 'none', matches: number, total: number } {
  const total = item.gamedayTotal ?? item.matches
  if (item.gamedayTotal !== null && item.gamedayTotal !== item.matches) {
    return { key: item.matches === 0 ? 'none' : 'more', matches: item.matches, total }
  }
  return { key: item.matches === 1 ? 'one' : 'many', matches: item.matches, total }
}
