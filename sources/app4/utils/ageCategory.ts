/**
 * Age category and unknown birth date (DOC/specs/CATEGORIE_AGE.md, GLOSSARY.md « date de naissance inconnue »).
 * Source of truth: api2 App\AgeCategory\AgeCategoryRule (CONVENTIONAL_BIRTH_DATES, MIN/MAX_PLAUSIBLE_AGE); the values
 * below mirror it for display only. api2 derives the category and sends null when the birth date is unknown: the client
 * shows « ? » with the label « Date de naissance inconnue ».
 */
export const UNKNOWN_AGE_CATEGORY = '?'

/** Dates written by the old code for « unknown »: they are not birth dates. */
export const CONVENTIONAL_BIRTH_DATES = ['1901-01-01', '0000-00-00']

/** Plausible age bounds in the active season, enforced by api2 (story 14). */
export const MIN_PLAUSIBLE_AGE = 5
export const MAX_PLAUSIBLE_AGE = 100

/** Non-blocking warning code of api2 (PlayerEligibilityRules::WARNING_BIRTH_DATE_UNKNOWN). */
export const BIRTH_DATE_UNKNOWN_WARNING = 'Naissance_inconnue'

/** Stats columns holding the age category derived by api2 (not the declared surclassement category). */
const AGE_CATEGORY_STAT_COLUMNS = ['categorie', 'categorieSaison']

/** True for an absent birth date and for the conventional dates standing for « unknown ». */
export function isUnknownBirthDate(naissance: string | null | undefined): boolean {
  return !naissance || CONVENTIONAL_BIRTH_DATES.includes(naissance)
}

/** Age category shown, « ? » when unknown, followed by the gender when given (« SEN-F »). */
export function ageCategoryLabel(code: string | null | undefined, sexe?: string | null): string {
  const category = code || UNKNOWN_AGE_CATEGORY
  return sexe ? `${category}-${sexe}` : category
}

/** True when api2 warns that the age category cannot be checked (unknown birth date, competition with age bounds). */
export function hasUnverifiableAgeCategory(warnings: string[] | undefined): boolean {
  return !!warnings?.includes(BIRTH_DATE_UNKNOWN_WARNING)
}

/** True for a stats column to display as an age category (« ? » when unknown). */
export function isAgeCategoryStatColumn(column: string): boolean {
  return AGE_CATEGORY_STAT_COLUMNS.includes(column)
}
