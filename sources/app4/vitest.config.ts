import { defineConfig } from 'vitest/config'

// Unit tests of the pure helpers in utils/ (CLEAN_CODE.md: utils = pure functions tested without Nuxt).
export default defineConfig({
  test: {
    include: ['tests/unit/**/*.spec.ts'],
    environment: 'node',
  },
})
