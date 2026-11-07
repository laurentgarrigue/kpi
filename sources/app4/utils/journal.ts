// Journal d'activité : catégories d'actions (PAGE_GESTION_JOURNAL.md § 5.2).
// La liste des catégories, leurs actions et la sélection par défaut viennent d'api2 (/admin/journal/actions).

export interface JournalCategory {
  key: string
  defaultSelected: boolean
  actions: string[]
}

/** Catégories cochées à l'ouverture de la page (toutes sauf le scoring). */
export function defaultCategoryKeys(categories: JournalCategory[]): string[] {
  return categories.filter(c => c.defaultSelected).map(c => c.key)
}

/** True when the selection filters nothing: none or every category checked. */
function selectsEverything(selected: string[], categories: JournalCategory[]): boolean {
  return selected.length === 0 || categories.every(c => selected.includes(c.key))
}

/** Valeur du paramètre `categories` de GET /admin/journal, absente quand la sélection ne filtre rien. */
export function categoriesParam(selected: string[], categories: JournalCategory[]): string | undefined {
  if (selectsEverything(selected, categories)) return undefined
  return categories.filter(c => selected.includes(c.key)).map(c => c.key).join(',')
}

/** Groupes du select des actions : les catégories cochées (toutes si aucune) qui ont au moins une action. */
export function actionGroups(categories: JournalCategory[], selected: string[]): JournalCategory[] {
  const all = selectsEverything(selected, categories)
  return categories.filter(c => c.actions.length > 0 && (all || selected.includes(c.key)))
}
