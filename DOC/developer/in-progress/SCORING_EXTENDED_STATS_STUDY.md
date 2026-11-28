# Étude — Statistiques de jeu étendues (possession, passes, tirs, arrêts, interceptions)

> **Statut** : étude / aide à la décision. Aucune ligne de code engagée.
> - v1 (2026-11-27) : contraintes, sources de données possibles, solutions graduées, questions Q1–Q8 ;
> - **v2 (2026-11-27)** : réponses Q1–Q8 (§2.2), **possession reconstruite sans touche nouvelle** (§3.3),
>   raccourci de correction (§3.4), **squelette pré-rempli par le chronomètre de tir et complété par les
>   bancs** (§4.1), recommandation révisée (§6), questions Q9–Q13 ;
> - **v3 (2026-11-27)** : réponses Q10–Q13 (§2.2) — le chronométreur de tir **ne regarde pas l'écran** :
>   `/` devient une commande **à sens fixe** « 60 s, même équipe » (§3.4) ; accès des bancs par le
>   **responsable d'équipe** (mandat profil 7), fenêtre de **24 h**, seuil de publication **80 %**
>   paramétrable, stats sous le seuil **masquées** ; `kp_stats` supprimé. Q9 en attente ;
> - **v4 (2026-11-27)** : réponses Q9, Q14, Q15 ; **inversion des côtés** (« switch ») de la console et du
>   panneau à chaque période (§3.5) et **touches spatiales** « 60 s gauche / 60 s droite » qui
>   s'en déduisent ; risque d'oubli du switch traité (§3.5.3) ; questions Q16–Q19 ;
> - **v5 (2026-11-27)** : réponses Q16–Q19 ; **changement de côté automatique** à chaque période ;
>   panneau inversé, incrustations fixes ; **recommandation : touches spatiales** et critère de
>   validation au pilote (§3.5.4) ;
> - **v6 (2026-11-27)** : spec du switch écrite ([PAGE_SCORING §0.18](../../specs/PAGE_SCORING.md),
>   SIDE-01 à 18) ; **correction** : `app_wsm_dev` est le relais de la console propriétaire (onglet WSM)
>   et reste jusqu'au lot 5 — seule sa page Stats est supprimée (§7) ; saisie sur console propriétaire
>   (§3.6) ;
> - **v7 (2026-11-28)** : un 60 s peut aussi suivre un **avantage** (faute non sifflée, geste
>   d'avantage) ; **décision : touches gauche / droite seules**, le mode relatif est abandonné ;
>   **pavé USB externe à 4 touches en carré** (§3.5.5) ;
> - **v9 (2026-11-28)** : réponses Q23–Q26 — touches `T` / `A` (FR) et `S` / `K` (EN), **numéro facultatif**
>   (tireur ou gardien), pas de troisième touche, tir contré = `T` ; `T` seul étudié et écarté (il perd les
>   arrêts dont la défense récupère le ballon, §4.0.1) ; spec écrite (PAGE_SCORING §0.19), table dédiée
>   `scoring_live_stat` au lieu de `scoring_live_event` (§7) ;
> - **v8 (2026-11-28)** : saisie par les bancs (§4.1) **déclassée** (architecture lourde, implication
>   inégale des bancs) ; **concession sur C3** : le secrétaire saisit les **tirs** et les **arrêts** avec
>   **deux touches**, l'équipe étant déduite de la possession (chronomètre de tir + switch) — nouvelle
>   solution retenue (§4.0) ; recommandation, modèle de données et questions revus (Q23–Q26).
>
> **Contexte** : console Scoring d'app4 ([PAGE_SCORING.md](../../specs/PAGE_SCORING.md), notamment
> §0.4 « fait de match extensible », §0.13 touches du chronomètre de tir, §6.5 chronomètre de tir),
> médailles de match ([FEATURE_MATCH_MEDALS.md](../../specs/public/FEATURE_MATCH_MEDALS.md)), afficheurs du
> chronomètre de tir ([SCORING_SHOTCLOCK_REMOTE_DISPLAYS_STUDY.md](SCORING_SHOTCLOCK_REMOTE_DISPLAYS_STUDY.md)).
> **Terminologie** : « fait de match » / « fait de jeu » (jamais « événement » seul, cf. PAGE_SCORING §0.4).

---

## 1. Objectif

Capturer, pendant ou après un match, des données qui ne figurent pas sur la feuille de match
officielle, pour :

1. produire des **statistiques** par équipe et par joueur ;
2. proposer plus tard de **nouvelles médailles** (ex. « Mur » pour un gardien, « Pressing » pour une
   équipe qui récupère beaucoup de ballons, « Efficacité » pour un buteur).

**Priorités** : Q5 donnait l'**intérêt** (1. arrêts, 2. tirs, 3. possession). **Revues le 2026-11-28 selon la
facilité de mise en place et d'exploitation** :

| Ordre | Donnée | Mise en place | Exploitation | Pourquoi ce rang |
|---|---|---|---|---|
| **1** | **Possession** (par équipe) | **aucune personne en plus** : touches gauche / droite du chronométreur de tir (§3.5), déjà là | calcul serveur, contrôlé par les buts (§3.5.3) ; **tous les matchs scorés** | coût d'organisation nul, couverture totale |
| **2** | **Tirs et arrêts** (par équipe) | **deux touches du secrétaire** (§4.0), l'équipe venant de la possession | contrôle par les 40 s du chronomètre de tir (§4.0) ; tous les matchs scorés | coût d'organisation nul (v8) ; ~~minorant par les seuls 40 s~~ |
| **3** | Arrêts et tirs **par joueur** | ~~bancs (§4.1, déclassé)~~ ; seulement si le numéro devient facultatif (Q24) | — | **repoussé** : aucune voie sans bénévole |
| 4 | Passes, interceptions | vidéo (§4.3) | lourde | repoussé |

| Donnée visée | Granularité utile | Exemples de stats dérivées |
|---|---|---|
| **Arrêts** | joueur | arrêts, % d'arrêts (arrêts / tirs cadrés subis) |
| **Tirs** | joueur, issue | tirs, tirs cadrés, efficacité (buts / tirs), tirs par possession |
| **Possession** | équipe | temps de possession, nombre de possessions, durée moyenne, pertes de balle |
| Passes | joueur (passeur, receveur) | passes réussies, passes décisives |
| Interceptions | joueur | interceptions, balles récupérées |

## 2. Contraintes et réponses

### 2.1 Contraintes

