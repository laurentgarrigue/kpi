# Spécification - Désignations, jury d'appel, bilan de journée et rétributions

## Statut : 📝 SPÉCIFIÉE (2026-11-22) — à valider, découpage en tickets à faire

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
| D4 | `kp_rc` | Absorbée dans `kp_designation` (rôle `RC`, périmètre compétition ; un RC « national sans compétition », ex `- CNA -`, devient un RC sur le **niveau national**), puis remplacée par une vue SQL en lecture seule pendant la période de secours (l'ancienne table est gardée sous le nom `kp_rc_avant_designations`). |
| D5 | Compte | Si un licencié désigné n'a pas de compte, le compte est créé automatiquement (`Code` = licence, profil de base 8, **limité aux compétitions de la Désignation** et à sa saison, jamais à toutes), **seulement si son email est connu**. La base des licences n'ayant pas d'email, il est saisi avec la Désignation (`email`, facultatif) ; il ne sert qu'à créer le compte. Le profil de base ni les filtres d'un compte existant ne sont jamais modifiés ; une Désignation ultérieure ne les élargit que par le mandat généré (D6). |
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
| D22 | Club fournisseur | Le **club** de l'officiel est **figé dans la Désignation** à sa création (pré-rempli depuis le PCE, modifiable par les profils 1 à 3). Le relevé des faits et le classement national des clubs l'utilisent tous les deux. Les deux se calculent **pendant la saison**, pas la saison suivante. |
| D23 | Autorité de désignation | Chaque Désignation porte qui l'a décidée : `CNA` (par défaut) ou `ORGANISATEUR`. Pour `ARBITRE_NJ` : désigné par le club organisateur → rétribution au club fournisseur ; désigné par la CNA (pool arbitres) → budget CNA, pas de rétribution au club. |
| D24 | Ordre de livraison | 1. Fusion du classement national des clubs tel quel. 2. Désignations. 3. Bascule des calculateurs 7.3.1, 7.3.2, 7.3.4 sur `kp_designation`, la table d'alias restant pour les saisons antérieures. |
| D25 | Contenu du bilan de journée | Structure validée le 2026-11-22, § 7. |
| D26 | Rôles requis et états du suivi | Exigences par niveau dans le catalogue des rôles (nationales = codes `N…`, `CF…`) : RC requis partout ; responsable d'équipe, délégué CNA, R1 et juge arbitre requis en nationale (par équipe ou par journée, héritage compris) ; le reste facultatif. États : **rouge** = rôle requis non pourvu ; **orange** = pourvu par une personne sans email ; **vert** = pourvu (y compris par une personne du même club qu'une équipe engagée, signalée par le triangle d'alerte sans changer la couleur). Détail § 4. |
| D27 | Jury d'appel | Composition par défaut (journées nationales) : délégué CNA (président de droit) + R1, repris de leurs Désignations, + un **représentant des équipes** (rôle `REP_EQUIPES`, alimente la colonne legacy `Rep_athletes`). En finale, des Désignations `JURY_APPEL` avec qualité (« représentant FFCK », « membre du bureau CNA »…) complètent le jury. Le rapport de jury d'appel reprend la composition et peut la **modifier pour l'affaire** (absence, récusation), dans le rapport seulement. Le délégué CNA peut créer les Désignations `REP_EQUIPES` et `JURY_APPEL` de son périmètre (sans mandat). Le **représentant des clubs** n'existe que dans le bilan de journée et ne siège pas au jury. |
| D28 | Rédacteurs et destinataires des rapports | **Incident** : tout officiel du périmètre (ou saisi pour un arbitre, D17) → délégué CNA et juge arbitre du périmètre, RC des compétitions concernées, copie au rédacteur. **Jury d'appel** : président du jury (délégué CNA) → bureau CNA, RC concernés, membres du jury, copie au rédacteur ; notification aux responsables des équipes en cause ; date et heure d'affichage sur site. **Évaluation d'arbitrage** : saisie par l'évaluateur s'il est déjà officiel du périmètre, sinon par le bureau CNA (pas de rôle évaluateur pour l'instant) → juge arbitre du périmètre, bureau CNA, copie au rédacteur ; **pas de copie à l'arbitre évalué** ; lecture réservée à ces personnes ; note sur 50 calculée (somme des 5 blocs sur 10). |
| D29 | PDF vierge | Première version : PDF vierge **imprimable** seulement, généré par Gotenberg depuis le catalogue (annotation possible dans un lecteur PDF). Pas de PDF à champs ni de `.docx` tant que le terrain ne le demande pas. |
| D30 | Reprise des saisies legacy | Deux sources coexistaient pour les officiels d'une journée : le texte des colonnes legacy de `kp_journee` (saisi avant les Désignations) et `kp_designation`. Les Désignations deviennent la source ; la commande `app:designations:backfill-legacy --season=AAAA [--apply]` (simulation par défaut) reprend en Désignations ce qui est lisible (licence entre parenthèses), sans jamais toucher à un rôle déjà désigné, et liste le reste. Le RC (`Responsable_insc`) est repris sur la compétition quand toutes ses journées désignent la même licence. Le club organisateur est identifié partout comme le fait le classement national des clubs : `Code_organisateur` d'abord, sinon le libellé (`OrganiserClubResolver`) ; le formulaire de journée et la copie de compétition enregistrent désormais le code et pas seulement le libellé. **Ordre : la bascule du classement (D24 étape 3) ne se fait qu'après la reprise de la saison concernée** ; tant qu'elle n'est pas faite, le classement continue de lire les colonnes legacy, qui restent recopiées depuis les Désignations. |

