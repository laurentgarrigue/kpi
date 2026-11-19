import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'

// Unit tests of the pure helpers in utils/ (CLEAN_CODE.md: utils = pure functions tested without Nuxt).
export default defineConfig({
  // Lets v8 compile the .vue files that no test imports, so that they are counted at 0 % (COV-03).
  plugins: [vue()],
  test: {
    include: ['tests/unit/**/*.spec.ts'],
    environment: 'node',
    // COV-01/03: `npm run test:coverage`. Every application folder is listed so that files no test imports
    // count as 0 % (otherwise the rate only reflects what is tested). Measure only, no threshold yet
    // (DOC/developer/in-progress/TEST_COVERAGE_STRATEGY.md).
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
  },
})
