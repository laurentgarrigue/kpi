# Étude — Statistiques de jeu étendues (possession, passes, tirs, arrêts, interceptions)

> **Statut** : étude / aide à la décision. Aucune ligne de code engagée.
> - v1 (2026-11-27) : contraintes, sources de données possibles, solutions graduées, questions.
>
> **Contexte** : console Scoring d'app4 ([PAGE_SCORING.md](../../specs/PAGE_SCORING.md), notamment
> §0.4 « fait de match extensible » et §6.5 chronomètre de tir), médailles de match
> ([FEATURE_MATCH_MEDALS.md](../../specs/public/FEATURE_MATCH_MEDALS.md)), afficheurs du chronomètre de tir
> ([SCORING_SHOTCLOCK_REMOTE_DISPLAYS_STUDY.md](SCORING_SHOTCLOCK_REMOTE_DISPLAYS_STUDY.md)).
> **Terminologie** : « fait de match » / « fait de jeu » (jamais « événement » seul, cf. PAGE_SCORING §0.4).

---

## 1. Objectif

Capturer, pendant ou après un match, des données qui ne figurent pas sur la feuille de match
officielle, pour :

1. produire des **statistiques** par équipe et par joueur ;
2. proposer plus tard de **nouvelles médailles** (ex. « Mur » pour un gardien, « Meneur » pour un
   passeur, « Pressing » pour une équipe qui intercepte beaucoup).

