# Spec — Tutoriel dynamique admin2 (app4)

## But
Onboarding des admins legacy sur app4 : tour guidé interactif + page /help.
Déclenchement auto à la 1re visite, re-déclenchement partiel des nouveautés au retour,
consultable à volonté.

## Architecture
- [driver.js](https://driverjs.com/) (tour interactif, client-only, ~5 kB, MIT).
- `utils/tourSteps.ts` : source de vérité déclarative des tours.
- `composables/useTour.ts` : moteur (start / autostart / versioning / navigation).
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
type Tour = { id: string; version: number; steps: TourStep[] }
```

## Tour MVP "welcome" (version 1)
1. anchor `''` — accueil : bienvenue + rappel app1 vs app2.
2. anchor `mandate` (route `/`) — le choix du mandat (expliqué depuis le user-menu ;
   note : la vraie page de choix est `/select-mandate`, hors tour).
3. anchor `work-context` (route `/`) — contexte de travail : saison + périmètre
   (sélection / section / groupe / événement) ; modifiable à tout moment.
4. anchor `menu` (route `/`) — raccourci menu horizontal.
5. anchor `home-shortcuts` (route `/`) — raccourcis en cards sur l'accueil.
6. anchor `table-links` (route `/games`) — liens rapides dans les tableaux.

## Ancres à poser (data-tour)
| anchor          | fichier                                   | élément                     |
|-----------------|-------------------------------------------|-----------------------------|
| mandate         | components/admin/Header.vue               | bloc mandat du user-menu    |
| work-context    | components/admin/WorkContextSelector.vue  | conteneur racine            |
| menu            | components/admin/Header.vue               | `<nav ref="navRef">`        |
| home-shortcuts  | pages/index.vue                           | grille de cards             |
| table-links     | pages/games/index.vue                     | 1er lien de ligne           |

## Déclenchement
- `pages/index.vue` `onMounted` → `useTour().maybeAutoStart()`.
- Règle : version vue absente → tour complet ; version vue < courante → proposer
  seulement les étapes `isNew` ; sinon rien.
- Header : bouton `?` (start manuel) + badge si nouveautés dispo.

## Comment ajouter/mettre en valeur une nouvelle fonctionnalité (procédure de reprise)
1. Poser un `data-tour="ma-feature"` sur l'élément concerné.
2. Ajouter une étape dans le tour "welcome" (ou un nouveau tour) dans `tourSteps.ts`,
   avec `isNew: true`.
3. Incrémenter `version` du tour.
4. Ajouter les clés i18n `tour.ma_feature_title` / `tour.ma_feature_body` en fr + en.
5. (Optionnel) compléter `/help`.

Au prochain retour des admins, le badge "Nouveautés" apparaît et seules les nouvelles
étapes leur sont proposées.

## Captures d'écran (évolution future, hors MVP)
Un script Playwright pourra générer des captures des étapes clés (login → mandat →
contexte → tableau) à déposer dans `public/img/help/` et référencer dans `/help`.
Harnais existant : `tests/playwright/` (voir README ; shim fetch `:8003`).

## Contraintes
- driver.js importé dynamiquement client-only (`import.meta.client`).
- i18n : anglais = "Games" (pas "Matches").
- Ne pas lancer npm/dev server automatiquement (fait par l'utilisateur).
- Préfixe localStorage `kpi_admin_*` (cohérence `authStore`).
