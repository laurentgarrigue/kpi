# Spec — Tutoriel dynamique admin2 (app4)

## But
Onboarding des admins legacy sur app4 : tour guidé interactif + page /help.
Déclenchement auto à la 1re visite, re-déclenchement partiel des nouveautés au retour,
consultable à volonté.

## Architecture
- [driver.js](https://driverjs.com/) (tour interactif, client-only, ~5 kB, MIT).
- `utils/tourSteps.ts` : source de vérité déclarative des tours.
- `composables/useGuidedTour.ts` : moteur (start / autostart / versioning / navigation
  / filtrage par profil). Nommé `useGuidedTour` et **pas** `useTour` : @nuxt/ui expose
  son propre `useTour` qui masquait le nôtre en build de prod.
- `pages/help.vue` : doc statique (différences app1/app2, nouveautés, navigation).
- Ancres `data-tour="..."` posées sur les éléments réels.
- Versioning : localStorage `kpi_admin_tour_<id>_version`.

## Modèle de données d'une étape (tourSteps.ts)
```ts
type TourStep = {
  route?: string          // page où l'étape s'affiche (navigation auto si besoin)
  anchor: string          // valeur du data-tour ciblé ; '' = étape centrée sans cible
  titleKey: string        // clé i18n tour.*
  bodyKey: string         // clé i18n tour.*
  isNew?: boolean         // marquée "nouveauté" pour le re-déclenchement
}
type Tour = {
  id: string
  version: number
  labelKey: string        // libellé dans la liste /help
  icon: string            // icône heroicons dans la liste /help
  autoStartRoute?: string // page déclenchant l'auto-start (tours de page)
  maxProfile?: number     // profil max autorisé ; absent = tous profils
  steps: TourStep[]
}
```

## Deux familles de tours
- **Tour d'accueil** (`welcome`) : pas d'`autoStartRoute`, lancé par `pages/index.vue`.
- **Tours de page** : `autoStartRoute` renseigné, `maybeAutoStart()` appelé dans le
  `onMounted` de la page **après le chargement des données** (les ancres n'existent
  qu'une fois le contenu rendu).

Tous les tours (accueil + pages) sont listés ensemble sur `/help`, filtrés par
profil via `useAvailableTours()`.

## Tour MVP "welcome" (version 1)
1. anchor `''` — accueil : bienvenue + rappel app1 vs app2.
2. anchor `mandate` (route `/`) — le choix du mandat (expliqué depuis le user-menu ;
   note : la vraie page de choix est `/select-mandate`, hors tour).
3. anchor `work-context` (route `/`) — contexte de travail : saison + périmètre
   (sélection / section / groupe / événement) ; modifiable à tout moment.
4. anchor `menu` (route `/`) — raccourci menu horizontal.
5. anchor `home-shortcuts` (route `/`) — raccourcis en cards sur l'accueil.
6. anchor `clickable-cells` (route `/competitions`) — données cliquables du tableau
   (groupe, équipes, journées, matchs). Note : le tableau est vide tant qu'aucun
   contexte n'est choisi ; l'ancre est posée sur le `<tr>` d'en-tête, et si absent
   driver.js centre la bulle (le texte reste explicatif).
7. anchor `context-summary` (route `/competitions`) — le rappel de contexte affiché
   en haut de chaque page (via `AdminPageHeader` → `WorkContextSummary compact`) ;
   son bouton « Modifier » renvoie à l'accueil pour changer le contexte.

## Tour "rankings" (version 1) — profils ≤ 4 uniquement
Auto-déclenché à la 1re arrivée sur `/rankings`. Rappelle le nouvel enchaînement :
**vérifier les égalités → consolider les phases → affecter les équipes dans Matchs.**

1. anchor `''` — intro : ce qui change sur la page.
2. anchor `ties-justification` — bouton « Égalités » donnant accès au PDF
   « Justification du départage ».
3. anchor `phase-consolidation` — case « Consolider la phase ».
4. anchor `''` — outro : l'affectation auto dans Matchs exige la consolidation.

**Cibles conditionnelles (important).** Les deux ancres n'existent pas toujours :
- `ties-justification` : `v-if="hasTies"` — uniquement si des équipes sont à égalité.
- `phase-consolidation` : compétition **CP** avec une phase de **type C** ; l'ancre
  n'est posée que sur la **première** phase consolidable (sélecteur unique).

Quand l'ancre est absente, `waitForElement` expire (2 s) et driver.js centre la
bulle. Les textes sont donc rédigés pour rester justes dans ce cas (« lorsque des
équipes sont à égalité, un bouton apparaît ici »). Décision assumée : pas de saut
d'étape conditionnel, pour garder le moteur simple.

## Ancres à poser (data-tour)
| anchor          | fichier                                   | élément                        |
|-----------------|-------------------------------------------|--------------------------------|
| mandate         | components/admin/Header.vue               | bouton du user-menu (desktop)  |
| work-context    | components/admin/WorkContextSelector.vue  | conteneur racine               |
| menu            | components/admin/Header.vue               | `<nav ref="navRef">`           |
| home-shortcuts  | pages/index.vue                           | grille de cards                |
| clickable-cells | pages/competitions/index.vue              | `<tr data-tour>` d'en-tête     |
| context-summary | components/admin/WorkContextSummary.vue   | barre compacte (bouton Modifier)|
| ties-justification | pages/rankings/index.vue               | conteneur du bouton « Égalités » |
| phase-consolidation | pages/rankings/index.vue              | `<label>` de la 1re phase type C |

## Déclenchement
- `pages/index.vue` `onMounted` → `useGuidedTour('welcome').maybeAutoStart()`.
- Tours de page → `useGuidedTour('<id>').maybeAutoStart()` dans le `onMounted` de
  la page, après chargement des données (ex. `pages/rankings/index.vue`).
- Règle : version vue absente → tour complet ; version vue < courante → proposer
  seulement les étapes `isNew` ; sinon rien.
- Entrée manuelle : **uniquement depuis `/help`**, où tous les tutoriels sont
  listés avec un bouton « Rejouer ». Le dropdown du user-menu ne contient plus
  qu'un lien « Aide et tutoriels » vers `/help` (l'ancienne entrée « Revoir le
  tutoriel » a été supprimée : elle ne pouvait relancer que le tour d'accueil).
- Le badge « Nouveautés » est porté par le bouton du user-menu (visible dropdown
  fermé) et par l'entrée « Aide et tutoriels ».

## Comment ajouter/mettre en valeur une nouvelle fonctionnalité (procédure de reprise)
1. Poser un `data-tour="ma-feature"` sur l'élément concerné.
2. Ajouter une étape dans le tour "welcome" (ou un nouveau tour) dans `tourSteps.ts`,
   avec `isNew: true`.
3. Incrémenter `version` du tour.
4. Ajouter les clés i18n `tour.ma_feature_title` / `tour.ma_feature_body` en fr + en.
5. (Optionnel) compléter `/help`.

Au prochain retour des admins, le badge "Nouveautés" apparaît et seules les nouvelles
étapes leur sont proposées.

## Comment ajouter un tutoriel de page (nouveau tour)
1. Poser les `data-tour="..."` sur les éléments de la page. Si l'élément est dans
   un `v-for`, n'ancrer que la **première** occurrence (sélecteur unique).
2. Déclarer le tour dans `tourSteps.ts` (`labelKey`, `icon`, `autoStartRoute`,
   éventuellement `maxProfile`) et l'ajouter à `TOURS`.
3. Dans la page, appeler `useGuidedTour('<id>')` au setup et `maybeAutoStart()`
   dans `onMounted`, **après** le chargement des données (+ `await nextTick()`).
4. Ajouter les clés i18n `tour.*` en fr **et** en (rappel : anglais = "Games").
5. Rien à faire sur `/help` : la liste des tutoriels est générée depuis `TOURS`
   et filtrée par profil.

Si une ancre peut être absente selon le contexte (donnée conditionnelle), rédiger
le texte pour qu'il reste juste sans la cible : driver.js centrera la bulle.

## Captures d'écran (évolution future, hors MVP)
Un script Playwright pourra générer des captures des étapes clés (login → mandat →
contexte → tableau) à déposer dans `public/img/help/` et référencer dans `/help`.
Harnais existant : `tests/playwright/` (voir README ; shim fetch `:8003`).

## Contraintes
- driver.js importé dynamiquement client-only (`import.meta.client`).
- i18n : anglais = "Games" (pas "Matches").
- Ne pas lancer npm/dev server automatiquement (fait par l'utilisateur).
- Préfixe localStorage `kpi_admin_*` (cohérence `authStore`).
