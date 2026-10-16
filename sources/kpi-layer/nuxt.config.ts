import { fileURLToPath } from 'node:url'

// kpi-layer — shared Nuxt layer (README.md). Consumed by app3 first; app2/app4 adopt it in phase 6.
export default defineNuxtConfig({
  // Explicit imports of the layer's non auto-imported modules (e.g. `#kpi-layer/utils/results/games`).
  alias: { '#kpi-layer': fileURLToPath(new URL('./app', import.meta.url)) },
  runtimeConfig: {
    // Server-side api2 URL on the Docker network (no Traefik, no /api2 prefix). Overridden by NUXT_API2_INTERNAL_URL.
    api2InternalUrl: '',
    public: {
      // Browser-side api2 URL. Overridden by NUXT_PUBLIC_API2_BASE_URL.
      api2BaseUrl: 'https://kpi.localhost/api2',
    },
  },
})
