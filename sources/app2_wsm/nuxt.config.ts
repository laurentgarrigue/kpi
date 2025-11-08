// https://nuxt.com/docs/api/configuration/nuxt-config
const baseUrl = process.env.BASE_URL ?? ''
const apiBaseUrl = process.env.API_BASE_URL ?? ''
const backendBaseUrl = process.env.BACKEND_BASE_URL ?? ''

export default defineNuxtConfig({
  devtools: { enabled: true },

  compatibilityDate: '2024-04-03',

  future: {
    compatibilityVersion: 4,
  },

  app: {
    baseURL: baseUrl,
    head: {
      htmlAttrs: {
        lang: 'fr',
      },
      charset: 'utf-8',
      viewport: 'width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0',
      title: 'KPI WSM - WebSocket Manager',
      meta: [
        { name: 'description', content: 'Kayak Polo Information - WebSocket Manager for live game data' },
        { name: 'format-detection', content: 'telephone=no' },
        { name: 'theme-color', content: '#1f2937' },
        { name: 'apple-mobile-web-app-capable', content: 'yes' },
        { name: 'apple-mobile-web-app-status-bar-style', content: 'black-translucent' },
      ],
      link: [
        { rel: 'icon', type: 'image/x-icon', href: baseUrl + '/favicon.ico' },
        { rel: 'apple-touch-icon', href: baseUrl + '/apple-touch-icon.png' },
      ],
    },
  },

  runtimeConfig: {
    public: {
      baseUrl,
      apiBaseUrl,
      backendBaseUrl,
    },
  },

  modules: [
    '@nuxt/eslint',
    '@pinia/nuxt',
    '@nuxtjs/i18n',
    '@nuxt/ui',
    '@vite-pwa/nuxt',
  ],

  css: ['@/assets/css/app.css'],

  i18n: {
    strategy: 'no_prefix',
    defaultLocale: 'fr',
    langDir: 'locales',
    locales: [
      { code: 'en', file: 'en.json', name: 'English' },
      { code: 'fr', file: 'fr.json', name: 'Français' },
      { code: 'es', file: 'es.json', name: 'Español' },
    ],
  },

  pwa: {
    registerType: 'autoUpdate',
    includeAssets: ['favicon.ico', 'apple-touch-icon.png'],
    manifest: {
      name: 'KPI WSM - WebSocket Manager',
      short_name: 'KPI WSM',
      description: 'Kayak Polo Information - WebSocket Manager for live game data',
      theme_color: '#1f2937',
      background_color: '#ffffff',
      display: 'standalone',
      orientation: 'any',
      icons: [
        {
          src: baseUrl + '/pwa-192x192.png',
          sizes: '192x192',
          type: 'image/png',
        },
        {
          src: baseUrl + '/pwa-512x512.png',
          sizes: '512x512',
          type: 'image/png',
        },
        {
          src: baseUrl + '/pwa-512x512.png',
          sizes: '512x512',
          type: 'image/png',
          purpose: 'any maskable',
        },
      ],
    },
    workbox: {
      navigateFallback: baseUrl + '/index.html',
      navigateFallbackDenylist: [/^\/api\//],
      runtimeCaching: [
        {
          urlPattern: '^' + apiBaseUrl + '/.*',
          handler: 'NetworkOnly',
        },
      ],
    },
    devOptions: {
      enabled: false,
      type: 'module',
    },
  },

  devServer: {
    port: 3000,
  },

  vite: {
    optimizeDeps: {
      include: ['@stomp/stompjs', 'sockjs-client'],
    },
  },
})