| # | Contrainte | Conséquence |
|---|---|---|
| C1 | **Le gardien n'est pas un joueur fixe.** C'est généralement un rôle dédié, mais pendant le match n'importe lequel des 5 joueurs peut l'être : le règlement désigne comme gardien **le joueur qui se tient sous le but, pagaie levée à la verticale**. | Un arrêt **ne peut pas** être imputé automatiquement au « gardien de la composition » : il faut l'imputer au **joueur qui a fait l'arrêt** (son numéro). Le rôle « gardien » est un **état instantané**, pas un attribut du joueur. |
| C2 | **Le chronométreur de tir est un rôle dédié**, avec un **statut d'arbitre**, qui **ne peut rien faire d'autre** que le chronomètre de tir. | Aucune saisie **en plus** de son rôle. Ce qu'il saisit **déjà** (départ 60 s, départ 40 s, arrêt) est une source de données (§3.2). |
| C3 | **Le secrétaire de table de marque** (scoring : chrono principal, buts, cartons, pénalités) est **déjà très occupé et concentré**. | ~~Aucune saisie supplémentaire.~~ **Concession du 2026-11-28** : **deux touches** (tir, arrêt), **un appui chacune**, sans joueur, sans temps, sans fenêtre — l'équipe vient de la possession (§4.0). Rien d'autre ne lui est demandé. |
| C4 | **Bénévoles supplémentaires rarement disponibles** à chaque table de marque. | Une solution qui exige une personne de plus **par terrain et par match** ne sera pas généralisable. |
| C5 | **Ne pas impacter le déroulement du match.** | Les stats étendues sont **non officielles** : jamais bloquantes, jamais sur la feuille de match, jamais dans `kp_match_detail`. |
| C6 | Le jeu est **rapide** : une possession dure au plus 60 s, les passes s'enchaînent en 1–3 s. | La saisie exhaustive des passes en direct est irréaliste. |
| **C7** | **Expérience 2022 (Q6)** : « KPI Stats » utilisé une fois, aux championnats du monde 2022, **avec des bénévoles dédiés** → **qualité trop faible et inégale** pour être exploitable. | Une personne dédiée **ne suffit pas** à garantir la qualité. Il faut : **peu de choses à saisir**, un **squelette déjà juste** fourni par la machine, des **contrôles de cohérence** et une **relecture** avant publication. |
| **C8** | **Publication immédiate et publique (Q8)**, **après le match seulement (Q7)**. | Pas de contrainte de temps réel : on peut **consolider et relire** entre la fin du match et la publication. Mais ce qui est publié doit être **fiable** : une stat qui ne passe pas les contrôles n'est pas publiée (ou publiée comme « incomplète »). |

### 2.2 Réponses (v2)

