# Afficher la suspension dans la feuille de présence ou la console de scoring

Status: ready-for-human
Blocked by: 02

Spec : [FEATURE_CARD_SUSPENSIONS.md](../../../DOC/specs/FEATURE_CARD_SUSPENSIONS.md) (§ 8)

## Question

Nouveau statut « suspendu » dans la feuille de présence, alerte dans la console de scoring, ou les deux, modifiable
par les profils 1–2 seulement ? Préalable : comment compter les matchs purgés. Recommandation au § 8 de la spec.

## Comments

- 2026-11-26 — Décidé : statut « Suspendu » (`S`) dans la feuille de présence d'équipe (S16 à S19, SUS-19 à SUS-24), posé par KPI, levé par les profils 1–2 depuis la page Suspensions. Implémenté (api2 + app4). Reste à décider : décompte automatique des matchs purgés.
