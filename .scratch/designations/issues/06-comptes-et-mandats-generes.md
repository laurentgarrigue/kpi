# Comptes et mandats générés depuis les Désignations

Status: ready-for-agent
Blocked by: 02, 03

Spec : [FEATURE_DESIGNATIONS.md](../../../DOC/specs/FEATURE_DESIGNATIONS.md) (D5, D6, D8, D10, D16)

## À faire

Colonnes `designation_role`, `designation_saison` sur `kp_user_mandat` ; création du compte (email connu), recalcul du mandat `(utilisateur, saison, rôle)` à chaque écriture de Désignation et (dés)association de journée ; e-mail d'activation (PASSWORD_RENEWAL).

## Critères

MAN-01 à MAN-11.

## Comments

- 2026-11-23 — Implémenté : migration `SQL/migrations/2026-11-23_designations_mandates.sql` (colonnes
  `designation_role`, `designation_saison` + clé unique), `MandatePerimeter` (filtres), `MandateRepository`,
  `GeneratedMandates` (compte + mandat), `GamedayDesignations` (création, duplication, (dés)association de journée),
  crochets dans `DesignationService`. **Écart à la spec** : `kp_licence` n'a pas d'email ; l'email est un champ
  facultatif de `POST /admin/designations`, qui ne sert qu'à créer le compte (spec D5 et MAN-01 mises à jour).
  Le lien d'activation est celui du mot de passe oublié (`NotificationService::sendPasswordReset`).
  Tests écrits, non exécutés dans la session cloud.
