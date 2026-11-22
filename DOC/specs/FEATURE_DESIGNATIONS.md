# Spécification - Désignations, jury d'appel, bilan de journée et rétributions

## Statut : 📝 EN CADRAGE (interrogatoire en cours, sections à compléter)

**Spec créée le** : 2026-11-22
**Décisions d'architecture** : [ADR-0002](../developer/reference/adr/0002-plus-d-evolution-du-legacy.md), [ADR-0003](../developer/reference/adr/0003-designations-source-unique-absorbe-kp-rc.md)
**Vocabulaire** : [GLOSSARY.md](../../GLOSSARY.md), section « Officiels et responsables »

---

## 1. Objectif

1. **Désigner**, en amont de la saison ou des journées, les responsables et les officiels : RC (compétition),
   responsables d'équipe (équipe et club), R1, délégués CNA, juges arbitres, arbitres non-joueurs et membres du jury d'appel
   (événement ou journée), en leur attribuant **dans le même geste** le compte et le mandat adéquats.
2. Permettre au **délégué CNA** de remplir un **bilan de journée** à la fin de la compétition ou dans les 3 jours
   qui suivent : représentants des clubs et des athlètes, bilan général (dont l'évaluation du site), bilan sportif,
   bilan arbitrage, bilan logistique, rétributions et infractions. Le bilan est complété par les données KPI
   (dates, lieu, officiels, nombre de matchs, cartons verts, jaunes et rouges, etc.).
3. Faciliter, en cours ou en fin de saison, la **synthèse des bilans** et le **calcul des rétributions**.

Périmètre technique : app4 (saisie), api2 (modèle, règles, PDF), app3 si besoin public. Aucune évolution legacy
(ADR-0002), mais le legacy doit continuer à fonctionner pendant la période de secours.

## 2. Décisions prises

| # | Sujet | Décision |
|---|---|---|
| D1 | Rattachement du bilan | Un bilan **par événement**, avec repli sur la **journée** quand elle n'appartient à aucun événement. |
| D2 | Source de vérité | Table `kp_designation` `(saison, rôle, type et id de périmètre, licence, club, autorité, ordre, qualité)`. Les champs texte de `kp_journee` en sont recopiés. |
| D3 | Rôles | Le catalogue des rôles est défini à un seul endroit (api2) : périmètres autorisés et profil du mandat généré. |
| D4 | `kp_rc` | Absorbée dans `kp_designation` (rôle `RC`, périmètre compétition), puis remplacée par une vue SQL en lecture seule pendant la période de secours. |
| D5 | Compte | Si un licencié désigné n'a pas de compte, le compte est créé automatiquement (`Code` = licence, profil de base 8), **seulement si son email est connu**. Le profil de base d'un compte existant n'est jamais modifié. |
| D6 | Mandat | Un **mandat généré par saison et par rôle**, dont le périmètre est l'union des Désignations de ce rôle. Il est recalculé à chaque création ou suppression de Désignation, et supprimé quand il n'en reste plus. |
| D7 | Personnes désignables | Toute personne de la base des joueurs, Joueurs internationaux compris. Sans email, la Désignation est enregistrée sans compte ni mandat. |
| D8 | Profils des mandats | RC → 4 ; responsable d'équipe → 7 (avec `limitation_equipe_club` = club de l'équipe) ; délégué CNA → 5 ; R1 et juge arbitre → 6 ; arbitre non-joueur → aucun mandat ; représentant des athlètes et autres membres du jury d'appel → aucun mandat. Un profil 3–4 désigné garde ses droits et bascule par le sélecteur de mandat. |
| D9 | Périmètres emboîtés | Une Désignation se pose sur l'**événement**, la **compétition** (saison) ou la **journée**. Pour une journée et un rôle donnés, la Désignation la plus précise l'emporte : journée, puis compétition, puis événement. Cas du tournoi (CP) : on désigne une fois sur la compétition (une catégorie) ou sur l'événement, et toutes les phases en héritent. On ne désigne sur une phase que pour faire une exception. |
| D10 | Périmètre du mandat | Calculé depuis les journées effectivement couvertes : `filtre_competition` pour une Désignation sur une compétition, `filtre_journee` pour un événement ou une journée. Il est recalculé quand une journée est créée, supprimée ou (dés)associée à un événement. |
| D11 | Tableaux de suivi | Vues de couverture : rôles requis non pourvus par compétition, événement, journée et équipe ; club de chaque officiel désigné ; répartition par club et par personne pour l'équilibre des désignations et les rétributions (§ 4). |
| D12 | Rapports officiels | Le bilan de journée est le premier d'une famille de **rapports officiels** au même formalisme (formulaire app4 pré-rempli depuis KPI, PDF Gotenberg, rôle requis, destinataires) : rapport d'incident (arbitre), rapport de jury d'appel, fiche d'évaluation d'arbitrage. Le rôle requis et les destinataires sont définis par type de rapport. |
| D13 | Catalogue des types de rapport | Défini **en code** dans api2 et **versionné** ([ADR-0004](../developer/reference/adr/0004-regles-metier-en-catalogues-api2.md)) : champs et sections, champs calculés et pré-remplis, rattachements possibles, rédacteurs, destinataires. Les réponses sont stockées en JSON avec la version du modèle. Le constructeur des formulaires d'inscription n'est pas réutilisé. |
| D14 | Rapport d'incident | Rédigé par **n'importe quel officiel**. Rattaché à une compétition, un événement, une journée ou un match. |
| D15 | Officiel | Est **officiel** d'un périmètre : toute personne qui y a une Désignation (y compris héritée, D9), tout arbitre désigné d'un match de ce périmètre, et les profils 1 et 2. Les responsables d'équipe et la table de marque n'en sont pas. |
| D16 | Bureau CNA | `BUREAU_CNA` est un rôle de Désignation, sur un périmètre **niveau** : toutes les compétitions nationales de la saison. Mandat généré de profil 2, **sauf si le profil de base est déjà 1 ou 2** (cas habituel). |
| D17 | Rapport d'un arbitre sans compte | Pas de compte ni de lien de rédaction (pour commencer) : chaque type de rapport fournit un **PDF vierge** à remplir à la main ou sur ordinateur, sans connexion. Un officiel le **saisit dans KPI** : l'auteur enregistré est l'arbitre, avec la mention « saisi par » l'officiel. Le PDF vierge est généré depuis la définition du catalogue (D13), pour rester identique au formulaire. |
| D18 | Cycle de vie d'un rapport | **Brouillon** (visible du rédacteur et des profils 1–2) → **Soumis** (figé, PDF généré, destinataires notifiés). Seul un profil 1–2 peut **rouvrir** un rapport soumis, avec un motif journalisé ; le PDF précédent est conservé. Pas d'état « validé ». Le délai (3 jours après la fin pour le bilan de journée) est indicatif : au-delà, le rapport est marqué « hors délai ». Les destinataires sont résolus depuis les Désignations à la soumission. Bilan de journée : bureau CNA, RC des compétitions de l'événement, copie au rédacteur. |
| D19 | Rétributions et pénalités | L'outillage **prépare** le calcul (annexe 8 du règlement sportif) sans le faire : un **relevé des faits par club et par saison** (quantités, jamais de montants), exportable, avec le détail de chaque fait et sa source (§ 6). |
| D20 | Classement national des clubs | Les Désignations alimentent le classement national des clubs (branche `claude/classement-national-clubs`, [PAGE_CLASSEMENT_NATIONAL_CLUBS.md](PAGE_CLASSEMENT_NATIONAL_CLUBS.md) une fois fusionnée) : 7.3.1 (délégué CNA, juge arbitre), 7.3.2 (RC) et 7.3.4 (autre membre de la CNA) sont lus dans `kp_designation` au lieu des noms en texte libre, de `kp_rc` et des listes saisies à la main. |
| D21 | Rôles ajoutés | `MEMBRE_CNA` (périmètre niveau national, pour 7.3.4 ; le bureau CNA en est un sous-ensemble) et `ARBITRE_NJ` (arbitre non-joueur, sur une journée ou un événement, pour la prime de l'annexe 8 § 1). Le rôle s'appelle `JUGE_ARBITRE` (« chef des arbitres » est l'ancienne appellation, colonne legacy `kp_journee.ChefArbitre`). |
| D22 | Club fournisseur | Le **club** de l'officiel est **figé dans la Désignation** à sa création (pré-rempli depuis le PCE, modifiable par les profils 1–2). Le relevé des faits et le classement national des clubs l'utilisent tous les deux. Les deux se calculent **pendant la saison**, pas la saison suivante. |
| D23 | Autorité de désignation | Chaque Désignation porte qui l'a décidée : `CNA` (par défaut) ou `ORGANISATEUR`. Pour `ARBITRE_NJ` : désigné par le club organisateur → rétribution au club fournisseur ; désigné par la CNA (pool arbitres) → budget CNA, pas de rétribution au club. |
| D24 | Ordre de livraison | 1. Fusion du classement national des clubs tel quel. 2. Désignations. 3. Bascule des calculateurs 7.3.1, 7.3.2, 7.3.4 sur `kp_designation`, la table d'alias restant pour les saisons antérieures. |
| D25 | Contenu du bilan de journée | Structure validée le 2026-11-22, § 7. |
| D26 | Rôles requis et états du suivi | Exigences par niveau dans le catalogue des rôles (nationales = codes `N…`, `CF…`) : RC requis partout ; responsable d'équipe, délégué CNA, R1 et juge arbitre requis en nationale (par équipe ou par journée, héritage compris) ; le reste facultatif. États : **rouge** = rôle requis non pourvu ; **orange** = pourvu par une personne sans email ou du même club qu'une équipe engagée ; **vert** = pourvu. Détail § 4. |
| D27 | Jury d'appel | Composition par défaut (journées nationales) : délégué CNA (président de droit) + R1, repris de leurs Désignations, + un **représentant des équipes** (rôle `REP_EQUIPES`, alimente la colonne legacy `Rep_athletes`). En finale, des Désignations `JURY_APPEL` avec qualité (« représentant FFCK », « membre du bureau CNA »…) complètent le jury. Le rapport de jury d'appel reprend la composition et peut la **modifier pour l'affaire** (absence, récusation), dans le rapport seulement. Le délégué CNA peut créer les Désignations `REP_EQUIPES` et `JURY_APPEL` de son périmètre (sans mandat). Le **représentant des clubs** n'existe que dans le bilan de journée et ne siège pas au jury. |
| D28 | Rédacteurs et destinataires des rapports | **Incident** : tout officiel du périmètre (ou saisi pour un arbitre, D17) → délégué CNA et juge arbitre du périmètre, RC des compétitions concernées, copie au rédacteur. **Jury d'appel** : président du jury (délégué CNA) → bureau CNA, RC concernés, membres du jury, copie au rédacteur ; notification aux responsables des équipes en cause ; date et heure d'affichage sur site. **Évaluation d'arbitrage** : saisie par l'évaluateur s'il est déjà officiel du périmètre, sinon par le bureau CNA (pas de rôle évaluateur pour l'instant) → juge arbitre du périmètre, bureau CNA, copie au rédacteur ; **pas de copie à l'arbitre évalué** ; lecture réservée à ces personnes ; note sur 50 calculée (somme des 5 blocs sur 10). |

## 3. Questions ouvertes

- PDF vierge « remplissable sur ordinateur » (D17) : Chromium/Gotenberg ne produit pas de champs de formulaire PDF ; à évaluer (post-traitement, ou PDF imprimable seulement).

**Hors périmètre** (tickets créés le 2026-11-22) : états PDF des catalogues (`.scratch/catalogues-pdf/`), écrans de
scoring autonomes (`.scratch/scoring-ecrans-autonomes/`), cumuls de cartons et notifications (`.scratch/cumuls-cartons/`).

## 4. Suivi des désignations (app4)

À détailler. Intentions validées (D11) :

- **Matrice de couverture** : en lignes les compétitions, puis les événements et les journées (héritage visible : une
  case pourvue par héritage est distinguée d'une case pourvue en propre) ; en colonnes les rôles ; une case vide sur
  un rôle requis est signalée. Filtres : saison, niveau, compétition, « seulement les manques ».
- **Équipes sans responsable désigné**, par compétition.
- **Club de chaque officiel désigné** affiché dans la matrice, pour repérer les conflits d'intérêts (officiel du même
  club qu'une équipe engagée) et l'équilibre entre clubs.
- **Rôles requis** (D26) :

  | Rôle | Nationales (`N…`, `CF…`) | Régionales et autres |
  |---|---|---|
  | RC | Requis par compétition | Requis par compétition |
  | Responsable d'équipe | Requis par équipe engagée | Facultatif |
  | Délégué CNA | Requis par journée (héritage compris) | Sans objet |
  | R1 | Requis par journée | Facultatif |
  | Juge arbitre | Requis par journée | Facultatif |
  | Arbitre non-joueur | Facultatif | Facultatif |
  | Représentant des équipes | Facultatif (souvent désigné sur place) | Facultatif |
  | Autres membres du jury d'appel | Facultatif, sauf finales | Sans objet |

- **États** : rouge (requis non pourvu), orange (pourvu sans email, ou officiel du même club qu'une équipe engagée),
  vert (pourvu).
- **Récapitulatif par personne et par club** : nombre de désignations et de journées couvertes, en cours de saison et
  en fin de saison ; il alimente le calcul des rétributions.

## 5. Rapports officiels

Exemples fournis par le métier (2026-11-22) : rapport de jury d'appel, rapport d'incident disciplinaire, fiche
d'évaluation d'arbitrage (5 blocs notés sur 10, note finale sur 50, aptitudes et niveau recommandé).

| Rapport | Rédacteur | Rattachement | Données KPI pré-remplies |
|---|---|---|---|
| Bilan de journée | Délégué CNA | Événement (ou journée) | Dates, lieu, officiels désignés, nombre de matchs, cartons |
| Rapport d'incident | Tout officiel (D14) | Compétition, événement, journée ou match | Selon le rattachement ; pour un match : équipes, arbitres et leurs clubs, cartons |
| Rapport de jury d'appel | Président du jury (délégué CNA) | Événement ou journée, matchs concernés | Composition du jury, journée, dates, lieu, matchs, arbitres et leurs clubs |
| Fiche d'évaluation d'arbitrage | Évaluateur déjà officiel du périmètre, sinon bureau CNA (D28) | Match + arbitre évalué | Saison, compétition, journée, match, rencontre, scores, arbitre secondaire |

Destinataires : D28. Cycle de vie : D18.

## 6. Relevé des faits pour les rétributions et pénalités (annexe 8)

Source : annexes 2026 au règlement sportif, annexe 8 « Rétributions, pénalités et sanctions ». Le relevé donne des
**quantités par club**, que la CNA valorise elle-même avec le barème en vigueur.

| Fait relevé (par club, par saison) | Source KPI |
|---|---|
| Journées de compétition nationale accueillies, par division, et nombre de terrains | `kp_journee` (`Code_organisateur`, compétition), terrains des matchs |
| Organisation d'un podium (N2, NEM) | Bilan de journée |
| Finales accueillies (championnat, Coupe de France, play-off, play-down) et nombre de terrains | `kp_journee`, phases finales |
| RC fournis, par catégorie (championnat ; Coupe de France, U21, play-down) | Désignations `RC` + club du licencié |
| Délégués CNA, juges arbitres, arbitres non-joueurs désignés par l'organisateur (D23) fournis, par journée | Désignations `DELEGUE_CNA`, `JUGE_ARBITRE`, `ARBITRE_NJ`, confirmées par le bilan de journée (présence effective) |
| Joueurs ajoutés hors délai sur une feuille de présence | Journal des feuilles de présence (à vérifier) |
| Cartons rouges et jaunes pris par des joueurs | Timeline des matchs validés |
| Avertissements pour tenue d'arbitre non conforme, par arbitre | Bilan arbitrage (champ structuré par arbitre) |
| Infractions par catégorie (RP KAP 59) | Bilans de journée, rapports d'incident et de jury d'appel |

Les frais d'accueil des officiels (nuits, repas) restent hors KPI.

## 7. Contenu du bilan de journée (D25)

**[KPI]** = pré-rempli, non modifiable ; **[KPI✎]** = pré-rempli, corrigeable par le délégué.

| # | Section | Contenu |
|---|---|---|
| 1 | En-tête [KPI] | Événement ou journée, compétitions, dates, lieu, plan d'eau, club organisateur, nombre de terrains |
| 2 | Officiels [KPI✎] | Désignations effectives (héritage D9) : délégué CNA, R1, juge arbitre, arbitres non-joueurs (avec l'autorité D23), membres du jury d'appel ; pour chacun **présent / absent / remplacé par…** (alimente le relevé des faits, § 6) |
| 3 | Représentants | Représentant des clubs, représentant des athlètes (licenciés), remarques |
| 4 | Bilan général | Évaluation du site, critères notés 1 à 4 : plan d'eau, terrains et buts, vestiaires et sanitaires, **camping disponible pour les équipes**, table de marque et sono, affichage des résultats, accueil et restauration, sécurité et secours, hébergement et repas des officiels ; commentaire ; organisation d'un podium (N2, NEM) |
| 5 | Bilan sportif [KPI✎] | Matchs prévus et joués, forfaits, retards, équipes présentes ou absentes, commentaire |
| 6 | Bilan arbitrage | [KPI] cartons verts, jaunes, rouges, noirs par compétition ; par arbitre désigné : matchs arbitrés [KPI], avertissement tenue non conforme (annexe 8 § 2.3), observation ; commentaire |
| 7 | Bilan logistique | Horaires tenus, scoring et feuilles de match, matériel, commentaire |
| 8 | Rétributions et infractions | Faits pour le relevé (terrains utilisés, finale, podium) ; infractions constatées (club ou licencié, catégorie 1 à 6 RP KAP 59, description, lien vers un rapport) ; joueurs ajoutés hors délai [KPI si disponible] |
| 9 | Pièces liées [KPI] | Rapports d'incident et de jury d'appel rattachés au même périmètre |

Pour le camping, on note sa **disponibilité** (oui / non) en plus de la note de 1 à 4.
