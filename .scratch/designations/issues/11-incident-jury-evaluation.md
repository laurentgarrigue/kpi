# Rapports d'incident, de jury d'appel et fiche d'évaluation d'arbitrage

Status: ready-for-agent
Blocked by: 09

Spec : [FEATURE_DESIGNATIONS.md](../../../DOC/specs/FEATURE_DESIGNATIONS.md) (D14, D17, D27, D28)

## À faire

Types `INCIDENT`, `JURY_APPEL`, `EVALUATION_ARBITRAGE` v1 ; composition du jury modifiable dans le rapport ; note sur 50 calculée ; lecture réservée de l'évaluation.

## Critères

RAP-04, RAP-08, RAP-09.

## Comments

- 2026-11-23 — Implémenté. `JURY_APPEL` v1 (président = délégué CNA, composition `rowsFrom: jury` reprise des
  Désignations et modifiable pour l'affaire dans le rapport seulement, motif, matchs concernés, faits, décisions,
  affichage sur site) ; destinataires : bureau CNA, RC, membres du jury présents (`JURY`), responsables des équipes
  des matchs cités (`TEAMS_IN_CAUSE`, par n° de match du périmètre), rédacteur. `EVALUATION_ARBITRAGE` v1 (match,
  arbitre évalué pré-rempli, 20 critères en 5 blocs /10 calculés, note /50 calculée, aptitudes), lecture réservée
  (destinataires : juge arbitre du périmètre, bureau CNA, rédacteur ; pas l'arbitre évalué). `INCIDENT` v1 du ticket
  09 inchangé. Point d'entrée « Rapport » sur chaque match de la page des matchs (profils ≤ 5).