| # | Sujet | Réponse | Conséquence |
|---|---|---|---|
| Q1 | Chronomètre de tir orienté | **Oui**, si la saisie se fait **sur la console KPI** (pas sur une console propriétaire). Reste à trouver le **raccourci le plus cohérent**. | §3.3 et §3.4 : possession **reconstruite** sans touche nouvelle, plus **une** touche de correction. Avec une console propriétaire captée (étude afficheurs §5.3), on n'aura que 60 / 40 / arrêt, sans correction : la reconstruction §3.3 marche quand même. |
| Q2 | Règle du 40 s | **Oui** : chaque 40 s = un tir **arrêté par le gardien ou par le cadre du but**, rebond conservé par l'attaque. | Chaque 40 s est un **tir cadré non marqué** de l'équipe en possession, et un **arrêt ou poteau** de l'équipe en défense. |
| Q3 | Saisie par les bancs | **Oui, à creuser.** | §4.1 développé. |
| Q4 | Matchs filmés | **Part faible** pour l'instant. | Annotation vidéo (S3) **repoussée** : ne peut pas être la base. |
| Q5 | Priorités | **Arrêts, tirs, possession.** | Passes et interceptions sortent du premier lot. |
| Q6 | KPI Stats 2022 | Une fois (Mondiaux 2022), bénévoles dédiés, **qualité trop faible et inégale**. | C7. Statisticien volontaire (S2) **déclassé**. `kp_stats` : rien à conserver (à confirmer, Q13). |
| Q7 | Direct ou après | **Après le match seulement.** | C8 : place pour une **consolidation** et une **relecture**. |
| Q8 | Publication | **Publiques tout de suite.** | C8 : **contrôle qualité automatique** avant publication. |
| **Q9** | 60 s sans changement de possession | **Coup franc** (à l'équipe en possession) **ou avantage**. | Cas **fréquents** (les fautes le sont) : la commande « même équipe » serait d'usage courant, pas une exception. Renforce les touches spatiales (§3.5). |
| **Q10** | Regard du chronométreur de tir | **Concentré sur le match.** Il peut regarder son **pavé numérique déporté** (ou équivalent), **pas l'écran** de la console. | Aucune commande ne peut dépendre d'un état affiché à l'écran : `/` prend un **sens fixe** (§3.4) ; pas de pastille à surveiller. |
| **Q11** | Accès des bancs | **Rattaché au responsable d'équipe**, fenêtre de **24 h**. | §4.1 : mandat **profil 7** issu des désignations (FEATURE_DESIGNATIONS D8, MAN-09) ; délégation possible à un membre du banc (Q14). |
| **Q12** | Seuil de couverture | **80 %, paramétrable.** En dessous : **masquée**. | §4.1 : paramètre unique, à côté des seuils des médailles (`match_medals.yaml`). |
| **Q13** | `kp_stats` / KPI Stats 2022 | **On ne garde rien.** | Suppression de la table et de la **seule page Stats** d'`app_wsm_dev` — l'app reste, c'est le relais de la console propriétaire (§7). |
| **Q14** | Délégation par le responsable d'équipe | **Oui.** | §4.1 : lien nominatif limité au match et à la fenêtre de 24 h. |
| **Q15** | Quand supprimer `kp_stats` | **Avec le premier lot.** | §6, lot 1. |
| **Q16** | Sens de « gauche » | **L'équipe située sur la moitié gauche du terrain**, vue de l'**arbitre principal et de la table de marque** (même côté, R11 de l'étude afficheurs). | `/` = « possession à l'équipe de gauche », `*` = « à l'équipe de droite ». La touche désigne une **équipe** (par sa moitié de terrain), pas une direction d'attaque (§3.5.2). |
| **Q17** | Mode des touches | Pas d'avis arrêté (v5), puis **décidé le 2026-11-28 : gauche / droite**, avec un pavé USB à 4 touches en carré. | §3.5.4, §3.5.5 ; mode relatif abandonné. |
| **Q18** | Changements de côté | **Systématiques** à chaque nouvelle période et prolongation. | Le switch est **automatique** au passage de période (plus seulement « proposé ») ; annulable pour une correction. |
| **Q19** | Panneau / incrustations | **Le panneau change, pas les incrustations.** | Le côté est diffusé au panneau (scoreboard) ; les incrustations gardent **A à gauche** (identité TV stable). |

### 2.3 Ce qui existe déjà

- **Fait de match extensible** : `scoring_live_event.kind` est prévu pour accueillir `SHOT`, `SAVE`,
  `STEAL`… sans changement de schéma (PAGE_SCORING §0.4/§0.5).
- **Chronomètre de tir horodaté et persisté** : `scoring_live_clock` (`kind = SHOTCLOCK`) ; chaque
  `run` / `stop` est en plus journalisé avec le temps affiché (`kp_journal`, « Scoring chrono »,
  PAGE_SCORING §0.13).
- **Touches de la console** (`utils/scoringShortcuts.ts`) : `+` 60 s, `-` 40 s, `*` arrêt, `Espace`
  chrono principal, lettres des faits de jeu. Les touches sont comparées sur **`event.key`** : les
  chiffres du pavé numérique tapent donc aussi le **numéro du joueur**, et `Entrée` valide un fait.
- **Précédent legacy « KPI Stats » (2022)** : `sources/app_wsm_dev/src/views/Stats.vue`, table
  `kp_stats`, `PUT /api/wsm/stats/` (legacy) et son double `PUT /api2/admin/scoring/stats` — cf. C7 ;
  **à supprimer** (Q13). ⚠️ `app_wsm_dev` n'est **pas** qu'un outil de stats : c'est l'**onglet WSM**
  (`Manager.vue`) qui relaie la console propriétaire vers KPI ; il ne disparaîtra qu'avec le lot 5
  du plan de refonte (§3.6).
- **Médailles de match** : notion d'**éligibilité / fiabilité** d'un match (FEATURE_MATCH_MEDALS §5.1),
  réutilisable pour ne publier que des stats fiables.

## 3. Ce qu'on obtient sans personne de plus

### 3.1 Déjà dans les données

Buts (buteur, équipe, temps), cartons, exclusions, supériorité numérique : déjà exploités par les
médailles. Rien sur la possession, les tirs ou les arrêts.

### 3.2 Ce que révèle le chronomètre de tir

| Commande | Signification de jeu |
|---|---|
| **Départ 60 s** | **nouvelle possession** (engagement, après but, sortie, faute, arrêt récupéré par la défense, interception…) |
| **Départ 40 s** | **tir cadré non marqué** (arrêté par le gardien ou le cadre, Q2), **rebond conservé** par l'attaque |
| **Arrêt** | jeu arrêté, pas de possession |
| Expiration (0) | **violation du temps de tir** → perte de balle |

### 3.3 Possession reconstruite, sans touche nouvelle

L'équipe en possession n'a pas besoin d'être saisie à chaque fois : elle **se reconstruit après le
match** (Q7) à partir de règles simples et de points d'ancrage déjà connus.

**Règles de chaînage** (dans l'ordre du temps de jeu) :

| Fait | Possession suivante |
|---|---|
| Départ **40 s** | **même équipe** (Q2) |
| Départ **60 s** | **l'une ou l'autre équipe** : **l'autre** après un changement de possession (but, arrêt récupéré par la défense, interception, sortie, violation) ; **la même** après un **coup franc** ou une **faute non sifflée laissée à l'avantage** de l'équipe en possession (geste d'avantage de l'arbitre, le chronomètre de tir repart à 60 s) |
| **But** de A (saisi par le secrétaire) | la possession **qui précède** le but est à **A** ; la suivante (engagement) est indéterminée, voir ci-dessous |
| **Engagement** (début de période, après but) | indéterminée tant qu'aucune ancre ne la fixe |

**Points d'ancrage** : chaque **but** fixe l'équipe de la possession qui le précède (le buteur et son
équipe sont connus). La chaîne se déroule **dans les deux sens** à partir de chaque ancre ; deux
ancres successives doivent être **cohérentes**. Une incohérence signale soit un 60 s qui n'était pas
un changement de possession (Q9), soit une erreur de manipulation : on la localise entre deux ancres.

**Sans côté déclaré**, un 60 s ne dit donc pas à lui seul qui a le ballon : la reconstruction **suppose
le changement** de possession, et chaque coup franc ou avantage devient une incohérence entre deux
ancres. C'est pourquoi les départs 60 s **déclarent le côté** de l'équipe en possession (§3.5) ; les
règles ci-dessus ne servent seules que là où aucun côté n'est déclaré (console propriétaire, §3.6).

**Ce que ça donne, sans geste en plus** :

- possession par équipe : nombre, durée, temps total (le temps compte en **temps de jeu**, chrono
  principal en marche) ;
- **tirs cadrés non marqués** par équipe (chaque 40 s) et donc **arrêts + poteaux** par équipe ;
- **buts** par équipe et par joueur (déjà connus) → **tirs cadrés = buts + 40 s** (minorant : un tir
  arrêté dont la **défense** récupère le ballon donne un 60 s, indiscernable d'une interception) ;
- violations du temps de tir par équipe.

**Limite** : l'engagement. Après chaque but, l'équipe qui gagne le sprint d'engagement n'est pas
connue ; la possession suivante est ancrée **par le but suivant**, en remontant la chaîne. Une période
sans but (ou la fin de période après le dernier but) reste **non ancrée** : sa possession n'est pas
publiable sans correction (§3.4). *(v7 : levé par les touches gauche / droite, §3.5.)*

### 3.4 Raccourci `/` : « 60 s, même équipe » — ❌ abandonné (v7)

> **Abandonné le 2026-11-28** au profit des touches gauche / droite (§3.5) : avec deux touches 60 s
> disponibles, autant désigner directement l'équipe. Conservé pour mémoire.

**Q10** : le chronométreur de tir ne regarde **pas l'écran**. Une touche de *correction* (« basculer
l'équipe présumée ») serait donc **aveugle** : il ne sait pas ce que la machine a présumé. La touche doit
avoir un **sens fixe**, qui ne dépend que de ce qu'il voit sur le terrain.

| Touche | Action | Sens pour l'opérateur |
|---|---|---|
| `+` (inchangé) | départ/reset **60 s**, possession à **l'autre équipe** | « nouvelle possession » — le cas général |
| **`/`** (nouveau) | départ/reset **60 s**, possession à **la même équipe** | « on repart à 60 s, mais c'est toujours eux » — les cas de Q9 |
| `-` (inchangé) | départ/reset **40 s**, même équipe (rebond offensif) | inchangé |
| `*` (inchangé) | arrêt (`--`) | inchangé |

- **Effet sur le match : aucun.** `/` et `+` lancent exactement le même décompte de 60 s ; afficheurs,
  score et chrono principal ne voient pas la différence. Seule la reconstruction (§3.3) les distingue.
- **Pas d'état à vérifier** : l'opérateur décide d'après le terrain, comme aujourd'hui entre 60 s et
  40 s. S'il tape `+` au lieu de `/`, la reconstruction le signale (incohérence entre deux ancres) et
  le segment n'est pas publié.
- **Pourquoi `/`** : voisine de `*`, sur la rangée haute du pavé avec `-` ; `+` est juste en dessous.
  Les quatre commandes tiennent sous une main, **repérables au toucher** sur un pavé déporté. Ce n'est
  ni un chiffre (numéro du joueur, la console compare `event.key`), ni `Entrée` (valide un fait), ni une
  lettre (faits de jeu). Touche non liée aujourd'hui. Configurable comme les autres (préférence par
  poste).
- **Sur un boîtier** (étude afficheurs, R9) : un quatrième bouton, ou un appui long sur « 60 s ».
- **Usage facultatif** : sans `/`, la règle « 60 s = l'autre équipe » s'applique et les exceptions
  deviennent des incohérences signalées. La réponse à **Q9** dira si `/` vaut la peine d'être appris
  (exceptions fréquentes) ou s'il reste une option.
- **Alternatives écartées** :
  - touche de **bascule** + pastille de possession à l'écran : demande de regarder l'écran (Q10) ;
  - deux touches « 60 s A » / « 60 s B » : une décision de plus à **chaque** possession, et il faut
    savoir quelle équipe est A ;
  - `4` / `6` du pavé : ce sont des chiffres, ils tapent un numéro de joueur ; les distinguer par
    `event.code` (`Numpad4`) priverait le secrétaire des chiffres du pavé ;
  - `.` : exclue des touches du chronomètre de tir depuis le 2026-11-08, comme `Entrée` et les
    chiffres (`utils/scoringShortcuts.ts`).

**L'engagement reste non ancré** : qui gagne le sprint n'est connu ni du chronométreur de tir (il tape
`+` sans dire pour qui) ni du secrétaire. *(v7 : levé par les touches gauche / droite,
§3.5 ; le passage sur le banc, §4.1, est déclassé en v8.)* À défaut, le but suivant l'ancre en remontant la chaîne.

### 3.5 Inversion des côtés (« switch ») et touches spatiales

> Sujet **plus large que les stats** : il touche la console et le panneau de score. S'il est retenu, il
> passe dans [PAGE_SCORING.md](../../specs/PAGE_SCORING.md) (spec first) ; il est étudié ici parce
> qu'il change le choix des touches du chronomètre de tir.

#### 3.5.1 La fonction

Un **switch** de la console inverse **gauche / droite** des équipes A et B **sur la console et sur le
panneau** (scoreboard, et les afficheurs qui montrent les deux équipes), pour coller à la position
réelle des équipes sur leur demi-terrain après chaque changement de côté.

- **Avant le coup d'envoi** : le côté de départ de A (gauche ou droite, vu de la table) se règle dans
  **Paramètres**, avec les contrôles d'avant-match (PAGE_SCORING §0.11).
- **À chaque nouvelle période** (prolongations comprises, Q18) : l'inversion est **automatique** au
  passage à la période suivante (bouton « » », pause inter-périodes, PAGE_SCORING §0.13 / §7.5),
  **sans geste en plus** ; le secrétaire peut l'annuler (correction). Le switch reste disponible à tout moment (bouton
  dans l'en-tête, pas de raccourci : il ne doit pas partir par erreur).
- **Ce qui ne change pas** : l'identité A / B (feuille de match, couleurs, données, `team` dans
  `scoring_live_*`). Seul l'**affichage** est miroir. Le côté courant est un état de match
  (`sides = A_LEFT | A_RIGHT`) persisté et diffusé (Mercure, `BroadcastChannel`).
- **Qui suit le switch (Q19)** : la **console** et le **panneau** (scoreboard plein écran). Les
  **incrustations** vidéo ne le suivent **pas** : A reste à gauche, comme le téléspectateur l'a vu dès
  le début. Les afficheurs du chronomètre de tir ne montrent pas d'équipe : non concernés.
- **Repère** (Q16) : gauche / droite s'entendent **vus de l'arbitre principal et de la table**, qui sont
  du même côté du terrain.
- **Bénéfice hors stats** : le secrétaire trouve l'équipe qui a marqué **du même côté de la console
  que sur le terrain** (l'équipe de gauche marque dans le but de **droite**, qu'elle attaque) ; le
  public lit le panneau dans le sens des moitiés de terrain.

#### 3.5.2 Ce que ça permet : des touches « 60 s gauche » / « 60 s droite »

Avec des côtés connus, le chronométreur de tir n'a plus à décider « même équipe / autre équipe » : il
désigne **l'équipe qui a le ballon par sa moitié de terrain** (Q16 : l'équipe de gauche ou de droite,
vue de la table). La console traduit le côté en A / B grâce au switch.

> **Point de vigilance** : la touche désigne une **équipe** par sa position ; l'équipe de gauche attaque
> vers la **droite**. Les étiquettes portent donc **les deux** informations : position de la touche =
> moitié de terrain, flèche = sens de l'attaque (« 60 ▶ » à gauche, « ◀ 60 » à droite, §3.5.5). Le
> geste de l'arbitre (direction de l'attaque) se lit sur la flèche.

