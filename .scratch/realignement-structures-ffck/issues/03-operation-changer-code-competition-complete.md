# 03: Opération « changer un code compétition » complète

**What to build:** quand un administrateur renomme un code compétition depuis l'administration (toutes saisons ou saison active), toutes les références au code suivent, et plus seulement les compétitions, engagements, journées et filtres utilisateur. Voir la spec, section « Opération changer un code compétition ». Le contrat HTTP de l'endpoint ne change pas.

**Blocked by:** None (can start immediately)

**Status:** resolved

- [x] Les responsables de compétition (RC) suivent le renommage
- [x] Le code de référence des compétitions qui valait l'ancien code prend le nouveau
- [x] La liste des compétitions agrégées des compétitions multi-compétitions est mise à jour
- [x] Le filtre de compétitions des mandats utilisateur est mis à jour
- [x] En saison active, seules les références de la saison active changent (RC, compétitions agrégées) ; en toutes saisons, toutes
- [x] Le renommage reste atomique : un échec ne laisse aucune référence à moitié renommée
- [x] Test d'intégration de l'endpoint (requête authentifiée profil 1, comptes des fixtures) couvrant ces critères dans les deux modes
- [x] La suite de tests api2 et PHPStan passent
