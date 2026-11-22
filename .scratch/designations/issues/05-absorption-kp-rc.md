# Absorption de kp_rc dans kp_designation

Status: ready-for-agent
Blocked by: 03

Spec : [FEATURE_DESIGNATIONS.md](../../../DOC/specs/FEATURE_DESIGNATIONS.md) (D4, ADR-0003)

## À faire

Migration des lignes de `kp_rc` (rôle `RC`, club au PCE), remplacement de la table par une vue en lecture seule ; bascule des lecteurs api2 (`AdminRcController`, `AdminCompetitionsController`, `CompetitionCodeRenameService`, `SeasonOperationsService`, entité `Rc`) et de la page RC d'app4.

## Critères

RC-01 à RC-04.

## Comments