| | Relatif (§3.4) | **Spatial (switch)** |
|---|---|---|
| Ce que décide l'opérateur | « encore eux ? » (`+` ou `/`) | « vers où ? » (gauche / droite) |
| Repère sur le terrain | doit se souvenir de qui avait le ballon | **l'équipe qui a le ballon**, par sa moitié de terrain |
| Coups francs, avantages (Q9, fréquents) | piège : `+` par réflexe ⇒ erreur | naturel : même geste que les autres 60 s |
| Engagement | **inconnu** (personne ne sait qui gagne le sprint) | **connu** : il tape le côté de l'équipe qui a gagné |
| Dépend du switch | non | **oui** (§3.5.3) |
| Erreur typique | ponctuelle, dispersée, difficile à localiser | **une période entière** inversée, **détectable et corrigeable** |

**Touches proposées** (pavé numérique, rangée haute, gauche et droite comme sur le terrain) :

| Touche | Spatial | Aujourd'hui |
|---|---|---|
| **`/`** | **60 s, possession à l'équipe de gauche** | libre |
| **`*`** | **60 s, possession à l'équipe de droite** | arrêt |
| **`-`** | 40 s (même équipe) | 40 s |
| **`+`** | **arrêt** (`--`) | 60 s |

- `/` et `*` sont **côte à côte** : gauche / droite **sous les doigts**, sans regarder l'écran (Q10).
- `-` (40 s) ne bouge pas. L'arrêt passe sur `+`, la grande touche : c'est la commande la moins
  fréquente (le chronomètre de tir suit déjà le chrono principal), sans risque pour le match.
