import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'

// Unit tests of the pure helpers in utils/ (CLEAN_CODE.md: utils = pure functions tested without Nuxt).
// app2 had no test at all: this is the foothold of step 3 of TEST_COVERAGE_STRATEGY.md.
export default defineConfig({
  // Lets v8 compile the .vue files that no test imports, so that they count at 0 %.
  plugins: [vue()],
  test: {
    include: ['tests/unit/**/*.spec.ts'],
    environment: 'node',
    // `npm run test:coverage`. Application folders are listed so that untested files count at 0 %.
    // Measure only: the floor lives in scripts/ci/coverage-policy.json.
    coverage: {
      provider: 'v8',
      reporter: ['text-summary', 'json-summary', 'lcov'],
      reportsDirectory: 'coverage',
      include: ['components/**', 'composables/**', 'pages/**', 'stores/**', 'services/**', 'utils/**', 'middleware/**', 'plugins/**', 'layouts/**'],
      exclude: ['**/*.d.ts'],
    },
  },
})
