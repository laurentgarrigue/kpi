import { fileURLToPath } from 'node:url'
import { defineConfig } from 'vitest/config'
import { defineVitestProject } from '@nuxt/test-utils/config'

// Same alias as kpi-layer/nuxt.config.ts, for the plain-Node unit project (the Nuxt one gets it from Nuxt).
const kpiLayerAlias = { '#kpi-layer': fileURLToPath(new URL('../kpi-layer/app', import.meta.url)) }

// Two projects (CLEAN_CODE.md § 2):
//  - unit: pure functions (app3 utils, kpi-layer utils), plain Node, fast;
//  - nuxt: components, in a Nuxt runtime environment;
//  - e2e: the built server (routes, headers, SSR output).
/**
 * The site 404 is raised by `useApiResource` with `createError({ statusCode: 404, fatal: true })` during the async
 * setup of a page (CMP-14, HIS-05, TEA-05…). In the Nuxt test environment this expected rejection also surfaces
 * as an « unhandled rejection » once the page is unmounted: ignore exactly that one, report everything else.
 */
function isExpectedSiteNotFound(error: unknown): boolean {
  const { statusCode, cause } = error as { statusCode?: number, cause?: { fatal?: boolean } }
  return statusCode === 404 && cause?.fatal === true
}

export default defineConfig({
  test: {
    onUnhandledError: error => !isExpectedSiteNotFound(error),
    projects: [
      {
        resolve: { alias: kpiLayerAlias },
        test: {
          name: 'unit',
          environment: 'node',
          include: ['tests/unit/**/*.spec.ts', '../kpi-layer/tests/**/*.spec.ts'],
        },
      },
      {
        // Builds and serves the real app: slower, run on CI and before a merge.
        test: {
          name: 'e2e',
          environment: 'node',
          include: ['tests/e2e/**/*.spec.ts'],
          testTimeout: 60_000,
          hookTimeout: 300_000,
        },
      },
      await defineVitestProject({
        test: {
          name: 'nuxt',
          environment: 'nuxt',
          // Starting the Nuxt environment can exceed the 10 s default on a cold, busy machine (CI).
          hookTimeout: 60_000,
          include: ['tests/nuxt/**/*.spec.ts'],
          setupFiles: ['tests/nuxt/setup.ts'],
        },
      }),
    ],
  },
})