- **Coût du changement faible maintenant** : les touches actuelles n'ont été essayées qu'en dev, aucun
  match réel n'a été tenu à la console (SCORING_ROLLOUT_CHECKLIST). Après le pilote, ce sera plus cher.
- Sur un **boîtier** (étude afficheurs, R9) : deux boutons physiquement à gauche et à droite.

#### 3.5.3 Le risque d'oubli du switch, et pourquoi il est maîtrisable

**Risque** : switch oublié à un changement de période ⇒ toutes les touches spatiales de la période sont
**inversées** ⇒ possession attribuée à la mauvaise équipe pendant toute la période.

**Ce qui le rend maîtrisable** : l'erreur est **un seul bit par période** (« côtés inversés ou non »),
pas une suite d'erreurs dispersées.

1. **Prévention** : l'inversion est **automatique** au passage de période (§3.5.1, Q18) ; l'oubli
   n'est possible qu'en cas d'**annulation** erronée, ou de période changée hors du parcours normal
   (correction manuelle de la période → la console redemande le côté). Le risque réel n'est plus
   l'oubli mais une **erreur de côté de départ** dans Paramètres : même bit, même correction.
2. **Détection en direct** : chaque **but** saisi par le secrétaire dit quelle équipe avait le ballon. Si
   la dernière touche spatiale disait l'autre équipe, la console **signale une inversion probable**
   (bandeau discret pour le **secrétaire**, qui lui regarde l'écran ; proposition « inverser les côtés
   »). Le panneau public est alors corrigé en cours de période.
3. **Correction après le match** (Q7) : la reconstruction (§3.3) teste les deux valeurs du bit pour
   chaque période et garde celle qui s'accorde avec les ancres (buts, engagements saisis par le banc).
   Une période **sans aucun but** ni engagement renseigné ne peut pas être tranchée : sa possession est
   **masquée** (comme sous le seuil de couverture, Q12).

Résultat : l'oubli du switch coûte au pire **une période non publiée** quand elle n'a aucun but ; il ne
publie **jamais** une possession inversée.

#### 3.5.4 Spatial ou relatif ? Décision (Q17)

**Décidé le 2026-11-28 : touches spatiales seules** (`/` équipe de gauche, `*` équipe de droite, `-` 40 s,
`+` arrêt). Le mode relatif n'est pas développé (YAGNI). Arguments qui ont tranché :

