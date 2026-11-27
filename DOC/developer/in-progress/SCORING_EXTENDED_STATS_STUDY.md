# Étude — Statistiques de jeu étendues (possession, passes, tirs, arrêts, interceptions)

> **Statut** : étude / aide à la décision. Aucune ligne de code engagée.
> - v1 (2026-11-27) : contraintes, sources de données possibles, solutions graduées, questions Q1–Q8 ;
> - **v2 (2026-11-27)** : réponses Q1–Q8 (§2.2), **possession reconstruite sans touche nouvelle** (§3.3),
>   raccourci de correction (§3.4), **squelette pré-rempli par le chronomètre de tir et complété par les
>   bancs** (§4.1), recommandation révisée (§6), questions Q9–Q13.
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

**Priorités (Q5)** : **1. arrêts, 2. tirs, 3. possession.** Passes et interceptions : plus tard.

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
| C3 | **Le secrétaire de table de marque** (scoring : chrono principal, buts, cartons, pénalités) est **déjà très occupé et concentré**. | **Aucune** saisie supplémentaire. La console ne se complexifie pas pour lui. |
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
  `kp_stats`, `PUT /api2/admin/scoring/stats` — cf. C7.
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
| Départ **60 s** | **l'autre équipe** — cas général : après but, arrêt récupéré par la défense, interception, sortie, violation |
| **But** de A (saisi par le secrétaire) | la possession **qui précède** le but est à **A** ; la suivante (engagement) est indéterminée, voir ci-dessous |
| **Engagement** (début de période, après but) | indéterminée tant qu'aucune ancre ne la fixe |

**Points d'ancrage** : chaque **but** fixe l'équipe de la possession qui le précède (le buteur et son
équipe sont connus). La chaîne se déroule **dans les deux sens** à partir de chaque ancre ; deux
ancres successives doivent être **cohérentes**. Une incohérence signale soit un 60 s qui n'était pas
un changement de possession (Q9), soit une erreur de manipulation : on la localise entre deux ancres.

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
publiable sans correction (§3.4) ou sans saisie du banc (§4.1).

### 3.4 Raccourci de correction : `/`

Pour lever les cas non ancrés et corriger un 60 s qui n'était pas un changement de possession, **une
seule touche** facultative, à la main du chronométreur de tir :

| Touche | Action | Pourquoi celle-ci |
|---|---|---|
| **`/`** (pavé numérique) | **« Possession à l'autre équipe »** : bascule l'équipe attribuée à la possession **en cours**, **sans toucher au décompte** | voisine immédiate de `*` (arrêt), sur la rangée haute du pavé, avec `-` (40 s) et `+` (60 s) à portée : les quatre commandes du chronomètre de tir tiennent dans **une seule zone de la main** ; ce n'est **ni un chiffre** (numéro du joueur), **ni `Entrée`** (valide un fait), **ni une lettre** (faits de jeu) ; touche non liée aujourd'hui |

- **Affichage** : la console montre, à côté du chronomètre de tir, une **pastille aux couleurs de
  l'équipe** présumée en possession (« ? » si non ancrée). Un **clic** sur la pastille fait la même
  chose que `/`. Le chronométreur de tir n'a **rien à faire** quand la pastille est juste.
- **Effet nul sur le match** : `/` ne change ni le décompte, ni les afficheurs, ni le score. Une
  erreur ne fausse que la statistique, et la reconstruction §3.3 la signale.
