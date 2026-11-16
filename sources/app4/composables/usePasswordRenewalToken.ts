/**
 * Renewal token handed over by /auth/login to /renew-password (AUTH_HARDENING.md § 2bis).
 * Kept in memory only: it proves the current password for 15 minutes and must never reach localStorage.
 */
export const usePasswordRenewalToken = () => useState<string | null>('password-renewal-token', () => null)
