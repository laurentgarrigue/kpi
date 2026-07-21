// @ts-check
import withNuxt from './.nuxt/eslint.config.mjs'

export default withNuxt(
  {
    // Limité à components/** : ne pas réactiver la règle sur pages/ & layouts/,
    // que Nuxt exempte volontairement (les noms de fichiers y font foi).
    files: ['components/**/*.vue'],
    rules: {
      // Ces composants historiques portent un nom d'un seul mot ; les renommer
      // impacterait tous leurs points d'usage. On les exempte explicitement.
      'vue/multi-word-component-names': ['error', {
        ignores: ['Charts', 'Rating']
      }]
    }
  }
)
