# Médailles de match et statistiques de timeline

**Phase** : transverse (après la phase 4, avant ou pendant la phase 5) — **Statut** : ✅ Validée (09/11/2026,
maquettes comprises) — 🛠 Implémentée, à déployer (§ 12)
**Surfaces** : app3 (site public), app2 (PWA résultats), app4 (admin2, profils 1–2) — **Données** : api2, sans
saisie supplémentaire
**Remplace** : — (nouveauté)

## 1. Objectif

Raconter chaque match autrement que par le score : à partir de la **timeline** déjà saisie (buts et cartons
horodatés), attribuer des **médailles** aux équipes, aux joueurs et aux arbitres (premier but, but en supériorité numérique,
remontée au score…) et en tirer des **statistiques originales** par compétition et par événement, y compris sur
l'arbitrage (§ 3.5).

Public visé : joueurs, clubs, spectateurs et commentateurs. Rien n'est saisi en plus : tout est **déduit** des
faits de match existants.

Le catalogue est **évolutif** : chaque médaille, étiquette ou statistique est d'abord visible dans l'admin
(profils 1 et 2), puis **publiée** — sur app2, sur app3 ou sur les deux, selon ce qui sert le mieux chaque public —
quand elle a fait ses preuves, et peut être **retirée** si elle s'avère peu pertinente. On ajoute ainsi des nouveautés au fil des saisons.

### 1.1 Décisions de cadrage

