# Suivi des désignations : matrice de couverture et récapitulatif

Status: ready-for-agent
Blocked by: 02, 03

Spec : [FEATURE_DESIGNATIONS.md](../../../DOC/specs/FEATURE_DESIGNATIONS.md) (D11, D26 ; § 4, § 8.3, § 8.4)

## À faire

`GET /admin/designations/coverage`, `GET /admin/designations/summary` ; page app4 Admin › Désignations (matrice, filtres, « seulement les manques », ajout/retrait depuis une case, équipes sans responsable, récapitulatif par personne et par club).

## Critères

SUI-01 à SUI-06.

## Comments

- 2026-11-23 — Implémenté. api2 : `CoverageStatus`, `CoverageHolder`, `CoverageRepository`, `CoverageService`,
  `GET /admin/designations/coverage` (`season`, `level` = national|other|all, `competitions`, `onlyGaps`) et
  `GET /admin/designations/summary` (`season`, `from`, `to`), réservés aux profils 1–2 (DROITS_PAR_PROFIL.md mis à
  jour). La case RC ne compte que les RC posés sur la compétition (pas le RC national). « Même club » = club d'une
  équipe engagée dans la compétition. Le récapitulatif compte les Désignations sur la saison et les journées
  couvertes sur la période. app4 : page `/designations` (menu Référentiels), `utils/designations.ts`,
  `components/designations/CellHolders.vue`. Vitest et ESLint d'app4 verts dans la session cloud ; tests api2 écrits,
  non exécutés.
- 2026-11-23 — Retours après essai : deux tableaux par compétition (responsables : RC et équipes ; journées × rôles),
  phases d'un tournoi (CP) regroupées par dates et lieu (désigner sur la ligne désigne sur chaque phase, retirer
  retire de chaque phase), filtres événement/groupe et compétition du contexte masqués, sélecteur de compétitions
  avec filtre section et recherche (`AdminCompetitionSectionPicker`, même source que le filtre des mandats), choix des
  rôles affichés. api2 renvoie `type` (Code_typeclt) par compétition.
