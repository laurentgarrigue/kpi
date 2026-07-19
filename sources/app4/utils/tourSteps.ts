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
  /** Clé i18n du libellé affiché dans la liste des tutoriels de /help. */
  labelKey: string
  /** Icône (heroicons) affichée dans la liste de /help. */
  icon: string
  /**
   * Route déclenchant l'auto-start de ce tour à la première arrivée.
   * Absent = tour non auto-déclenché par une page (cas du tour d'accueil,
   * lancé explicitement depuis `pages/index.vue`).
   */
  autoStartRoute?: string
  /**
   * Profil maximum (au sens `authStore.profile`, plus petit = plus de droits)
   * autorisé à voir ce tour. Absent = tous profils.
   * Le tour est masqué de /help ET jamais auto-déclenché au-delà.
   */
  maxProfile?: number
  steps: TourStep[]
}

// Tour d'accueil MVP. Met en valeur les nouveautés essentielles d'app4.
export const WELCOME_TOUR: Tour = {
  id: 'welcome',
  version: 1,
  labelKey: 'tour.welcome_label',
  icon: 'heroicons:hand-raised',
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

// Tour de la page Classements. Auto-déclenché à la première arrivée sur
// /rankings, réservé aux profils <= 4 (ce sont eux qui consolident les phases).
// Note : les deux éléments ciblés sont conditionnels — la case « Consolider »
// n'existe qu'en compétition CP (phase de type C) et le bouton « Égalités »
// qu'en présence d'égalités de points. Quand l'ancre est absente, driver.js
// centre la bulle : les textes sont donc rédigés pour rester justes dans ce cas.
export const RANKINGS_TOUR: Tour = {
  id: 'rankings',
  version: 1,
  labelKey: 'tour.rankings_label',
  icon: 'heroicons:trophy',
  autoStartRoute: '/rankings',
  maxProfile: 4,
  steps: [
    {
      route: '/rankings',
      anchor: '',
      titleKey: 'tour.rankings_intro_title',
      bodyKey: 'tour.rankings_intro_body',
    },
    {
      route: '/rankings',
      anchor: 'ties-justification',
      titleKey: 'tour.rankings_ties_title',
      bodyKey: 'tour.rankings_ties_body',
    },
    {
      route: '/rankings',
      anchor: 'phase-consolidation',
      titleKey: 'tour.rankings_consolidate_title',
      bodyKey: 'tour.rankings_consolidate_body',
    },
    {
      route: '/rankings',
      anchor: '',
      titleKey: 'tour.rankings_outro_title',
      bodyKey: 'tour.rankings_outro_body',
    },
  ],
}

export const TOURS: Record<string, Tour> = {
  [WELCOME_TOUR.id]: WELCOME_TOUR,
  [RANKINGS_TOUR.id]: RANKINGS_TOUR,
}