| Sujet | Décision |
|---|---|
| Surfaces | **app3** (stats compétition / événement, fiche équipe, page d'explication) et **app2** (feuille de match, stats) pour le public ; **app4** (profils 1–2) pour tout le catalogue. Incrustations TV et fiche joueur : plus tard (§ 10). |
| Matchs éligibles | **Matchs validés uniquement** (`kp_match.Validation = 'O'`), publiés, **matchs de classement et de placement compris**. |
| Appellation | **Double appellation** : un nom français et son équivalent anglais, affichés ensemble en français (« Premier but · Opener ») pour que le vocabulaire anglais s'installe progressivement ; le nom anglais seul sur le site anglais. Jamais de vocabulaire guerrier. |
| Explications | Chaque médaille, étiquette et statistique a une **explication** stockée et affichée (§ 3.1, § 8). |
| Tirs (`T`) et arrêts (`A`) | **Exclus** : buts et cartons seulement (§ 10). |
| Durée des périodes | **Non stockée, approximation acceptée** : 10 min par période et 5 min par prolongation dans la grande majorité des cas ; les formats courts (jeunes : 7 min et 3 min) gardent des proportions proches (§ 5.2). |
| Exclusions | Carton vert, jaune, rouge : **2 min**, levée au premier but adverse. Rouge définitif (`D`, futur carton noir) : **infériorité jusqu'à la fin du match** (§ 5.3). |
| Seuils | **Centralisés en un seul endroit**, communs à toutes les compétitions (§ 5.4). Depuis le 11/11/2026, le **profil 1** les modifie depuis l'admin (§ 5.4, § 9.3) : le fichier fournit les valeurs d'origine, l'admin ses remplacements. |
| Recalcul | **Toutes les saisons**, **saison par saison** : changer un seuil ne recalcule que les saisons choisies, les anciennes gardent leurs résultats et leurs seuils (§ 6.3). |
| Premier lot public | **Sélection réduite** (§ 4) ; le reste n'est visible que dans l'admin en attendant d'être publié. |
| Publication | **Par surface** : chaque élément est publié sur app2, sur app3, sur les deux ou sur aucune (§ 3.1, § 4). |
| Noms français, premier lot (09/11/2026) | **Validés** tels que proposés (§ 3, § 4). |
| Pictogrammes (09/11/2026) | **Dessinés exprès**, un par élément (§ 7.1). Validés avec les maquettes. |
| Étiquette « peu de buts » (09/11/2026) | **Partie d’échecs · Chess Match** (pion d’échecs) remplace *Verrouillé · Lockdown*, dont le nom et le cadenas se confondaient avec le verrouillage d’un match. |
| Ballon (09/11/2026) | Les pictogrammes qui montrent un ballon dessinent le **ballon de kayak-polo** (celui du water-polo), d'après le **dessin de référence choisi** : disque plein, panneaux blancs en trois groupes autour d'une jonction en « Y » décentrée. Le ballon seul (`goal`) devient le **symbole du but** dans toutes les applications, à la place des cercles et disques actuels (§ 7.2). |
| Blason (10/11/2026) | Une médaille s'affiche dans un **blason** (écu français moderne), **plein** dans la couleur de sa famille, pictogramme clair, **filigrane « KPI »** en haut à gauche, compteur en pastille (« ×3 ») : **56 px** par défaut, 40 px dans les tableaux. Un seul rendu pour **tous** les affichages (app2, app3, app4, étiquettes de match) ; il remplace le disque de 28 px (§ 7.3). |
| Page équipe d'app2 (10/11/2026) | Les médailles d'**équipe** et de **joueur** s'affichent sur la page équipe d'app2, sur le périmètre de l'événement ou du groupe choisi (nouveaux endpoints § 6.4). Dans ses statistiques de joueurs, le **nombre de buts** devient le **ballon** (`goal`) avec la **même pastille** que les médailles (« ×13 ») (§ 9.1). |
| Arbitres, publication (09/11/2026) | Les **médailles d'arbitre** sont publiées **dès le premier lot** (§ 4). Les statistiques d'arbitrage autres que *Matchs arbitrés* et *Médailles des arbitres* restent réservées à l'admin, **profils 1 et 2 seulement**. |
| Arbitres (09/11/2026) | Les actions de l'arbitre principal et du secondaire ne se distinguent pas dans la timeline : **chaque fait de match compte pour les deux**. Seuls les matchs dont un arbitre au moins est **désigné nominativement** (`Matric_arbitre_principal` / `Matric_arbitre_secondaire` renseigné) comptent, et seulement pour l'arbitre ainsi désigné (§ 3.5, § 5.6). |

## 2. Vocabulaire

| Terme | Définition |
|---|---|
| **Timeline** | Suite des faits de match d'un match (`kp_match_detail`) : buts (`B`) et cartons (`V`, `J`, `R`, `D`), chacun avec une période et un temps. |
| **Temps de jeu écoulé** | Position d'un fait sur un axe continu depuis le coup d'envoi, en secondes (§ 5.2). Le champ `Temps` stocke le **temps restant** au chrono de la période (décompte). |
| **Exclusion** | Infériorité numérique provoquée par un carton (§ 5.3). |
| **Supériorité / infériorité** | Une équipe est en **supériorité** quand l'adversaire a plus d'exclusions en cours qu'elle ; en **infériorité** dans le cas inverse. |
| **Médaille** | Distinction attribuée par un match à une équipe ou à un joueur. Un match peut attribuer la même médaille plusieurs fois (ex. deux buts en supériorité). Une médaille de joueur compte aussi pour son équipe dans les agrégats. |
| **Étiquette** | Qualificatif d'un match entier (ex. « Suspense · Thriller »). |
| **Statistique de timeline** | Indicateur agrégé sur une compétition ou un événement (ex. « Supériorité % · Powerplay % »). |
| **Élément du catalogue** | Une médaille, une étiquette ou une statistique de timeline, avec ses noms, son explication et ses surfaces de publication (§ 3). |
| **Surface** | Application publique où un élément peut être publié : **app2** (résultats en direct, match par match) ou **app3** (site public, vue saison et analyse). |
| **Arbitre désigné** | Arbitre principal ou secondaire d'un match identifié par son numéro de licence (`Matric_arbitre_*` > 0), et non par un simple libellé (« Équipe X », nom saisi à la main). |
| **Matchs arbitrés** | Pour un arbitre désigné : les matchs éligibles où il est principal ou secondaire. Les faits de ces matchs (buts, cartons) sont ceux du **binôme arbitral**, comptés à chacun de ses membres. |
| **Seuils de saison** | Copie des seuils utilisés pour calculer une saison (§ 6.3). |
| **Timeline fiable** | Timeline qui permet les éléments « chronologiques » (§ 5.1). |

> Ces termes seront ajoutés à `GLOSSARY.md` à la validation de la spec.

## 3. Catalogue

### 3.1 Structure d'un élément

Chaque élément du catalogue a :

- un **code** stable (clé technique), un **type** (`medal`, `label`, `stat`) et, pour une médaille, sa **portée**
  (équipe, joueur, ou les deux) ;
- un **nom français** et un **nom anglais** ;
- une **explication** en français et en anglais, courte (une à deux phrases), qui peut citer les seuils sous forme de
  variables (`{bounceBackSeconds}`) remplacées par les seuils de la saison affichée ;
- une **famille** (pour le regroupement et la couleur) et un **niveau de données** requis :
  **S** (score et faits de match, sans ordre ni temps) ou **T** (timeline fiable, § 5.1) ;
- un **statut** : `active` (calculé, visible dans l'admin) ou `retired` (masqué partout, plus calculé) ;
- ses **surfaces de publication** : aucune, `app2`, `app3`, ou les deux. Un élément actif sans surface n'est visible
  que dans l'admin (« non publié »). Un nouvel élément naît actif et sans surface.

Les éléments actifs sont **calculés qu'ils soient publiés ou non** : publier un élément sur une surface affiche
aussitôt tout son historique, sans recalcul. Les surfaces ne filtrent que la lecture.

Le catalogue est stocké en base (§ 6.2), initialisé par migration à partir des tableaux ci-dessous ; les **noms,
explications, statuts et surfaces** se modifient dans l'admin (§ 9.3). Les **règles** de calcul restent dans le code.

### 3.2 Médailles

Les seuils en *italique* sont des paramètres (§ 5.4). Portée : É = équipe, J = joueur.

| Code | Nom FR · EN | Portée | Explication (attribution) | Famille | Données | Publication initiale |
|---|---|---|---|---|---|---|
| `opener` | **Premier but · Opener** | É, J | L'équipe et le joueur qui ouvrent le score. | Élan | T | **app2, app3** |
| `wire_to_wire` | **De bout en bout · Wire to Wire** | É | Ouvre le score, n'est jamais rejointe, et gagne. | Élan | T | — (admin) |
| `scoring_run` | **Série · Scoring Run** | É | Marque au moins *3* buts consécutifs sans en encaisser (une médaille par série). | Élan | T | — (admin) |
| `fast_start` | **Entrée express · Fast Start** | J | Marque dans les *30* premières secondes d'une mi-temps. | Élan | T | — (admin) |
| `bounce_back` | **Réplique · Bounce Back** | É, J | Marque moins de *60 s* après avoir encaissé un but (une médaille par but). | Réaction | T | — (admin) |
| `comeback` | **Remontée · Comeback** | É | Menée d'au moins *3* buts, finit par gagner. | Réaction | T | **app2, app3** |
| `fightback` | **Retour au score · Fightback** | É | Menée d'au moins *3* buts, obtient le match nul (match sans prolongation). | Réaction | T | — (admin) |
| `game_winner` | **But de la victoire · Game Winner** | J | Marque le but décisif (§ 5.5). | Réaction | T | **app2** |
| `late_winner` | **Dernière minute · Late Winner** | É, J | Marque le but décisif dans les *60* dernières secondes du temps réglementaire. | Réaction | T | — (admin) |
| `lifeline` | **Égalisation salvatrice · Lifeline** | É, J | Égalise dans les *60* dernières secondes de la 2ᵉ mi-temps et arrache la prolongation. | Réaction | T | — (admin) |
| `extra_time` | **Prolongation · Extra Time** | É | Le match va en prolongation (les deux équipes). | Endurance | S | — (admin) |
| `marathon` | **Marathon · Marathon** | É | Le match atteint au moins une *2ᵉ* prolongation (les deux équipes). | Endurance | S | — (admin) |
| `golden_goal` | **But en or · Golden Goal** | É, J | Gagne en prolongation ; le joueur marque le but en or. | Endurance | S | **app2, app3** |
| `powerplay` | **Supériorité · Powerplay** | É, J | Marque en supériorité numérique (une médaille par but). | Supériorité numérique | T | **app2, app3** |
| `shorthanded` | **Infériorité · Shorthanded** | É, J | Marque en infériorité numérique (une médaille par but). | Supériorité numérique | T | — (admin) |
| `penalty_kill` | **Résistance · Penalty Kill** | É | Traverse au moins *2* exclusions sans encaisser de but pendant celles-ci. | Supériorité numérique | T | — (admin) |
| `hat_trick` | **Triplé · Hat-trick** | J | Marque au moins *3* buts dans le match. | Finition | S | **app2, app3** |
| `natural_hat_trick` | **Triplé consécutif · Natural Hat-trick** | J | Marque *3* buts consécutifs du match, sans autre but entre eux. | Finition | T | — (admin) |
| `quadruple` | **Quadruplé · Four-goal Haul** | J | Marque au moins *4* buts dans le match. | Finition | S | — (admin) |
| `natural_quadruple` | **Quadruplé consécutif · Natural Four-goal Haul** | J | Marque *4* buts consécutifs du match, sans autre but entre eux. | Finition | T | — (admin) |
| `clean_sheet` | **Cage inviolée · Clean Sheet** | É | N'encaisse aucun but. | Défense | S | **app2, app3** |
| `big_win` | **Large victoire · Big Win** | É | Gagne avec au moins *5* buts d'écart. | Défense | S | — (admin) |
| `fair_play` | **Fair-play · Fair Play** | É | Ne reçoit aucun carton dans le match. | Esprit sportif | S | **app3** |

**Règles communes** : les faits de la période `TB` (tirs au but) ne comptent pour aucune médaille ; les buts d'un
membre marqué entraîneur (`kp_match_joueur.Capitaine = 'E'`) et les buts sans joueur identifié rapportent la
médaille d'équipe, jamais celle de joueur. Aucune médaille « négative » : on ne distingue jamais une équipe pour ce
qu'elle a subi.

### 3.3 Étiquettes de match

| Code | Nom FR · EN | Explication | Données | Publication initiale |
|---|---|---|---|---|
| `thriller` | **Suspense · Thriller** | Au moins *3* changements de leader (§ 5.5). | T | **app2, app3** |
| `seesaw` | **Chassé-croisé · Seesaw** | Au moins *4* égalisations. | T | — (admin) |
| `goal_fest` | **Festival · Goal Fest** | Total de buts parmi les *10 %* les plus élevés de la compétition (au moins *10* matchs éligibles). | S | — (admin) |
| `chess_match` | **Partie d’échecs · Chess Match** | Au plus *2* buts dans le match : un match tactique, où chaque but se joue comme un coup. | S | — (admin) |

### 3.4 Statistiques de timeline (compétition, événement)

Chaque statistique est un fournisseur `CompetitionStatInterface` (API_PUBLIC_RESULTS.md § 5.4), sur le périmètre
d'une compétition ou d'un événement. Ne comptent que les matchs éligibles ; celles qui reposent sur des éléments **T**
ne comptent que les matchs à timeline fiable et indiquent **sur combien de matchs** elles portent.

| Kind | Nom FR · EN | Explication (calcul) | Publication initiale |
|---|---|---|---|
| `medals_teams` | **Médailles des équipes · Team Medals** | Par équipe : nombre de médailles par code et total. | **app3** |
| `medals_players` | **Médailles des joueurs · Player Medals** | Par joueur : idem, top 20 (`limit`). | **app2, app3** |
| `opener_wins` | **Premier but gagnant · First Goal Wins** | Part des matchs gagnés par l'équipe qui ouvre le score (nuls à part). | **app3** |
| `powerplay_rate` | **Supériorité % · Powerplay %** | Par équipe : buts en supériorité / exclusions adverses. | **app3** |
| `penalty_kill_rate` | **Résistance % · Penalty Kill %** | Par équipe : exclusions sans but encaissé / exclusions. | — (admin) |
| `goal_timing` | **Minutes des buts · Goal Timing** | Répartition des buts par tranche de *2 min* de jeu (histogramme). | — (admin) |
| `first_goal_time` | **Délai du premier but · First Goal Time** | Délai moyen et médian avant le premier but ; but le plus rapide. | — (admin) |
| `comebacks` | **Remontées · Comebacks** | Nombre de remontées, plus grosse remontée (écart, match). | — (admin) |
| `overtimes` | **Prolongations · Extra Time** | Matchs en prolongation, plus grand nombre de prolongations, buts en or. | — (admin) |
| `discipline` | **Discipline · Discipline** | Cartons par match et par équipe, minutes d'infériorité. | — (admin) |
| `highlights` | **Matchs à revoir · Highlights** | Matchs étiquetés (`thriller`, `seesaw`, `goal_fest`), par date. Ne liste que les étiquettes publiées sur la même surface. | — (admin) |
| `medals_{code}` | (nom de la médaille) | Classement d'une médaille donnée (ex. meilleurs buteurs en supériorité). Suit les surfaces de la médaille. | — |

### 3.5 Arbitres

Les arbitres reçoivent des médailles et ont leurs statistiques, **dans le même esprit** : rien n'est saisi en plus,
tout vient de la timeline et des désignations.

**Deux principes**, rappelés dans l'explication de chaque élément :

- **Les faits du match sont ceux du binôme.** La timeline ne dit pas quel arbitre a sorti un carton : chaque carton
  (et chaque fait de match) d'un match compte **pour l'arbitre principal et pour l'arbitre secondaire**. Une
  statistique d'arbitre décrit donc **les matchs qu'il a arbitrés**, pas ses décisions personnelles. Le rôle
  (principal / secondaire) n'est connu qu'à l'échelle du match : on peut compter les matchs par rôle, pas les
  cartons par rôle.
- **Ni palmarès de sévérité, ni classement par cartons.** Les statistiques d'arbitres sont triées par nombre de
  matchs arbitrés puis par nom ; les indicateurs de cartons sont présentés **à côté de la moyenne du périmètre**, pour
  l'accompagnement des arbitres, jamais comme un classement. Un indicateur par arbitre n'est affiché qu'à partir de
  *5* matchs arbitrés sur le périmètre (en dessous : « — », pas assez de matchs).

#### Médailles d'arbitre

Attribuées à chaque arbitre désigné du match (portée Ar = arbitre).

| Code | Nom FR · EN | Portée | Explication (attribution) | Famille | Données | Publication initiale |
|---|---|---|---|---|---|---|
| `ref_smooth_game` | **Match fluide · Smooth Game** | Ar | Arbitre un match sans aucun carton. | Arbitrage | S | **app2, app3** |
| `ref_calm_game` | **Calme plat · Dead Calm** | Ar | Arbitre un match sans aucun but ni aucun carton. | Arbitrage | S | — (admin) |
| `ref_knockout` | **Match couperet · Knockout** | Ar | Arbitre un match à élimination directe (type `E`). | Arbitrage | S | **app2, app3** |
| `ref_extra_time` | **Prolongation · Extra Time** | Ar | Arbitre un match qui va en prolongation. | Arbitrage | S | **app2, app3** |
| `ref_thriller` | **Suspense · Thriller** | Ar | Arbitre un match étiqueté *Suspense* (§ 3.3). | Arbitrage | T | **app2, app3** |
| `ref_golden_goal` | **But en or · Golden Goal** | Ar | Arbitre un match décidé par un but en or. | Arbitrage | S | **app2, app3** |

#### Statistiques d'arbitrage

Sur le périmètre d'une compétition ou d'un événement (et, dans l'admin, d'une saison et d'un ensemble de
compétitions, comme la page Statistiques). Une ligne par arbitre désigné.

| Kind | Nom FR · EN | Explication (calcul) | Données | Publication initiale |
|---|---|---|---|---|
| `referee_games` | **Matchs arbitrés · Games Officiated** | Matchs arbitrés : total, dont principal et secondaire, dont matchs couperets et prolongations. | S | **app3** |
| `referee_cards` | **Cartons par match · Cards per Game** | Cartons des matchs arbitrés (vert, jaune, rouge, rouge définitif) par match, avec la moyenne du périmètre. | S | — (admin) |
| `referee_smooth_rate` | **Matchs fluides · Smooth Games** | Part des matchs arbitrés sans aucun carton, avec la moyenne du périmètre. | S | — (admin) |
| `referee_card_timing` | **Moment des cartons · Card Timing** | Répartition des cartons des matchs arbitrés par tranche de *2 min* de jeu ; délai moyen avant le premier carton. | T | — (admin) |
| `referee_card_balance` | **Répartition entre équipes · Card Balance** | Écart moyen, par match, entre les cartons de l'équipe A et de l'équipe B, avec la moyenne du périmètre. | S | — (admin) |
| `referee_card_reasons` | **Motifs des cartons · Card Reasons** | Répartition des cartons des matchs arbitrés par motif (`motif`), sur les cartons dont le motif est saisi ; affiche la part de motifs saisis. | S | — (admin) |
| `referee_powerplay` | **Impact des exclusions · Powerplay Impact** | Part des buts des matchs arbitrés marqués en supériorité numérique, avec la moyenne du périmètre. | T | — (admin) |
| `referee_pairs` | **Binômes · Referee Pairs** | Binômes d'arbitres désignés ayant arbitré ensemble : nombre de matchs, cartons par match. Ne compte que les matchs où les deux sont désignés. | S | — (admin) |
| `medals_referees` | **Médailles des arbitres · Referee Medals** | Par arbitre : nombre de médailles d'arbitre par code. | S | **app3** |

## 4. Premier lot public, par surface

Une sélection courte, facile à comprendre et peu sensible aux approximations de temps, répartie selon l'usage de
chaque application :

- **app2 — l'instant du match.** Consultée sur téléphone pendant le tournoi, match par match : on y montre ce qui
  raconte **le match qui vient de se jouer** et l'événement en cours.
- **app3 — la saison et l'analyse.** Consultée à froid, pour une compétition, une équipe ou un club : on y montre
  les **bilans** et les **indicateurs** qui demandent du recul.

| Élément | app2 | app3 | Pourquoi |
|---|:-:|:-:|---|
| Premier but · Opener | ✓ | ✓ | Simple et parlant partout ; base de « Premier but gagnant ». |
| Remontée · Comeback | ✓ | ✓ | Fait marquant du match, et trace dans le bilan d'équipe. |
| But de la victoire · Game Winner | ✓ | | Médaille de joueur, surtout lue sur la feuille de match. |
| But en or · Golden Goal | ✓ | ✓ | Moment fort du match, et rareté dans le bilan. |
| Supériorité · Powerplay | ✓ | ✓ | Sur le match (le but) et dans le bilan (base de « Supériorité % »). |
| Triplé · Hat-trick | ✓ | ✓ | Exploit individuel, lisible partout. |
| Cage inviolée · Clean Sheet | ✓ | ✓ | Exploit collectif, lisible partout. |
| Fair-play · Fair Play | | ✓ | Prend son sens en cumul sur la saison (valeur de club) plutôt que match par match. |
| Suspense · Thriller | ✓ | ✓ | Pastille du match dans app2 ; repère « match à revoir » dans les listes d'app3. |
| Médailles des joueurs · Player Medals | ✓ | ✓ | Classement de l'événement en direct (app2) et de la compétition (app3). |
| Médailles des équipes · Team Medals | | ✓ | Bilan d'équipe, plutôt saisonnier. |
| Premier but gagnant · First Goal Wins | | ✓ | Indicateur d'analyse, demande un volume de matchs. |
| Supériorité % · Powerplay % | | ✓ | Indicateur d'analyse, demande un volume de matchs. |
| Matchs arbitrés · Games Officiated | | ✓ | Met en valeur l'engagement des arbitres, sans jugement sur leurs décisions. |
| Médailles d'arbitre (*Match fluide*, *Match couperet*, *Prolongation*, *Suspense*, *But en or*) | ✓ | ✓ | Sur la feuille de match, à côté des arbitres (app2) ; dans les bilans (app3). |
| Médailles des arbitres · Referee Medals | | ✓ | Bilan des arbitres de la compétition, trié par nombre de matchs. |

Tout le reste naît non publié, **en particulier les autres statistiques d'arbitrage** (cartons, moment des cartons,
répartition, motifs, impact des exclusions, binômes), réservées à l'accompagnement des arbitres dans l'admin. Publier un élément sur une surface, l'en retirer ou le retirer complètement est un
changement dans l'admin (§ 9.3), sans déploiement ni recalcul.

## 5. Règles de calcul

### 5.1 Éligibilité et fiabilité

Un match **attribue des médailles** si :

1. il est **validé** (`Validation = 'O'`) et **publié** (match, journée et compétition publiés) ;
2. son score est numérique des deux côtés (pas de forfait ni de score non saisi) ;
3. il a deux équipes.

Les matchs de placement et de classement sont traités comme les autres.

Sa **timeline est fiable** (éléments **T** autorisés) si, en plus :

4. le nombre de buts `B` hors `TB` de chaque équipe est égal à son score (`ScoreA`, `ScoreB`) ;
5. chaque but et chaque carton hors `TB` a une période et un temps renseignés ;
6. chaque période est connue (`M1`, `M2`, `P1`, `P2`…). Un temps `00:00:00` compte comme **non saisi** : le legacy
   enregistre « `00:` + temps saisi », un temps vide y devient donc `00:00:00` (décision d'implémentation, § 12).

Un match éligible dont la timeline n'est pas fiable n'attribue **que les médailles S**. L'API l'indique
(`timeline_reliable: false`) pour que l'affichage l'explique, sans jamais l'imputer à une équipe.

### 5.2 Axe de temps

- Ordre des périodes : `M1`, `M2`, `P1`, `P2`, … `Pn` (non borné, cf. PAGE_SCORING.md § 0.6). `TB` est hors axe.
- **Durée d'une période** (non stockée) : *600 s* pour une mi-temps, *300 s* pour une prolongation, **allongée** à la
  minute pleine supérieure au plus grand temps restant saisi dans la période — même règle que la frise d'app2
  (`sources/app2/utils/gameTimeline.ts`), à **mutualiser** plutôt qu'à dupliquer.
- Temps écoulé d'un fait = somme des durées des périodes précédentes + (durée de sa période − temps restant).
- Les fenêtres **de fin de période** (*Dernière minute*, *Égalisation salvatrice*) se lisent directement sur le temps
  restant : elles sont exactes quel que soit le format.
- À temps égal : carton avant but, puis ordre de saisie (`date_insert`, puis `Id`).

> **Approximation acceptée (décision du 08/11/2026)** : les formats courts (7 min par mi-temps, 3 min par
> prolongation, surtout chez les jeunes) sont calculés avec les durées par défaut. Les écarts **dans une même
> période** restent exacts ; les écarts **entre deux périodes** et *Entrée express* sont approximatifs pour ces
> matchs. Les proportions restant proches, c'est jugé suffisant.

### 5.3 Exclusions et supériorité numérique

- Un carton **vert, jaune ou rouge** (`V`, `J`, `R`) ouvre une **exclusion de 120 s** de temps de jeu, qui se
  poursuit d'une période à l'autre et se **termine plus tôt** au premier but encaissé par l'équipe exclue.
- Un carton **rouge définitif** (`D`, futur **carton noir**) ouvre une exclusion **jusqu'à la fin du match**,
  prolongations comprises, jamais levée par un but. La liste des codes « définitifs » est un paramètre
  (`permanentCards`, § 5.4) pour accueillir le futur code du carton noir sans changer les règles.
- Si l'équipe a plusieurs exclusions temporaires en cours, un but adverse lève **la plus ancienne**.
- Une équipe ne descend jamais sous 3 joueurs : au plus **2 exclusions comptées** par équipe à un instant donné.

Un but est **en supériorité** si, à l'instant du but, l'adversaire a strictement plus d'exclusions en cours que
l'équipe qui marque ; **en infériorité** dans le cas inverse ; ni l'un ni l'autre à égalité.

### 5.4 Seuils (un seul endroit)

Tous les seuils sont réunis dans **un seul fichier de configuration d'api2** (`config/packages/match_medals.yaml`, lu par un
objet `MedalThresholds`), **communs à toutes les compétitions**. Aucun seuil n'est écrit ailleurs (ni dans les règles,
ni dans les explications, qui les citent par variable). Valeurs initiales :

