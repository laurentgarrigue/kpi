# Classement national des clubs

**Statut** : 📝 Brouillon — décisions de cadrage prises le 16/11/2026 (§ 1.1), restent Q-7, Q-9 à Q-11 (§ 12.2) — **Surfaces** : app4 (calcul, revue, publication), PDF
(api2 + Gotenberg) ; page publique app3 plus tard (§ 11) — **Données** : api2, données KPI + saisies manuelles
**Remplace** : le classeur `ClassementNationalClubs<AAAA>.xlsx` (onglet de calcul, onglets d'extraction
`7_1_1`, `7_1_2`, `7_1_3`, `7_1_4_EdF`, `Délégués_-_Chefs_arbitres`, `Arbitres`) et les requêtes
[`SQL/classement_clubs.sql`](../../SQL/classement_clubs.sql), [`SQL/activite_arbitres.sql`](../../SQL/activite_arbitres.sql)

**Références**

- Règlement sportif kayak-polo, article **RP KAP 66 — Classement national des clubs**.
- Annexes au règlement sportif, **article 7** : barème de la saison (annexes 2026, p. 16-17, reproduit au § 4).
- Règles d'interprétation **non officielles** du rédacteur du classement :
  « Rétributions et classement national des clubs », kayak-polo.info, page 2849 (mise à jour du 19/01/2023).
  Elles ne s'appliquent qu'**à défaut de règle explicite** dans les annexes de la saison.
- Archives des calculs 2017 → 2025 (onglets `Classement_<AAAA>` du classeur).

## 1. Objectif

Chaque fin de saison, la CNA kayak-polo publie le classement national des clubs : chaque club reçoit des points
pour sa participation aux compétitions nationales (7.1), l'organisation d'événements (7.2), l'implication de ses
licenciés dans la vie fédérale (7.3) et ses résultats (7.4). Le total donne le classement ; les 40 premiers sont
publiés (PDF), après validation en bureau CNA.

Aujourd'hui, le calcul se fait à la main dans un classeur : une dizaine de requêtes SQL copiées dans des onglets,
des comptages reportés colonne par colonne, des listes saisies (Équipe de France, membres CNA, délégués), puis un
tri et une copie vers l'onglet public. C'est long, peu traçable (un nombre dans une case, sans justificatif) et
fragile (requêtes à adapter chaque année aux codes de compétition, oublis, doublons).

KPI doit permettre de :

1. **décrire le barème de chaque saison** tel que l'annexe le définit, sans développement quand il change ;
2. **calculer** automatiquement toutes les rubriques calculables à partir des données KPI ;
3. **compléter** à la main ce que KPI ne connaît pas (Équipe de France, membres de la CNA, formations…) ;
4. **justifier chaque point** (quel club, quelle rubrique, à cause de quelle équipe / quel licencié / quel
   événement) pour la relecture en bureau CNA ;
5. **figer et publier** le classement (PDF, puis page publique), et **archiver** chaque saison avec son barème.

### 1.1 Décisions de cadrage

| Sujet | Décision |
|---|---|
| Barème | **Un barème par saison**, saisi depuis l'annexe de la saison, copiable depuis la saison précédente puis ajusté (§ 3). Les points et libellés ne sont **jamais** codés en dur. |
| Moteur | Un petit nombre de **types de calcul paramétrables** (§ 3.3). Une nouvelle rubrique de l'annexe se configure ; seul un **nouveau type** demande du code. |
| Interprétation | Quand l'annexe est muette, la règle appliquée est **un paramètre visible de la rubrique** (ex. : cumul 7.1.1 + 7.1.2, bénéficiaire des points 7.4). Chaque choix est affiché dans le détail et dans le PDF de travail. |
| Club d'un licencié | Club du licencié **à la date de référence** du calcul (§ 5.1), figé dans l'instantané. |
| Traçabilité | Chaque point est une **ligne de détail** (club, rubrique, motif, objet source). Le total d'un club est la somme de ses lignes. |
| Corrections | Ajout ou retrait manuel d'une ligne, **avec motif obligatoire**, conservé et visible (jamais une modification silencieuse d'un total). |
| Cycle de vie | `Brouillon` → `Calculé` → `Validé` (bureau CNA) → `Publié`. Une saison publiée est **figée** : un recalcul ultérieur ne la modifie pas. |
| Publication | **Top 40** (paramètre) en PDF Gotenberg, comme aujourd'hui ; tableau complet et détail dans un PDF/export de travail. |
| Application du règlement (16/11/2026) | **Le texte de l'annexe prime** sur la pratique du classeur et sur les règles non officielles. |
| Bénéficiaire 7.4 (16/11/2026) | **Chaque club d'origine des joueurs** de l'équipe, **sélections CR/CD comprises** (lecture littérale de l'annexe 2026) — I-7. |
| Licencié dans une équipe (16/11/2026) | **Joueur ayant disputé au moins un match** validé de l'équipe dans la catégorie ; **entraîneurs exclus** — I-4. |
| Unité 7.3 (16/11/2026) | **Une fois par licencié et par rubrique**, quel que soit le nombre de compétitions ou de journées — I-16. |
| Arbitres (16/11/2026) | Comptent s'ils sont **licenciés pour la saison** et ont **arbitré dans la saison** — I-18. |
| Égalité (16/11/2026) | **Même rang** pour les clubs à égalité de points — I-21. |
| Première saison (16/11/2026) | **2026**, calculée dans KPI **en parallèle du classeur** pour comparaison (CNC-32). |
| Historique (16/11/2026) | Saisons 2017 → 2025 **non importées** : elles restent dans le classeur. |
| Accès (16/11/2026) | **Profil 1 uniquement** : barème, calcul, saisies, validation, publication. |

## 2. Vocabulaire

| Terme | Définition |
|---|---|
| **Barème** | Ensemble des rubriques et points d'une saison, tel que défini par l'article 7 des annexes de cette saison. |
| **Rubrique** | Une ligne d'un tableau de l'article 7 (ex. « Arbitre national catégorie A — 15 pts »). Identifiée par son article (7.1 … 7.4) et son rang dans le tableau (7.3.5). |
| **Type de calcul** | Algorithme générique, paramétré par la rubrique, qui produit des lignes de détail (§ 3.3). |
| **Unité d'attribution** | Ce qui déclenche une fois les points : par équipe, par licencié, par événement, une fois par club. |
| **Catégorie** | Libellé de l'annexe (N1D, U15, CFH, Play Off H…) associé, pour la saison, à une liste de **codes de compétition KPI** (N15, N15A, N15B, N15_2…). |
| **Club bénéficiaire** | Club qui reçoit les points d'une ligne. Seuls les **clubs** comptent : pas les sélections régionales/départementales ni les clubs étrangers (§ 5.3). |
| **Équipe de club** | Équipe engagée dont le `Code_club` est un club (pas une sélection). |
| **Entente (bi-club)** | Équipe représentant officiellement plusieurs clubs. KPI ne porte qu'un `Code_club` par équipe : les clubs associés sont **déclarés à la main** (§ 7). |
| **Date de référence** | Date à laquelle on lit le club des licenciés et les qualifications d'arbitre (§ 5.1). |
| **Ligne de détail** | Un point attribué : club, rubrique, nombre d'unités, points, motif, référence de l'objet source, origine (`auto` ou `manuel`). |
| **Instantané** | Copie figée des lignes de détail et du classement au moment du calcul. |

## 3. Barème par saison

### 3.1 Structure d'une rubrique

| Champ | Exemple | Rôle |
|---|---|---|
| `article` | `7.3` | Regroupement (colonnes du tableau, sous-totaux) |
| `rang` | `5` | Ordre dans l'article → code `7.3.5` |
| `libelle` | « Arbitre national catégorie A » | **Texte exact de l'annexe** |
| `libelle_court` | « Arb. nat. A » | En-tête de colonne |
| `points` | `15` | Points par unité |
| `type_calcul` | `REFEREE_QUALIFICATION` | § 3.3 |
| `parametres` | `{ "qualifications": [["Nat","A"]], "activite_min": 0 }` | Propres au type |
| `unite` | `par_licencie` | § 2 |
| `source` | `auto` / `manuel` / `mixte` | `manuel` : aucune ligne calculée, saisie seule |
| `note_interpretation` | « 1 fois par licencié, quel que soit le nombre de journées » | Affichée dans le détail et le PDF de travail |

### 3.2 Catégories de la saison

Table de correspondance **par saison** : catégorie de l'annexe → codes de compétition KPI, avec pour chacune :

- les codes **d'engagement** (toutes les phases, tours préliminaires compris : `N15`, `N15A`, `N15B`, `N15_2`…) ;
- le code **de classement final** (compétition dont le classement publié donne les places 7.4) ;
- pour N1H / N1D : le code de **saison régulière** et le code de **play-off**.

Pré-remplissage proposé à partir de la saison précédente et des groupes de compétitions (`kp_competition.Code_ref`
→ `kp_groupe`), **à vérifier** par l'utilisateur : les codes changent d'une saison à l'autre (N1F → N1D, N4 qui
disparaît, NEM…).

