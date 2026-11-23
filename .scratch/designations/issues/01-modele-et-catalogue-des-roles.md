# Table kp_designation et catalogue des rôles

Status: ready-for-agent
Blocked by: —

Spec : [FEATURE_DESIGNATIONS.md](../../../DOC/specs/FEATURE_DESIGNATIONS.md) (D2, D3, D8, D16, D21, D23, D26 ; § 8.1, § 8.2)

## À faire

- Migration `SQL/migrations/2026-11-22_designations.sql` (idempotente) + `SQL/fixtures/schema.sql` : table `kp_designation` (§ 8.1).
- `App\Designation\RoleCatalog` (api2), seul endroit des rôles : périmètres autorisés, profil du mandat (ou aucun), exigence par niveau (D26), qui peut désigner, colonne legacy recopiée.
- Règle « compétition nationale » (codes `N…`, `CF…`) en un seul endroit, réutilisable par le classement national des clubs.

## Critères

DES-01, DES-05 ; tests unitaires du catalogue (chaque rôle de la spec présent avec ses valeurs).

## Comments

- 2026-11-23 — Implémenté sur `claude/spec-designations-bilan-journee`. Tests écrits mais **non exécutés** dans la session cloud (Composer ne peut pas télécharger depuis GitHub, pas de Docker) : lancer `make api2_test` avant de clore.