## 3. Questions ouvertes

Aucune question de cadrage ouverte. Reste à faire : relecture des critères (§ 9) et
découpage en tickets.

**Hors périmètre** (tickets créés le 2026-11-22) : états PDF des catalogues (`.scratch/catalogues-pdf/`), écrans de
scoring autonomes (`.scratch/scoring-ecrans-autonomes/`), cumuls de cartons et notifications (`.scratch/cumuls-cartons/`, [FEATURE_CARD_SUSPENSIONS.md](FEATURE_CARD_SUSPENSIONS.md)).

## 4. Suivi des désignations (app4)

À détailler. Intentions validées (D11) :

- **Matrice de couverture** : en lignes les compétitions (dans l'ordre du site public : section et ordre du groupe, tour,
  ordre dans le groupe), puis les événements et les journées (héritage visible : une
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

- **États** : rouge (requis non pourvu), orange (pourvu sans email),
  vert (pourvu ; l'officiel du même club qu'une équipe engagée reste vert, avec le triangle et le message d'alerte).
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
| Cartons jaunes, rouges et noirs pris par des joueurs, **un type de fait par couleur** (pénalités différentes) | Timeline des matchs validés |
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

## 8. Modèle de données et API (esquisse)

### 8.1 Tables

| Table | Colonnes principales | Remarques |
|---|---|---|
| `kp_designation` | `id`, `saison`, `role`, `perimetre_type` (`NIVEAU`, `COMPETITION`, `EQUIPE`, `EVENEMENT`, `JOURNEE`), `perimetre_id`, `matric`, `club` (figé, D22), `autorite` (`CNA`, `ORGANISATEUR`), `ordre`, `qualite`, `created_by`, `created_at` | Unicité `(saison, role, perimetre_type, perimetre_id, matric)` |
| `kp_user_mandat` | + `designation_role`, `designation_saison` (nullable) | Un mandat généré par `(utilisateur, saison, rôle)` (D6) ; un mandat sans ces colonnes est un mandat saisi à la main, jamais touché |
| `kp_report` | `id`, `type`, `version_modele`, `perimetre_type` (`COMPETITION`, `EVENEMENT`, `JOURNEE`, `MATCH`), `perimetre_id`, `saison`, `auteur_matric`, `saisi_par` (code utilisateur), `etat` (`BROUILLON`, `SOUMIS`), `soumis_le`, `hors_delai`, `reponses` (JSON) | Un rapport rouvert repasse en `BROUILLON` |
| `kp_report_submission` | `report_id`, `numero`, `version_modele`, `reponses`, `donnees_kpi`, `destinataires`, `submitted_at`, `submitted_by` | Une ligne figée par soumission ; son PDF est rendu à la demande depuis elle, donc chaque PDF soumis reste disponible après une réouverture (D18) |
| vue `kp_rc` | `Id`, `Code_competition`, `Code_saison`, `Ordre`, `Matric` | Lecture seule sur `kp_designation` rôle `RC` (D4) |

### 8.2 Catalogues (api2, ADR-0004)

- `App\Designation\RoleCatalog` : pour chaque rôle, périmètres autorisés, profil du mandat (ou aucun), exigence par
  niveau (D26), qui peut le désigner (profils 1 à 3, le bureau CNA restant aux profils 1–2 car son mandat est de profil 2 ; délégué CNA pour `REP_EQUIPES` et `JURY_APPEL`, D27), colonne
  legacy recopiée (`Delegue`, `Responsable_R1`, `ChefArbitre`, `Rep_athletes`, `Arb_nj1…5`).
- `App\Report\ReportTypeCatalog` : par type et par version, sections, champs, champs calculés et pré-remplis,
  rattachements, rédacteurs, destinataires, lecteurs, délai.

### 8.3 Endpoints (api2)

| Endpoint | Rôle |
|---|---|
| `GET/POST/PATCH/DELETE /admin/designations` | CRUD (filtres saison, rôle, périmètre, personne, club) |
| `GET /admin/designations/coverage` | Matrice de couverture (D11, D26) |
| `GET /admin/designations/summary` | Récapitulatif par personne et par club |
| `GET /admin/designations/roles` | Catalogue des rôles actif (app4 ne le réimplémente pas) |
| `GET /admin/reports/types` | Catalogue des types de rapport |
| `GET/POST /admin/reports`, `GET/PATCH /admin/reports/{id}`, `POST /admin/reports/{id}/submit`, `POST /admin/reports/{id}/reopen` | Rapports et cycle de vie |
| `GET /admin/reports/prefill?scopeType=&scopeId=&season=` | Données KPI du périmètre |
| `GET /admin/reports/{id}/pdf?number=`, `GET /admin/reports/blank/{type}?season=` | PDF d'une soumission (ou du brouillon), PDF vierge (D29) |
| `GET /admin/designations/facts` | Relevé des faits par club (D19), export CSV / Excel |

### 8.4 Écrans app4

- **Admin › Désignations** : matrice de couverture (filtres saison, niveau, compétition, « seulement les manques »),
  ajout ou retrait d'une Désignation depuis une case, récapitulatif par personne et par club, relevé des faits.
- **Compétitions, Événements, Journées, Équipes** : bloc « Désignations » du périmètre (remplace l'onglet RC et la
  section Officiels de la modale journée, en lecture des champs legacy).
- **Rapports** : liste filtrable (type, état, saison, périmètre, hors délai), formulaire, aperçu PDF ; accès
  « Mes rapports » pour un officiel.

## 9. Critères d'acceptation

Les tests portent le code du critère dans leur nom. « National » = compétition dont le code commence par `N` ou `CF`.

**Désignations et catalogue des rôles**

- **DES-01** — Étant donné le rôle `DELEGUE_CNA`, quand on le désigne sur une équipe, alors `422` (périmètre non
  autorisé par le catalogue).
- **DES-02** — Étant donné une Désignation, alors elle porte une licence existante ; un nom sans licence est refusé.
- **DES-03** — Étant donné la même personne, le même rôle et le même périmètre, quand on la désigne deux fois, alors
  `409`.
- **DES-04** — Étant donné un licencié du club A au PCE, quand on le désigne, alors `club = A` ; quand le PCE le passe
  ensuite au club B, alors la Désignation garde A ; un profil 1–2 peut corriger le club.
- **DES-05** — Étant donné une Désignation sans autorité précisée, alors `autorite = CNA`.
- **DES-06** — Étant donné un utilisateur de profil 3 à 10, quand il crée une Désignation, alors `403`, sauf
  DES-07.
- **DES-07** — Étant donné le délégué CNA désigné sur un événement, quand il désigne un `REP_EQUIPES` ou un
  `JURY_APPEL` sur cet événement ou l'une de ses journées, alors c'est accepté ; sur un autre périmètre, `403`.
- **DES-08** — Étant donné une Désignation `DELEGUE_CNA` sur un événement, alors chacune de ses journées a ce
  délégué ; étant donné en plus une Désignation sur une de ses journées, alors cette journée a le délégué de la
  journée (D9).
- **DES-09** — Étant donné un délégué désigné sur une compétition et un autre sur l'événement qui la contient, alors
  les journées de cette compétition ont le délégué de la compétition.
- **DES-10** — Étant donné une compétition de type CP désignée une fois, quand on ajoute une phase, alors la
  nouvelle phase hérite des Désignations sans action.
- **DES-11** — Étant donné une modification de Désignation qui touche une journée, alors les colonnes legacy de
  `kp_journee` (`Delegue`, `Responsable_R1`, `ChefArbitre`, `Rep_athletes`, `Arb_nj1…5`, `Responsable_insc`) valent
  « NOM Prénom (licence) » (format legacy) des Désignations effectives de la journée, pour les **rôles touchés** par
  le changement ; la colonne d'un rôle qu'aucune Désignation ne touche reste telle quelle (transition, ADR-0002). La
  création d'une phase et la (dés)association à un événement recopient aussi les rôles hérités.

**Absorption de `kp_rc` et legacy (D4, ADR-0002)**

- **RC-01** — Étant donné les lignes de `kp_rc` avant migration, alors chacune devient une Désignation `RC` sur la
  compétition, avec la même saison, le même ordre et la même licence, et le club du licencié au PCE.
- **RC-02** — Après migration, `SELECT * FROM kp_rc` rend les mêmes lignes qu'avant (vue).
- **RC-03** — Après migration, les pages legacy `GestionRc`, `GestionCompetition`, `GestionParamJournee`,
  `GestionInstances`, `GestionOperations` s'affichent sans erreur.
- **RC-04** — Les écrans et endpoints api2 qui lisaient `kp_rc` (page RC, compétitions, renommage de code,
  opérations de saison) donnent le même résultat qu'avant à partir de `kp_designation`.

**Comptes et mandats générés (D5, D6, D8, D10, D16)**

- **MAN-01** — Étant donné un licencié sans compte, quand on le désigne sur un rôle à mandat en donnant son email,
  alors un compte `Code = licence`, profil 8, est créé et un e-mail d'activation est envoyé ; la réponse indique
  `account: created` (`existing` si le compte existait, `none` sinon).
- **MAN-12** — Étant donné un licencié sans compte désigné avec son email sur une journée de la compétition `NDES`
  (saison 2998), alors son compte (profil 8) a `Filtre_competition = |NDES|` et `Filtre_saison = |2998|` : il ne voit
  pas les autres compétitions. Un périmètre sans compétition (événement sans journée) ne crée pas de compte.
- **MAN-02** — Étant donné un licencié sans email, quand on le désigne, alors la Désignation est créée, sans compte
  ni mandat, et la réponse le signale.
- **MAN-03** — Étant donné un compte existant de profil 7, quand on le désigne délégué CNA, alors son profil de
  base reste 7 et il reçoit un mandat de profil 5.
- **MAN-04** — Étant donné un délégué désigné sur deux événements de la même saison, alors il a **un** mandat
  « Délégué CNA <saison> » dont `filtre_journee` contient les journées des deux événements.
- **MAN-05** — Étant donné un RC désigné sur une compétition, alors son mandat a `filtre_competition` = cette
  compétition et la saison ; une phase ajoutée ensuite est couverte sans recalcul.
- **MAN-06** — Étant donné un délégué désigné sur un événement, quand on associe une journée à l'événement, alors
  elle entre dans `filtre_journee` ; quand on la dissocie, elle en sort.
- **MAN-07** — Étant donné la dernière Désignation d'un rôle et d'une saison supprimée, alors le mandat généré est
  supprimé et le compte est conservé.
- **MAN-08** — Étant donné un mandat saisi à la main, alors aucune opération sur les Désignations ne le modifie.
- **MAN-09** — Étant donné un responsable d'équipe désigné, alors son mandat (profil 7) a
  `limitation_equipe_club` = le club de l'équipe.
- **MAN-10** — Étant donné un membre du bureau CNA de profil de base 1 ou 2, alors aucun mandat n'est généré ; de
  profil 3 ou plus, un mandat de profil 2.
- **MAN-11** — Étant donné une Désignation `REP_EQUIPES`, `JURY_APPEL` ou `ARBITRE_NJ`, alors aucun compte ni mandat.

**Suivi des désignations (D11, D26)**

- **SUI-01** — Étant donné une journée nationale sans délégué CNA (ni hérité), alors sa case « Délégué CNA » est
  rouge ; pour une journée régionale, la case est « sans objet ».
- **SUI-02** — Étant donné une équipe engagée en nationale sans responsable désigné, alors elle figure dans la
  liste des équipes sans responsable.
- **SUI-03** — Étant donné une case pourvue par héritage, alors elle est distinguée d'une case pourvue en propre et
  indique d'où vient la Désignation.
- **SUI-04** — Étant donné un juge arbitre du même club qu'une équipe engagée sur la journée, alors la case est
  orange avec le motif ; idem pour une personne sans email.
- **SUI-05** — Étant donné le filtre « seulement les manques », alors seules les lignes ayant au moins une case
  rouge sont listées.
- **SUI-07** — Dans le récapitulatif, les phases d'une compétition de type CP de mêmes dates et lieu comptent pour
  **une seule** journée couverte et **une seule** Désignation (9 phases désignées = 1), comme dans la matrice.
- **SUI-08** — Chaque tableau du récapitulatif (par personne, par club) se replie et se déplie, et s'exporte en CSV et
  en Excel (une ligne par personne ou club ; par rôle, Désignations et journées couvertes), sur la saison ou la période.
- **SUI-06** — Le récapitulatif donne, par personne et par club, le nombre de Désignations par rôle et le nombre de
  journées couvertes, sur la saison ou une période.

**Rapports officiels (D12–D18, D28, D29)**

- **RAP-01** — Étant donné un type de rapport, quand app4 l'affiche, alors les champs viennent de
  `GET /admin/reports/types` (aucun champ codé dans app4).
- **RAP-02** — Étant donné un rapport soumis avec la version 1 d'un modèle, quand le modèle passe en version 2,
  alors le rapport s'affiche et s'imprime selon la version 1.
- **RAP-03** — Étant donné une personne ni officielle du périmètre (D15) ni profil 1–2, quand elle crée un rapport
  d'incident, alors `403`.
- **RAP-04** — Étant donné un arbitre désigné d'un match de la journée, alors il est proposé comme auteur d'un
  rapport d'incident saisi par un officiel, et le rapport enregistre `auteur_matric` (arbitre) et `saisi_par`.
- **RAP-05** — Étant donné un rapport en brouillon, alors seuls son rédacteur, son auteur s'il a un compte et les
  profils 1–2 le lisent.
- **RAP-06** — Étant donné un rapport soumis, alors il n'est plus modifiable, un PDF est généré et les
  destinataires de D28 sont notifiés par e-mail (résolus depuis les Désignations au moment de la soumission).
- **RAP-07** — Étant donné un rapport soumis, quand un profil 3 à 10 demande sa réouverture, alors `403` ; un profil
  1–2 doit donner un motif, journalisé, et le PDF précédent est conservé.
- **RAP-08** — Étant donné une fiche d'évaluation d'arbitrage, alors elle n'est lisible que par son rédacteur, le
  juge arbitre du périmètre et le bureau CNA ; la note sur 50 est la somme des cinq blocs, non saisissable.
- **RAP-09** — Étant donné un rapport de jury d'appel, alors la composition est pré-remplie depuis les Désignations ;
  quand on remplace un membre dans le rapport, alors les Désignations ne changent pas.
- **RAP-10** — Étant donné un type de rapport, alors `GET /admin/pdf/report-blank` rend un PDF vierge imprimable
  avec les mêmes sections que le formulaire.

**Bilan de journée (D1, D18, D25)**

- **BIL-01** — Étant donné un événement, alors un seul bilan peut y être rattaché ; étant donné une journée
  rattachée à un événement, alors on ne peut pas créer de bilan sur la journée seule.
- **BIL-02** — Étant donné le délégué CNA effectif de l'événement, alors lui seul (et les profils 1–2) peut rédiger
  le bilan.
- **BIL-03** — Étant donné un bilan, alors l'en-tête, les officiels, le nombre de matchs et les cartons (verts,
  jaunes, rouges, noirs) sont pré-remplis depuis KPI.
- **BIL-04** — Étant donné un bilan soumis plus de 3 jours après la fin de l'événement, alors il est accepté et
  marqué « hors délai ».
- **BIL-05** — Étant donné un bilan soumis, alors le bureau CNA, les RC des compétitions de l'événement et le
  rédacteur reçoivent le PDF.
- **BIL-06** — Étant donné un officiel marqué « absent » ou « remplacé par » dans un bilan soumis, alors le relevé des
  faits en tient compte.
- **BIL-07** — Étant donné les rapports d'incident et de jury d'appel du même périmètre, alors ils sont listés dans
  la section « Pièces liées ».

**Relevé des faits (D19, D22, D23)**

- **REL-01** — Le relevé ne contient que des quantités, jamais de montant.
- **REL-02** — Étant donné un arbitre non-joueur désigné par l'organisateur, alors il compte pour son club
  fournisseur ; désigné par la CNA, alors il n'y compte pas.
- **REL-03** — Étant donné un délégué CNA présent sur deux journées, alors son club a deux « délégués fournis » sur le
  club figé dans la Désignation.
- **REL-04** — Étant donné un fait du relevé, alors son détail donne la source (journée, match, Désignation ou
  rapport).
- **REL-06** — Les cartons jaunes, rouges et noirs sont trois types de fait distincts (`card_yellow`, `card_red`,
  `card_black`) ; l'écran filtre le relevé par type de fait, en plus du club.
- **REL-05** — Le relevé s'exporte en CSV et en Excel.

**Classement national des clubs (D20, D24)**

- **CLA-01** — Étant donné une saison où des Désignations existent, alors les rubriques 7.3.1, 7.3.2 et 7.3.4 sont
  calculées depuis `kp_designation` avec le club figé, et non depuis les noms en texte libre, `kp_rc` ou la liste
  manuelle.
- **CLA-02** — Étant donné une saison antérieure aux Désignations, alors le calcul existant (noms et table d'alias)
  s'applique inchangé.

**Reprise des saisies legacy (D30)**

- **REP-01** — Étant donné une journée dont `Delegue`, `Responsable_R1`, `ChefArbitre`, `Rep_athletes` ou `Arb_nj1…5`
  contient « NOM Prénom (licence) » et dont le rôle n'a aucune Désignation effective, alors la reprise crée la
  Désignation sur la journée, avec le club de la licence ; la fenêtre « Officiels » la montre.
- **REP-02** — Étant donné un rôle déjà désigné (à tout périmètre qui couvre la journée), alors la reprise n'y touche pas.
- **REP-03** — Étant donné un texte sans licence lisible, alors rien n'est créé pour ce rôle, la colonne legacy reste
  telle quelle et la saisie est listée dans le rapport de la commande.
- **REP-04** — Étant donné une compétition dont toutes les journées renseignent le même `Responsable_insc` et qui n'a
  pas de RC, alors la reprise désigne ce RC sur la compétition ; si les journées divergent, rien n'est créé et le cas est listé.
- **REP-05** — Sans `--apply`, la commande n'écrit rien ; avec, elle est rejouable sans doublon.
- **ORG-01** — Étant donné une journée créée ou modifiée en choisissant un club dans la liste, alors `Code_organisateur`
  est enregistré avec le libellé ; une saisie libre du libellé vide le code.
- **ORG-02** — Étant donné une journée avec un code ou un libellé de club, alors le tableau des journées du suivi des
  Désignations affiche le même club organisateur que le classement national des clubs (code d'abord, sinon libellé
  identifiant un seul club).
- **ORG-03** — Étant donné une copie de compétition, alors le code du club organisateur est copié avec le libellé.
