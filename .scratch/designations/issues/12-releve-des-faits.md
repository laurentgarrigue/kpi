# Relevé des faits pour les rétributions et pénalités

Status: ready-for-agent
Blocked by: 10

Spec : [FEATURE_DESIGNATIONS.md](../../../DOC/specs/FEATURE_DESIGNATIONS.md) (D19, D22, D23 ; § 6)

## À faire

`GET /admin/designations/facts` + export CSV / Excel ; onglet app4 dans Admin › Désignations.

## Critères

REL-01 à REL-05.

## Comments

- 2026-11-23 — Implémenté. api2 : `src/Designation/Facts/` (`Fact`, `FactsAggregator` pur, `FactsCollector`),
  `GET /admin/designations/facts?season=&format=json|csv|xlsx` (profils 1–2). Faits, compétitions nationales :
  journées accueillies et terrains (organisateur de la journée, terrains distincts des matchs, sinon terrains du
  bilan), finale et podium (bilan soumis), RC fournis par compétition, délégués CNA / juges arbitres / arbitres
  non-joueurs de l'organisateur fournis par journée (moins les absents ou remplacés du bilan soumis, BIL-06),
  cartons J/R/D des matchs validés au club de l'équipe, avertissements de tenue (club de la licence), infractions des
  bilans (club reconnu par code ou libellé, sinon « à attribuer »). **Non relevé** : joueurs ajoutés hors délai (aucune
  donnée fiable, le bilan le note en texte libre). app4 : onglet « Relevé des faits » de la page Désignations, filtre
  par club, sources dépliables, export CSV et Excel.
