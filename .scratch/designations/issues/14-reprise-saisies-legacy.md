# Reprise des officiels et clubs organisateurs saisis avant les Désignations

Status: ready-for-human
Blocked by: 06

Spec : [FEATURE_DESIGNATIONS.md](../../../DOC/specs/FEATURE_DESIGNATIONS.md) (D30)

## À faire

Commande `app:designations:backfill-legacy`, colonne « Club organisateur » du suivi, enregistrement du code du club
organisateur par le formulaire de journée et la copie de compétition. À lancer saison par saison **avant** la bascule du
ticket 13.

## Critères

REP-01 à REP-05, ORG-01 à ORG-03.

## Comments

- 2026-11-25 — Implémenté (tests unitaires écrits ; le reste se vérifie à la main en dev) : `LegacyBackfillPlanner`
  (pur), `LegacyBackfillService`, `OrganiserClubResolver` (partagé avec `SeasonDataLoader`). Le formulaire de journée
  envoyait `codeOrganisateur: ''` à chaque modification : le code était effacé. Les journées déjà touchées retrouvent
  leur code par le libellé (la commande le fait aussi).
