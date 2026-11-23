# Absorption de kp_rc dans kp_designation

Status: ready-for-agent
Blocked by: 03

Spec : [FEATURE_DESIGNATIONS.md](../../../DOC/specs/FEATURE_DESIGNATIONS.md) (D4, ADR-0003)

## À faire

Migration des lignes de `kp_rc` (rôle `RC`, club au PCE), remplacement de la table par une vue en lecture seule ; bascule des lecteurs api2 (`AdminRcController`, `AdminCompetitionsController`, `CompetitionCodeRenameService`, `SeasonOperationsService`, entité `Rc`) et de la page RC d'app4.

## Critères

RC-01 à RC-04.

## Comments

- 2026-11-23 — Implémenté : migration `SQL/migrations/2026-11-23_designations_absorb_kp_rc.sql` (copie, renommage
  de la table en `kp_rc_avant_designations`, vue `kp_rc` en utf8mb3), `AdminRcController` réécrit sur
  `DesignationService` (contrat inchangé ; changer de compétition ou de licencié remplace la Désignation, donc l'id
  change), `nbRc` des compétitions, renommage de code (toutes les Désignations sur la compétition), copie des RC
  entre saisons, entité `Rc` supprimée (inutilisée). Le RC national (`Code_competition` NULL ou `- CNA -`) devient un
  RC sur le niveau national : `RoleCatalog` autorise désormais `NIVEAU` pour `RC`. RC-01 (la migration elle-même) se
  vérifie à la main en dev : `SELECT COUNT(*) FROM kp_rc_avant_designations` = `SELECT COUNT(*) FROM kp_rc`.
  Tests écrits, non exécutés dans la session cloud.