| Donnée visée | Granularité utile | Exemples de stats dérivées |
|---|---|---|
| **Possession** | équipe | temps de possession, nombre de possessions, durée moyenne, pertes de balle |
| **Passes** | joueur (passeur, receveur) | passes réussies, passes décisives (passe suivie d'un but) |
| **Tirs** | joueur, issue | tirs, tirs cadrés, efficacité (buts / tirs), tirs par possession |
| **Arrêts du gardien** | joueur | arrêts, % d'arrêts (arrêts / tirs cadrés subis) |
| **Interceptions** | joueur | interceptions, balles récupérées |

## 2. Contraintes (données d'entrée)

| # | Contrainte | Conséquence |
|---|---|---|
| C1 | **Le gardien n'est pas un joueur fixe.** C'est généralement un rôle dédié, mais pendant le match n'importe lequel des 5 joueurs peut l'être : le règlement désigne comme gardien **le joueur qui se tient sous le but, pagaie levée à la verticale**. | Un arrêt **ne peut pas** être imputé automatiquement au « gardien de la composition » : il faut l'imputer au **joueur qui a fait l'arrêt** (son numéro), au moment de l'action. Le rôle « gardien » est un **état instantané**, pas un attribut du joueur. |
| C2 | **Le chronométreur de tir est un rôle dédié**, avec un **statut d'arbitre**, qui **ne peut rien faire d'autre** que le chronomètre de tir. | Aucune saisie supplémentaire ne peut lui être demandée **en plus** de son rôle. En revanche, ce qu'il saisit **déjà** (départ 60 s, départ 40 s, arrêt) est une source de données exploitable (§3.2). |
| C3 | **Le secrétaire de table de marque** (scoring : chrono principal, buts, cartons, pénalités) est **déjà très occupé et concentré**. | **Aucune** saisie supplémentaire ne doit lui être imposée. La console Scoring ne doit pas se complexifier pour lui. |
| C4 | **Bénévoles supplémentaires rarement disponibles** à chaque table de marque. | Une solution qui exige une personne de plus **par terrain et par match** ne sera pas généralisable. Elle peut exister en **option**, là où quelqu'un est disponible. |
| C5 | **Ne pas impacter le déroulement du match.** | Les stats étendues sont **non officielles** : jamais bloquantes, jamais sur la feuille de match, jamais dans `kp_match_detail`. Une panne ou un oubli de saisie ne gêne ni le score, ni le chrono, ni la validation. |
| C6 | Le jeu est **rapide** : une possession dure au plus 60 s (souvent bien moins), les passes s'enchaînent en 1–3 s. | Une saisie **exhaustive des passes en direct** par une seule personne est irréaliste ; les tirs, arrêts et interceptions (quelques dizaines par match) le sont davantage. |

### 2.1 Ce qui existe déjà

- **Fait de match extensible** : `scoring_live_event.kind` est prévu pour accueillir `SHOT`, `PASS`,
  `STEAL`, `SAVE`… sans changement de schéma (PAGE_SCORING §0.4/§0.5).
- **Chronomètre de tir horodaté et persisté** : `scoring_live_clock` (`kind = SHOTCLOCK`) — chaque
  départ 60 s / 40 s / arrêt est une écriture serveur horodatée (PAGE_SCORING §6.5).
- **Précédent legacy : « KPI Stats » (2022)** — `sources/app_wsm_dev/src/views/Stats.vue`, table
  `kp_stats`, endpoint `PUT /api2/admin/scoring/stats` (actions `pass`, `possession`, `kickoff`,
  `kickoff-ko`, `shot-in`, `shot-out`, `shot-stop`). Une page tactile où un opérateur tape l'équipe
  puis le numéro du joueur. Elle démontre le besoin… et la limite C4 (un opérateur dédié).
- **Médailles de match** : calcul serveur à partir des feuilles de match, avec notion
  d'**éligibilité / fiabilité** d'un match (FEATURE_MATCH_MEDALS §5.1) — réutilisable pour ne
  calculer des médailles « stats étendues » que sur les matchs suffisamment couverts.

## 3. Ce qu'on peut obtenir sans personne de plus

### 3.1 Déjà dans les données (gratuit)

Buts (avec buteur et temps), cartons, exclusions, temps de supériorité numérique : déjà exploités
par les médailles. Rien de neuf sur la possession, les passes, les tirs ou les arrêts.

### 3.2 Ce que révèle déjà le chronomètre de tir

Les trois commandes du chronométreur de tir **décrivent déjà le flux des possessions** :

| Commande | Signification de jeu |
|---|---|
| **Départ 60 s** | **nouvelle possession** (engagement, but, sortie, faute, interception, arrêt du gardien…) |
| **Départ 40 s** | **tir** de l'équipe en possession, **suivi d'un rebond conservé** par la même équipe |
| **Arrêt** | jeu arrêté / pas de possession |
| Expiration (0) | **violation du temps de tir** → perte de balle |

Sans rien changer, on obtient donc : le **nombre de possessions** (≈ nombre de départs 60 s),
leur **durée** (jusqu'au départ suivant, chrono principal arrêté déduit), un **minimum de tirs**
(chaque 40 s est un tir), les **violations du temps de tir**. Il manque une information
essentielle : **quelle équipe** a la possession.

### 3.3 Variante « chronomètre de tir orienté » (sans tâche supplémentaire au sens strict)

Le chronométreur de tir **sait déjà**, au moment où il appuie sur « 60 s », quelle équipe prend le
ballon : c'est ce qui déclenche sa commande. Si le boîtier (ou le pavé numérique, cf. étude
afficheurs R9) propose **« 60 s équipe A » / « 60 s équipe B »** au lieu d'un seul « 60 s », on
obtient **la possession par équipe** pour le prix d'un choix de touche, sans geste en plus.

- Possession par équipe, temps de possession, nombre de possessions : **complets**.
- Tirs avec rebond offensif (40 s) : **attribués à l'équipe**.
- Pertes de balle / récupérations : tout « 60 s » de l'autre équipe sans but intermédiaire.
- Ce que ça **ne donne pas** : le joueur, les passes, la distinction tir arrêté / tir non cadré /
  interception quand la balle change d'équipe.

> ⚠️ **À valider** (question Q1) : cette variante touche à l'outil d'un officiel au statut
> d'arbitre. Elle n'ajoute pas de tâche mais modifie son interface (deux touches « 60 s »). Une
> erreur d'orientation **ne doit avoir aucun effet** sur le décompte affiché (même 60 s des deux
> côtés) : seule la statistique serait fausse. Option de repli : l'orientation peut être
> **désactivée par compétition**, le chronomètre de tir reste alors à 3 commandes.

## 4. Solutions avec une saisie humaine supplémentaire, hors table de marque

Toutes partagent le même principe : **la saisie des stats se fait sur un autre appareil, par une
autre personne, hors du chemin critique du match**, et s'appuie sur l'horloge du match diffusée par
Mercure (période + temps de jeu) pour horodater chaque fait.

### 4.1 S1 — Saisie depuis le banc, par chaque équipe (« stats de mon équipe »)

Une page mobile (PWA app4 ou app2) où un membre **de l'équipe** (remplaçant, coach, parent)
saisit les faits **de son équipe uniquement** : tirs (cadré / non cadré / bloqué), arrêts de
« notre » gardien, interceptions, voire passes décisives. Le numéro du joueur se choisit sur la
composition déjà connue.

- **Motivation intrinsèque** : les clubs veulent ces stats pour eux-mêmes (coaching, réseaux
  sociaux). Pas besoin de recruter un bénévole « neutre » à la table.
