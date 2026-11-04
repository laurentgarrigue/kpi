# KPI

Gestion des compétitions de kayak-polo de la FFCK : structures fédérales, saisons, compétitions, équipes, compositions, joueurs et matchs.

## Structures fédérales

**PCE**:
Extraction quotidienne de la base des licenciés de la FFCK. C'est la seule source de vérité pour les licenciés, les CR, les CD et les Clubs.
_Avoid_: base fédérale, fichier FFCK

**CR (Comité Régional)**:
Structure fédérale régionale issue du découpage de 2016 (13 régions métropolitaines et l'outre-mer), identifiée par son Code CR (`CR01` à `CR28`). `CR99` désigne la Ligue fédérale et `98` regroupe les structures étrangères.
_Avoid_: ligue, région

**Ancienne ligue**:
Structure régionale antérieure au découpage de 2016 (ex. Ligue Rhône-Alpes, ancien code `CR18`). Elle n'existe plus dans le PCE, et sa numérotation chevauche celle des CR actuels.
_Avoid_: ancien CR

**CD (Comité Départemental)**:
Structure fédérale départementale, rattachée à exactement un CR.
_Avoid_: comité dép

**Club**:
Structure affiliée qui délivre des licences, rattachée à exactement un CD. On garde un Club absent du PCE tant que l'historique le référence.

**Sélection régionale**:
Pseudo-club unique par CR, codé avec le Code CR, qui porte les équipes représentant une région. Il remplace les pseudo-clubs des Anciennes ligues.
_Avoid_: club ligue, pseudo-club ligue

## Compétitions

**Compétition régionale**:
Compétition de niveau `REG`. Son code (préfixe `REGxx` ou `T-Rxx`) contient le numéro du CR organisateur, et ce code reste le même sur toutes les saisons.

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