| Argument | Pourquoi il penche vers le spatial |
|---|---|
| Q9 : coups francs et avantages fréquents | en relatif, chaque 60 s demande « encore eux ? » et le réflexe `+` se trompe ; en spatial, **tous les 60 s se tapent pareil** |
| Q10 : pas de regard sur l'écran | le spatial n'exige aucun état mémorisé : l'opérateur **voit** qui a le ballon |
| Engagement | connu en spatial (côté de l'équipe qui gagne le sprint) ; **inconnu** en relatif, à demander au banc |
| Q18 : changement de côté systématique | le switch est **automatique** : la dépendance au switch, seul défaut du spatial, devient faible |
| Nature des erreurs | spatial : au pire **une période entière**, détectée par les buts et corrigée (§3.5.3) ; relatif : erreurs **dispersées**, une par coup franc mal tapé, que les ancres situent mal |
| Coût de changement | nul maintenant (touches jamais utilisées en match réel), réel après le pilote |

**Seule objection** : le 60 s, geste le plus fréquent, quitte la grande touche `+` pour deux touches plus
petites. Le pavé externe (§3.5.5) la lève : quatre touches de même taille.

**Indicateur de qualité au pilote** (mesurable, sans ressenti) : la reconstruction compte les
**conflits** (but dont la possession précédente est attribuée à l'autre équipe, hors période inversée en
bloc). Cible : **≤ 1 conflit par période** en moyenne, et retour des chronométreurs de tir (gêne,
hésitation).

#### 3.5.5 Pavé USB externe à 4 touches en carré

Un **petit clavier USB à 4 touches**, posé devant le chronométreur de tir, qu'il utilise **au toucher**
sans regarder (Q10). Disposition (vue de l'opérateur, qui est du côté de la table) :

```
┌──────────────────┬──────────────────┐
│      60 ▶        │       ◀ 60       │
│ équipe de gauche │ équipe de droite │
│ attaque → droite │ attaque → gauche │
├──────────────────┼──────────────────┤
│      ARRÊT       │        40        │
│    (retour --)   │  rebond offensif │
└──────────────────┴──────────────────┘
```

- **Rangée du haut** = les deux 60 s, chacun **du côté de son équipe**, flèche = sens de l'attaque ;
  **rangée du bas** = arrêt (gauche) et 40 s (droite).
- **Ce qu'il envoie** : les touches de la console (`/`, `*`, `+`, `-`), **rien à développer** côté KPI :
  la console voit un clavier. Les quatre touches restent réassignables dans la modale Raccourcis clavier
  si un pavé ne peut émettre que d'autres codes (F13–F16, par exemple).
- **Trouver** : les **macro-pads USB 4 touches programmables** du commerce (firmware QMK / VIA ou logiciel
  du fabricant, de l'ordre de 10 à 40 €) se programment **une fois** pour émettre ces touches, mémorisées
  dans le pavé (aucun logiciel sur le PC de la table). À vérifier à l'achat : programmation stockée dans
  le pavé, câble d'au moins 1,5 m, touches assez grosses et espacées.
- **Créer** : 4 boutons type arcade (gros, robustes) + un microcontrôleur qui se présente en clavier USB
  (Raspberry Pi Pico ou Arduino Pro Micro), dans un boîtier imprimé ou du commerce : quelques dizaines
  d'euros, utilisable en extérieur (IP54 avec des boutons étanches). Le même boîtier peut converger avec
  la base des afficheurs déportés (étude afficheurs §5.2), qui prévoit déjà des boutons pour le
  chronométreur de tir.
- **Étiquettes** : couleur neutre (les couleurs des équipes changent à chaque match), flèches et « 60 » /
  « 40 » / « ARRÊT » en gros ; repère tactile sur les deux touches du haut.
- **Sans pavé** : le pavé numérique du PC ou un pavé numérique déporté (`/` et `*` côte à côte en haut,
  `-` à droite, `+` en dessous) fait le même travail.



### 3.6 Terrains équipés d'une console propriétaire

Sur les terrains équipés d'un **panneau du commerce**, la table de marque (chrono, score, chronomètre de
tir) saisit sur **la console du constructeur**, pas sur la console KPI (plan de refonte §1.1).

**Où en est le chantier** (plan [§0.1](LIVE_MATCH_SCORING_REFACTORING_PROPOSALS.md), lot 5) :

| Élément | État |
|---|---|
| Aujourd'hui | l'**onglet WSM** (`sources/app_wsm_dev`, `Manager.vue`) capte le flux STOMP de la passerelle et le relaie vers KPI ; il doit rester ouvert tout l'événement |
| Cible (lot 5) | un **relais** (serveur KPI ou boîtier local) → endpoint d'ingestion api2 → **traduction serveur** du protocole vers les commandes internes → `scoring_live_*` ; la console KPI passe en **supervision** |
| État | 🔴 **non commencé**, bloqué par l'**action 0.5** : enregistrer de vraies sessions STOMP au prochain événement équipé (format fermé : sans captures, on code à l'aveugle) |

**Conséquences pour les stats étendues** :

- tant que le lot 5 n'est pas livré, **aucune stat étendue** sur ces terrains : rien n'arrive dans
  `scoring_live_*` ;
- après le lot 5, la reconstruction (§3.3) dépend de ce que transporte le flux du constructeur : les
  départs **60 s / 40 s distincts** sont nécessaires (à vérifier sur les captures 0.5) ; **aucun côté**
  n'est déclaré (pas de touches spatiales, réponse Q1) : la règle relative « 60 s = l'autre équipe »
  s'applique, avec les coups francs et avantages (Q9) comme incohérences ; les tirs et arrêts du
  secrétaire (§4.0) n'ont pas d'équipe sûre et seraient attribués par la reconstruction ;
- **à ajouter à l'action 0.5** : relever dans les captures comment le constructeur code les départs du
  chronomètre de tir (60 / 40 / arrêt) et s'il transporte une notion d'équipe ou de côté.

## 4. Solutions avec une saisie humaine, hors table de marque

### 4.0 S0 — Le secrétaire : deux touches, l'équipe vient de la possession (solution retenue, v8)

**Concession sur C3 (2026-11-28)** : puisque le chronométreur de tir (touches gauche / droite) et le switch
des côtés disent **quelle équipe a le ballon**, le secrétaire n'a plus à désigner d'équipe ni de joueur. Il
signale l'issue d'un tir non marqué d'**un seul appui** ; le but, lui, est déjà saisi.

| Touche (FR / EN, proposition Q23) | Fait enregistré | Équipe | Exemple |
|---|---|---|---|
| **`T`** / **`S`** (*Tir* / *Shot*) | **tir non marqué et non arrêté par le gardien** : à côté, sur le cadre, contré par un joueur de champ | **en possession** (attaque) | le ballon frappe la barre |
| **`A`** / **`K`** (*Arrêt* / *Keeper save*) | **tir arrêté par le gardien** | **en défense** (l'autre équipe) | le gardien repousse le tir |
| `B` (existant) | but | marqueur | inchangé |

Le joueur, le temps et l'équipe ne sont **pas** saisis : le temps est celui du chrono, l'équipe celle de la
dernière possession déclarée (dernier départ 60 s, maintenue par un 40 s), le gardien n'est pas désigné (C1).

**Règles de saisie** (à porter dans la spec) :

- **un appui = un fait enregistré**, sans fenêtre, sans confirmation, sans choix ; il n'arme pas de fait et ne
  touche pas à une saisie en cours (un carton à moitié saisi reste là) ;
- un **retour visuel discret** sur le tableau de la console (« Tir A » / « Arrêt B » une seconde), jamais de son ;
- **annulable** comme un fait de jeu : la ligne apparaît dans la chronologie (style atténué) avec sa corbeille ;
- **possession inconnue** (aucun départ 60 s encore dans la période) : le fait est enregistré **sans équipe**,
  signalé dans la chronologie, et la reconstruction de fin de match tente de l'attribuer ; sinon il est écarté ;
- **jamais** sur la feuille de match, le PDF ni `kp_match_detail` : statistiques non officielles (C5) ;
- **mode direct seulement**, match En cours ; inactif en post-match et en mode score seul.

**Ce que ça donne** (par équipe, tous les matchs scorés) :

| Stat | Calcul |
|---|---|
| Tirs | buts + `A` subis par l'adversaire + `T` |
| Tirs cadrés | buts + arrêts subis (+ cadre si Q25 le distingue) |
| Arrêts, % d'arrêts | `A` ; `A` / (`A` + buts encaissés) |
| Efficacité | buts / tirs |
| Tirs par possession | tirs / possessions (§3.3) |

**Contrôle de qualité, sans bénévole** : le chronométreur de tir produit **déjà** une trace indépendante. Un
**40 s** (rebond offensif après un tir sur le cadre ou un arrêt, Q2) doit être **précédé** de quelques secondes
par un `T` ou un `A`. La part des 40 s ainsi précédés mesure la **couverture** du match ; sous le seuil (80 %,
Q12), les tirs et arrêts du match sont **masqués** (pas publiés). Inversement, un `A` suivi d'un 60 s de
l'**autre** équipe dit que la défense a récupéré : cohérent.

**Coût pour le secrétaire** : deux touches, le même geste qu'aujourd'hui pour un but sans le numéro. Tenable
même sur des séquences rapides, puisqu'il n'y a ni équipe ni joueur à choisir. Le **pavé 4 touches** du
chronométreur de tir (§3.5.5) n'est pas concerné : ces touches sont sur le clavier du secrétaire.

#### 4.0.1 Et `T` seul ? (étudié le 2026-11-28, écarté)

Idée : « s'il n'y a ni but ni 40 s après un `T`, c'est un tir contré ou non cadré ». Faux dans un cas fréquent :
un tir **arrêté par le gardien dont la défense récupère le ballon** n'est suivi ni d'un but ni d'un 40 s, mais
d'un 60 s pour l'autre équipe, exactement comme un tir non cadré ou contré. Avec `T` seul on obtient tirs,
efficacité, tirs par possession et taux de rebonds offensifs (40 s / tirs), mais **pas les arrêts** ni le % d'arrêts
(priorité n° 1 d'intérêt, Q5). La touche `A` porte la seule information qu'aucune trace ne donne : **gardée**.

**Limite** : sans le numéro facultatif (Q24 : accepté), pas d'arrêts ni de tirs **par joueur**. Les médailles possibles
sont **d'équipe** (« Muraille » : meilleur % d'arrêts d'une équipe dans un match ; « Efficacité »). Un numéro
**facultatif** juste après la touche (le temps de le taper, sinon rien) est possible plus tard (Q24).

### 4.1 S1 — Les bancs complètent un squelette déjà pré-rempli — ❌ déclassé (v8)

> **Déclassé le 2026-11-28** : architecture lourde (accès par responsable d'équipe, délégation, fenêtre de
> 24 h, contrôles croisés) pour un résultat qui dépend de la **disponibilité et de l'implication du banc**,
> très inégales d'une équipe à l'autre. Remplacé par S0 (§4.0). Conservé pour mémoire.

**Leçon de 2022 (C7)** : demander à quelqu'un de **tout** saisir, en direct, à partir d'une page
blanche, donne une qualité trop faible. Ici, la personne du banc **ne crée presque rien** : elle
**complète** des lignes que la machine a déjà posées, au bon moment.

**Le squelette** (créé automatiquement, à partir de la console) :

| Ligne créée | Origine | Ce que le banc ajoute |
|---|---|---|
| « Tir cadré de A, arrêté (gardien ou cadre) — 1re, 6:42 » | chaque **40 s** | côté **A** : le **tireur** (n°) ; côté **B** : **qui a arrêté** (n°) ou **cadre** |
| « But de A — n° 7 — 1re, 4:15 » | chaque **but** | côté **B** : **qui était dans le but** (n°), pour le % d'arrêts |
| « Possession de A, perdue — 1re, 3:02 » | chaque **60 s** qui change d'équipe | **facultatif** : « tir arrêté, ballon récupéré par la défense » → transforme la perte en **tir + arrêt** (le minorant de §3.3 devient exact) |
| « Engagement — 2e, 10:00 » | chaque **premier 60 s** d'une période ou après un but | **« gagné par nous / par eux »** : ancre la possession — **inutile avec les touches spatiales** (§3.5), qui le donnent déjà |

**Qui, quand, comment** :

- **Qui** : une personne **par équipe**, sur le banc (remplaçant, coach, parent), qui saisit **pour son
  équipe seulement** : ses tireurs, et **ses** arrêts. Motivée : ce sont les stats de son équipe,
  publiées (Q8). Le rôle de gardien (C1) est connu du banc.
- **Quand** : **pendant** le match, chaque ligne apparaît en direct (Mercure) et se complète en un
  toucher (numéro du joueur) ; **ou après** le match (Q7), pendant une **fenêtre de relecture de 24 h**
  après la fin du match (Q11), de mémoire ou avec une vidéo d'équipe. À la fermeture de la fenêtre, les
  contrôles tournent et la publication suit.
- **Comment** : une page mobile, **un seul geste par ligne** : la ligne en haut, les numéros des
  joueurs de la composition en gros boutons, « Cadre » / « Je ne sais pas ». Aucune saisie de temps
  (déjà donné par le squelette), aucun type de fait à choisir.
- **Accès (Q11)** : rattaché au **responsable d'équipe** désigné (mandat **profil 7**,
  `limitation_equipe_club`, FEATURE_DESIGNATIONS D8 / MAN-09). Il voit les matchs de son équipe et
  n'écrit que **les stats de son équipe**, pendant la fenêtre de 24 h, rien d'autre (ni feuille de
  match, ni composition). Le responsable n'est pas toujours sur le banc : **délégation** possible à une
  personne du banc : **délégation autorisée (Q14)** par un lien nominatif limité au match et à la
  fenêtre de 24 h, révocable. Une équipe sans responsable désigné n'a pas de stats par joueur.

**Contrôles de cohérence** (automatiques, à la fin de la fenêtre) :

- chaque 40 s doit avoir **un tireur** (banc attaquant) **et** un **arrêteur ou « cadre »** (banc
  défenseur) ;
- un tireur doit être sur la **composition** de l'équipe, un arrêteur aussi, et **pas exclu** à cet
  instant (pénalités connues) ;
- **gardien vraisemblable** : un même joueur enchaîne en général les arrêts ; un changement fréquent
  est signalé (pas refusé, C1) ;
- **couverture** par match et par équipe : part des lignes complétées.

**Publication (Q8, Q12)** : les **comptes d'équipe** (tirs cadrés, arrêts + cadre, possession ancrée)
sont publiés à la fin du match, ils ne dépendent que de la console. Les stats **par joueur** (tireurs,
arrêts, % d'arrêts) sont publiées pour une équipe à la fermeture de la fenêtre si sa **couverture**
atteint **80 %** (paramètre unique, à côté des seuils des médailles) ; **en dessous, elles sont
masquées**. Les médailles fondées sur ces stats n'utilisent que les matchs au-dessus du seuil (même
logique que FEATURE_MATCH_MEDALS §5.1). La possession d'un segment non ancré ou incohérent est masquée
de la même façon.

**Risques** : partialité (un banc gonfle ses arrêts) — limitée car **le nombre** de lignes vient de la
console, le banc ne fait qu'**attribuer** ; une seule équipe saisit — ses stats sont publiées, pas
celles de l'adversaire ; personne ne saisit — restent les comptes d'équipe.

### 4.2 S2 — Statisticien volontaire (déclassé)

Même page que S1, pour les deux équipes, par une personne neutre. **Déclassé par C7** : en 2022, des
bénévoles dédiés n'ont pas suffi. Reste possible **en complément** de S1 (même squelette, même
contrôles) pour une équipe dont le banc ne saisit pas.

### 4.3 S3 — Annotation vidéo différée (repoussée)

Lecture de la vidéo avec pause et ralenti, annotation au clavier, calage sur les buts et les départs
du chronomètre de tir. Seule voie réaliste pour les passes. **Repoussée (Q4)** : trop peu de matchs
filmés. Le même squelette que S1 pourrait s'y afficher plus tard.

### 4.4 S4 — Analyse vidéo automatique (perspective)

Hors de portée à court terme ; même dépendance aux vidéos que S3.

### 4.5 Écartées

| Piste | Raison |
|---|---|
| ~~Ajouter des boutons dans la console pour le secrétaire~~ | ~~viole C3~~ — **levé en v8** pour deux touches seulement (§4.0) ; toute autre saisie reste écartée |
| Demander les tireurs / arrêteurs au chronométreur de tir | viole C2 |
| Saisie exhaustive en direct par une personne dédiée | C7 (2022) |
| Capteurs embarqués | coût, robustesse, budget (< 1 000 € / terrain, étude afficheurs) |
| Rendre une saisie obligatoire pour valider le match | viole C5 |

## 5. Comparatif (v2)

| Solution | Arrêts | Tirs | Possession | Personne en plus | Impact match | Couverture attendue |
|---|---|---|---|---|---|---|
| §3.3 Reconstruction (chrono de tir + buts) | par équipe (arrêts + cadre) | cadrés par équipe (minorant) | par équipe, **hors segments non ancrés** | non | aucun | tous les matchs scorés |
| §3.4 Touche `/` (60 s même équipe) | — | — | coups francs / avantages (Q9) | non | nul | tous les matchs scorés |
| §3.5 Switch + touches spatiales | — | — | **toutes les possessions, engagements compris** | non | nul (panneau dans le sens du jeu) | tous les matchs scorés ; période sans but non tranchable si switch oublié |
| **§4.0 Secrétaire, deux touches (v8)** | ✅ **par équipe** | ✅ **par équipe** (issue : arrêté / non arrêté) | — | **non** | deux appuis du secrétaire | tous les matchs scorés, mesurée par les 40 s |
| ~~§4.1 Bancs sur squelette~~ (déclassé) | par joueur | par joueur | (corrige les pertes) | oui | aucun | très inégale |
| §4.2 Statisticien | ✅ | ✅ | — | oui | aucun | complément |
| §4.3 Vidéo | ✅ | ✅ | ✅ | oui, différé | aucun | faible (Q4) |

## 6. Recommandation (v2, ordre revu le 2026-11-28)

0. **Switch des côtés et touches gauche / droite** : spec écrite ([PAGE_SCORING §0.18](../../specs/PAGE_SCORING.md),
   SIDE-01 à 23), **en cours d'implémentation** (2026-11-28). Utile **sans** les stats, et c'est lui qui
   rend la possession gratuite.
1. **Lot 1 — Possession par équipe** (§3.3, §3.5.3) : calcul serveur après le match, à partir des départs du
   chronomètre de tir (côté déclaré), des buts, des côtés et du chrono principal. Aucune organisation à prévoir,
   tous les matchs scorés. Comptes d'équipe publics. **Suppression de `kp_stats`** dans ce lot (Q15).
2. **Lot 2 — Tirs et arrêts par équipe** (§4.0) : deux touches du secrétaire, équipe déduite de la possession,
   numéro facultatif. **Saisie spécifiée et codée** (PAGE_SCORING §0.19, 2026-11-28). Restent la couverture
   mesurée par les 40 s, le masquage sous 80 % et la publication : spec des statistiques à écrire.
3. **Lot 3 — Pilote** : un tournoi (Moreuil, terrain pilote du scoring) : conflits de possession (§3.5.4),
   couverture des tirs (§4.0), choix du pavé (Q22), retour des secrétaires sur la charge.
4. **Médailles** (après une saison de données) : « Maîtrise » (temps de possession), puis « Efficacité »
   (buts / tirs) et « Muraille » (% d'arrêts d'une équipe), seulement sur les matchs au-dessus du seuil.

Abandonné : la page « Stats de mon équipe » (S1, §4.1). Les stats **par joueur** restent hors de portée
tant qu'aucune voie sans bénévole n'existe (Q24).

> **Préalable** : le scoring live n'est pas encore validé de bout en bout (lots 1–3 essayés en dev, cf.
> SCORING_ROLLOUT_CHECKLIST R1/R2). Ces lots supposent que la console tienne de vrais matchs ; ils ne
> s'ouvrent pas avant.

