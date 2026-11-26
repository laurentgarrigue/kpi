# Résolution des Désignations effectives d'une journée (héritage)

Status: ready-for-agent
Blocked by: 01

Spec : [FEATURE_DESIGNATIONS.md](../../../DOC/specs/FEATURE_DESIGNATIONS.md) (D9, D27)

## À faire

Service pur qui, pour une journée et un rôle, choisit la Désignation la plus précise : journée, puis compétition, puis événement (puis niveau pour le bureau CNA / membres CNA). Indique d'où vient chaque Désignation (propre ou héritée).

## Critères

DES-08, DES-09, DES-10 ; base de SUI-03.

## Comments

- 2026-11-23 — Implémenté sur `claude/spec-designations-bilan-journee`. Tests écrits mais **non exécutés** dans la session cloud (Composer ne peut pas télécharger depuis GitHub, pas de Docker) : lancer `make api2_test` avant de clore.