- **C1 résolu** : la personne sur le banc sait qui est sous le but.
- **Risques** : partialité (compenser par le croisement des deux équipes : un tir cadré de A doit
  correspondre à un arrêt ou un but contre B), couverture inégale (une seule équipe saisit).
- **Accès** : lien/QR code par match et par équipe, sans compte ou avec un compte « équipe » ; pas
  de droit sur la feuille de match.

### 4.2 S2 — Saisie par un volontaire « statisticien » (spectateur, jeune, encadrant)

Même page que S1, mais pour **les deux équipes**, par une personne qui n'est pas à la table :
spectateur, joueur d'une autre équipe en attente, stagiaire arbitre/officiel (bonne formation à la
lecture du jeu). Utile sur les matchs à enjeu (phases finales, matchs filmés).

- Volume réaliste en direct pour une personne : **tirs + arrêts + interceptions** (quelques
  dizaines par match), éventuellement **possession** si la variante §3.3 n'est pas retenue.
- **Pas les passes** en direct (C6), sauf « passe décisive » rattachée à un but déjà saisi.

### 4.3 S3 — Annotation vidéo différée (après le match)

Beaucoup de matchs sont **filmés** (diffusion avec incrustations). Une page app4 « Annotation
vidéo » lit la vidéo (fichier ou lien de replay) et permet d'annoter au clavier, **avec pause,
ralenti et retour arrière**.

- **Seule solution réaliste pour les passes** et pour une possession fine.
- **Zéro impact** sur le match, **zéro bénévole** à la table, travail faisable **n'importe où,
  n'importe quand**, par plusieurs personnes (un match chacun, ou une équipe chacun).
- **Synchronisation vidéo ↔ match** : les faits déjà connus (buts, cartons, départs du chronomètre
  de tir) servent de **points d'ancrage** — l'annotateur cale la vidéo sur le premier but, l'outil
  propose ensuite automatiquement la position de chaque fait connu. Les incrustations affichent
  le chrono, ce qui facilite le calage.
- **Coût** : temps humain (≈ 1,5 à 2 × la durée du match pour une annotation complète) ;
  dépendance à la disponibilité des vidéos.

### 4.4 S4 — Analyse vidéo automatique (long terme)

Suivi du ballon et des bateaux par vision par ordinateur sur les vidéos filmées. Possession,
passes et tirs sont en théorie détectables ; l'imputation au joueur (numéros sur les casques/gilets)
est difficile. **Hors de portée à court terme**, mais les données de S3 constitueraient le jeu
d'entraînement et de validation. À garder comme perspective, pas comme plan.

### 4.5 Écartées

| Piste | Raison |
|---|---|
| Ajouter des boutons dans la console Scoring | viole C3 |
| Demander les tirs/arrêts au chronométreur de tir | viole C2 (tâche en plus, même si la variante §3.3 est acceptable) |
| Capteurs embarqués (ballon, bateaux) | coût, homologation, robustesse ; sans rapport avec les budgets (cf. étude afficheurs, < 1 000 € / terrain) |
| Rendre une saisie obligatoire pour valider le match | viole C5 |

## 5. Comparatif