### 3.3 Types de calcul

| Type | Produit | Paramètres principaux |
|---|---|---|
| `TEAM_OF_CLUB` | 1 unité par **équipe de club** engagée dans une des catégories | catégories, `une_fois_par_equipe_et_categorie`, `ententes` (oui/non), `match_joue_requis` |
| `LICENSEE_IN_TEAM` | 1 unité par **(équipe, club)** pour chaque club ayant au moins un licencié dans l'équipe | catégories, `rôles` (joueurs ; entraîneurs exclus par défaut), `présence` (feuille de match validée par défaut / composition), `inclure_club_de_l_equipe` |
| `RANKING` | 1 unité par équipe classée à une place donnée | catégories, `classement` (`final` / `saison_reguliere`), `places` (ex. `[2]` pour un championnat, `[1]` pour une coupe), `beneficiaire` (`club_de_l_equipe` / `clubs_des_licencies`) |
| `EVENT_ORGANISER` | 1 unité par **événement** organisé par le club | `nature` (international, finale multi, finale mono / finale CdF, journée multiple, journée simple), `niveaux` |
| `JOURNEE_OFFICIAL` | 1 unité par **licencié** ayant tenu le rôle sur au moins une journée nationale | `roles` (`Delegue`, `ChefArbitre`) |
| `COMPETITION_MANAGER` | 1 unité par **licencié** responsable d'au moins une compétition nationale (`kp_rc`) | `phases_preliminaires` (oui) |
| `REFEREE_QUALIFICATION` | 1 unité par **licencié** ayant la qualification | `qualifications` (couples `arbitre`/`niveau`), `activite_min` (matchs, défaut 1), `perimetre_activite` (`saison` = toute compétition de la saison ; `national` pour 7.3.3) |
| `MANUAL_LIST` | Lignes saisies (§ 7) | `unite` |

Un barème est **valide** quand chaque rubrique a un type, des points et, pour les types automatiques, des
catégories ou paramètres résolus. Le calcul est refusé sur un barème invalide.

## 4. Barème 2026 (annexes 2026, article 7)

Proposition de configuration ; la colonne « Interprétation » renvoie aux points du § 10 à trancher.

### 4.1 Participation aux compétitions officielles (7.1)

| Code | Libellé (annexe) | Pts | Type | Unité | Interprétation |
|---|---|---|---|---|---|
| 7.1.1 | Équipe du club en N1D, U15, U18, CFD, CF15 (par équipe de club engagée) | 20 | `TEAM_OF_CLUB` | équipe × catégorie | I-1, I-2, I-5, I-6, I-23 |
| 7.1.2 | Un ou plusieurs licenciés dans une équipe N1D, U15, U18, CFD, CF15, U21D, U21H | 15 | `LICENSEE_IN_TEAM` | équipe × club | I-1, I-3, I-4 |
| 7.1.3 | Équipe du club en N1H, N2, N3, NEM, CF | 10 | `TEAM_OF_CLUB` | équipe × catégorie | I-2, I-24 |
| 7.1.4 | Un ou plusieurs licenciés en Équipe de France (Joueur(euse), Entraîneur, Staff…) | 5 | `MANUAL_LIST` | une fois par club | I-20, N-1 |

### 4.2 Implication dans l'organisation d'évènements (7.2)

