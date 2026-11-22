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

## Matchs

**Timeline** :
Suite des faits de match d'un match (buts et cartons, `kp_match_detail`), chacun avec sa période et le temps restant au chrono. Un temps `00:00:00` est un temps non saisi.
_Avoid_ : historique, déroulé (seul)

**Médaille** :
Distinction attribuée par un match validé à une équipe, un joueur ou un arbitre désigné, déduite de la timeline selon une règle du catalogue (FEATURE_MATCH_MEDALS.md). Rien n'est saisi pour l'obtenir.
_Avoid_ : badge, trophée ; ne pas confondre avec les médailles du classement (podium)

**Exclusion** :
Infériorité numérique provoquée par un carton : 2 minutes de jeu levées au premier but adverse, ou jusqu'à la fin du match pour un rouge définitif.
_Avoid_ : pénalité (seul)

**Arbitre désigné** :
Arbitre principal ou secondaire d'un match identifié par son numéro de licence, et non par un simple libellé saisi à la main. Seuls les arbitres désignés comptent dans les statistiques d'arbitrage.

## Officiels et responsables

**Désignation** :
Attribution nominative d'un rôle (RC, responsable d'équipe, R1, délégué CNA, membre du jury d'appel…) à un licencié, sur un périmètre (compétition, équipe, événement ou journée) et pour une saison. C'est la seule source de vérité de « qui tient quel rôle » ([ADR-0003](DOC/developer/reference/adr/0003-designations-source-unique-absorbe-kp-rc.md)).
_Avoid_ : nomination, affectation, officiel saisi

**Catalogue des rôles** :
Liste unique, définie dans api2, des rôles désignables, avec pour chacun les types de périmètre autorisés et le profil du mandat généré.

**Officiel** :
Personne qui a une Désignation sur un périmètre (héritée ou non), ou arbitre désigné d'un match de ce périmètre. Les profils 1 et 2 sont officiels partout. Un responsable d'équipe n'est pas un officiel.

**Bureau CNA** :
Membres du bureau de la commission nationale d'activité, désignés pour une saison sur toutes les compétitions nationales.
_Avoid_ : bureau CNAKP (libellé du profil 2)

**Délégué CNA** :
Officiel désigné par la commission nationale d'activité sur un événement ou une journée. Il préside le jury d'appel et rédige le bilan de journée.
_Avoid_ : délégué fédéral (libellé du profil 5), délégué (seul)

**Jury d'appel** :
Instance qui statue sur les réclamations d'un événement ou d'une journée. Elle est composée de Désignations : le délégué CNA (président), le R1, un représentant des équipes ou des athlètes et, pour les finales, des représentants des instances fédérales.

**Rapport officiel** :
Document rédigé dans app4 par un officiel désigné ou un arbitre (bilan de journée, rapport d'incident, rapport de jury d'appel, fiche d'évaluation d'arbitrage), pré-rempli avec les données KPI et exporté en PDF.
_Avoid_ : formulaire (réservé aux formulaires d'inscription publics, FEATURE_FORMS.md)

**Bilan de journée** :
Rapport officiel du délégué CNA sur un événement, ou sur une journée hors événement, rédigé au plus tard 3 jours après la fin.

**Juge arbitre** :
Officiel désigné qui dirige le corps arbitral d'un événement ou d'une journée.
_Avoid_ : chef des arbitres, chef arbitre (anciennes appellations)

**Arbitre non-joueur** :
Arbitre désigné sur une journée sans être joueur d'une équipe engagée, soit par le club organisateur (rétribué au club), soit par la CNA depuis le pool arbitres (budget CNA).

**Relevé des faits** :
Liste, par club et par saison, des faits qui ouvrent droit à une rétribution ou à une pénalité (annexe 8), en quantités et sans montant.
