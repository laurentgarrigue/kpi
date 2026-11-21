/** Shown when an account never signed in through api2 (PAGE_UTILISATEURS.md § 2.1 n° 16). */
export const NO_LAST_LOGIN = '—'

/**
 * Last sign-in of the users list: api2 sends ISO 8601 in UTC, shown as a short date and time of the viewer's
 * time zone (or `timeZone`, for the tests).
 */
export const formatLastLogin = (iso: string | null | undefined, locale: string, timeZone?: string): string => {
  const date = iso ? new Date(iso) : null
  if (date === null || Number.isNaN(date.getTime())) {
    return NO_LAST_LOGIN
  }
  return new Intl.DateTimeFormat(locale, { dateStyle: 'short', timeStyle: 'short', timeZone }).format(date)
}
