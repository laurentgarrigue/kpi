import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'
import { defineVitestProject } from '@nuxt/test-utils/config'

// Two projects (CLEAN_CODE.md § 2), as in app4:
//  - unit: pure helpers of utils/, plain Node, fast;
//  - nuxt: components, in a Nuxt runtime environment (@nuxt/test-utils).
export default defineConfig({
  // Lets v8 compile the .vue files that no test imports, so that they count at 0 %.
  plugins: [vue()],
  test: {
    // `npm run test:coverage`. Application folders are listed so that untested files count at 0 %.
    // Measure only: the floor lives in scripts/ci/coverage-policy.json.
    coverage: {
      provider: 'v8',
      reporter: ['text-summary', 'json-summary', 'lcov'],
      reportsDirectory: 'coverage',
      include: ['components/**', 'composables/**', 'pages/**', 'stores/**', 'services/**', 'utils/**', 'middleware/**', 'plugins/**', 'layouts/**'],
      exclude: ['**/*.d.ts'],
    },
    projects: [
      {
        plugins: [vue()],
        test: {
          name: 'unit',
          environment: 'node',
          include: ['tests/unit/**/*.spec.ts'],
        },
      },
      await defineVitestProject({
        test: {
          name: 'nuxt',
          environment: 'nuxt',
          // Starting the Nuxt environment can exceed the 10 s default on a cold, busy machine (CI).
          hookTimeout: 60_000,
          include: ['tests/nuxt/**/*.spec.ts'],
        },
      }),
    ],
  },
})
