/** Languages of the public website (SITE_NAVIGATION.md § 5). Single source for nuxt.config and the app. */
export const DEFAULT_LOCALE = 'fr'

export const LOCALES = [
  { code: 'fr', language: 'fr-FR', file: 'fr.json', name: 'Français' },
  { code: 'en', language: 'en-GB', file: 'en.json', name: 'English' },
] as const

/** Locales served under a `/<code>` prefix (every locale but the default one). */
export const PREFIXED_LOCALES: readonly string[] = LOCALES.map(locale => locale.code).filter(code => code !== DEFAULT_LOCALE)
