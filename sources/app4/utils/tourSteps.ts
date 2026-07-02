// Définition déclarative des tours guidés (onboarding admin2).
// Voir DOC/specs/TUTORIEL_ADMIN2.md — c'est LE fichier à éditer pour ajouter ou
// mettre en valeur une nouvelle fonctionnalité dans le tutoriel.

export interface TourStep {
  /** Route où l'étape s'affiche ; navigation automatique si l'on n'y est pas déjà. */
  route?: string
  /** Valeur du `data-tour` ciblé. Chaîne vide = étape centrée sans élément ciblé. */
  anchor: string
  /** Clé i18n du titre (namespace `tour.*`). */
  titleKey: string
  /** Clé i18n du corps (namespace `tour.*`). */
  bodyKey: string
  /** Marque l'étape comme nouveauté : re-proposée aux utilisateurs déjà venus. */
  isNew?: boolean
}

export interface Tour {
  id: string
  /** Incrémenter à chaque ajout de nouveauté pour déclencher le badge "Nouveautés". */
  version: number
  steps: TourStep[]
}

// Tour d'accueil MVP. Met en valeur les nouveautés essentielles d'app4.
export const WELCOME_TOUR: Tour = {
  id: 'welcome',
  version: 1,
  steps: [
    {
      anchor: '',
      titleKey: 'tour.welcome_title',
      bodyKey: 'tour.welcome_body',
    },
    {
      route: '/',
      anchor: 'mandate',
      titleKey: 'tour.mandate_title',
      bodyKey: 'tour.mandate_body',
    },
    {
      route: '/',
      anchor: 'work-context',
      titleKey: 'tour.work_context_title',
      bodyKey: 'tour.work_context_body',
    },
    {
      route: '/',
      anchor: 'menu',
      titleKey: 'tour.menu_title',
      bodyKey: 'tour.menu_body',
    },
    {
      route: '/',
      anchor: 'home-shortcuts',
      titleKey: 'tour.home_shortcuts_title',
      bodyKey: 'tour.home_shortcuts_body',
    },
    {
      route: '/competitions',
      anchor: 'clickable-cells',
      titleKey: 'tour.clickable_cells_title',
      bodyKey: 'tour.clickable_cells_body',
    },
    {
      route: '/competitions',
      anchor: 'context-summary',
      titleKey: 'tour.context_summary_title',
      bodyKey: 'tour.context_summary_body',
    },
    {
      route: '/',
      anchor: '',
      titleKey: 'tour.outro_title',
      bodyKey: 'tour.outro_body',
    },
  ],
}

export const TOURS: Record<string, Tour> = {
  [WELCOME_TOUR.id]: WELCOME_TOUR,
}
