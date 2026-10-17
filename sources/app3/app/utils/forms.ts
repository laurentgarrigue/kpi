import { withQuery } from 'ufo'
import { cmsLanguageQuery } from './news'
import type { PostedValues } from '~~/shared/utils/form-submission'

/** Formats of api2 `/forms…` (FEATURE_FORMS.md § 4). */

export type FormState = 'open' | 'closed' | 'upcoming'
export type FieldType = 'text' | 'textarea' | 'email' | 'phone' | 'number' | 'date' | 'select' | 'radio' | 'checkbox' | 'club' | 'competition'

export interface FormOption {
  value: string
  label: string
}

export interface FormField {
  key: string
  type: FieldType
  label: string
  help: string
  required: boolean
  options: FormOption[]
  min: number | null
  max: number | null
}

export interface FormDefinition {
  slug: string
  title: string
  intro: string
  state: FormState
  opens_at: string | null
  closes_at: string | null
  capacity: number | null
  confirmed: number
  full: boolean
  waiting_list: boolean
  manual_confirmation: boolean
  has_payment: boolean
  public_list: boolean
  purpose: string
  retention_days: number
  event: number | null
  competition: string | null
  fields: FormField[]
  token: string
}

export interface FormSummary {
  slug: string
  title: string
  state: FormState
}

export interface PublicEntries {
  title: string
  columns: string[]
  confirmed: string[][]
  waiting: string[][]
  capacity: number | null
}

const INPUT_TYPES: Partial<Record<FieldType, string>> = { text: 'text', email: 'email', phone: 'tel', number: 'number', date: 'date' }
const AUTOCOMPLETE: Partial<Record<FieldType, string>> = { email: 'email', phone: 'tel' }

/** `<input type>` of a single-line field, `null` for the other controls. */
export function inputType(type: FieldType): string | null {
  return INPUT_TYPES[type] ?? null
}

export function autocomplete(type: FieldType): string | undefined {
  return AUTOCOMPLETE[type]
}

/** Fields rendered as a list of choices (`<select>` for long lists, radios or checkboxes otherwise). */
export function isSelectField(type: FieldType): boolean {
  return type === 'select' || type === 'club' || type === 'competition'
}

/** A checkbox without options is a single yes / no box (consent…). */
export function isSingleCheckbox(field: FormField): boolean {
  return field.type === 'checkbox' && field.options.length === 0
}

export function formApiPath(slug: string, locale: string, suffix = ''): string {
  return withQuery(`/forms/${slug}${suffix}`, cmsLanguageQuery(locale))
}

export function attachedFormsApiPath(kind: 'event' | 'competition', ref: string, locale: string): string {
  return withQuery('/forms', { [kind]: ref, ...cmsLanguageQuery(locale) })
}

/** Value of a field to show again after a refused submission. */
export function postedValue(values: PostedValues | undefined, key: string): string[] {
  const value = values?.[key]
  return value === undefined ? [] : Array.isArray(value) ? value : [value]
}

/**
 * Opening or closing of a form (« 12 octobre 2026 à 18:00 »). api2 gives the Paris wall-clock time
 * (`2026-10-12 18:00:00`): it is formatted as is, whatever the time zone of the server or the browser.
 */
export function formDateTime(value: string, locale: string): string {
  const date = new Date(`${value.replace(' ', 'T')}Z`)
  return new Intl.DateTimeFormat(locale, { dateStyle: 'long', timeStyle: 'short', timeZone: 'UTC' }).format(date)
}
