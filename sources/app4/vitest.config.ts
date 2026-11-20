import { defineConfig } from 'vitest/config'
import { defineVitestProject } from '@nuxt/test-utils/config'

// Two projects (CLEAN_CODE.md § 2), as in app3:
//  - unit: pure helpers of utils/, plain Node, fast;
//  - nuxt: components and pages, in a Nuxt runtime environment (@nuxt/test-utils).
export default defineConfig({
  test: {
    // COV-01/03: `npm run test:coverage`. Every application folder is listed so that files no test imports
    // count as 0 % (otherwise the rate only reflects what is tested). Measure only: the floor lives in
    // scripts/ci/coverage-policy.json (DOC/developer/in-progress/TEST_COVERAGE_STRATEGY.md).
    coverage: {
      provider: 'v8',
      reporter: ['text-summary', 'json-summary', 'lcov'],
      reportsDirectory: 'coverage',
      include: [
        'components/**', 'composables/**', 'pages/**', 'stores/**',
        'utils/**', 'middleware/**', 'plugins/**', 'layouts/**',
      ],
      exclude: ['**/*.d.ts'],
    },
    projects: [
      {
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