| Paramètre | Valeur | Utilisé par |
|---|---|---|
| `bounceBackSeconds` | 30 | `bounce_back` |
| `comebackDeficit` | 3 | `comeback`, `fightback` |
| `scoringRunGoals` | 3 | `scoring_run` |
| `lateWindowSeconds` | 30 | `late_winner`, `lifeline` |
| `fastStartSeconds` | 30 | `fast_start` |
| `hatTrickGoals` | 3 | `hat_trick`, `natural_hat_trick` |
| `quadrupleGoals` | 4 | `quadruple`, `natural_quadruple` |
| `bigWinMargin` | 5 | `big_win` |
| `penaltyKillExclusions` | 2 | `penalty_kill` |
| `marathonOvertimes` | 2 | `marathon` |
| `exclusionSeconds` | 120 | § 5.3 (`V`, `J`, `R`) |
| `permanentCards` | `[D]` | § 5.3 |
| `regularPeriodSeconds` / `extraPeriodSeconds` | 600 / 300 | § 5.2 |
| `thrillerLeadChanges` / `seesawEqualizers` | 3 / 4 | étiquettes |
| `goalFestTopPercent` / `goalFestMinGames` / `chessMatchMaxGoals` | 10 / 10 / 2 | étiquettes |
| `goalTimingSliceSeconds` | 120 | `goal_timing`, `referee_card_timing` |
| `refereeMinGames` | 5 | statistiques d'arbitrage (§ 3.5) |

