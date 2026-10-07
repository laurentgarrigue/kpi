# Principes de code propre — KPI

**Date** : 7 octobre 2026
**Portée** : **tout nouveau code** du dépôt (site public app3, `kpi-layer`, app2, app4, api2), et tout code
legacy que l'on modifie. Référencé par [CLAUDE.md](../../../CLAUDE.md) : il s'applique à chaque chantier.

Ce guide ne remplace pas les conventions existantes ([BEST_PRACTICES_JAVASCRIPT_SMARTY.md](BEST_PRACTICES_JAVASCRIPT_SMARTY.md)
pour le legacy, [NAMING_CONVENTION.md](../../NAMING_CONVENTION.md) pour la doc). Il fixe les principes communs et
la *Definition of Done*.

---

## 1. Les principes, appliqués à ce dépôt

| Principe | Ce que ça veut dire ici | Exemple concret |
|---|---|---|
| **Spec d'abord** | Toute page ou fonctionnalité a une spec avec des **critères d'acceptation testables** avant la première ligne de code. | `DOC/specs/public/SITE_NAVIGATION.md` → `app3/tests/unit/navigation.spec.ts` |
| **TDD** | Rouge → vert → refactor. Un critère d'acceptation = au moins un test, écrit **avant** le code. Un bug corrigé = un test qui le reproduit d'abord. | `resolveMenuLink()` est spécifié par ses tests avant d'exister |
| **KISS** | La solution la plus simple qui satisfait la spec. Pas d'abstraction « pour plus tard ». | Le domaine beta est dérivé de `KPI_DOMAIN_NAME` plutôt que d'ajouter une variable |
| **YAGNI** | On n'écrit pas ce que la spec ne demande pas. Une option, un paramètre ou une couche apparaît quand un besoin réel la justifie. | Pas de CMS de menu tant que la phase 4a n'est pas lancée : le menu est une constante typée |
| **DRY** | Une connaissance a **une seule source**. Pas de copier-coller entre contrôleurs, composants ou apps : on factorise (service, composable, util, composant du layer). | Logique de classement : service api2 partagé par `Admin*Controller` et `Public*Controller` |
| **SOLID — S** (responsabilité unique) | Un module a une raison de changer. Composant = affichage ; composable = état/données ; util = calcul pur ; contrôleur = HTTP ; service = règle métier ; repository = accès aux données. | Un composant Vue ne fait pas de `fetch` ; un contrôleur ne contient pas de SQL métier |
| **SOLID — O** (ouvert/fermé) | On étend par configuration ou composition plutôt qu'en modifiant du code éprouvé. | Une nouvelle entrée de menu = une ligne dans la config, pas une modification du composant |
| **SOLID — L, I** | Les types/interfaces sont petits et respectés par toutes leurs implémentations. | `MenuItem` minimal ; les props d'un composant sont limitées à ce qu'il affiche |
| **SOLID — D** (inversion des dépendances) | Le code métier dépend d'abstractions injectées (paramètres, services Symfony, `useRuntimeConfig`), jamais de chemins ou d'URL en dur. | `legacy_document_root` injecté dans api2 au lieu de `__DIR__` |
| **Nommage** | Noms explicites, en anglais dans le code, sans abréviations obscures. Booléens en `is/has/can`. Fonctions = verbes. | `isExternalLink`, `resolveMenuLink`, `fetchPublicCompetitions` |
| **Fonctions courtes** | Une fonction fait une chose ; au-delà d'une trentaine de lignes, se demander ce qu'on peut extraire. | |
| **Pas de magie** | Pas de nombres ni de chaînes magiques : constantes nommées, types, enums. | `CACHE_TTL_RESULTS = 60` plutôt que `60` dans trois fichiers |
| **Code mort** | Pas de code commenté ni de fonctions inutilisées : Git garde l'historique. | |

## 2. Règles par brique

### Nuxt (app3, `kpi-layer`, app2, app4)

- **Découpage** : `utils/` = fonctions **pures** (testées unitairement, sans Nuxt) ; `composables/` = état et
  accès aux données ; `components/` = affichage, props typées, pas d'appel réseau ; `pages/` = assemblage.
- **TypeScript strict** pour le nouveau code ; pas de `any` sans justification en commentaire.
- **Données** : un seul client API (`useApi2` du layer) ; pas d'URL d'API en dur, tout passe par `runtimeConfig`.
- **Textes** : tous les libellés via i18n (FR/EN) ; aucune chaîne visible en dur dans un template.
- **Style** : jetons de la charte (`kpi-layer`), pas de couleur hexadécimale dans un composant.
- **Tests** : Vitest. `utils/` → tests unitaires ; composants → tests de rendu (`@nuxt/test-utils`) sur les
  critères d'acceptation ; pas de test sur des détails d'implémentation.

### api2 (Symfony 7.4)

- **Contrôleurs minces** : validation de l'entrée, appel d'un service, sérialisation. La règle métier vit dans
  `src/Service/`.
- **Pas de duplication Admin/Public** : la logique commune est extraite dans un service avant d'écrire le
  second contrôleur.
- **Sorties publiques** : DTO dédiés, jamais une entité complète (cf. données personnelles).
- **Configuration injectée** (paramètres, `bind`), jamais de chemin ou d'URL déduits de l'environnement d'exécution.
- **Tests** : PHPUnit — unitaires pour les services, intégration sur fixtures pour les endpoints (`make api2_test`).

### Legacy PHP / Smarty

- On ne le refactore pas pour lui-même. Quand on y touche : correctif minimal, testé à la main ou par un
  test d'intégration, et pas de nouvelle fonctionnalité si elle a sa place dans api2/app4/app3.

## 3. Definition of Done

Une PR est terminée quand :

- [ ] la **spec** existe, est à jour et ses critères d'acceptation sont couverts ;
- [ ] les **tests** ont été écrits avant le code et passent (`npm test` / `make api2_test`) ;
- [ ] le **lint** et le **typecheck** passent ; la CI est verte ;
- [ ] aucune **duplication** introduite (ou duplication temporaire tracée, avec l'étape qui la supprime) ;
- [ ] aucun texte visible en dur, aucune couleur ou URL en dur ;
- [ ] la **doc** concernée est mise à jour (spec, README de la brique, runbook si l'exploitation change) ;
- [ ] le changement est **déployable seul** sans régression sur les autres briques.

## 4. Revue

En relisant (soi-même ou une autre personne), poser trois questions :

1. Ce code fait-il **exactement** ce que dit la spec, ni plus ni moins (KISS, YAGNI) ?
2. Y a-t-il une **seconde source** pour une même connaissance (DRY) ?
3. Chaque module a-t-il **une seule raison de changer** (SOLID — S) ?