| Solution | Possession | Passes | Tirs | Arrêts | Interceptions | Personne en plus | Impact match | Couverture attendue |
|---|---|---|---|---|---|---|---|---|
| §3.2 Chrono de tir actuel | nombre, durée (pas l'équipe) | — | partiel (40 s) | — | — | non | aucun | tous les matchs scorés |
| §3.3 Chrono de tir orienté | ✅ par équipe | — | partiel, par équipe | — | pertes/récup. par équipe | non | quasi nul (Q1) | tous les matchs scorés |
| S1 Banc de chaque équipe | (si saisie) | décisives | ✅ | ✅ | ✅ | oui, mais **motivée** | aucun | variable, croissante |
| S2 Statisticien volontaire | ✅ | décisives | ✅ | ✅ | ✅ | oui | aucun | matchs à enjeu |
| S3 Annotation vidéo | ✅ fine | ✅ | ✅ | ✅ | ✅ | oui, **différé** | aucun | matchs filmés |
| S4 Vidéo automatique | ✅ | ✅ ? | ✅ ? | ? | ? | non | aucun | perspective |

## 6. Recommandation (v1)

Une démarche **en couches**, chaque couche apportant de la valeur seule :

1. **Couche 0 — exploiter le chronomètre de tir (§3.2)**, sans rien changer à la table : nombre et
   durée des possessions, violations du temps de tir, tirs avec rebond. Coût : un calcul serveur.
2. **Couche 1 — chronomètre de tir orienté (§3.3)**, si Q1 est validé : possession par équipe sur
   **tous** les matchs scorés. C'est la seule source exhaustive possible sans bénévole.
3. **Couche 2 — une seule page de saisie « stats » mobile, hors console**, utilisable en S1 (une
   équipe) **et** S2 (les deux équipes) : tirs (cadré / non cadré / contré), arrêts, interceptions,
   passe décisive. Non bloquante, non officielle.
4. **Couche 3 — la même saisie en mode annotation vidéo (S3)**, pour les matchs filmés et les
   passes. Mêmes types de faits, autre horloge (temps vidéo recalé sur le temps de jeu).

**Médailles** : ne calculer une médaille fondée sur ces stats que sur les matchs dont la
**couverture** est suffisante (ex. tirs saisis des deux côtés, cohérence tirs cadrés ↔ arrêts +
buts), en réutilisant la logique d'éligibilité de FEATURE_MATCH_MEDALS §5.1. Les médailles
« possession » peuvent s'appuyer sur la couche 1 seule.

## 7. Esquisse du modèle de données (à affiner dans une spec)

Les stats étendues **ne sont pas** des faits de la feuille de match : elles ne doivent pas se mêler
aux buts/cartons qui sont consolidés dans `kp_match_detail` en fin de match. Deux options :

- **A.** les stocker dans `scoring_live_event` avec un `kind` dédié (`SHOT`, `SAVE`, `STEAL`,
  `PASS`…) et une colonne `source`, en excluant ces kinds de la consolidation ;
- **B. (préférée)** une table dédiée `match_stat_fact` : plusieurs sources concurrentes pour un même
  match (banc A, banc B, statisticien, vidéo) sans risque pour le chemin critique du scoring, et
  conservation au-delà de la fin du match (l'annotation vidéo arrive après la consolidation).

Colonnes indicatives : `uid`, `id_match`, `kind` (`SHOT`/`SAVE`/`STEAL`/`PASS`/`POSSESSION`…),
`outcome` (ex. tir : `ON_TARGET`/`OFF_TARGET`/`BLOCKED`/`GOAL`), `team`, `player` (licence ou
numéro), `player2` (receveur de passe), `periode`, `game_time_ms`, `source`
(`SHOTCLOCK`/`BENCH_A`/`BENCH_B`/`STATISTICIAN`/`VIDEO`), `author`, `created_at`.
`kp_stats` (2022) est un précédent à migrer ou à archiver, selon Q6.

**C1 (gardien)** : `SAVE` porte le joueur qui a arrêté, sans référence à un rôle « gardien »
déclaré. La stat « arrêts du gardien » est la somme des `SAVE` du joueur ; une médaille « Mur » peut
s'appuyer sur un % d'arrêts par joueur, quel que soit son poste habituel.

## 8. Questions ouvertes (v1)

| # | Question |
|---|---|
| **Q1** | **Chronomètre de tir orienté** (§3.3) : est-il acceptable, réglementairement et pour les arbitres, que le chronométreur de tir choisisse « 60 s A » / « 60 s B » au lieu d'un seul « 60 s » ? Ou son interface est-elle intouchable ? |
| **Q2** | **Règle du 40 s** : est-ce exactement « tir de l'équipe en possession, rebond récupéré par la même équipe » (donc chaque 40 s = un tir) ? Un tir arrêté par le gardien puis récupéré par l'attaque donne-t-il aussi 40 s ? |
| **Q3** | **Saisie par les équipes** (S1) : acceptable d'ouvrir une saisie (non officielle) aux bancs ? Avec quel accès : lien/QR code par match, compte équipe, responsable d'équipe déjà connu dans KPI ? |
| **Q4** | **Vidéo** (S3) : quelle part des matchs est filmée, où sont les fichiers (YouTube, fichiers OBS locaux…), et sont-ils récupérables après coup ? |
| **Q5** | **Priorités** : parmi possession, passes, tirs, arrêts, interceptions, lesquelles comptent le plus pour les premières médailles ? |
| **Q6** | **KPI Stats (2022, `kp_stats`)** : a-t-il été utilisé en compétition ? Quels retours (rythme tenable, fiabilité) ? Des données à conserver ? |
| **Q7** | **Diffusion** : ces stats doivent-elles apparaître **en direct** (incrustations : « possession 58 % / 42 % ») ou seulement **après le match** (site public, médailles) ? Le direct impose la qualité dès la saisie. |
| **Q8** | **Publication** : stats publiques par joueur dès le départ, ou d'abord réservées aux équipes / admins le temps de mesurer la fiabilité ? |
