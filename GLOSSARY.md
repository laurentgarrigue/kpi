# KPI — Kayak-polo

Gestion des compétitions de kayak-polo : saisons, compétitions, équipes, compositions, joueurs et matchs.

## Joueurs

**Joueur international** :
Licencié qui ne relève pas de la FFCK (licence étrangère, importée ou saisie à la main), dont l'état civil est souvent incomplet.
_Avoid_ : étranger, licence ICF

**Date de naissance inconnue** :
État d'un joueur dont la date de naissance n'est pas connue ; c'est une absence de valeur, jamais une date conventionnelle (1901-01-01, 0000-00-00).
_Avoid_ : date par défaut, date fictive

**Catégorie d'âge** :
Tranche d'âge (Poussin … Vétéran 9) d'un joueur pour une saison donnée, déduite de son année de naissance et de la saison de la compétition ; elle n'est pas déclarée et change donc d'une saison à l'autre. Un joueur dont la date de naissance est inconnue n'a pas de catégorie d'âge.
_Avoid_ : catégorie (seul), catégorie de licence
