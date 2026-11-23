// Rapports officiels (DOC/specs/FEATURE_DESIGNATIONS.md § 5, § 8 ; ticket designations/09) : formulaires rendus
// depuis le catalogue de api2 (RAP-01), aucune règle de validation réimplémentée ici.

export type ReportScope = 'COMPETITION' | 'EVENEMENT' | 'JOURNEE' | 'MATCH'
export type ReportState = 'BROUILLON' | 'SOUMIS'
export type Localized = { fr: string, en: string }

export interface ReportFieldDefinition {
  key: string
  type: 'text' | 'textarea' | 'number' | 'date' | 'time' | 'checkbox' | 'select' | 'score' | 'sum'
  label: Localized
  required?: boolean
  options?: string[]
  max?: number
  sumOf?: string[]
}

export interface ReportSectionDefinition { key: string, title: Localized, fields: ReportFieldDefinition[] }

export interface ReportTypeDefinition {
  code: string
  version: number
  label: Localized
  scopes: ReportScope[]
  recipients: string[]
  deadlineDays: number | null
  restricted: boolean
  sections: ReportSectionDefinition[]
}

export type ReportTypes = Record<string, { current: number, versions: Record<string, ReportTypeDefinition> }>

export interface ReportSummary {
  id: number
  type: string
  version: number
  scopeType: ReportScope
  scopeId: string
  season: string
  authorMatric: number | null
  enteredBy: string
  state: ReportState
  submittedAt: string | null
  late: boolean
}

export interface ReportDetail extends ReportSummary {
  answers: Record<string, unknown>
  definition: ReportTypeDefinition
  submissions: { number: number, version: number, submittedAt: string, submittedBy: string }[]
  canEdit: boolean
  canReopen: boolean
}

export const localized = (value: Localized, locale: string): string => (locale === 'en' ? value.en : value.fr)

/** Types de rapport rédigeables sur un périmètre, dans leur version courante. */
export const typesForScope = (types: ReportTypes, scope: ReportScope): ReportTypeDefinition[] =>
  Object.values(types)
    .map(type => type.versions[String(type.current)])
    .filter((definition): definition is ReportTypeDefinition => definition !== undefined && definition.scopes.includes(scope))

/** Valeur affichée d'un champ calculé (somme), recalculée en direct comme le fera api2 à l'enregistrement. */
export const computedValue = (field: ReportFieldDefinition, answers: Record<string, unknown>): number =>
  (field.sumOf ?? []).reduce((total, key) => total + (typeof answers[key] === 'number' ? (answers[key] as number) : 0), 0)

/** Réponses envoyées à api2 : sans les champs calculés ni les valeurs vides. */
export const answersToSend = (definition: ReportTypeDefinition, answers: Record<string, unknown>): Record<string, unknown> => {
  const keys = new Set(definition.sections.flatMap(section => section.fields.filter(field => field.type !== 'sum').map(field => field.key)))
  return Object.fromEntries(Object.entries(answers).filter(([key, value]) => keys.has(key) && value !== '' && value !== null && value !== undefined))
}

/** Champs obligatoires encore vides (aide avant soumission ; api2 fait foi). */
export const missingRequired = (definition: ReportTypeDefinition, answers: Record<string, unknown>): string[] =>
  definition.sections.flatMap(section => section.fields)
    .filter(field => field.required && field.type !== 'sum' && [undefined, null, ''].includes(answers[field.key] as never))
    .map(field => field.key)
