# Bilan de journée

Status: ready-for-agent
Blocked by: 09

Spec : [FEATURE_DESIGNATIONS.md](../../../DOC/specs/FEATURE_DESIGNATIONS.md) (D1, D18, D25 ; § 7)

## À faire

Type de rapport `BILAN_JOURNEE` v1 : sections 1 à 9, pré-remplissage KPI (officiels effectifs, matchs, cartons), présence des officiels, critères du site dont le camping, infractions, pièces liées, délai de 3 jours.

## Critères

BIL-01 à BIL-07.

## Comments

- 2026-11-23 — Implémenté. Moteur étendu : champ `table` (colonnes, lignes pré-remplies `rowsFrom`, colonnes en
  lecture seule), champ `prefill` (« matches.planned »), règle de rédacteur `DELEGATE` (BIL-02), un rapport par
  périmètre (`uniquePerScope`, BIL-01), journée seulement hors événement (`gamedayOnlyOutsideEvent`, D1).
  `ReportKpiData` : officiels effectifs, arbitres désignés et matchs arbitrés, matchs prévus et joués (statut END),
  cartons V/J/R/D par compétition, rapports liés (BIL-07) ; figés à la soumission. `BILAN_JOURNEE` v1 dans le
  catalogue (sections 2 à 8 de § 7 ; 1 et 9 = données KPI en tête). PDF : tableaux et sections KPI. app4 :
  `ReportsTableField`, données KPI enrichies. BIL-06 (relevé des faits) : la présence est saisie et stockée ici, son
  exploitation est le ticket 12.
