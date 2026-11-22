# Exporter en PDF les valeurs de chaque catalogue de règles

Status: needs-triage
Blocked by: —

## Besoin

Le profil 1 doit pouvoir produire un **état PDF des valeurs** de chaque catalogue de règles métier d'api2, pour
les soumettre à validation (bureau CNA) ou proposer leurs évolutions d'une saison à l'autre.

Catalogues concernés (inventaire de l'ADR-0004) : joueur en règle (`PlayerEligibilityRules`), catégories d'âge
(`kp_categorie`), règles de jeu du scoring (`ScoringRules`), médailles et seuils (`MedalCatalog`,
`match_medals.yaml`), contrôle équipement (`EquipmentRules`), documents PDF (`PdfCatalog`), et les catalogues à
venir : rôles de désignation, types de rapport officiel, cumuls de cartons.

## Pistes

- Un document par catalogue, ou un document unique avec sommaire, dans le `PdfCatalog` (Gotenberg), réservé au profil 1.
- Chaque catalogue expose une description lisible de ses valeurs (méthode commune), pour que l'état PDF ne
  réimplémente pas la règle.
- Mentionner la saison ou la version du catalogue, la date d'édition et le fichier source.

## Comments