**Modification depuis l'admin (11/11/2026, profil 1 seulement)** : les seuils actuels sont ceux du fichier
`match_medals.yaml` **surchargés par ceux enregistrés dans l'admin** (table `kp_medal_setting`). Chaque valeur est
validée (entier dans ses bornes ; `permanentCards` : codes de carton `V`, `J`, `R`, `D`). « Rétablir » supprime les
remplacements et revient au fichier. Chaque changement est journalisé (`kp_journal`, avant / après).

Modifier un seuil ne change rien tant qu'on ne recalcule pas une saison (§ 6.3).

### 5.5 Définitions dérivées

- **Leader** : équipe qui mène. Un **changement de leader** est un but qui donne l'avantage à l'équipe qui ne l'avait
  pas au dernier moment où une équipe menait (une égalisation n'en est pas un).
- **But décisif** : pour le vainqueur, le but qui porte son score à (score final du perdant + 1). Aucun pour un nul.

### 5.6 Arbitres

- Un match compte pour un arbitre si le match est éligible (§ 5.1) et que l'arbitre y est **désigné** :
  `Matric_arbitre_principal` ou `Matric_arbitre_secondaire` égal à son numéro de licence (> 0). Un match dont aucun
  arbitre n'est désigné ne compte pour aucune statistique d'arbitrage ; un match dont un seul l'est compte pour lui
  seul (et pas pour `referee_pairs`).
- Le même numéro de licence en principal et en secondaire (erreur de saisie) compte **une fois**.
- Chaque fait de match (hors `TB`) du match compte pour chacun de ses arbitres désignés. Les éléments **T** ne
  comptent que les matchs à timeline fiable, et indiquent sur combien de matchs ils portent.
- Les **moyennes du périmètre** se calculent par match (chaque match compte une fois, quel que soit le nombre
  d'arbitres désignés), sur les matchs ayant au moins un arbitre désigné.
- Identité publique : prénom et nom de l'arbitre (déjà publics sur les feuilles de match), identifiant public opaque,
  **jamais** le numéro de licence.

## 6. Données (api2)

### 6.1 Calcul

- Un **service de domaine pur** (`src/MatchMedals/`) prend un match (score, type, période finale), sa timeline et des
  seuils, et renvoie ses médailles et étiquettes. Sans accès base ni horloge : testé unitairement règle par règle.
- Chaque élément est une **règle** indépendante (`MedalRuleInterface` : `code()`, `type()`, `scopes()`,
  `dataLevel()`, `award(MatchTimeline, MedalThresholds)`), enregistrée par étiquette de service Symfony. Ajouter un
  élément = une classe, sa ligne de catalogue (migration) et sa ligne dans cette spec.

### 6.2 Tables

- **`kp_medal_catalog`** : `code` (clé), `type`, `scopes`, `family`, `data_level`, `name_fr`, `name_en`,
  `explanation_fr`, `explanation_en`, `status` (`active`/`retired`), `published_app2`, `published_app3`,
  `icon`, `sort_order`, `updated_at`, `updated_by`. Initialisée par migration ; modifiable dans l'admin (noms, explications,
  statut, surfaces, ordre).
- **`kp_medal_season`** : `season` (clé), `thresholds` (JSON, copie des seuils), `rules_version`, `computed_at`,
  `games_count`, `reliable_games_count`.
- **`kp_match_medal`** : `id`, `match_id`, `code`, `scope` (`team`/`player`/`referee`), `team_side` (`A`/`B`, NULL
  pour un arbitre), `team_id`, `person_matric` (joueur ou arbitre ; NULL pour l'équipe), `event_detail_id` (but à
  l'origine), `game_seconds`. Index sur `match_id`, `team_id`, `person_matric`, `code`.
- **`kp_match_card`** (une ligne par carton d'un match calculé, dérivée de la timeline) : `match_id`, `event_detail_id`,
  `team_side`, `code`, `motif`, `period`, `game_seconds` (NULL si la timeline n'est pas fiable). Sert les
  statistiques de discipline et d'arbitrage sans recalculer l'axe de temps.
- **`kp_match_referee`** (une ligne par arbitre désigné d'un match calculé) : `match_id`, `referee_matric`, `role`
  (`principal`/`secondaire`). Copie des désignations au moment du calcul ; entre dans `source_hash`, de sorte qu'un
  changement de désignation sur un match validé est repris par `sync`.
- **`kp_match_insight`** (une ligne par match calculé) : `match_id`, `season`, `timeline_reliable`, `labels` (JSON),
  `lead_changes`, `equalizers`, `first_goal_seconds`, `max_deficit_A`, `max_deficit_B`, `exclusions_A`,
  `exclusions_B`, `powerplay_goals_A`, `powerplay_goals_B`, `penalty_killed_A`, `penalty_killed_B`, `source_hash`,
  `computed_at`.

Migration SQL datée `SQL/migrations/2026-11-09_public_site_match_medals.sql` (idempotente, comme celle du module
éditorial, chargée aussi par les fixtures de test), tables préfixées `kp_` comme le reste du schéma.

### 6.3 Recalcul saison par saison

Chaque saison est calculée avec **ses** seuils, copiés dans `kp_medal_season` au moment de son calcul complet.

- **Calcul complet d'une saison** — commande `app:match-medals:compute --season=2026` (cible
  `make api2_match_medals_compute season=2026`) : copie les seuils actuels et la `rules_version` du code dans
  `kp_medal_season`, puis recalcule tous les matchs éligibles de la saison et supprime les résultats des matchs qui ne
  le sont plus. Les **autres saisons ne sont pas touchées**.
- **Mise à jour au fil de l'eau** — à la **validation / dévalidation** d'un match dans api2 (`AdminGamesController`)
  et par la commande `app:match-medals:sync` lancée **par cron** (toutes les 10 min, couvre la validation faite dans
  le legacy) : calcule les matchs validés sans résultat ou dont `source_hash` (score + timeline + arbitres désignés) a changé, supprime
  ceux qui ne sont plus validés. Elle utilise **les seuils de la saison du match** ; une saison jamais calculée
  reçoit d'abord sa copie des seuils actuels.
- **Depuis l'admin** (profil 1, après confirmation) : *Recalculer* une saison fait exactement le calcul complet
  ci-dessus ; *Supprimer les calculs* d'une saison efface ses médailles, étiquettes, cartons, désignations et sa
  ligne `kp_medal_season` (les pages publiques n'en montrent plus). Une saison supprimée est **mémorisée**
  (`kp_medal_setting`) : ni `sync` ni la validation d'un match ne la recalculent, seul un calcul complet la remet en
  service.
- **Mise en service** : un calcul complet de **chaque saison**, une par une, de la plus récente à la plus ancienne.
- **Changer un seuil** : depuis l'admin (§ 9.3, profil 1), ou en modifiant `config/packages/match_medals.yaml` et en déployant ; puis lancer le calcul complet des seules
  saisons voulues (en général la saison en cours). Les saisons passées gardent leurs résultats et leurs seuils, et
  leurs explications continuent de citer les seuils qui les ont produits.
- **Changer une règle dans le code** : incrémenter `rules_version` ; l'admin signale les saisons calculées avec une
  version antérieure (§ 9.3), sans recalcul automatique.

### 6.4 Endpoints

Lecture seule, mêmes règles de publication que les résultats. **Aucun numéro de licence** dans les réponses
publiques (identifiant joueur public opaque, comme `scorers`). Les endpoints publics partagés prennent un paramètre
**`surface=app2|app3`** (obligatoire, 400 sinon) et ne renvoient **que les éléments publiés sur cette surface** ; un
endpoint propre à une application (feuille de match → app2, fiche équipe → app3) applique sa surface d'office. Les
endpoints admin renvoient tous les éléments actifs, avec leurs surfaces.

| Endpoint | Contenu | Consommateurs |
|---|---|---|
| `GET /medals/catalog?surface=&season=` | éléments publiés sur la surface : code, type, famille, noms, explication **rendue avec les seuils de la saison** | app3, app2 |
| `GET /game-sheet/{id}` (existant) | **ajoute** `medals: { reliable, labels: [...], team: [{ code, side, count }], player: [{ code, side, number, first_name, last_name, event_id }] }`, éléments publiés sur app2 ; vide si le match n'est pas validé | app2 |
| `GET /competition/{season}/{code}/stats?surface=` (existant) | `kinds` **s'enrichit** des statistiques publiées sur la surface (§ 3.4) | app3, app2 |
| `GET /competition/{season}/{code}/stats/{kind}?surface=` | format commun `{ kind, columns, rows }` + `games_count` / `reliable_games_count` ; kind non publié sur la surface → 404 | app3, app2 |
| `GET /event/{id}/stats?surface=` et `…/stats/{kind}?surface=` | mêmes statistiques sur un événement | app3, app2 |
| `GET /competition/{season}/{code}/stats/referee_*`, `GET /event/{id}/stats/referee_*` | statistiques d'arbitrage, même format commun ; aucune ligne pour un arbitre sous `refereeMinGames` sur les indicateurs de cartons | app3, app2 |
| `GET /team/{id}/medals?season=` | médailles de l'équipe publiées sur app3 sur la saison, par code, avec les matchs concernés | app3 |
| `GET /event/{eventId}/team/{teamId}/medals`, `GET /group/{season}/{groupCode}/team/{teamId}/medals` et `GET /competition/{season}/{code}/team/{teamId}/medals` | médailles d'une équipe sur le périmètre : `{ team: [{ code, count }], players: [{ first_name, last_name, number, medals: [{ code, count }] }] }`, éléments publiés sur app2 (`teamId` = `kp_competition_equipe.Id`, comme les statistiques de joueurs existantes ; l'équipe est retrouvée dans les autres compétitions par son numéro) ; `eventId` ≥ 3000 désigne une journée (contrat historique d'app2) ; le point d'accès par compétition sert aux tableaux par compétition d'un groupe | app2 |
| `GET /admin/medals/catalog`, `PATCH /admin/medals/catalog/{code}` | catalogue complet ; modification des noms, explications, statut, surfaces, ordre (profils 1–2) | app4 |
| `GET /admin/medals/seasons` | saisons calculées : seuils figés, `rules_version` (et retard éventuel), date, nombre de matchs ; **seuils actuels**, valeurs d'origine du fichier, remplacements, bornes ; saisons existantes non calculées (`uncomputed`) | app4 |
| `PUT /admin/medals/thresholds`, `DELETE /admin/medals/thresholds` | enregistre des seuils (partiels) / rétablit les valeurs du fichier ; **profil 1** | app4 |
| `POST /admin/medals/seasons/{season}/compute`, `DELETE /admin/medals/seasons/{season}` | calcul complet de la saison avec les seuils actuels, **lancé en arrière-plan** (202, 409 si un calcul est déjà en cours ; son état `job` est dans `GET /seasons`) / suppression de ses calculs ; **profil 1** | app4 |
| `GET /admin/medals/stats/{kind}?season=&competitions=&event=` | toutes les statistiques actives, mêmes formats, avec leurs surfaces | app4 |
| `GET /admin/medals/games/{id}` | médailles et étiquettes actives d'un match, avec leurs surfaces | app4 |

Les endpoints admin exigent un JWT de profil ≤ 2 (`ROLE_…` équivalent) ; les autres profils reçoivent 403.

## 7. Affichage commun

- Une médaille s'affiche en **pastille** : pictogramme (§ 7.1),
  **nom double** en français (« Supériorité · Powerplay »), nom anglais seul en anglais, compteur si > 1 (« ×2 »),
  et **info-bulle** avec l'explication et, pour un but, son temps (« 2ᵉ mi-temps · 03:12 »).
- Couleur par **famille**, issue des jetons de la charte, jamais seule porteuse du sens (nom toujours écrit).
- Accessibilité : `aria-label` = nom + explication + compteur.

### 7.3 Blason

La pastille « disque de 28 px » est remplacée par un **blason** : écu français moderne (chef plat, côtés droits,
pointe arrondie) rempli de la **couleur de la famille**, liseré intérieur, chef éclairci, pictogramme clair
(foncé en thème sombre) occupant 60 % de la largeur. Le **filigrane « KPI »** se place en haut à gauche, à 55 %
d'opacité, il se lit à partir de 56 px et devient un simple motif en dessous. Le **compteur** (« ×3 ») est une
pastille à l'angle bas droit du blason. Tailles : 40 px (tableaux, mobile, nom en info-bulle), 56 px (défaut,
nom double dessous), 88 px (page d'explications). Même accessibilité que la pastille (§ 7). Maquette : `blasons`.

### 7.1 Pictogrammes

- **Dessinés exprès**, un par élément du catalogue (médailles, étiquettes, statistiques, arbitres), dans un style
  commun : trait simple, lisible à 16 px, monochrome (couleur portée par la famille), version claire et sombre par
  `currentColor`. Vocabulaire visuel sportif (kayak, pagaie, ballon, but, chrono, cartons), **jamais guerrier**.
- Livrés en **SVG** optimisés, servis par api2 (`/api2/medals/icons/{code}.svg`, fichiers statiques versionnés) et
  référencés par le catalogue (`icon`) : app2, app3 et app4 les affichent sans copie, et un nouvel élément arrive
  avec son pictogramme sans redéploiement des applications.
- Les pictogrammes sont dessinés dans [maquettes/src/medal_icons.py](maquettes/src/medal_icons.py), source unique
  dont `build.py` tire les fichiers SVG de [sources/api2/public/medals/icons/](../../../sources/api2/public/medals/icons/),
  servis par api2 (`/api2/medals/icons/{code}.svg`) et inclus en ligne par son catalogue (`icon_svg`). Planche : maquette `pictogrammes`. Tant qu'un élément n'a pas son pictogramme, il affiche le
  pictogramme générique de sa famille.
- **Ballon de kayak-polo** : dessin **plein** (la seule exception au trait), d'après le dessin de référence choisi le
  09/11/2026 : un disque dans la couleur de l'élément, dont les panneaux blancs sont découpés (règle *even-odd*,
  donc transparents sur tout fond). Les panneaux ont été tracés sur le modèle puis lissés ; les coutures sont
  légèrement épaissies pour rester visibles à 16 px. Des ballons groupés (*Triplé*) ne se touchent pas, sinon ils
  se confondent.
- Le code d'un élément est **unique dans tout le catalogue** (clé de `kp_medal_catalog`) : la statistique des
  prolongations s'appelle `overtimes`, pas `extra_time` (code de la médaille).

### 7.2 Symbole du but

Le pictogramme `goal` (ballon seul, `sources/api2/public/medals/icons/goal.svg`) remplace, partout où un but est représenté, le cercle
ou le disque actuel. Il prend la **couleur de l'équipe** (`currentColor`) ; sur une frise, le numéro du buteur
l'accompagne dans une petite étiquette.

| Application | Emplacement actuel | Changement |
|---|---|---|
| app2 | `components/GameEventIcon.vue` (disque `bg-bar` et cercle blanc), `components/GameTimelineBar.vue` (pastille numérotée) | ballon `goal` ; sur la frise, ballon + numéro |
| app4 | Scoring : bouton « But » et historique des faits (`components/scoring/EventButtons.vue`, `EventHistory.vue`) ; liste des matchs | ballon `goal` |
| app3 | futures pages de match, frises et listes de buts | ballon `goal` dès la conception |
| PDF (api2) | feuilles de match et récapitulatifs qui marquent les buts | ballon `goal` (SVG copié avec la charte, cf. `make pdf_tokens_sync`) |

Ce changement est **indépendant des médailles** : il peut être livré seul, avant elles. Chaque application le
reprend dans sa propre spec (`PAGE_SCORING.md`, feuille de match d'app2) au moment de l'implémenter.

## 8. Explications publiques

Chaque élément publié est expliqué **là où il apparaît** (info-bulle de la pastille, en-tête de la statistique) et
sur une **page d'explication** d'app3 : `/medals` (+ `/en/medals`), « Médailles et statistiques », qui liste les
éléments publiés sur app2 **ou** app3 (un élément visible dans app2 doit pouvoir y être expliqué) par famille avec leur pictogramme, leurs deux noms et leur explication. Un sélecteur de saison
affiche les explications avec les seuils de la saison choisie. L'onglet Stats et la feuille de match d'app2 y
renvoient par un lien « Comment sont attribuées les médailles ? ».

## 9. Surfaces

### 9.1 app2 (PWA résultats)

- **Feuille de match** (`/game/[id]`) : bloc « Médailles » sous le score, une colonne par équipe (médailles d'équipe
  puis de joueurs), étiquettes du match en tête. Sur la frise (`GameTimelineBar`), les buts médaillés portent un
  repère discret.
- Match non validé : bloc absent. Timeline non fiable : mention « Médailles chronologiques indisponibles (feuille
  incomplète) ».
- **Page équipe** (`/team/[team]`, périmètre de l'événement ou du groupe choisi) : bloc « Médailles de l'équipe »
  (blasons de 64 px, nom double, compteur) et colonne « Médailles » du tableau des statistiques (blasons de 40 px
  sans nom ; sous 600 px de large, sur une ligne sous le joueur). Dans ce tableau, le **nombre de buts**
  est le **ballon** (`goal`) avec la **même pastille** que les médailles (« ×13 ») ; un joueur sans but n'a pas de ballon.
- Statistiques de l'événement et du groupe : les kinds publiés sur app2 s'ajoutent à la liste existante (arbitrage
  compris, s'ils y sont publiés).
- Sous la ligne des arbitres : les médailles d'arbitre du match (une seule ligne, communes aux deux arbitres).
- Seuls les éléments publiés sur **app2** apparaissent.

### 9.2 app3 (site public)

- Seuls les éléments publiés sur **app3** apparaissent (sauf la page `/medals`, § 8).
- **Onglet Stats** de la compétition et de l'événement : les kinds publiés sur app3 dans le sélecteur générique, avec leur
  explication en en-tête.
- **Liste des matchs** : icône discrète à côté du score d'un match étiqueté (étiquettes publiées sur app3), avec info-bulle.
- **Onglet Stats** : une rubrique **Arbitrage** regroupe les statistiques d'arbitrage publiées, avec le rappel
  « Les cartons d'un match comptent pour ses deux arbitres ».
- **Fiche équipe** : section « Médailles de la saison » (médailles publiées sur app3, compteurs par médaille, lien vers les matchs).
- **Page `/medals`** (§ 8), ajoutée au menu une fois livrée (`ready` dans `app/utils/navigation.ts`).

### 9.3 app4 (admin2, profils 1 et 2)

Une page **Médailles** (menu Statistiques), réservée aux profils 1 et 2 :

- **Catalogue** : tous les éléments (tri par famille), avec type, niveau de données, statut, noms, explication et
  nombre d'attributions sur la saison du contexte. Édition des noms, explications (aperçu avec les seuils de la
  saison), ordre, **surfaces de publication** (cases app2 / app3) et **statut** (actif / retiré, et retour). Chaque changement est journalisé
  (`kp_journal`, comme les autres actions d'admin).
- **Saisons** : pour chaque saison calculée, les seuils utilisés (ceux qui diffèrent des seuils actuels sont
  signalés), la `rules_version` (alerte si antérieure à celle du code), la date du calcul, le nombre de matchs et de
  timelines fiables. **Profil 1 seulement** : *Seuils par défaut* modifiables (formulaire **replié par défaut**, affiché à la demande) (enregistrer, rétablir le fichier),
  *Recalculer avec les seuils actuels* et *Supprimer les calculs* par saison, chacun **après une confirmation qui
  dit ce qui va se passer** ; les saisons existantes sans calcul proposent *Calculer*. Le profil 2 voit tout en
  lecture seule.
- **Statistiques** : toutes les statistiques actives sur le périmètre du contexte de travail (saison,
  compétitions, événement), avec le même tableau générique que la page Statistiques (PAGE_STATISTIQUES.md) et un
  badge de surface (« app2 », « app3 » ou « non publié ») sur chaque élément.
- **Arbitrage** (profils 1 et 2 seulement, comme toute la page) : toutes les statistiques et médailles d'arbitres
  sur le périmètre du contexte, avec la moyenne du
  périmètre, et la liste des matchs écartés faute d'arbitre désigné (nombre et lien), pour suivre la qualité des
  désignations.
- **Match** : dans la fiche d'un match de la page Matchs, les médailles et étiquettes actives, avec leur badge
  de surface.

Les profils au-delà de 2 ne voient ni la page ni les éléments non publiés. La spec de page admin détaillée
(`DOC/specs/PAGE_MEDAILLES.md`) sera dérivée de cette section avant implémentation.

## 10. Critères d'acceptation

**Éligibilité**

- **MED-01** — Étant donné un match terminé non validé, quand on demande sa feuille, alors `medals` est vide et
  aucune ligne n'existe dans `kp_match_medal`.
- **MED-02** — Étant donné un match validé dont le total des buts de A diffère de `ScoreA`, quand on calcule, alors
  `timeline_reliable = false` et seules les médailles **S** sont attribuées.
- **MED-03** — Étant donné un match validé dont un but n'a pas de temps, quand on calcule, alors
  `timeline_reliable = false`.
- **MED-04** — Étant donné un match dont un score est un forfait (non numérique), alors aucune médaille.
- **MED-05** — Étant donné des buts en période `TB`, alors ils ne comptent pour aucune médaille ni pour la fiabilité.
- **MED-06** — Étant donné un match de placement validé, alors il attribue des médailles comme un autre match.

**Axe de temps et exclusions**

- **MED-07** — Étant donné un but en `M2` avec 9:10 restant, alors son temps écoulé est 650 s ; avec un temps restant
  de 11:30 en `M2`, la durée retenue de `M2` est 720 s.
- **MED-08** — Étant donné un carton jaune de B à 05:00 restant en `M1` et un but de A à 04:00 restant, alors le but
  de A est `powerplay` et un but de B à 03:30 n'est pas en infériorité (exclusion levée).
- **MED-09** — Étant donné un carton vert de B à 05:00 restant en `M1` et un but de A à 02:50 restant, alors le but
  n'est pas `powerplay` (130 s > 120 s).
- **MED-10** — Étant donné un carton `D` de B en `M1` puis trois buts de A en `M2` et en `P1`, alors les trois buts
  sont `powerplay` (infériorité jusqu'à la fin du match, non levée par un but).
- **MED-11** — Étant donné `permanentCards` contenant un nouveau code de carton noir, alors ce carton se comporte
  comme `D` sans autre changement de code.
- **MED-12** — Étant donné un carton de B à 00:30 restant en `M1` et un but de A à 09:20 restant en `M2`, alors le
  but est `powerplay` (30 s + 40 s < 120 s).
- **MED-13** — Étant donné une exclusion de chaque côté en cours, quand A marque, alors le but n'est ni `powerplay`
  ni `shorthanded`.
- **MED-14** — Étant donné un carton de A et un but de A à la même seconde, alors le but est `shorthanded`.
- **MED-15** — Étant donné trois cartons temporaires de B en cours, alors seules 2 exclusions sont comptées.

**Médailles et étiquettes**

- **MED-16** — `opener` va à l'équipe et au joueur du premier but sur l'axe de temps.
- **MED-17** — Étant donné A mène 1-0, B égalise, puis A gagne 3-1, alors A n'a pas `wire_to_wire`.
- **MED-18** — Étant donné B mène 4-1 puis A gagne 5-4, alors A reçoit `comeback` ; avec un final 4-4 sans
  prolongation, A reçoit `fightback`.
- **MED-19** — Étant donné A encaisse à 06:00 restant et marque à 05:01 restant dans la même période, alors A et le
  buteur reçoivent `bounce_back` ; à 05:00 restant (60 s), non.
- **MED-20** — Étant donné A marque 4 buts consécutifs, alors une seule `scoring_run` ; deux séries de 3 séparées par
  un but de B donnent deux médailles.
- **MED-21** — Étant donné un match décidé en `P2`, alors les deux équipes reçoivent `extra_time` et `marathon`, le
  vainqueur et le buteur `golden_goal`.
- **MED-22** — Étant donné un match terminé 0-0, alors les deux équipes reçoivent `clean_sheet` ; une équipe sans
  carton reçoit `fair_play`.
- **MED-23** — Étant donné B exclue deux fois sans encaisser pendant ces exclusions, alors B reçoit `penalty_kill`.
- **MED-24** — Étant donné un joueur auteur de 3 buts, alors `hat_trick` (même sans timeline fiable) ; si ces buts
  sont consécutifs dans le match, aussi `natural_hat_trick`.
- **MED-25** — Étant donné A gagne 4-2, alors le 3ᵉ but de A porte `game_winner`.
- **MED-26** — Étant donné le but décisif à 00:45 restant en `M2`, alors `late_winner` ; une égalisation dans la même
  fenêtre d'un match qui va en prolongation porte `lifeline`. Le résultat est le même pour un match en mi-temps de
  7 min (fenêtre lue sur le temps restant).
- **MED-27** — Étant donné un but d'un membre `Capitaine = 'E'` ou sans joueur, alors médaille d'équipe seulement.
- **MED-28** — Étant donné un match avec 3 changements de leader, alors il reçoit l'étiquette `thriller`.

**Seuils et recalcul**

- **MED-29** — Étant donné un seuil modifié dans `config/packages/match_medals.yaml`, quand on recherche ce seuil ailleurs
  dans le code, des règles ou des explications, alors on ne le trouve pas (une seule définition).
- **MED-30** — Étant donné les saisons 2025 et 2026 calculées avec `bounceBackSeconds = 30`, quand le seuil passe à
  45 et qu'on lance le calcul complet de 2026, alors seules les médailles de 2026 changent, et `kp_medal_season`
  garde 30 pour 2025 et 45 pour 2026.
- **MED-31** — Étant donné un match de 2025 validé après le changement de seuil, quand `sync` passe, alors il est
  calculé avec les seuils de 2025.
- **MED-32** — Étant donné une saison jamais calculée, quand `sync` traite un de ses matchs, alors la saison reçoit
  d'abord la copie des seuils actuels.
- **MED-33** — Étant donné un match validé dans le legacy, quand `sync` passe, alors ses médailles existent ;
  repassée sans changement, elle ne réécrit rien (`source_hash` identique). Dévalidé, ses médailles disparaissent.
- **MED-34** — Étant donné une `rules_version` du code supérieure à celle d'une saison, alors l'admin signale la
  saison, et aucun recalcul n'a lieu sans commande.

**Catalogue, publication et explications**

- **MED-35** — Étant donné un élément actif sans surface, alors il n'apparaît dans aucune réponse publique
  (feuille, stats, catalogue, équipe) et son kind renvoie 404 ; il apparaît dans les endpoints admin.
- **MED-36** — Étant donné un élément non publié déjà calculé sur plusieurs saisons, quand un profil 1 le publie sur
  app3, alors il apparaît aussitôt dans app3 sur toutes ces saisons, sans recalcul.
- **MED-37** — Étant donné une statistique publiée sur app3 seulement, alors `…/stats?surface=app3` la liste,
  `…/stats?surface=app2` ne la liste pas et `…/stats/{kind}?surface=app2` renvoie 404 ; sans `surface`, 400.
- **MED-38** — Étant donné une médaille publiée sur app3 seulement (`fair_play`), alors elle est absente de la
  feuille de match app2 et présente sur la fiche équipe app3.
- **MED-39** — Étant donné un élément `retired`, alors il n'apparaît nulle part et n'est plus calculé.
- **MED-40** — Étant donné un profil 3 ou plus, alors la page Médailles d'app4 et les endpoints `/admin/medals/*`
  lui sont refusés (403).
- **MED-41** — Étant donné une explication contenant `{bounceBackSeconds}`, alors elle s'affiche avec 30 pour une
  saison calculée à 30 et 45 pour une saison calculée à 45.
- **MED-42** — Étant donné le site en français, alors une pastille affiche « Supériorité · Powerplay » ; en anglais,
  « Powerplay ».
- **MED-43** — Toute modification du catalogue dans l'admin est journalisée (auteur, élément, avant / après).
- **MED-44** — Aucune réponse publique ne contient de numéro de licence.

**Affichage**

- **MED-45** — Étant donné un match validé médaillé, la feuille de match app2 affiche le bloc « Médailles » ; chaque
  pastille a un nom écrit, une explication en info-bulle et un `aria-label`.
- **MED-46** — Étant donné une timeline non fiable, la feuille de match app2 affiche les médailles S et la mention
  « Médailles chronologiques indisponibles (feuille incomplète) ».
- **MED-47** — L'onglet Stats d'app3 affiche les statistiques publiées sur app3 avec leur explication et le nombre de matchs
  pris en compte, sans code spécifique autre que les libellés.
- **MED-48** — La page `/medals` d'app3 liste les éléments publiés sur app2 ou app3, et seulement eux, avec leurs deux noms et
  leur explication.
- **MED-49** — La fiche équipe d'app3 affiche les médailles de la saison publiées sur app3, chaque compteur menant aux matchs.

**Arbitres**

- **MED-50** — Étant donné un match validé dont `Matric_arbitre_principal` et `Matric_arbitre_secondaire` sont vides
  ou à 0 (arbitres saisis seulement en texte), alors il ne compte dans aucune statistique d'arbitrage ni médaille
  d'arbitre.
- **MED-51** — Étant donné un match avec 3 cartons, un arbitre principal désigné X et un secondaire désigné Y, alors
  `referee_cards` compte 3 cartons pour X **et** 3 pour Y ; la moyenne du périmètre compte ce match une fois (3).
- **MED-52** — Étant donné un match où seul le principal X est désigné, alors il compte pour X, pas pour
  `referee_pairs`.
- **MED-53** — Étant donné le même numéro de licence en principal et en secondaire, alors le match compte une fois
  pour cet arbitre.
- **MED-54** — Étant donné un arbitre avec 4 matchs arbitrés sur le périmètre (`refereeMinGames = 5`), alors ses
  indicateurs de cartons affichent « — » et il apparaît dans `referee_games`.
- **MED-55** — Les statistiques d'arbitrage sont triées par nombre de matchs arbitrés puis par nom, jamais par
  nombre de cartons.
- **MED-71** — Étant donné un joueur auteur de 4 buts, alors `quadruple` (en plus de `hat_trick`, les médailles
  « au moins N buts » se cumulent) ; avec 3 buts, pas de `quadruple`. Si les 4 buts se suivent sans but adverse ni
  d'un autre joueur entre eux, alors `natural_quadruple` en plus ; un but adverse entre eux l'écarte.
- **MED-72** — Étant donné un match 0-0 sans carton arbitré par X et Y, alors X et Y reçoivent `ref_calm_game` (et
  `ref_smooth_game`) ; un seul but ou un seul carton l'écarte. Un match sans temps saisi suffit (niveau score).
- **MED-56** — Étant donné un match sans carton arbitré par X et Y, alors X et Y reçoivent `ref_smooth_game`.
- **MED-57** — Étant donné une désignation d'arbitre modifiée sur un match validé, quand `sync` passe, alors ses
  statistiques et médailles d'arbitres sont recalculées (`source_hash` changé).
- **MED-58** — `referee_games` distingue les matchs comme principal et comme secondaire ; aucune statistique ne
  répartit les cartons par rôle.
- **MED-59** — Aucune réponse publique d'arbitrage ne contient de numéro de licence.

**Pictogrammes**

- **MED-60** — Chaque élément du catalogue a un pictogramme SVG servi par api2 ; un élément sans pictogramme dédié
  affiche celui de sa famille.
- **MED-61** — Dans app2, app3, le Scoring d'app4 et les PDF, un but est représenté par le pictogramme `goal`
  (ballon de kayak-polo) dans la couleur de son équipe, et non plus par un cercle ou un disque.

**Blason et page équipe d'app2** (10/11/2026)

- **MED-62** — Toute médaille s'affiche dans un blason plein de la couleur de sa famille, avec le filigrane « KPI »
  en haut à gauche et le compteur en pastille quand il dépasse 1 ; un nom de médaille reste accessible (`aria-label`,
  info-bulle).
- **MED-63** — Étant donné une équipe et un groupe (ou un événement), alors `…/team/{teamId}/medals` renvoie ses
  médailles d'équipe et de joueur publiées sur app2, sans numéro de licence ; une équipe inconnue renvoie 404.
- **MED-64** — Les médailles ne comptent que les matchs validés du périmètre demandé : un match d'une autre journée
  ou d'un autre événement n'y apparaît pas.
- **MED-65** — Étant donné un joueur de 13 buts sur la page équipe d'app2, alors le tableau montre le ballon avec la
  pastille « ×13 » ; avec 1 but, le ballon sans pastille ; sans but, rien.

**Seuils, recalcul et suppression depuis l'admin** (11/11/2026)

- **MED-66** — Étant donné le profil 1, quand il enregistre `bounceBackSeconds = 45`, alors les seuils actuels valent 45
  (les saisons déjà calculées gardent les leurs) et le changement est journalisé ; un profil 2 reçoit 403.
- **MED-67** — Un seuil hors de ses bornes, inconnu ou de mauvais type est refusé (400) sans rien enregistrer ;
  *Rétablir* revient aux valeurs du fichier.
- **MED-68** — Étant donné des seuils modifiés, quand le profil 1 recalcule la saison 2998, alors ses seuils figés sont
  les seuils actuels et ses médailles sont recalculées ; les autres saisons sont intactes.
- **MED-69** — Quand le profil 1 supprime les calculs de la saison 2998, alors plus aucune médaille, étiquette, carton,
  désignation ni ligne de saison n'existe pour elle, et ni `sync` ni la validation d'un match ne les recréent ; un
  calcul complet la remet en service.
- **MED-73** — Un calcul complet lancé depuis l'admin s'exécute dans un processus à part (la requête répond 202) ; un
  seul à la fois (409) ; son état (en cours, terminé avec le nombre de matchs, erreur) est relu par la page, qui peut
  être quittée ; un calcul « en cours » depuis plus d'une heure est signalé en erreur. Le journal nomme l'utilisateur.
- **MED-74** — Étant donné une feuille de match d'app2 ouverte depuis le site public, alors app2 se place sur le
  périmètre du match : l'événement du lien (`?event=`) s'il le contient, sinon la sélection actuelle si elle contient
  le match, sinon le **groupe** du match ; les autres matchs et les équipes auxquels on navigue ensuite sont ceux de ce
  périmètre. `GET /game-sheet/{id}` renvoie pour cela `context: { season, group, events }` (événements publiés).
- **MED-70** — L'admin demande une confirmation avant de recalculer ou de supprimer, et la dit en une phrase (saison,
  nombre de matchs, conséquence) ; le profil 2 ne voit aucun bouton d'action.

## 11. Hors périmètre et questions ouvertes

**Hors périmètre (lots suivants)** :

- Incrustations TV / live (« Premier but ! », « Supériorité » en direct) : demanderait le calcul sur matchs non
  validés.
- Fiche joueur publique et collection de carrière.
- Tirs (`T`) et arrêts (`A`) : médailles de gardien et d'efficacité dès que ces faits seront saisis régulièrement.
- Agrégats de saison toutes compétitions, palmarès historique des médailles, badges partageables.
- Statistiques par rôle d'arbitre (impossible sans saisie de l'arbitre auteur de chaque carton).
- Statistiques des autres officiels (secrétaire, chronométreur, juges de ligne).

**Questions ouvertes** : aucune (Q1 à Q5 tranchées les 08 et 09/11/2026, cf. § 1.1).

**Maquettes (validées les 09 et 10/11/2026, dont `blasons` et `app2-equipe`)** : [maquettes/](maquettes/README.md#médailles-et-statistiques-de-timeline) (`pictogrammes`, `medailles`,
`stats-medailles`, `equipe-medailles`, `app2-feuille`, `admin-medailles`, `blasons`, `app2-equipe`).

## 12. Implémentation (09/11/2026)

Livrée sur la branche `claude/optimistic-cray-9nl0r6` ; déploiement : [rollout checklist](../../developer/in-progress/MATCH_MEDALS_ROLLOUT_CHECKLIST.md).

| Brique | Où | Tests |
|---|---|---|
| Moteur | `sources/api2/src/MatchMedals/` (`MatchTimeline`, `Rule/*`, `MatchMedalCalculator`) | `tests/Unit/MatchMedals/` (MED-01 à 29, 53, 56) |
| Persistance, commandes | `MatchMedalRepository`, `MatchMedalService`, `app:match-medals:compute` / `:sync`, recalcul dans `AdminGamesController` | `tests/Integration/MatchMedalsPersistenceTest.php`, `MatchMedalsValidationTest.php` |
| Endpoints, statistiques | `Catalog/MedalCatalog`, `Stats/*` (20 statistiques), `MatchMedalReader`, `PublicMedalsController`, `AdminMedalsController` | `tests/Integration/MatchMedalsEndpointsTest.php` |
| app3 | `components/medal/` (dont `MedalShield.vue`), `pages/medals.vue`, `components/team/TeamMatchMedals.vue`, onglet Stats, liste des matchs | `tests/unit/medals.spec.ts`, `tests/nuxt/medals.spec.ts` |
| app2 | `components/GameMedals.vue`, `MedalPill.vue`, `MedalShield.vue`, `GoalCount.vue`, `TeamPlayerStats.vue`, `pages/team/[[team]].vue`, `GameEventIcon.vue` (ballon) | lint (app2 n'a pas de tests) |
| app4 | `pages/medals/index.vue` | `tests/unit/medals.spec.ts` |

**Écarts assumés par rapport aux sections précédentes** :

- **Page équipe d'app2** (§ 9.1) : un point d'accès par compétition s'ajoute à ceux de l'événement et du groupe, pour que chaque tableau d'un groupe ne montre que les médailles de sa compétition. Les joueurs des statistiques et des médailles se retrouvent par leur **nom** (aucun numéro de licence dans les réponses médailles). Le blason de **64 px** est utilisé pour les médailles d'équipe (la maquette annonçait aussi 56 px par défaut) ; sur la page d'explications, 88 px.

- **Surface** (§ 6.4) : sur les endpoints de compétition, `surface` vaut `app3` par défaut (au lieu d'un 400) pour
  qu'app3 reste déployable indépendamment ; une valeur inconnue ou `admin` renvoie 400. Les kinds des statistiques de
  timeline ne sont listés que si la compétition a au moins un match calculé (pas d'onglet vide).
- **Événement** (§ 3.4, § 6.4) : l'onglet Stats d'un événement montre la compétition choisie (comme les autres
  statistiques, EVT-07) ; pas d'endpoint `/event/{id}/stats` propre.
- **Étiquettes dans les listes d'app3** : endpoint dédié `/competition/{season}/{code}/labels` plutôt qu'un champ de
  plus dans le format des matchs partagé avec app2.
- **Pictogrammes** (§ 7.1) : le catalogue renvoie aussi le dessin en ligne (`icon_svg`), car un masque CSS chargé
  depuis une autre origine exigerait du CORS sur les fichiers statiques.
- **Admin** (§ 9.3) : page dans le groupe **Opérations** d'app4 (la rubrique Statistiques n'est pas un menu).
- **Discipline** (§ 3.4) : cartons par couleur et par match ; les minutes d'infériorité ne sont pas calculées.
- **Goal Fest** (§ 3.3) : décidé à la lecture (il dépend de toute la compétition), jamais stocké.

**Reste à faire** (lots suivants) :

- app2 : statistiques de l'événement et du groupe publiées sur app2 (pas d'endpoint d'événement, cf. ci-dessus).
- app4 : médailles d'un match dans la page Matchs (l'endpoint `/admin/medals/games/{id}` existe).
- Symbole du but (§ 7.2) : ballon dans le Scoring d'app4 et dans les PDF (fait dans app2).

