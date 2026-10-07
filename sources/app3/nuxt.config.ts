// app3 — public website (DOC/specs/public/). Server-side rendered, deployed on beta.* until the switch-over.
import { version } from './package.json'
import { DEFAULT_LOCALE, LOCALES } from './shared/locales'

// PAGE_HOME.md § 3: 5 minutes, stale-while-revalidate.
const HOME_CACHE = { cache: { maxAge: 300, swr: true } }

export default defineNuxtConfig({
  extends: ['../kpi-layer'],

  compatibilityDate: '2025-07-15',

  modules: ['@nuxt/eslint', '@nuxt/ui', '@nuxtjs/i18n', '@nuxt/test-utils/module'],

  css: ['~/assets/css/main.css'],

  // Fonts are self-hosted by kpi-layer: disable @nuxt/fonts, which would query third-party providers.
  ui: {
    fonts: false,
  },

  // Every value below is overridden at runtime by NUXT_* variables (SITE_PLATFORM.md § 3):
  // one build serves preprod and production.
  runtimeConfig: {
    public: {
      beta: true,
      legacyBaseUrl: 'https://kpi.localhost',
      app2BaseUrl: 'https://app.kpi.localhost',
      version,
    },
  },

  // Light theme only in phase 1 (SITE_LAYOUT.md § 3).
  colorMode: {
    preference: 'light',
    fallback: 'light',
  },

  // Cache per page, as set by each page spec.
  routeRules: {
    '/': HOME_CACHE,
    '/en': HOME_CACHE,
  },

  i18n: {
    strategy: 'prefix_except_default',
    defaultLocale: DEFAULT_LOCALE,
    // Canonical site URL, used for canonical/hreflang links and robots.txt.
    // Runtime override: NUXT_PUBLIC_I18N_BASE_URL (SITE_PLATFORM.md § 3).
    baseUrl: 'https://beta.kpi.localhost',
    locales: [...LOCALES],
    detectBrowserLanguage: false,
  },

  icon: {
    provider: 'iconify',
    clientBundle: { scan: true },
  },

  devServer: { port: 3003 },
})