- **Alternatives écartées** :
  - deux touches « 60 s A » / « 60 s B » à la place de `+` : double la décision à chaque engagement
    (geste nouveau à chaque possession), alors que la règle « 60 s = l'autre équipe » est juste dans la
    grande majorité des cas ;
  - `4` / `6` du pavé (gauche / droite comme les équipes à l'écran) : ce sont des chiffres, ils
    tapent un numéro de joueur (comparaison sur `event.key`) ; les distinguer par `event.code`
    (`Numpad4`) priverait le secrétaire des chiffres du pavé pour taper les numéros, et une erreur
    de touche (chiffre au lieu de commande) deviendrait silencieuse ;
  - `.` : dernière touche libre du pavé, mais séparée des trois autres commandes et proche de `0` /
    `Entrée` ; gardée en réserve.
- **Configurable** comme les autres (modale Raccourcis clavier, préférence par poste).

> **Q10** : en pratique, le chronométreur de tir regarde-t-il assez la console pour voir la pastille ?
> S'il ne regarde que le terrain et ses afficheurs, `/` ne servira que rarement, et la reconstruction
> par ancres (§3.3) + les bancs (§4.1) porteront l'essentiel.

## 4. Solutions avec une saisie humaine, hors table de marque

### 4.1 S1 — Les bancs complètent un squelette déjà pré-rempli (solution retenue pour arrêts et tirs)

**Leçon de 2022 (C7)** : demander à quelqu'un de **tout** saisir, en direct, à partir d'une page
blanche, donne une qualité trop faible. Ici, la personne du banc **ne crée presque rien** : elle
**complète** des lignes que la machine a déjà posées, au bon moment.

**Le squelette** (créé automatiquement, à partir de la console) :

| Ligne créée | Origine | Ce que le banc ajoute |
|---|---|---|
| « Tir cadré de A, arrêté (gardien ou cadre) — 1re, 6:42 » | chaque **40 s** | côté **A** : le **tireur** (n°) ; côté **B** : **qui a arrêté** (n°) ou **cadre** |
| « But de A — n° 7 — 1re, 4:15 » | chaque **but** | côté **B** : **qui était dans le but** (n°), pour le % d'arrêts |
| « Possession de A, perdue — 1re, 3:02 » | chaque **60 s** qui change d'équipe | **facultatif** : « tir arrêté, ballon récupéré par la défense » → transforme la perte en **tir + arrêt** (le minorant de §3.3 devient exact) |

**Qui, quand, comment** :

- **Qui** : une personne **par équipe**, sur le banc (remplaçant, coach, parent), qui saisit **pour son
  équipe seulement** : ses tireurs, et **ses** arrêts. Motivée : ce sont les stats de son équipe,
  publiées (Q8). Le rôle de gardien (C1) est connu du banc.
- **Quand** : **pendant** le match, chaque ligne apparaît en direct (Mercure) et se complète en un
  toucher (numéro du joueur) ; **ou après** le match (Q7), pendant une **fenêtre de relecture** (ex.
  jusqu'au match suivant de l'équipe ou 24 h), de mémoire ou avec une vidéo d'équipe.
- **Comment** : une page mobile, **un seul geste par ligne** : la ligne en haut, les numéros des
  joueurs de la composition en gros boutons, « Cadre » / « Je ne sais pas ». Aucune saisie de temps
  (déjà donné par le squelette), aucun type de fait à choisir.
- **Accès** : lien ou QR code par match et par équipe, émis depuis la console ou la liste des matchs,
  à durée limitée ; droit d'écriture sur les **seules stats de son équipe** pour ce match, rien d'autre
  (Q11).

**Contrôles de cohérence** (automatiques, à la fin de la fenêtre) :

- chaque 40 s doit avoir **un tireur** (banc attaquant) **et** un **arrêteur ou « cadre »** (banc
  défenseur) ;
- un tireur doit être sur la **composition** de l'équipe, un arrêteur aussi, et **pas exclu** à cet
  instant (pénalités connues) ;
- **gardien vraisemblable** : un même joueur enchaîne en général les arrêts ; un changement fréquent
  est signalé (pas refusé, C1) ;
- **couverture** par match et par équipe : part des lignes complétées.

**Publication (Q8)** : les **comptes d'équipe** (tirs cadrés, arrêts + cadre, possession ancrée) sont
publiés dès la fin du match, ils ne dépendent que de la console. Les stats **par joueur** (tireurs,
arrêts, % d'arrêts) sont publiées pour une équipe quand sa **couverture** atteint un seuil (ex. 90 %),
sinon marquées « incomplètes ». Les médailles fondées sur ces stats n'utilisent que les matchs au-dessus
du seuil (même logique que FEATURE_MATCH_MEDALS §5.1).

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
| Ajouter des boutons dans la console pour le secrétaire | viole C3 |
| Demander les tireurs / arrêteurs au chronométreur de tir | viole C2 |
| Saisie exhaustive en direct par une personne dédiée | C7 (2022) |
| Capteurs embarqués | coût, robustesse, budget (< 1 000 € / terrain, étude afficheurs) |
| Rendre une saisie obligatoire pour valider le match | viole C5 |

## 5. Comparatif (v2)

| Solution | Arrêts | Tirs | Possession | Personne en plus | Impact match | Couverture attendue |
|---|---|---|---|---|---|---|
| §3.3 Reconstruction (chrono de tir + buts) | par équipe (arrêts + cadre) | cadrés par équipe (minorant) | par équipe, **hors segments non ancrés** | non | aucun | tous les matchs scorés |
| §3.4 Touche `/` | — | — | **segments non ancrés levés** | non | nul | selon l'usage (Q10) |
| §4.1 Bancs sur squelette | ✅ par joueur | ✅ par joueur | (corrige les pertes) | **oui, motivée** | aucun | variable, croissante |
| §4.2 Statisticien | ✅ | ✅ | — | oui | aucun | complément |
| §4.3 Vidéo | ✅ | ✅ | ✅ | oui, différé | aucun | faible (Q4) |

## 6. Recommandation (v2)

1. **Lot 1 — Reconstruction** (§3.3) : calcul serveur après le match, à partir de `scoring_live_clock`
   (départs 60 / 40 / arrêt), des buts et du chrono principal. Comptes d'équipe publics : possession
   (quand ancrée), tirs cadrés, arrêts + cadre, violations. **Aucun changement à la table de marque.**
   C'est aussi le **socle** du squelette du lot 3.
2. **Lot 2 — Pastille de possession + touche `/`** (§3.4) dans la console : une ligne d'interface et un
   raccourci configurable. Lève les segments non ancrés.
3. **Lot 3 — Page « Stats de mon équipe »** (§4.1) : squelette pré-rempli, un geste par ligne, fenêtre
   de relecture, contrôles de cohérence, seuil de couverture avant publication par joueur. Démarrer par
   un **tournoi pilote** (Moreuil, terrain pilote du scoring) avec deux ou trois clubs volontaires.
4. **Médailles** (après une saison de données) : « Mur » (% d'arrêts, couverture ≥ seuil), « Efficacité »
   (buts / tirs cadrés), « Maîtrise » (temps de possession).

> **Préalable** : le scoring live n'est pas encore validé de bout en bout (lots 1–3 essayés en dev, cf.
> SCORING_ROLLOUT_CHECKLIST R1/R2). Ces lots supposent que la console tienne de vrais matchs ; ils ne
> s'ouvrent pas avant.

## 7. Esquisse du modèle de données (à affiner dans une spec)

**Table dédiée `match_stat_fact`** (préférée à des `kind` en plus dans `scoring_live_event`) : les stats
étendues vivent **après** la consolidation du match (fenêtre de relecture), viennent de plusieurs
sources concurrentes et ne doivent jamais toucher le chemin critique du scoring.

Colonnes indicatives : `uid`, `id_match`, `kind` (`SHOT` / `SAVE` / `POSSESSION`…), `outcome` (tir :
`GOAL` / `SAVED` / `FRAME` / `LOST`), `team`, `player` (licence), `periode`, `game_time_ms`, `origin_clock`
(id du départ 40 s / 60 s qui a créé la ligne), `source` (`SHOTCLOCK` / `GOAL` / `BENCH_A` / `BENCH_B` /
`STATISTICIAN` / `VIDEO`), `author`, `created_at`, `updated_at`.

- Les lignes du squelette sont créées par le serveur (`source = SHOTCLOCK` ou `GOAL`) ; le banc ne fait
  que renseigner `player` (et `outcome` pour une perte).
- **Possession** : calculée, pas saisie — une vue ou une table de résultats recalculable, comme les
  médailles (recalcul saison par saison).
- **Touche `/`** : un fait `POSSESSION_SWAP` horodaté dans `scoring_live_*` (c'est une saisie de la
  console), lu par la reconstruction.
- `kp_stats` (2022) : à archiver (Q13).

## 8. Questions ouvertes (v2)

| # | Question |
|---|---|
| **Q9** | **60 s sans changement de possession** : dans quels cas le chronomètre de tir repart-il à 60 s pour **la même équipe** (faute de la défense sanctionnée par un coup franc à l'attaque, sortie concédée par la défense, temps mort, carton…) ? Leur fréquence dit si la règle « 60 s = l'autre équipe » tient sans correction. |
| **Q10** | Le chronométreur de tir regarde-t-il assez la console pour voir une **pastille de possession** et corriger avec `/` ? |
| **Q11** | **Accès des bancs** : lien / QR code par match et par équipe émis à la table, ou rattaché au **responsable d'équipe** déjà connu dans KPI (désignations, FEATURE_DESIGNATIONS) ? Durée de la fenêtre de relecture ? |
| **Q12** | **Seuil de couverture** pour publier les stats par joueur (90 % ?) et affichage d'une stat incomplète : masquée, ou affichée avec la mention « incomplète » ? |
| **Q13** | `kp_stats` et la page « KPI Stats » (2022) : on archive (tag + suppression) ou on garde les données des Mondiaux 2022 ? |
