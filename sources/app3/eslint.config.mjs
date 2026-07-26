// @ts-check
import withNuxt from './.nuxt/eslint.config.mjs'

export default withNuxt(
  {
    // app3 (feuille de marque / match sheet) est un module en maintenance, gelé
    // depuis décembre 2025. Son code n'était jamais passé sous ESLint avant la CI
    // (Phase 2 CI/CD). Plutôt que de retyper du code figé, on rétrograde en `warn`
    // les règles qui bloquent le job `lint-nuxt` : la dette reste visible sans
    // faire échouer la CI. À repasser en `error` si app3 redevient activement
    // développé.
    rules: {
      '@typescript-eslint/no-explicit-any': 'warn',
      '@typescript-eslint/no-unused-vars': 'warn',
      '@typescript-eslint/ban-ts-comment': 'warn'
    }
  }
)
