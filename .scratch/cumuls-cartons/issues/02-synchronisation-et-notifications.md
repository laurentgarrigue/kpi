# Enregistrement, notifications et brouillon de jury d'appel

Status: ready-for-agent
Blocked by: 01

Spec : [FEATURE_CARD_SUSPENSIONS.md](../../../DOC/specs/FEATURE_CARD_SUSPENSIONS.md) (S9 à S14, § 4 à § 6)

## À faire

Table `kp_card_suspension`, commande `app:card-suspensions:sync [--season] [--silent]` (cron 10 min), e-mails de
suspension et d'annulation au bureau CNA et aux RC (repli : adresse d'administration), brouillon `JURY_APPEL`,
`GET /admin/card-suspensions`.

## Critères

SUS-11 à SUS-17.

## Comments
