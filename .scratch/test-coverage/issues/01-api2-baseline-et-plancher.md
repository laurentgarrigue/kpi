# api2 : mesurer la baseline et fixer le plancher

Status: resolved
Blocked by: première exécution du job `tests-api2` avec PCOV

Spec : [TEST_COVERAGE_STRATEGY.md](../../../DOC/developer/in-progress/TEST_COVERAGE_STRATEGY.md)

Lire le taux de lignes dans le résumé du job `tests-api2` (ou `make api2_coverage`), le reporter au § 4 de la spec, puis remplacer `"api2": null` dans `scripts/ci/coverage-policy.json` par ce taux arrondi 0,1 pt en dessous.

Vérifier aussi que le cliquet lit bien les chemins absolus du clover en CI (`coverage-check.mjs api2 clover ...`).

## Comments

Baseline mesurée par la CI de la PR #408 : 51,00 % de lignes. Plancher fixé à 50,9 dans `coverage-policy.json`. Le cliquet lit bien les chemins absolus du clover (mode PR vérifié, `BASE_REF: main`).
