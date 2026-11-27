# Étude — Statistiques de jeu étendues (possession, passes, tirs, arrêts, interceptions)

> **Statut** : étude / aide à la décision. Aucune ligne de code engagée.
> - v1 (2026-11-27) : contraintes, sources de données possibles, solutions graduées, questions Q1–Q8 ;
> - **v2 (2026-11-27)** : réponses Q1–Q8 (§2.2), **possession reconstruite sans touche nouvelle** (§3.3),
>   raccourci de correction (§3.4), **squelette pré-rempli par le chronomètre de tir et complété par les
>   bancs** (§4.1), recommandation révisée (§6), questions Q9–Q13 ;
> - **v3 (2026-11-27)** : réponses Q10–Q13 (§2.2) — le chronométreur de tir **ne regarde pas l'écran** :
>   `/` devient une commande **à sens fixe** « 60 s, même équipe » (§3.4) ; accès des bancs par le
>   **responsable d'équipe** (mandat profil 7), fenêtre de **24 h**, seuil de publication **80 %**
>   paramétrable, stats sous le seuil **masquées** ; `kp_stats` supprimé. Q9 en attente.
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
| Q9 | 60 s sans changement de possession | **En cours de renseignement.** | Dimensionne l'usage de `/` (§3.4). |
| **Q10** | Regard du chronométreur de tir | **Concentré sur le match.** Il peut regarder son **pavé numérique déporté** (ou équivalent), **pas l'écran** de la console. | Aucune commande ne peut dépendre d'un état affiché à l'écran : `/` prend un **sens fixe** (§3.4) ; pas de pastille à surveiller. |
| **Q11** | Accès des bancs | **Rattaché au responsable d'équipe**, fenêtre de **24 h**. | §4.1 : mandat **profil 7** issu des désignations (FEATURE_DESIGNATIONS D8, MAN-09) ; délégation possible à un membre du banc (Q14). |
| **Q12** | Seuil de couverture | **80 %, paramétrable.** En dessous : **masquée**. | §4.1 : paramètre unique, à côté des seuils des médailles (`match_medals.yaml`). |
| **Q13** | `kp_stats` / KPI Stats 2022 | **On ne garde rien.** | Suppression de la table, de la page `app_wsm_dev` et de `PUT /admin/scoring/stats` (§7). |

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
  `kp_stats`, `PUT /api2/admin/scoring/stats` — cf. C7 ; **à supprimer** (Q13).
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

### 3.4 Raccourci `/` : « 60 s, même équipe »

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
  - `.` : dernière touche libre du pavé, mais isolée près de `0` / `Entrée` ; gardée en réserve.

**L'engagement reste non ancré** : qui gagne le sprint n'est connu ni du chronométreur de tir (il tape
`+` sans dire pour qui) ni du secrétaire. C'est le **banc** qui le fixe (§4.1, ligne « Engagement ») ; à
défaut, le but suivant l'ancre en remontant la chaîne.

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
| « Engagement — 2e, 10:00 » | chaque **premier 60 s** d'une période ou après un but | **« gagné par nous / par eux »** : ancre la possession (§3.4) |

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
  personne du banc (Q14). Une équipe sans responsable désigné n'a pas de stats par joueur.

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
| Ajouter des boutons dans la console pour le secrétaire | viole C3 |
| Demander les tireurs / arrêteurs au chronométreur de tir | viole C2 |
| Saisie exhaustive en direct par une personne dédiée | C7 (2022) |
| Capteurs embarqués | coût, robustesse, budget (< 1 000 € / terrain, étude afficheurs) |
| Rendre une saisie obligatoire pour valider le match | viole C5 |

## 5. Comparatif (v2)

| Solution | Arrêts | Tirs | Possession | Personne en plus | Impact match | Couverture attendue |
|---|---|---|---|---|---|---|
| §3.3 Reconstruction (chrono de tir + buts) | par équipe (arrêts + cadre) | cadrés par équipe (minorant) | par équipe, **hors segments non ancrés** | non | aucun | tous les matchs scorés |
| §3.4 Touche `/` (60 s même équipe) | — | — | **exceptions de Q9 levées** | non | nul | selon Q9 |
| §4.1 Bancs sur squelette | ✅ par joueur | ✅ par joueur | (corrige les pertes) | **oui, motivée** | aucun | variable, croissante |
| §4.2 Statisticien | ✅ | ✅ | — | oui | aucun | complément |
| §4.3 Vidéo | ✅ | ✅ | ✅ | oui, différé | aucun | faible (Q4) |

## 6. Recommandation (v2)

1. **Lot 1 — Reconstruction** (§3.3) : calcul serveur après le match, à partir de `scoring_live_clock`
   (départs 60 / 40 / arrêt), des buts et du chrono principal. Comptes d'équipe publics : possession
   (quand ancrée), tirs cadrés, arrêts + cadre, violations. **Aucun changement à la table de marque.**
   C'est aussi le **socle** du squelette du lot 3.
2. **Lot 2 — Touche `/` « 60 s, même équipe »** (§3.4) : un raccourci configurable, aucun affichage
   nouveau. À trancher après Q9.
3. **Lot 3 — Page « Stats de mon équipe »** (§4.1) : squelette pré-rempli (dont les engagements), un
   geste par ligne, responsable d'équipe, fenêtre de 24 h, contrôles de cohérence, seuil de 80 %. Démarrer par
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
- **Touche `/`** : la commande du chronomètre de tir porte un attribut `sameTeam` (`start60` +
  `sameTeam = true`), persisté avec le départ dans `scoring_live_*` et le journal ; la machine à états
  du chronomètre de tir (`ScoringRules::shotclockTransition`) ne change pas.
- **`kp_stats` (2022) : supprimé (Q13)** — migration de suppression de la table, retrait de
  `sources/app_wsm_dev/src/views/Stats.vue` et de `PUT /admin/scoring/stats`
  (`ScoringController::putStats`). Indépendant des lots ci-dessus : peut se faire à tout moment.

## 8. Questions ouvertes (v3)

| # | Question |
|---|---|
| **Q9** | *(en cours de renseignement)* **60 s sans changement de possession** : dans quels cas le chronomètre de tir repart-il à 60 s pour **la même équipe** (faute de la défense sanctionnée par un coup franc à l'attaque, sortie concédée par la défense, temps mort, carton…) et à quelle fréquence ? |
| **Q14** | **Délégation** : le responsable d'équipe peut-il confier la saisie à une personne du banc (lien nominatif limité au match), ou doit-il saisir lui-même ? |
| **Q15** | **Suppression de `kp_stats`** : la faire tout de suite (petit ménage indépendant), ou avec le lot 1 ? |
