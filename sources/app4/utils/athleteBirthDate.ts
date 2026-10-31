// Birth date field of the athlete sheet (CATEGORIE_AGE.md stories 13-15, GLOSSARY.md « date de naissance inconnue »).

/** Dates written by the old code for « unknown »: they are not birth dates. */
const CONVENTIONAL_BIRTH_DATES = ['1901-01-01', '0000-00-00']

/** Plausible age bounds in the active season, enforced by api2 (AgeCategoryRule, story 14). */
export const MIN_PLAUSIBLE_AGE = 5
export const MAX_PLAUSIBLE_AGE = 100

const IMPLAUSIBLE_BIRTHDATE = 'IMPLAUSIBLE_BIRTHDATE'
const HTTP_FORBIDDEN = 403

export interface BirthDateField {
  unknown: boolean
  date: string
}

/** Initial state of the field: an absent or conventional date is « date inconnue ». */
export function birthDateField(naissance: string | null | undefined): BirthDateField {
  if (!naissance || CONVENTIONAL_BIRTH_DATES.includes(naissance)) {
    return { unknown: true, date: '' }
  }
  return { unknown: false, date: naissance }
}

/** Birth date to send: null for « date inconnue », undefined when a known date is missing (not submittable). */
export function submittedBirthDate(field: BirthDateField): string | null | undefined {
  if (field.unknown) return null
  return field.date || undefined
}

/** i18n key of the message for a failed PUT /admin/athletes/{matric}. */
export function athleteUpdateErrorKey(error: unknown): string {
  const err = error as { status?: number, data?: { code?: string } } | null
  if (err?.data?.code === IMPLAUSIBLE_BIRTHDATE) return 'athletes.edit.implausible_birthdate'
  if (err?.status === HTTP_FORBIDDEN) return 'athletes.edit.forbidden'
  return 'athletes.edit.error'
}