| Code | Libellé (annexe) | Pts | Type | Unité | Interprétation |
|---|---|---|---|---|---|
| 7.2.1 | Club organisateur d'une compétition internationale officielle (ECA Cup, Championnats d'Europe ou du Monde) | 55 | `EVENT_ORGANISER` (mixte) | événement | N-4 |
| 7.2.2 | Club organisateur d'une finale de championnats (plusieurs divisions / catégories) | 45 | `EVENT_ORGANISER` | événement | I-12, I-13 |
| 7.2.3 | Club organisateur d'une finale de Championnat (une division/catégorie) ou d'une finale de Coupe de France | 35 | `EVENT_ORGANISER` | événement | I-12, I-13 |
| 7.2.4 | Club organisateur d'une journée multiple de compétitions nationales (plusieurs divisions / catégories) | 20 | `EVENT_ORGANISER` | événement | I-12, I-14 |
| 7.2.5 | Club organisateur d'une ou plusieurs formations d'arbitre national | 15 | `MANUAL_LIST` | une fois par club | N-3 |
| 7.2.6 | Club organisateur d'une journée simple de compétition nationale (une seule division / catégorie) | 10 | `EVENT_ORGANISER` | événement | I-12, I-14 |

### 4.3 Implication dans la vie fédérale (7.3) — « points par licencié du club »

