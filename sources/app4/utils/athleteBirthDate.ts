// Birth date field of the athlete sheet (CATEGORIE_AGE.md stories 13-15). The unknown dates and the plausibility
// bounds live in utils/ageCategory.ts (mirror of api2 AgeCategoryRule).
import { isUnknownBirthDate } from './ageCategory'

const IMPLAUSIBLE_BIRTHDATE = 'IMPLAUSIBLE_BIRTHDATE'
const HTTP_FORBIDDEN = 403

export interface BirthDateField {
  unknown: boolean
  date: string
}

/** Initial state of the field: an absent or conventional date is « date inconnue ». */
export function birthDateField(naissance: string | null | undefined): BirthDateField {
  if (isUnknownBirthDate(naissance)) {
    return { unknown: true, date: '' }
  }
  return { unknown: false, date: naissance ?? '' }
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
