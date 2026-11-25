# Blocs « Désignations » sur les pages compétitions, événements, journées, équipes

Status: ready-for-agent
Blocked by: 03, 05

Spec : [FEATURE_DESIGNATIONS.md](../../../DOC/specs/FEATURE_DESIGNATIONS.md) (§ 8.4)

## À faire

Bloc réutilisable app4 listant et éditant les Désignations d'un périmètre (héritées visibles) ; remplace la page RC et la section Officiels de la modale journée.

## Critères

DES-07 (côté écran), SUI-03 (affichage de l'héritage).

## Comments

- 2026-11-23 — Implémenté. api2 : `GET /admin/designations/effective?gameday=` (titulaires effectifs avec provenance),
  lecture des Désignations limitée aux profils ≤ 5 (`GET /admin/designations` compris). app4 : `DesignationsScopePanel`
  (bloc), `DesignationsScopeModal`, `DesignationsAddModal` (fenêtre « Désigner » partagée avec le suivi).
  Journées : la fenêtre « Officiels » affiche le bloc (profils ≤ 5 ; au-delà, le résumé texte) et le formulaire de
  journée n'envoie plus les colonnes d'officiels. Compétitions : l'icône RC ouvre le bloc de la compétition (au lieu
  de la page RC). Événements et équipes : bouton « Désignations » (profils 1–2). **Non fait** : la page RC reste au
  menu (copie des RC entre saisons) ; l'action en masse « Officiels » des journées écrit encore les colonnes legacy
  directement (à retirer ou à remplacer par une copie de Désignations : à décider).
- 2026-11-23 — Décisions : action en masse « Officiels » des journées **retirée** (app4 et
  `PATCH /admin/gamedays/bulk/officials`) ; page RC **retirée** (app4 `pages/rc`, menu, `AdminRcController` et son
  test ; la copie des RC d'une saison passe dans la page Désignations, `POST /admin/operations/seasons/copy-rc`
  inchangé ; les tests utiles deviennent `DesignationRcTest`). Lignes de tournoi : « n phases » sans les noms.
  Les nouveaux composants demandent un redémarrage du serveur de dev (`make app4_dev_restart`).
