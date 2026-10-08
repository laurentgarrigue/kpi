import { defineConfig } from 'vitest/config'
import { defineVitestProject } from '@nuxt/test-utils/config'

// Two projects (CLEAN_CODE.md § 2):
//  - unit: pure functions (app3 utils, kpi-layer utils), plain Node, fast;
//  - nuxt: components, in a Nuxt runtime environment;
//  - e2e: the built server (routes, headers, SSR output).
export default defineConfig({
  test: {
    projects: [
      {
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
          include: ['tests/nuxt/**/*.spec.ts'],
          setupFiles: ['tests/nuxt/setup.ts'],
        },
      }),
    ],
  },
})