## 7. Esquisse du modèle de données (à affiner dans une spec)

**Tirs et arrêts dans une table dédiée `scoring_live_stat`** (v9, PAGE_SCORING §0.19 T8) : saisis par la console
pendant le match, comme les buts, mais **hors** `scoring_live_event`. Cette dernière alimente la consolidation
`kp_match_detail` **et** la vue `v_match_detail`, lue par les médailles, les suspensions et les désignations :
y ajouter des codes inconnus aurait exposé toutes ces règles. Colonnes : `uid`, `id_match`, `kind` (`SHOT` /
`SAVE`), `periode`, `temps`, `team` (ou vide si possession inconnue), `id_player`, `numero`, `created_at`.

- **Possession** : calculée, pas saisie — une vue ou une table de résultats recalculable, comme les
  médailles (recalcul saison par saison).
- **Touches du chronomètre de tir** : la commande `start60` porte le **côté déclaré** (`side = L | R`),
  l'équipe se déduisant des côtés de la période (PAGE_SCORING §0.18, D10), persistée avec le départ dans
  `scoring_live_*` et le journal ; la machine à états du chronomètre de tir
  (`ScoringRules::shotclockTransition`) ne change pas.
- **Côtés** : `sides` (`A_LEFT` / `A_RIGHT`) dans `scoring_live_state`, chaque changement horodaté
  (période, temps) pour que la reconstruction sache quel côté valait à chaque départ.
