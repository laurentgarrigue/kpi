// https://nuxt.com/docs/api/configuration/nuxt-config

const baseUrl = process.env.BASE_URL ?? '/admin2'
const api2BaseUrl = process.env.API2_BASE_URL ?? 'https://kpi.localhost/api2'
const legacyBaseUrl = process.env.LEGACY_BASE_URL ?? 'https://kpi.localhost'
const app2BaseUrl = process.env.APP2_BASE_URL ?? 'https://app.kpi.localhost'

const matomoUrl = process.env.MATOMO_URL ?? ''
const matomoSiteId = process.env.MATOMO_SITE_ID ?? ''
const matomoEnabled = process.env.MATOMO_ENABLED === 'true'

// 'development' | 'preprod' | 'production'
const appEnv = process.env.APP_ENV ?? (process.env.NODE_ENV === 'production' ? 'production' : 'development')

export default defineNuxtConfig({
  ssr: false,

  app: {
    baseURL: baseUrl,
    head: {
      title: 'KPI Admin',
      meta: [
        { name: 'theme-color', content: '#1e40af' },
        { name: 'description', content: 'KPI Administration Panel' }
      ],
      link: [
        { rel: 'icon', type: 'image/png', href: `${baseUrl}/favicon.png` }
      ]
    }
  },

  runtimeConfig: {
    public: {
      baseUrl,
      api2BaseUrl,
      legacyBaseUrl,
      app2BaseUrl,
      matomoUrl,
      matomoSiteId,
      matomoEnabled,
      appEnv
    }
  },

  compatibilityDate: '2025-07-15',

  devtools: { enabled: true },

  devServer: {
    host: '0.0.0.0',
    port: 3004
  },

  // Pre-bundle CJS deps at startup so they are not discovered late (which forces a
  // dep re-optimization + full reload and can leave the browser requesting a stale
  // chunk hash). easytimer.js is UMD/CJS and used by the Scoring console.
  vite: {
    optimizeDeps: {
      include: ['easytimer.js']
    }
  },

  modules: [
    '@nuxt/eslint',
    '@pinia/nuxt',
    '@nuxtjs/i18n',
    '@nuxt/ui',
    '@vite-pwa/nuxt'
  ],

  // PWA — installable console for the scoring table's tablet (spec §0.8 / plan §4.9).
  //
  // Two deliberate choices:
  //  - `registerType: 'autoUpdate'` + `skipWaiting`/`clientsClaim`: a new deployment is
  //    activated immediately, never a stale app shell (the "mise à jour immédiate"
  //    requirement — the same mechanism is to be reused on app2). `usePwaUpdate` reloads
  //    the page once the new worker takes control.
  //  - navigateFallback denylist on /api2 and /api: the SPA shell must never answer an
  //    API call. Precaching stays limited to the built assets: match data is always
  //    fetched online (offline write queue = plan lot 7).
  pwa: {
    registerType: 'autoUpdate',
    manifest: {
      name: 'KPI Scoring',
      short_name: 'KPI Scoring',
      description: 'Console de scoring KPI — table de marque',
      lang: 'fr',
      start_url: `${baseUrl}/`,
      scope: `${baseUrl}/`,
      display: 'standalone',
      orientation: 'landscape',
      background_color: '#000000',
      theme_color: '#1e40af',
      icons: [
        { src: `${baseUrl}/favicon.png`, sizes: '192x192', type: 'image/png', purpose: 'any' }
      ]
    },
    workbox: {
      // Keep the shell fresh: activate the new service worker as soon as it is installed.
      skipWaiting: true,
      clientsClaim: true,
      cleanupOutdatedCaches: true,
      globPatterns: ['**/*.{js,css,html,png,svg,ico,woff2}'],
      navigateFallback: `${baseUrl}/`,
      navigateFallbackDenylist: [/^\/api2\//, /^\/api\//]
    },
    client: {
      // We drive the reload ourselves (usePwaUpdate) instead of the module's prompt.
      installPrompt: false,
      periodicSyncForUpdates: 300 // check for a new version every 5 min during an event
    },
    devOptions: {
      // Never register a service worker in dev: it would cache the Vite dev chunks.
      enabled: false
    }
  },

  // Light by default; users can opt into dark via the header toggle (persisted in localStorage).
  colorMode: {
    preference: 'system',
    fallback: 'light',
    classSuffix: '',
    storageKey: 'nuxt-color-mode-admin4',
  },

  icon: {
    provider: 'iconify',
    clientBundle: {
      scan: true,
      includeCustomCollections: true,
      icons: [
        'heroicons:bars-3',
        'heroicons:x-mark',
        'heroicons:calendar-days',
        'heroicons:document-text',
        'heroicons:chart-bar',
        'heroicons:cog-6-tooth',
        'heroicons:arrow-right-on-rectangle',
        'heroicons:chevron-down',
        'heroicons:magnifying-glass',
        'heroicons:plus',
        'heroicons:pencil',
        'heroicons:trash',
        'heroicons:check-circle',
        'heroicons:x-circle',
        'heroicons:device-phone-mobile',
        'heroicons:arrows-up-down',
        'heroicons:arrow-up',
        'heroicons:arrow-down',
        'heroicons:chevron-left',
        'heroicons:chevron-right',
        'heroicons:arrow-path',
        'heroicons:exclamation-triangle',
        'heroicons:wifi',
        'heroicons:signal-slash',
        'heroicons:check-circle-solid',
        'heroicons:x-circle-solid',
        'heroicons:device-phone-mobile-solid',
        'heroicons:pencil-solid',
        'heroicons:trash-solid',
        'heroicons:user-group',
        'heroicons:trophy',
        'heroicons:chart-bar-square',
        'heroicons:calendar',
        'heroicons:shield-check',
        'heroicons:document-duplicate',
        'heroicons:table-cells',
        'heroicons:arrow-top-right-on-square',
        'heroicons:qr-code',
        'heroicons:eye-solid',
        'heroicons:map-pin-solid',
        'heroicons:calendar-solid',
        'heroicons:lock-closed-solid',
        'heroicons:lock-open-solid'
      ]
    }
  },

  i18n: {
    strategy: 'no_prefix',
    defaultLocale: 'fr',
    langDir: 'locales',
    locales: [
      { code: 'en', file: 'en.json', name: 'English' },
      { code: 'fr', file: 'fr.json', name: 'Français' }
    ]
  },

  css: ['@/assets/css/admin.css']
})
