import { describe, expect, it } from 'vitest'
import { formatLastLogin, NO_LAST_LOGIN } from '../../utils/lastLogin'

describe('users list, last sign-in (PAGE_UTILISATEURS.md § 2.1 n° 16)', () => {
  it('shows the UTC sign-in in the local time zone', () => {
    expect(formatLastLogin('2026-11-10T09:30:00+00:00', 'fr', 'Europe/Paris')).toBe('10/11/2026 10:30')
  })

  it('follows the interface language', () => {
    expect(formatLastLogin('2026-11-10T09:30:00+00:00', 'en', 'UTC')).toBe('11/10/26, 9:30 AM')
  })

  it('shows a dash without any sign-in, or with an unreadable date', () => {
    expect(formatLastLogin(null, 'fr')).toBe(NO_LAST_LOGIN)
    expect(formatLastLogin(undefined, 'fr')).toBe(NO_LAST_LOGIN)
    expect(formatLastLogin('not a date', 'fr')).toBe(NO_LAST_LOGIN)
  })
})