- **`kp_stats` (2022) : supprimé (Q13), avec le lot 1 (Q15)** — migration de suppression de la
  table ; retrait de la page `Stats.vue`, de sa route et de son entrée de menu dans `app_wsm_dev`
  (**le reste de l'app, relais WSM, est conservé jusqu'au lot 5**), de `PUT /api/wsm/stats/` (legacy,
  `wsmControllers.php`) et de `PUT /api2/admin/scoring/stats` (`ScoringController::putStats`).

## 8. Questions ouvertes (v9)

Réponses Q23–Q26 intégrées (§4.0, spec PAGE_SCORING §0.19) : `T` / `A` et `S` / `K` ; numéro facultatif ;
pas de troisième touche ; tir contré = `T`.

À vérifier **au pilote** :

| # | Question |
|---|---|
| **Q20** | Les étiquettes du pavé (position = équipe, flèche = sens de l'attaque, §3.5.5) suffisent-elles pour que le geste de l'arbitre ne trouble pas les opérateurs ? |
| **Q21** | Le taux de conflits tient-il la cible (≤ 1 par période, §3.5.4) ? |
| **Q22** | Pavé : macro-pad du commerce ou fabrication maison (§3.5.5) ? Un modèle à tester avant le pilote. |

Prochaine étape : après Q23–Q26, spec des tirs et arrêts (PAGE_SCORING, nouvelle section) puis implémentation
en TDD ; spec de la possession (lot 1).
