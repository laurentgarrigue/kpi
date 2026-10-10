// app3 — public website (DOC/specs/public/). Server-side rendered, deployed on beta.* until the switch-over.
import { version } from './package.json'
import { DEFAULT_LOCALE, LOCALES } from './shared/locales'

// Cache durations set by each page spec, stale-while-revalidate.
// Home page and competitions list: 5 minutes (PAGE_HOME.md § 3, PAGE_COMPETITIONS.md § 3).
const SLOW_CACHE = { cache: { maxAge: 300, swr: true } }
// Results pages: 1 minute, the browser refreshes live games itself (PAGE_COMPETITION.md § 4, PAGE_EVENT_GROUP.md § 4).
const RESULTS_CACHE = { cache: { maxAge: 60, swr: true } }
// Redirections (`/competitions`, default tabs) must follow the current season / status: never cached.
const NO_CACHE = { cache: false as const }
// Honours and clubs only change at the end of a competition (PAGE_HISTORY.md § 3, PAGE_CLUBS.md § 3).
const LONG_CACHE = { cache: { maxAge: 3600, swr: true } }

// NuxtLink prefetches the extracted payload of a cached page (`{page}/_payload.json`). That path has one more
// segment than the page, so a rule written for the page does not cover it: without the second entry, every
// prefetch is a 404 and the cached payload is never used (PLT-07).
const cachedPage = (path: string, rule: typeof SLOW_CACHE): [string, typeof SLOW_CACHE][] =>
  [[path, rule], [`${path === '/' ? '' : path}/_payload.json`, rule]]

export default defineNuxtConfig({
  extends: ['../kpi-layer'],

  compatibilityDate: '2025-07-15',

  modules: ['@nuxt/eslint', '@nuxt/ui', '@nuxtjs/i18n', '@nuxt/test-utils/module'],

  css: ['~/assets/css/main.css'],

  // Same favicon as app4 (the KPI logo), served by app3 itself (SITE_LAYOUT.md § 2, LAY-10).
  app: {
    head: {
      link: [{ rel: 'icon', type: 'image/png', href: '/favicon.png' }],
    },
  },

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
    ...Object.fromEntries(['', '/en'].flatMap(prefix => [
      ...cachedPage(prefix || '/', SLOW_CACHE),
      [`${prefix}/competitions`, NO_CACHE],
      ...cachedPage(`${prefix}/competitions/*`, SLOW_CACHE),
      [`${prefix}/competitions/*/*`, NO_CACHE],
      [`${prefix}/competitions/*/*/**`, RESULTS_CACHE],
      ...cachedPage(`${prefix}/events`, SLOW_CACHE),
      [`${prefix}/events/*`, NO_CACHE],
      [`${prefix}/events/*/**`, RESULTS_CACHE],
      [`${prefix}/groups/*/*`, NO_CACHE],
      [`${prefix}/groups/*/*/**`, RESULTS_CACHE],
      // Phase 3 (PAGE_CALENDAR.md, PAGE_HISTORY.md, PAGE_TEAM.md, PAGE_CLUBS.md, FEATURE_SEARCH.md § 3).
      ...cachedPage(`${prefix}/calendar`, SLOW_CACHE),
      [`${prefix}/history`, NO_CACHE],
      ...cachedPage(`${prefix}/history/*`, LONG_CACHE),
      ...cachedPage(`${prefix}/teams`, SLOW_CACHE),
      ...cachedPage(`${prefix}/teams/*`, SLOW_CACHE),
      ...cachedPage(`${prefix}/clubs`, LONG_CACHE),
      ...cachedPage(`${prefix}/clubs/*`, LONG_CACHE),
      // Rate-limited per visitor by api2: a shared cache would serve one visitor's search to another.
      [`${prefix}/search`, NO_CACHE],
    ])),
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
