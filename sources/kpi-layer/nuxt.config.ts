// kpi-layer — shared Nuxt layer (README.md). Consumed by app3 first; app2/app4 adopt it in phase 6.
export default defineNuxtConfig({
  runtimeConfig: {
    // Server-side api2 URL on the Docker network (no Traefik, no /api2 prefix). Overridden by NUXT_API2_INTERNAL_URL.
    api2InternalUrl: '',
    public: {
      // Browser-side api2 URL. Overridden by NUXT_PUBLIC_API2_BASE_URL.
      api2BaseUrl: 'https://kpi.localhost/api2',
    },
  },
})
