# Couverture de tests — stratégie et spec de l'étape 1 (mesure)

**Date** : 19 novembre 2026 — **Statut** : 🚧 Étape 1 implémentée (baseline api2 à confirmer en CI) — **Portée** : app2, app3 (+ `kpi-layer`), app4, api2

## 1. Contexte

app3 est la référence : trois niveaux de tests (`unit`, `nuxt`, `e2e`), typecheck, lint, tests nommés d'après les
critères d'acceptation des specs. Les autres briques sont en retrait (app4 : utils purs seulement ; app2 : aucun
test ; api2 : PHPUnit `unit` + `integration`). **Aucun taux de couverture n'est mesuré nulle part** : l'objectif
« api2 ≥ 30 % » de [CI_CD_STRATEGY.md](../archive/ci-cd/CI_CD_STRATEGY.md) n'a jamais pu être vérifié (pas de pilote de
couverture en CI). Sans baseline, on ne peut pas piloter la progression.

## 2. Stratégie (5 étapes)

1. **Mesurer** (cette spec) : rapport de couverture par brique, local et en CI, non bloquant.
2. **Cliquet** : figer la baseline comme plancher bloquant (elle ne peut que monter) + exigence de couverture sur le code modifié par une PR.
3. **Niveau d'app3 partout** : app4 (projet `nuxt` pour pages/composants, smoke e2e Playwright en CI), app2 (Vitest, selon sa durée de vie restante), api2 (PHPStan niveau 3 → 5, contrôleurs Admin sans test d'intégration, contrat OpenAPI).
4. **Socle commun** : nomenclature `tests/unit|nuxt|e2e`, configuration mutualisée, cible `make test`, jobs requis dans `ci-summary`.
5. **Objectifs chiffrés** fixés **après** la baseline (pas avant : arbitraires sans mesure).

## 3. Spec de l'étape 1 — critères d'acceptation

| ID | Critère |
|---|---|
| COV-01 | `npm run test:coverage` (app3, app4) lance les tests **et** produit `coverage/coverage-summary.json`, `coverage/lcov.info` et un résumé texte en console. |
| COV-02 | Le périmètre mesuré d'app3 est le code applicatif (`app/`, `server/`, `shared/` et `../kpi-layer/app/`) ; les tests, fixtures et fichiers générés (`.nuxt`, `.output`) en sont exclus. |
| COV-03 | Le périmètre d'app4 est `components/`, `composables/`, `pages/`, `stores/`, `utils/`, `middleware/`, `plugins/`, `layouts/` ; **un fichier jamais importé par un test compte pour 0 %** (sinon le taux est faussé à la hausse). |
| COV-04 | `composer test-coverage` (api2) exécute les suites `unit` + `integration` avec PCOV, mesure `src/` et produit `var/coverage/clover.xml` et un résumé texte. |
| COV-05 | Le script `scripts/ci/coverage-summary.mjs` transforme un `coverage-summary.json` Vitest ou un `clover.xml` PHPUnit en ligne de tableau Markdown (lignes, instructions, % ) ; un rapport illisible ou absent donne une ligne « non disponible », sans faire échouer le script. |
| COV-06 | En CI, les jobs `tests-app3`, l'étape de tests app4 et `tests-api2` écrivent le tableau dans `$GITHUB_STEP_SUMMARY` et déposent le dossier de couverture en artefact. **Aucun seuil** : la couverture ne fait pas échouer la CI à cette étape. |
| COV-07 | Make : `make app3_coverage`, `make app4_coverage`, `make api2_coverage`. |
| COV-08 | app2 n'a aucun test : sa couverture est documentée à **0 %** (non instrumentée), pas mesurée. L'instrumenter relève de l'étape 3. |
| COV-09 | Les dossiers de rapport (`coverage/`, `var/coverage/`) sont ignorés par Git. |
| COV-10 | La baseline mesurée (date, commit, % par brique) est consignée au § 4 de ce document. |

Tests : `scripts/ci/coverage-summary.test.mjs` (COV-05, `node --test`, `make ci_scripts_test`). Les autres critères
sont vérifiés en exécutant les commandes.

## 4. Baseline

Mesurée en local le 19/11/2026 sur `3b02b69` (Vitest 5.1.1, v8). Valeurs à confirmer par la première exécution de la CI.

| Brique | Lignes | Instructions | Remarque |
|---|---|---|---|
| app2 | 0 % | — | Aucun test, non instrumenté (COV-08) |
| app3 + kpi-layer | **91,5 %** | 92,1 % | 440 tests (unit + nuxt + e2e), 192 fichiers dont 130 `.vue` |
| app4 | **3,3 %** | 3,5 % | 338 tests, 234 fichiers dont 153 `.vue` : seuls les utils sont testés |
| api2 | _à mesurer en CI_ | | PCOV absent de l'environnement de rédaction ; `make api2_coverage` ou job `tests-api2` |

⚠️ **Piège évité** : sans `@vitejs/plugin-vue` dans `vitest.config.ts`, les `.vue` jamais importés par un test
disparaissent du rapport et app4 affichait 19 % au lieu de 3,3 %. Le plugin est donc nécessaire à COV-03.

Constat : l'écart app3 / app4 est bien plus grand que l'impression de départ (« 32 fichiers de test chacun »).
La priorité de l'étape 3 est app4, ses pages et composants.