| Code | Libellé (annexe) | Pts | Type | Unité | Interprétation |
|---|---|---|---|---|---|
| 7.3.1 | Délégué CNA ou Juge arbitre sur une ou plusieurs journées de compétition nationale | 50 | `JOURNEE_OFFICIAL` | licencié | I-16, N-5 |
| 7.3.2 | Responsable de compétition | 40 | `COMPETITION_MANAGER` | licencié | I-16 |
| 7.3.3 | Arbitre international officiant en compétition nationale | 35 | `REFEREE_QUALIFICATION` (`Int`, `activite_min = 1`) | licencié | I-17 |
| 7.3.4 | Autre membre de la CNA (Hors RC, délégué CNA, juge arbitre) | 20 | `MANUAL_LIST` | licencié | I-17, N-2 |
| 7.3.5 | Arbitre national catégorie A | 15 | `REFEREE_QUALIFICATION` (`Nat`/`A`, activité saison ≥ 1) | licencié | I-17, I-18 |
| 7.3.6 | Arbitre national catégorie B ou C | 10 | `REFEREE_QUALIFICATION` (`Nat`/`B`, `Nat`/`C`, activité saison ≥ 1) | licencié | I-18 |
| 7.3.7 | Arbitre national stagiaire ou régional, Officiel de table de marque, Jeune Officiel | 3 | `REFEREE_QUALIFICATION` (`Nat`/`S`, `Reg`/*, `OTM`, `JO`, activité saison ≥ 1) | licencié | I-18, N-10, Q-7 |

### 4.4 Résultats aux compétitions nationales (7.4) — « club ayant un ou plusieurs licenciés dans une équipe »

Toutes les rubriques 7.4 : `beneficiaire = clubs_des_licencies` — chaque club ayant au moins un joueur de
l'équipe (au sens du § 5.5) reçoit les points **une fois par équipe**, que l'équipe soit une équipe de club, une
entente ou une sélection CR/CD.

| Code | Libellé (annexe) | Pts | Type | Places / classement | Interprétation |
|---|---|---|---|---|---|
| 7.4.1 | Champion de France (U15, U18, Play Off D, Play Off H) | 40 | `RANKING` | 1re, final | I-7 à I-11 |
| 7.4.2 | Vainqueur de la saison régulière (N1H, N1D, N2, N3) | 35 | `RANKING` | 1re, saison régulière | I-7, I-8, I-11 |
| 7.4.3 | 2ème de championnat de France / Vainqueur Coupe de France (CF15, CFD, CFH) | 30 | `RANKING` | 2e championnat, 1re coupe | I-9 |
| 7.4.4 | 2ème de la saison régulière (N1H, N1D, N2, N3) | 25 | `RANKING` | 2e, saison régulière | |
| 7.4.5 | 3ème de championnat de France / 2ème Coupe de France | 25 | `RANKING` | 3e / 2e | |
| 7.4.6 | 3ème de la saison régulière (N1H, N1D, N2, N3) | 20 | `RANKING` | 3e, saison régulière | |
| 7.4.7 | 4ème de championnat de France / 3ème Coupe de France | 15 | `RANKING` | 4e / 3e | |
| 7.4.8 | 5ème de championnat de France / 4ème Coupe de France | 12 | `RANKING` | 5e / 4e | |
| 7.4.9 | 6ème de championnat de France / 5ème Coupe de France | 10 | `RANKING` | 6e / 5e | |
| 7.4.10 | 7ème de championnat de France | 8 | `RANKING` | 7e | |
| 7.4.11 | 8ème de championnat de France | 6 | `RANKING` | 8e | |

## 5. Règles de calcul communes

### 5.1 Saison et date de référence

- Le calcul porte sur la saison KPI `Code_saison = AAAA` (année civile).
- Le **club d'un licencié** est `kp_licence.Numero_club` et sa **qualification d'arbitre** `kp_arbitre`
  (`arbitre`, `niveau`). KPI ne conserve **que la valeur courante** (mise à jour quotidienne depuis le PCE,
  `REPLACE INTO` pour `kp_arbitre`) : il n'y a pas d'historique.
- D'où la règle déjà appliquée dans le classeur (« faire les calculs avant la fin de l'année ») : la **date de
  référence** est la date du calcul ; elle est affichée, et l'instantané (§ 8) fige clubs et qualifications lus.
  Un calcul lancé après le basculement des mutations de la saison suivante affiche un **avertissement**.
- Un licencié ne compte que si sa licence couvre la saison (`kp_licence.Origine >= AAAA`) : règle générale, et
  exigence explicite pour les arbitres (I-18).

### 5.2 Compétitions nationales

Une compétition est nationale si `kp_competition.Code_niveau = 'NAT'` (et non plus « code commençant par `N` ou
`CF` », règle des requêtes actuelles). Les compétitions internationales (`INT`) n'interviennent qu'en 7.2.1 ;
l'activité exigée en 7.3.3 se mesure, elle, en compétition nationale.

### 5.3 Clubs bénéficiaires

Reçoivent des points : les clubs (`kp_club`) rattachés à un CD français. Sont exclus :

- les **sélections** régionales (code CR) et départementales (code CD) : leurs équipes ne rapportent rien au titre
  de « équipe du club » (7.1.1, 7.1.3), mais **leurs joueurs rapportent à leur club** en 7.1.2 et en 7.4 ;
- les **clubs et licenciés étrangers** (`Numero_comite_reg = '98'`, `Matric >= 2000000`).

### 5.4 Équipe et dédoublonnage

Une équipe est identifiée par `kp_competition_equipe.Numero` (→ `kp_equipe`). Une même équipe engagée dans
plusieurs compétitions **d'une même catégorie** (tours préliminaires, phases, `N15A` + `N15`) compte **une fois**
par catégorie. Les requêtes actuelles la comptent plusieurs fois (« compte plusieurs fois les qualifiés ») et
le dédoublonnage se fait à la main.

### 5.5 Licenciés d'une équipe (7.1.2, 7.4)

Décision (I-4) : sont « licenciés dans une équipe » les **joueurs** figurant sur la feuille d'au moins un
**match validé** de l'équipe dans la catégorie (`kp_match_joueur`), **entraîneurs exclus** (`Capitaine = 'E'`).
Une simple inscription sur la composition (`kp_competition_equipe_joueur`, règle des requêtes actuelles) ne suffit
pas. Le paramètre `présence = composition` reste disponible pour une saison dont l'annexe le demanderait.

### 5.6 Classements (7.4)

- Classement **final** : classement **publié** de la compétition de classement final de la catégorie
  (`Clt_publi` pour un championnat, `CltNiveau_publi` pour une coupe / tournoi).
- **Saison régulière** N1H / N1D : classement publié de la compétition de saison régulière, avant play-off.
  N2 / N3 : classement publié de la compétition (pas de play-off).
- Un classement **non publié** ou **non verrouillé** bloque le calcul de la rubrique (message explicite).
- **Ex æquo** de classement (ex. 5e ex æquo sans match de placement) : chaque équipe reçoit les points de la place
  (proposition, I-10).
- Bénéficiaires : clubs des joueurs de l'équipe (§ 4.4, § 5.5), une ligne par (équipe, club).

### 5.7 Événements organisés (7.2)

- Un **événement organisé** = ensemble des journées nationales ayant le même `Code_organisateur`, des dates qui se
  chevauchent et le même lieu. Proposition : s'appuyer d'abord sur `kp_evenement` quand les journées y sont
  rattachées, sinon sur ce regroupement.
- Un événement rapporte **une seule rubrique** : la plus élevée applicable (finale multi > finale mono / CdF >
  journée multiple > journée simple). « Multiple » = plusieurs catégories (codes de compétition de catégories
  différentes) dans l'événement.
- Une journée est une **finale** si elle porte la phase finale de la compétition de classement final de sa
  catégorie (play-off N1H/N1D, finale U15/U18, finale de Coupe de France) — voir I-13 et N-6.
- `Code_organisateur` vide, ou code d'un CD/CR, ou d'un club étranger : aucune ligne automatique ; l'événement est
  signalé dans la liste « à vérifier » (I-14).

### 5.8 Cumul entre rubriques

- Les rubriques se **cumulent** (une équipe de club en N1D rapporte 7.1.1 et 7.1.2 ; un club champion de la saison
  régulière puis des play-off reçoit 7.4.2 et 7.4.1), sauf exclusion écrite dans l'annexe (7.3.4 « hors RC,
  délégué CNA, juge arbitre »). Voir I-1, I-8, I-17.

### 5.9 Total et rang

- Total d'un club = somme des points de ses lignes (automatiques + manuelles − retraits).
- Rang : ordre décroissant du total ; **à égalité, même rang** et le rang suivant est sauté (décision, I-21).
- Seuls les clubs ayant au moins un point apparaissent.

## 6. Données KPI utilisées et limites

| Rubrique | Source KPI | Fiabilité |
|---|---|---|
| 7.1.1, 7.1.3 | `kp_competition_equipe` (`Code_compet`, `Code_club`, `Numero`), `kp_competition` | ✅ Fiable, sauf ententes (N-7) |
| 7.1.2 | + `kp_match_joueur` / `kp_competition_equipe_joueur`, `kp_licence.Numero_club` | ✅ Fiable à la date de référence |
| 7.1.4 | — (sélections Équipe de France : site FFCK) | ❌ Saisie (N-1) |
| 7.2.1 | `kp_competition` (`INT`), `kp_journee.Code_organisateur` si saisis | ⚠️ Partiel, à confirmer (N-4) |
| 7.2.2 → 7.2.4, 7.2.6 | `kp_journee` (`Code_organisateur`, dates, lieu), `kp_evenement` | ⚠️ Heuristique de regroupement et de « finale » à vérifier (N-6) |
| 7.2.5 | — | ❌ Saisie (N-3) |
| 7.3.1 | `kp_journee.Delegue`, `kp_journee.ChefArbitre` (**texte libre**) | ⚠️ Rapprochement nom → licencié (N-5) |
| 7.3.2 | `kp_rc` (`Matric`, `Code_competition`, `Code_saison`) | ✅ Fiable (si `kp_rc` est tenue à jour) |
| 7.3.3, 7.3.5 → 7.3.7 (activité des arbitres) | `kp_arbitre` + `kp_match.Matric_arbitre_principal/secondaire` sur les matchs validés de la saison | ✅ Fiable si les arbitres sont désignés nominativement |
| 7.3.4 | — (composition de la CNA) | ❌ Saisie (N-2) |
| 7.3.5 → 7.3.7 (qualification) | `kp_arbitre` (`arbitre`, `niveau`) | ⚠️ Valeur courante seulement (§ 5.1) |
| 7.3.7 (activité OTM / JO) | `kp_match.Secretaire`, `Chronometre`, `Timeshoot`, `Ligne1/2` (**texte libre**) | ⚠️ Rapprochement nom → licencié (N-10) |
| 7.4 | `kp_competition_equipe` (`Clt_publi`, `CltNiveau_publi`) + catégories § 3.2 | ✅ Fiable si classements publiés |
| Département / région (publication) | `kp_club.Code_comite_dep` → `kp_cd` → `kp_cr` | ✅ Remplace la colonne saisie du classeur (`LEFT(N°, 2)` donnait des départements faux, ex. Mandelieu, Muret) |

## 7. Saisies manuelles et corrections

Écran de saisie par rubrique `manuel` / `mixte` :

- **Liste de licenciés** (7.1.4 EdF, 7.3.4 membres CNA) : recherche de licencié (autocomplete existant, cf.
  [PAGE_RC.md](PAGE_RC.md) § 4.2) → club lu à la date de référence ; ou club saisi directement pour un staff non
  licencié/non trouvé. Champ « précision » (ex. « U21 F », « chargé de mission »).
- **Liste de clubs** (7.2.5 formations, 7.2.1 compétitions internationales) : club + libellé + date.
- **Ententes** : pour une équipe engagée, clubs associés. Utilisé par `TEAM_OF_CLUB` (`ententes = oui`) et
  `RANKING` (`beneficiaire = club_de_l_equipe`).
- **Rapprochements** (7.3.1, activité OTM/JO de 7.3.7) : pour chaque nom de délégué, juge arbitre ou officiel de
  table non rapproché automatiquement, choisir le licencié (mémorisé pour les saisons suivantes).
- **Ajustement** : ajout ou retrait de points à un club pour une rubrique, motif obligatoire, auteur et date
  conservés, affiché en italique dans le détail.
- **Copie N-1** : les listes 7.3.4 (CNA) et les rapprochements sont proposés depuis la saison précédente, à
  confirmer ligne par ligne.

## 8. Cycle de vie d'une saison

1. **Barème** : créer la saison (copie du barème N-1), ajuster rubriques, points et catégories selon l'annexe.
2. **Calcul** : lance tous les types automatiques ; remplace les lignes `auto` de la saison, conserve les lignes
   `manuel` et ajustements. Affiche la date de référence et les **alertes** : classements non publiés, journées
   sans organisateur, délégués non rapprochés, équipes de sélection, catégorie sans compétition, licences non
   renouvelées.
3. **Revue** : tableau clubs × rubriques (comme l'onglet de calcul) ; clic sur une case → lignes de détail
   (équipe, licencié, événement, classement). Export tableur du tableau et du détail.
4. **Validation** (bureau CNA) : statut `Validé`, instantané figé (lignes + clubs + qualifications lus).
5. **Publication** : PDF « Classement national des clubs — Saison AAAA » (club, N°, département, région, points,
   rang ; top N = 40) via api2 + Gotenberg ([PDF_GOTENBERG.md](PDF_GOTENBERG.md)) ; PDF de travail complet
   (toutes rubriques, barème et notes d'interprétation en annexe).
6. **Archive** : saison consultable en lecture seule avec son barème. Repasser en brouillon (profil 1) crée une
   nouvelle version, l'ancienne publication reste consultable.

## 9. Données (api2) — esquisse

| Table | Contenu |
|---|---|
| `kp_club_ranking_season` | saison, statut, date de référence, top N publié, auteur/dates de validation et de publication |
| `kp_club_ranking_rule` | barème : saison, article, rang, libellé, libellé court, points, type, paramètres (JSON), unité, source, note d'interprétation |
| `kp_club_ranking_category` | saison, catégorie, codes d'engagement, code de classement final, codes saison régulière / play-off |
| `kp_club_ranking_line` | saison, rubrique, club, unités, points, motif, type et id de l'objet source, Matric éventuel, origine (`auto`/`manuel`/`ajustement`), auteur |
| `kp_club_ranking_entente` | saison, équipe (`Numero`), club associé |
| `kp_club_ranking_alias` | nom saisi (délégué / juge arbitre) → Matric |

Endpoints `/api2/admin/club-ranking/{saison}/…` : barème (CRUD + copie), catégories, calcul, lignes, saisies,
validation, publication, PDF. Le moteur vit dans `src/ClubRanking/` (un service par type de calcul, testé seul).
Un endpoint public en lecture seule (classement publié) sera ajouté pour app3 (§ 11).

## 10. Points sujets à interprétation, non calculables, à reformuler

C'est la partie à arbitrer avant d'implémenter, et à faire remonter pour la rédaction des **annexes 2027**.

### 10.1 Interprétations (l'annexe ne tranche pas)

| # | Rubrique | Question | Pratique actuelle (classeur / règles non officielles) | Proposition KPI |
|---|---|---|---|---|
| I-1 | 7.1.1 / 7.1.2 | Une équipe du club rapporte-t-elle **aussi** 7.1.2 (ses propres licenciés) ? | Oui : ex. 2025 Avranches 5 × 7.1.1 et 7 × 7.1.2 (les 5 équipes du club + U21D/H) | Cumul (paramètre `inclure_club_de_l_equipe = oui`) |
| I-2 | 7.1.1, 7.1.3 | Une équipe engagée dans plusieurs compétitions d'une catégorie (préliminaires, phases `N15A`/`N15`/`N15_2`) compte-t-elle plusieurs fois ? | Une fois (dédoublonnage manuel) ; règles non officielles : « inclure les tours préliminaires » | Une fois par équipe et par catégorie |
| I-3 | 7.1.2 | Par **club** ou par **équipe** ? | Par club en 2022, par équipe depuis 2023 | Par (équipe, club) |
| I-4 | 7.1.2, 7.4 | « Licencié dans une équipe » : inscrit sur la composition, ou ayant joué ? Les entraîneurs comptent-ils ? | Composition (`kp_competition_equipe_joueur`) ; TODO des règles non officielles : « matchs joués et verrouillés » | ✅ **Décidé** : joueur ayant disputé au moins un match validé, entraîneurs exclus |
| I-5 | 7.1.1 | Ententes (bi-club) : points pour chaque club ? | « 1 point pour chaque club pour les équipes bi-club » | Oui, via la déclaration d'entente (§ 7) |
| I-6 | 7.1.1, 7.1.3, 7.4 | Sélections régionales / départementales | « 0 point pour aucun club » au titre de l'équipe | ✅ **Décidé** : 0 au titre de l'équipe (7.1.1, 7.1.3) ; leurs joueurs rapportent à leur club en 7.1.2 et 7.4 |
| I-7 | 7.4 | **Qui reçoit les points de résultat ?** L'annexe 2026 dit « club ayant un ou plusieurs licenciés du club dans une équipe » ; les règles non officielles et le classeur : le **club de l'équipe** (1 point par club et par équipe, 0 pour les sélections). Avec la lecture littérale, un CD champion U18 rapporte 40 pts à chacun des clubs de ses joueurs. | Club de l'équipe | ✅ **Décidé** : application du texte, chaque club d'origine des joueurs, sélections comprises. **Changement de pratique** : à signaler lors de la publication 2026. |
| I-8 | 7.4 | Cumul saison régulière + play-off / championnat (ex. 1er saison régulière et champion) ? | Oui (2025 Avranches : 3 × 7.4.1 et 3 × 7.4.2) | Cumul |
| I-9 | 7.4.3 → 7.4.11 | « Championnat de France » désigne-t-il uniquement U15, U18, Play Off D, Play Off H (liste de 7.4.1) ? La « Coupe de France » uniquement CF15, CFD, CFH (liste de 7.4.3) ? | Implicitement oui | Oui, listes de 7.4.1 et 7.4.3 reprises dans les lignes suivantes |
| I-10 | 7.4 | Places ex æquo (pas de match de placement) | Non rencontré / manuel | Chaque équipe reçoit les points de la place |
| I-11 | 7.4.2, 7.4.4, 7.4.6 | N2, N3 sans play-off : seule la saison régulière compte (pas de « champion de France » N2/N3) ? | Oui | Oui |
| I-12 | 7.2 | Unité : par **événement** (week-end, lieu) ou par **journée** KPI ? Un événement donne-t-il une ou plusieurs rubriques ? | Par événement, une rubrique | Par événement, rubrique la plus élevée (§ 5.7) |
| I-13 | 7.2.2, 7.2.3 | Qu'est-ce qu'une « finale de championnat » ? Play-off N1H/N1D, finale U15/U18 ; la dernière journée de N2/N3 (sans play-off) en est-elle une ? « Plusieurs divisions » = play-off H et D ensemble ? | Règles non officielles : « inclut Championnats de France et PlayOffs », 1 point quel que soit le nombre de terrains | Finale = phase finale des catégories de 7.4.1 et 7.4.3 ; dernière journée N2/N3 = journée simple/multiple |
| I-14 | 7.2 | Organisation par un CD/CR, co-organisation par deux clubs, journée nationale organisée par une structure hors club | Au cas par cas | Pas de points automatiques ; liste « à vérifier » + saisie manuelle |
| I-15 | 7.2 | Périmètre « compétition nationale » : U21, NEM, CF comptent-ils ? | Codes `N%` et `CF%` | `Code_niveau = 'NAT'` |
| I-16 | 7.3.1, 7.3.2 | Un licencié RC de 3 compétitions : 40 pts ou 120 pts ? Un délégué sur 4 journées ? | Délégué : une fois (« sur une ou plusieurs journées ») ; RC : « 1 point par club d'origine de chaque responsable » | ✅ **Décidé** : une fois par licencié et par rubrique |
| I-17 | 7.3 | **Cumul pour une même personne** : un arbitre international aussi délégué et RC ; un arbitre international est-il aussi « arbitre national catégorie A » ? | Cumul, sauf 7.3.4 (exclusion écrite) ; `Int` non compté en `Nat A` | Idem, à confirmer |
| I-18 | 7.3.5 → 7.3.7 | Faut-il une **activité** (avoir arbitré dans la saison) et une **licence de la saison** ? Où classer l'arbitre **interrégional** (`IR`, absent de l'annexe) ? Un jeune officiel aussi arbitre régional compte-t-il deux fois ? | Aucune condition d'activité ; `IR` non compté ; une seule qualification par licencié dans KPI | ✅ **Décidé** : licence de la saison **et** au moins un match arbitré (validé) dans la saison, toutes compétitions. Reste ouvert : `IR` (proposé en 7.3.7) et activité des OTM / JO (Q-7) |
| I-19 | 7.1.2, 7.3, 7.4 | Club d'un licencié ayant muté pendant ou après la saison | Club au moment de l'extraction (« avant la fin de l'année ») | Club à la date de référence, figé (§ 5.1) |
| I-20 | 7.1.4 | Une fois par club, ou par sélection (Senior F, Senior H, U21 F, U21 H) / par licencié ? Quelles sélections (stages, entraînements exclus ; compétitions officielles seulement ?) | Une fois par club ; entraînements exclus | Une fois par club, sélections en compétition officielle de la saison |
| I-21 | Total | Égalité de points | Rangs distincts (2025 : 24e et 25e à 96 pts) | ✅ **Décidé** : même rang |
| I-22 | Tout | Clubs / licenciés étrangers | Exclus de fait | Exclus (§ 5.3) |
| I-23 | 7.1.1, 7.1.3 | Équipe engagée puis forfait général / retirée | Comptée si inscrite | Comptée si au moins un match joué (paramétrable) |
| I-24 | 7.1.3 | « CF » = CFH (CFD est déjà en 7.1.1) ? N4 (encore présente en 2025) et NEM : quelles compétitions KPI ? | N4 compté en 7.1.3 en 2025 (onglet `7_1_3`) | Catégories à configurer (§ 3.2) ; « CF » lu comme CFH |

### 10.2 Non calculables à partir des seules données KPI

| # | Donnée manquante | Contournement proposé | Évolution possible |
|---|---|---|---|
| N-1 | Sélections en **Équipe de France** (joueurs, entraîneurs, staff) | Saisie manuelle (§ 7), à partir des listes FFCK | Pré-remplissage depuis les compositions des équipes de France saisies dans KPI pour les compétitions internationales |
| N-2 | Composition de la **CNA** (membres, chargés de mission) | Saisie manuelle, copie N-1 | Table des membres de commission par saison |
| N-3 | **Formations d'arbitre national** organisées | Saisie manuelle | — |
| N-4 | Organisation de **compétitions internationales** (ECA Cup, Europe, Monde) | Pré-remplissage si la compétition `INT` et son organisateur sont dans KPI, sinon saisie | — |
| N-5 | **Délégué** et **juge arbitre** des journées : noms en **texte libre** (`kp_journee.Delegue`, `ChefArbitre`), rapprochés par nom/prénom (homonymes, fautes, ordre nom-prénom) | Rapprochement automatique + validation manuelle mémorisée (§ 7) | Saisir un licencié (Matric) dans l'admin des journées ; renommer « Chef arbitre » en « Juge arbitre » (vocabulaire 2026) |
| N-6 | Notion de **finale** et d'**événement** (week-end organisé) | Heuristique § 5.7 + liste « à vérifier » | Marqueur « finale » sur la journée/phase ; rattachement systématique des journées à un événement |
| N-7 | **Ententes** (équipes bi-club) : un seul `Code_club` par équipe | Déclaration manuelle (§ 7) | Clubs associés sur l'équipe |
| N-8 | **Historique** du club d'un licencié et de sa qualification d'arbitre | Date de référence + instantané (§ 5.1) | Historiser club et qualification par saison |
| N-10 | **Activité des OTM / Jeunes Officiels** : les officiels de table (`Secretaire`, `Chronometre`, `Timeshoot`, `Ligne1/2`) sont du **texte libre**, sans Matric | Rapprochement nom → licencié comme N-5 (Q-7) | Choisir un licencié (Matric) pour les officiels de table dans la feuille de match |
| N-9 | Données jamais saisies dans KPI (compositions incomplètes, `kp_rc` non tenue, organisateur vide) | Alertes du calcul (§ 8) | — |

### 10.3 À reformuler / clarifier dans les annexes 2027

1. **Numéroter les rubriques** (7.1.1 … 7.4.11) pour pouvoir s'y référer (justificatifs, PDF, contestations).
2. **7.1 ligne 3** : écrire « CFH » au lieu de « CF » ; dire si N4 / NEM existent et sont concernées ; harmoniser
   « N2 » / « N2H ».
3. **7.1.1** : préciser « une fois par équipe et par catégorie, tours préliminaires et phases compris » et le sort
   des équipes forfait.
4. **7.1.2** : écrire « par équipe » ; dire si l'équipe du club elle-même rapporte aussi ces points (cumul avec
   7.1.1) ; définir « licencié dans une équipe » (joueur ayant disputé au moins un match ? entraîneur ?).
5. **Ententes et sélections** : une phrase générale (points à chaque club d'une entente ; aucun point au titre
   de l'équipe pour une sélection CR/CD, mais ses joueurs comptent pour leur club en 7.1.2).
6. **7.1.4** : « une fois par club » ou « par licencié » ; quelles sélections (compétitions officielles de la
   saison, hors stages).
7. **7.2** : préciser que l'unité est l'**événement** et qu'un événement ne rapporte que la rubrique la plus
   élevée ; définir « finale de championnat » (play-off, finales U15/U18) et « plusieurs divisions » ; cas d'une
   organisation par un CD/CR ou de plusieurs clubs.
8. **7.3** : préciser « une fois par licencié et par rubrique, quel que soit le nombre de journées / compétitions »
   (RC notamment) ; règle de cumul pour une même personne ; écrire la condition retenue pour 2026 (« licencié de la
   saison et ayant officié sur au moins un match de la saison ») et ce qu'« officier » veut dire pour un OTM ou un
   Jeune Officiel ; classement de l'arbitre interrégional ; l'arbitre international compte-t-il aussi comme
   national A.
9. **7.4 — bénéficiaire** : le texte 2026 est appliqué (chaque club ayant au moins un joueur dans l'équipe,
   sélections comprises) ; le confirmer en toutes lettres, avec « une fois par équipe » et la définition du
   joueur (au moins un match disputé), pour lever toute ambiguïté avec l'ancienne pratique (club de l'équipe).
10. **7.4 — listes** : répéter dans chaque ligne les catégories concernées (championnat : U15, U18, Play-off D/H ;
    coupe : CF15, CFD, CFH ; saison régulière : N1H, N1D, N2, N3) ; dire que saison régulière et play-off se
    cumulent ; traitement des ex æquo.
11. **7.1.2 / 7.4 — « licencié dans une équipe »** : écrire « joueur ayant disputé au moins un match, entraîneurs
    exclus ».
12. **Club d'un licencié** : club de la licence de la saison considérée (ou à une date fixée, ex. 31 décembre).
13. **Classement** : écrire « à égalité de points, même rang » ; règle d'égalité de points ; nombre de clubs publiés (40) ; validation en bureau CNA ; délai
    de réclamation éventuel.
14. **Vocabulaire** : « Juge arbitre » (2026) a remplacé « Chef des arbitres » ; aligner le règlement, les annexes
    et KPI.

## 11. Hors périmètre (pour l'instant)

- **Page publique app3** (classement publié, détail par club, historique) : après validation du calcul sur une
  saison réelle ; spec à écrire dans [public/](public/README.md).
- **Rétributions** (même page de règles non officielles) : non traitées ici.
- Saisons 2017 → 2025 : ni import ni recalcul ; elles restent dans le classeur (barèmes et données de l'époque non
  reproductibles, notamment les clubs des licenciés).

## 12. Questions

### 12.1 Tranchées le 16/11/2026

| # | Question | Décision |
|---|---|---|
| Q-1 | Qui calcule, valide, publie ? | **Profil 1 uniquement** |
| Q-2 | Bénéficiaire des points 7.4 (I-7) | **Règlement appliqué** : chaque club d'origine des joueurs, sélections comprises |
| Q-3 | « Licencié dans une équipe » (I-4) | **Joueur ayant disputé au moins un match**, entraîneurs exclus |
| Q-4 | Unité 7.3 (I-16) | **Une fois** par licencié et par rubrique |
| Q-5 | Arbitres 7.3.5 → 7.3.7 (I-18) | **Licenciés de la saison et ayant arbitré dans la saison** |
| Q-6 | Égalité de points (I-21) | **Même rang** |
| Q-8 | Première saison / historique | **2026** en parallèle du classeur ; **pas d'import** de 2017 → 2025 |

### 12.2 Encore ouvertes

| # | Question | Proposition |
|---|---|---|
| Q-7 | « Avoir arbitré » pour un **OTM** ou un **Jeune Officiel** : avoir tenu la table de marque sur un match de la saison ? Les noms y sont en texte libre (N-10). | Oui, par rapprochement de nom mémorisé ; à défaut, saisie manuelle |
| Q-9 | « Arbitré dans la saison » : toutes compétitions KPI (régionales comprises) ou seulement nationales ? | Toutes compétitions de la saison (seul 7.3.3 exige le national, par son libellé) |
| Q-10 | Arbitre interrégional (`IR`) | En 7.3.7 avec les régionaux |
| Q-11 | Les points restants de § 10.1 sans mention « Décidé » | Propositions de la colonne de droite, sauf avis contraire |

## 13. Critères d'acceptation

**Barème**

- **CNC-01** — Étant donné une saison 2027 sans barème, quand on copie le barème 2026, alors toutes les rubriques,
  points, paramètres et catégories sont dupliqués pour 2027, et le barème 2026 est inchangé.
- **CNC-02** — Étant donné une rubrique dont on change les points de 15 à 12 et un nouveau calcul, alors les lignes
  de la rubrique valent 12 par unité, sans modification de code.
- **CNC-03** — Étant donné une rubrique automatique dont une catégorie n'a aucun code de compétition, alors le
  calcul est refusé avec le nom de la catégorie en cause.

**7.1 Participation**

- **CNC-04** — Étant donné une équipe de club engagée en `N15A`, `N15B` et `N15` (catégorie U15), quand on calcule
  7.1.1, alors le club reçoit **une** ligne de 20 pts pour cette équipe.
- **CNC-05** — Étant donné deux équipes du même club en U18, alors le club reçoit deux lignes 7.1.1.
- **CNC-06** — Étant donné une équipe de sélection CR en U18 comptant des joueurs de 3 clubs, alors aucune ligne
  7.1.1 et une ligne 7.1.2 par club (15 pts chacun).
- **CNC-07** — Étant donné une équipe de club en N1D dont 2 joueurs sont licenciés dans un autre club, alors le club
  de l'équipe reçoit 7.1.1 (20) **et** 7.1.2 (15), et l'autre club 7.1.2 (15) une seule fois.
- **CNC-08** — Étant donné un entraîneur (`Capitaine = 'E'`) seul licencié de son club dans une équipe, alors son
  club ne reçoit pas 7.1.2.
- **CNC-09** — Étant donné un joueur inscrit sur la composition mais absent de toute feuille de match validée, alors
  il ne rapporte pas 7.1.2 (paramètre par défaut) ; avec `présence = composition`, il rapporte.
- **CNC-10** — Étant donné une entente déclarée entre deux clubs pour une équipe N1D, alors chacun reçoit 7.1.1.
- **CNC-11** — Étant donné un licencié étranger (`Matric >= 2000000`) dans une équipe, alors il ne rapporte rien.

**7.2 Organisation**

- **CNC-12** — Étant donné un club organisateur, le même week-end au même lieu, de journées N1H et N1D, alors il
  reçoit une ligne 7.2.4 (20) et non deux lignes 7.2.6.
- **CNC-13** — Étant donné un club organisateur des play-off H et D le même week-end, alors une ligne 7.2.2 (45),
  sans ligne 7.2.4.
- **CNC-14** — Étant donné une journée nationale dont `Code_organisateur` est vide ou un code de CD, alors aucune
  ligne et la journée apparaît dans les alertes.

**7.3 Vie fédérale**

- **CNC-15** — Étant donné un délégué présent sur 4 journées nationales, alors son club reçoit **une** ligne 7.3.1.
- **CNC-16** — Étant donné un nom de délégué non rapproché d'un licencié, alors il apparaît dans les alertes ; après
  rapprochement manuel, le recalcul attribue la ligne, et le rapprochement est réutilisé la saison suivante.
- **CNC-17** — Étant donné un licencié RC de 3 compétitions nationales (`kp_rc`), alors une ligne 7.3.2.
- **CNC-18** — Étant donné un arbitre `Int` sans aucun match arbitré en compétition nationale de la saison, alors
  pas de ligne 7.3.3 ; avec un match, une ligne.
- **CNC-19** — Étant donné un arbitre `Nat`/`A` dont la licence n'est pas renouvelée pour la saison, alors pas de
  ligne 7.3.5, même s'il a arbitré.
- **CNC-19b** — Étant donné un arbitre `Nat`/`B` licencié pour la saison mais sans aucun match validé arbitré
  (principal ou secondaire) dans la saison, alors pas de ligne 7.3.6 ; avec un match, une ligne.
- **CNC-20** — Étant donné un licencié saisi en 7.3.4 qui est aussi RC, alors la saisie est refusée (exclusion de
  l'annexe) avec un message.

**7.4 Résultats**

- **CNC-21** — Étant donné une équipe 1re de la saison régulière N1H puis championne des play-off, alors son club
  reçoit 7.4.2 (35) et 7.4.1 (40).
- **CNC-22** — Étant donné une équipe classée 2e de la Coupe de France CFH, alors 7.4.5 (25).
- **CNC-23** — Étant donné une compétition de classement final non publiée, alors le calcul de 7.4 est bloqué pour
  sa catégorie avec une alerte.
- **CNC-24** — Étant donné deux équipes classées 5e ex æquo d'un championnat, alors chacune reçoit 7.4.8.
- **CNC-25** — Étant donné une sélection CD championne U18 dont les joueurs ayant disputé au moins un match viennent
  de 4 clubs, alors 4 lignes 7.4.1 (40 pts chacune) ; un joueur de la composition n'ayant joué aucun match ne fait
  pas entrer son club.
- **CNC-25b** — Étant donné une équipe de club N1H championne des play-off dont 3 joueurs viennent de 2 autres clubs,
  alors le club de l'équipe et les 2 autres clubs reçoivent chacun 7.4.1 une seule fois.

**Saisies, cycle de vie, publication**

- **CNC-26** — Étant donné un ajustement de −10 pts sans motif, alors il est refusé ; avec motif, il apparaît dans le
  détail avec auteur et date et le total du club baisse de 10.
- **CNC-27** — Étant donné un recalcul, alors les lignes `auto` sont remplacées et les lignes `manuel` /
  `ajustement` conservées.
- **CNC-28** — Étant donné une saison `Publiée`, alors calcul, saisie et ajustement sont refusés tant qu'elle n'est
  pas repassée en brouillon (profil 1).
- **CNC-28b** — Étant donné un utilisateur de profil 2 ou plus, alors la page et les endpoints du classement national
  des clubs lui sont refusés (403).
- **CNC-29** — Étant donné deux clubs à 96 pts aux rangs 24-25, alors ils sont tous deux 24e et le suivant 26e.
- **CNC-30** — Le PDF public liste les N premiers rangs (N = 40 par défaut) avec club, numéro, département, région
  (lus via `kp_cd` / `kp_cr`), points et rang ; le PDF de travail contient toutes les rubriques, le barème et les
  notes d'interprétation.
- **CNC-31** — Pour tout club, la somme des lignes de détail est égale au total affiché.
- **CNC-32** — Étant donné le barème 2026 et les données 2026, le calcul KPI et le classeur 2026 (tenu en parallèle)
  donnent les mêmes totaux aux écarts près **listés et expliqués** (décisions du 16/11/2026 qui changent la
  pratique : 7.4 aux clubs des joueurs, joueurs ayant joué, activité des arbitres), écarts relus avant publication.
