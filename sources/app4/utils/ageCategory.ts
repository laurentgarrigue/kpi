/**
 * Display of the age category (DOC/specs/CATEGORIE_AGE.md). api2 derives it and sends null when the birth date is
 * unknown: the client shows « ? » with the label « Date de naissance inconnue ».
 */
export const UNKNOWN_AGE_CATEGORY = '?'

/** Non-blocking warning code of api2 (PlayerEligibilityRules::WARNING_BIRTH_DATE_UNKNOWN). */
export const BIRTH_DATE_UNKNOWN_WARNING = 'Naissance_inconnue'

/** Age category shown, « ? » when unknown, followed by the gender when given (« SEN-F »). */
export function ageCategoryLabel(code: string | null | undefined, sexe?: string | null): string {
  const category = code || UNKNOWN_AGE_CATEGORY
  return sexe ? `${category}-${sexe}` : category
}

/** True when api2 warns that the age category cannot be checked (unknown birth date, competition with age bounds). */
export function hasUnverifiableAgeCategory(warnings: string[] | undefined): boolean {
  return !!warnings?.includes(BIRTH_DATE_UNKNOWN_WARNING)
}
