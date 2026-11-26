# API CRUD des Désignations et droits

Status: ready-for-agent
Blocked by: 01

Spec : [FEATURE_DESIGNATIONS.md](../../../DOC/specs/FEATURE_DESIGNATIONS.md) (D5, D7, D22, D23, D27 ; § 8.3)

## À faire

`GET/POST/PATCH/DELETE /admin/designations` (filtres saison, rôle, périmètre, personne, club), `GET /admin/designations/roles`. Club figé depuis le PCE à la création ; correction réservée aux profils 1–2 ; journalisation.

## Critères

DES-02, DES-03, DES-04, DES-06, DES-07.

## Comments

- 2026-11-23 — Implémenté sur `claude/spec-designations-bilan-journee`. Tests écrits mais **non exécutés** dans la session cloud (Composer ne peut pas télécharger depuis GitHub, pas de Docker) : lancer `make api2_test` avant de clore.
  Erreurs renvoyées `{ "error": code }` : 400 `invalid`, 403 `forbidden`, 404 `not_found`, 409 `duplicate`,
  422 `role_unknown` · `scope_not_allowed` · `authority_not_allowed` · `scope_full` · `scope_unknown` · `licence_unknown`.
  Un événement appartient à une saison s'il contient au moins une journée de cette saison.
- 2026-11-23 — `make api2_test` : 1 échec, `testAnUnknownScopeIsRefused`, dû au test (union de tableaux qui gardait
  la saison 2998) ; corrigé.
