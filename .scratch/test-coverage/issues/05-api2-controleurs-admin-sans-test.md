# api2 : contrôleurs sans test d'intégration

Status: needs-triage
Blocked by: —

Spec : [TEST_COVERAGE_STRATEGY.md](../../../DOC/developer/in-progress/TEST_COVERAGE_STRATEGY.md)

Lister les contrôleurs de `src/Controller/` absents de `tests/Integration/` (48 contrôleurs, ~50 fichiers d'intégration) et les couvrir par ordre de risque : auth, écritures `Admin*`, exports. Cible : ≥ 60 % sur `src/Controller/`.
