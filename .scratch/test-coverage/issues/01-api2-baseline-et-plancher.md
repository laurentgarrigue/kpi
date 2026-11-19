# api2 : mesurer la baseline et fixer le plancher

Status: needs-triage
Blocked by: première exécution du job `tests-api2` avec PCOV

Spec : [TEST_COVERAGE_STRATEGY.md](../../../DOC/developer/in-progress/TEST_COVERAGE_STRATEGY.md)

Lire le taux de lignes dans le résumé du job `tests-api2` (ou `make api2_coverage`), le reporter au § 4 de la spec, puis remplacer `"api2": null` dans `scripts/ci/coverage-policy.json` par ce taux arrondi 0,1 pt en dessous.

Vérifier aussi que le cliquet lit bien les chemins absolus du clover en CI (`coverage-check.mjs api2 clover ...`).
