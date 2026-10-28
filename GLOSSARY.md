# KPI

Gestion des compétitions de kayak-polo de la FFCK : structures fédérales, compétitions, équipes, matchs et licenciés.

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
