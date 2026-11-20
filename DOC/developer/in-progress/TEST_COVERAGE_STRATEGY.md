# Couverture de tests — stratégie et spec de l'étape 1 (mesure)

**Date** : 19 novembre 2026 — **Statut** : 🚧 Étapes 1, 2 et 4 livrées ; étape 3 amorcée (app2 et app4) ; étape 5 : objectifs posés, à valider après la première CI — **Portée** : app2, app3 (+ `kpi-layer`), app4, api2

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

## 3bis. Spec de l'étape 2 — cliquet

| ID | Critère |
|---|---|
| COV-11 | La politique vit dans **un seul fichier** versionné, `scripts/ci/coverage-policy.json` : plancher de lignes par brique (`floors`) et seuil de couverture du code modifié par brique (`diffThresholds`). |
| COV-12 | `scripts/ci/coverage-check.mjs` échoue (code 1) si le taux de lignes d'une brique est **sous son plancher** ; un plancher `null` ne bloque pas (baseline pas encore connue). |
| COV-13 | Il échoue si, parmi les lignes **ajoutées ou modifiées** par la PR (`git diff -U0 <base>...HEAD`) et présentes dans le rapport de couverture, la part couverte est **sous le seuil** de la brique. Les lignes non exécutables (commentaires, déclarations) et les fichiers hors périmètre de mesure sont ignorés. |
| COV-14 | Le message d'échec liste les fichiers et lignes non couverts du diff ; quand le taux dépasse le plancher d'au moins 1 point, il suggère de relever le plancher (cliquet). |
| COV-15 | Hors PR (`push`, exécution locale sans `--base`), seul le plancher est vérifié. |
| COV-16 | La CI exécute le contrôle après chaque rapport de couverture ; il est **bloquant** (le job échoue). |

Lire les chemins de `lcov.info` (relatifs à la brique) et de `clover.xml` (absolus) en les ramenant à la racine du dépôt.
Tests : `scripts/ci/coverage-check.test.mjs`.

## 3ter. Étapes 3 à 5 — ce qui est livré et ce qui reste

**Étape 3 — niveau d'app3 partout** (amorcée) :

| Brique | Livré | Reste (tickets `.scratch/test-coverage/issues/`) |
|---|---|---|
| app2 | Vitest + couverture, 19 tests sur `utils/` (`nameFormat`, `gameTimeline`, `medals`) | 06 : composables, stores, composants — **après décision sur sa durée de vie** (remplacée par app3) |
| app4 | Projet Vitest `nuxt` (`@nuxt/test-utils`, happy-dom) comme app3 + premier test de composant (`ExperimentalBanner`) | 02 : pages et composants par domaine (scoring, présence, auth, éditorial) ; 03 : smoke Playwright en CI |
| api2 | — | 04 : PHPStan 3 → 4 → 5 ; 05 : contrôleurs `Admin*` sans test d'intégration ; 07 : contrat OpenAPI |

**Étape 4 — socle commun** (livrée) : nomenclature `tests/unit|nuxt|e2e` ; `make test` (app2, app3, app4, api2, scripts de CI) ;
`tests-ci-scripts` ajouté aux jobs requis de `ci-summary` ; les jobs de tests de chaque brique y figuraient déjà.

**Étape 5 — objectifs chiffrés** (proposés, à valider une fois la baseline api2 connue) :

| Zone | Cible | Raison |
|---|---|---|
| `utils/`, services api2 (logique pure) | ≥ 80 % de lignes | Peu coûteux à tester, c'est là que sont les règles métier |
| Composants et pages Nuxt | ≥ 60 % | Tests de rendu sur les critères d'acceptation, pas sur l'implémentation |
| Contrôleurs api2 | ≥ 60 % (tests d'intégration) | Objectif « ≥ 30 % » de CI_CD_STRATEGY.md, jamais mesuré |
| Code modifié par une PR | ≥ 80 % (api2 : 60 % d'abord) | C'est le cliquet qui tire le reste |
| Critères d'acceptation des specs | 100 % couverts par au moins un test | Règle « spec first » de CLEAN_CODE.md |

Le **plancher global** ne baisse jamais ; on le relève dès que le taux le dépasse d'au moins 1 point
(`scripts/ci/coverage-policy.json`, le script le suggère). Objectif final : app4 au niveau d'app3 (≥ 90 %
sur ce qui est mesuré) — voir le ticket 08 pour la routine de relèvement.

## 4. Baseline

Mesurée en local le 19/11/2026 sur `3b02b69` + ces changements (Vitest 5.1.1, v8). Valeurs locales (app2, app3, app4) ; api2 mesuré par la première CI.

| Brique | Lignes | Plancher | Remarque |
|---|---|---|---|
| app2 | **2,9 %** | 2,9 | 19 tests (3 fichiers), 73 fichiers dont 42 `.vue` |
| app3 + kpi-layer | **91,5 %** | 91 | 440 tests (unit + nuxt + e2e), 192 fichiers dont 130 `.vue` |
| app4 | **15,7 %** | 15,5 | 509 tests (unit + nuxt), 237 fichiers dont 153 `.vue` ; lots 1 à 4 du scoring livrés (ticket 02) |
| api2 | **51,0 %** | 50,9 | Mesuré en CI (PCOV, unit + integration, PR #408) ; 358 fichiers dans `src/` |

⚠️ **Le taux de lignes d'un `.vue` dépend du compilateur** : avec le seul `@vitejs/plugin-vue`, app4 affichait
3,3 % (templates comptés comme du code) ; avec le projet `nuxt` (même mécanisme qu'app3) 9,9 %. Les planchers
ne se comparent donc **qu'au sein d'une brique** ; app4 et app3 sont comparables, app2 (plugin Vue seul) moins.
Sans aucun plugin Vue, les `.vue` jamais importés disparaissent du rapport (app4 : 19 % trompeur).

Constat : l'écart app3 / app4 est bien plus grand que l'impression de départ (« 32 fichiers de test chacun »).
La priorité de l'étape 3 est app4, ses pages et composants.
