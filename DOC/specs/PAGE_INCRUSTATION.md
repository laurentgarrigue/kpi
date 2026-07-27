# Spécification — Page d'incrustation unique (overlay vidéo live)

> Statut : cadrage (2026-07-27) — implémentation au **lot 4** du
> [plan d'action](../developer/reference/LIVE_MATCH_SCORING_REFACTORING_PROPOSALS.md).
> Cible : remplace les **~20 pages d'incrustation PHP** (`sources/live/*.php`) **et**
> l'application Vue **app_live** (`sources/app_live_dev/`).
> Modèle fonctionnel : app_live et les pages `live/*.php` (l'existant est décrit dans
> [LIVE_MATCH_WEBSOCKET_ARCHITECTURE.md §2.5–2.6 et §13](../developer/reference/LIVE_MATCH_WEBSOCKET_ARCHITECTURE.md)),
> **adapté à la nouvelle architecture** : état canonique `scoring_live_*` + diffusion Mercure —
> plus de cache JSON, plus de broker WebSocket.
> Document frère : [PAGE_SCORING.md](PAGE_SCORING.md) (la console qui **écrit** ; cette page
> **lit**).

---

## 1. Contexte et objectif

L'incrustation vidéo (score, chrono, faits de jeu…) est aujourd'hui servie par une vingtaine de
pages PHP quasi identiques plus une app Vue (`app_live`), toutes alimentées par le **cache JSON
fichier** régénéré par le worker, plus le broker WebSocket pour la couche temps réel d'app_live.
La refonte remplace tout cela par **une seule page paramétrée** :

- elle affiche **de façon autonome** l'incrustation du **bon match** selon l'**événement**, le
  **terrain**, le **style** (css) et les **options** définis **dans l'URL** ;
- elle **passe d'un match à l'autre toute seule**, en fonction du **statut** des matchs, de la
  définition du **match suivant** (next-game) et de l'**heure** — logique aujourd'hui portée par
  l'event-cache-worker, conservée côté serveur (cf. §6) ;
- elle est conçue comme **calque** (overlay) pour **OBS Studio ou équivalent** : **aucune
  interaction utilisateur** n'est attendue ni possible — la page a sa propre vie, entièrement
  déterminée par son URL et par ce que le serveur publie.

**Principe directeur** (hérité du plan §3) : la page **lit l'état de départ** (`GET`, servi par
cache HTTP) puis **s'abonne à Mercure** — uniquement aux topics de **son** terrain de **son**
événement, et uniquement aux **blocs** qu'elle affiche. Un rechargement (ou un redémarrage d'OBS)
la remet dans le bon état en une requête.

## 2. Principes non négociables

| # | Principe | Conséquence |
|---|---|---|
| P1 | **Zéro interaction** | aucun bouton, aucun clic, aucun clavier ; toute variation de comportement passe par l'URL ou par les réglages serveur (§7) |
| P2 | **Autonome et auto-réparante** | démarrage à froid = `GET` état + programme, puis SSE ; reconnexion automatique avec rejeu (`Last-Event-ID`) ; en cas de doute (trou de rejeu, réveil d'onglet), re-`GET` complet |
| P3 | **Lecture seule, publique** | la page n'écrit rien ; les données affichées sont publiques → **pas d'authentification** (comme les incrustations actuelles) |
| P4 | **Le serveur décide, la page affiche** | quel match est courant/suivant, quand basculer, quels délais : tout est calculé ou paramétré côté serveur ; la page n'embarque pas de règle métier d'aiguillage |
| P5 | **Interpolation locale des horloges** | chrono, chronomètre de tir (shotclock) et pénalités sont recalculés localement depuis le modèle 4 valeurs (plan §3.1) — le réseau ne porte que les play/pause |

## 3. Pile technique et format

- **Nuxt 4 + Tailwind CSS** (alignée sur les derniers développements — app4). Route publique
  **sans layout ni auth** (`definePageMeta({ layout: false })` + exclusion du middleware auth),
  hébergée dans app4 ou en mini-app dédiée — **recommandation : page publique du projet app4**,
  pour réutiliser les composants d'affichage (score, chrono, shotclock) partagés avec le
  scoreboard de la console ; à confirmer à l'implémentation (contrainte : l'URL doit rester
  simple et stable pour les régies).
- **Format par défaut : 1920×1080** (1080p50 des régies actuelles). La page est dessinée dans un
  « stage » 1920×1080 mis à l'échelle par transform pour s'adapter à la fenêtre (OBS capture en
  1080p → rendu 1:1). D'autres formats viendront avec la refonte des styles (§10).
- **Fond** : transparent par défaut (OBS gère l'alpha des sources navigateur), ou couleur unie
  pour chromakey (magenta, vert…) — paramétrable (§7, surchargeable par l'URL).
- **Pas de son** : le buzzer et les signaux sonores appartiennent à la console Scoring, jamais à
  l'incrustation.

## 4. Contrat d'URL

L'URL est **le seul contrat** de la page. Tout ce qui varie d'une incrustation à l'autre y figure :

```
/live/overlay?event=236&pitch=2&blocks=score,clock,shotclock&skin=nations&style=avranches2025&bg=transparent
```

| Paramètre | Obligatoire | Valeurs / défaut | Rôle |
|---|---|---|---|
| `event` | ✅ | id d'événement KPI | 1ᵉʳ niveau d'abonnement (isolation multi-événements, plan §3.3) |
| `pitch` | ✅ | numéro de terrain | 2ᵉ niveau d'abonnement |
| `blocks` | — | liste parmi `score`, `clock`, `shotclock`, `penalty`, `fact`, `teams`, `next` ; défaut `score,clock` | blocs affichés **et** topics souscrits (rien de plus) |
| `skin` | — | `nations` \| `clubs` ; défaut `nations` | habillage : drapeaux/nations ou logos/clubs (logo d'équipe d'abord, drapeau dérivé du code club en fallback — cf. PAGE_SCORING §0.9) |
| `style` | — | identifiant de style (ex. `avranches2025`) ; défaut : style de l'événement (§7) | thème visuel (css/assets), cf. §10 |
| `variant` | — | `live` \| `events` \| `static` \| `hd` ; défaut `live` | équivalents des suffixes legacy (sans/`_e`/`_s`/HD), le temps de la transition |
| `bg` | — | `transparent` \| `magenta` \| couleur CSS ; défaut : réglage événement (§7) | fond (alpha OBS ou chromakey) |
| `mode` | — | `single` \| `multi` ; défaut `single` | `multi` : vue multi-terrains (remplace `multi_score*.php`) — `pitch` accepte alors une liste (`pitch=1,2,3`) |
| `lang` | — | `fr` \| `en` ; défaut `fr` | libellés fixes |
| `debug` | — | absent par défaut | surcouche diagnostic (état SSE, tick, topics) — jamais en production |

**Correspondance avec les pages legacy** (grille de migration, cf. architecture §13.3) :

| Page legacy | URL équivalente |
|---|---|
| `score.php` / `score_club.php` | `blocks=score,clock,shotclock,penalty,fact` + `skin=nations|clubs` |
| `score_o.php` / `score_club_o.php` | `blocks=score,clock` (« score only ») |
| `score_e.php` / `_s.php` | `blocks=fact` + `variant=events|static` |
| `scoreHD.php` | `variant=hd` |
| `teams.php` / `teams_club.php` / `liveteams.php` | `blocks=teams` |
| `next_game.php` / `next_game_club.php` | `blocks=next` |
| `multi_score.php` / `multi_score2.php` | `mode=multi&pitch=1,2,3` |
| `matchs.php`, `liste_matchHD.php`, `presentation.php`, `presentationHD.php` | `blocks=next` (listes/présentation) — variantes à cadrer pendant le lot 4 |

## 5. Données consommées

Au démarrage (et à chaque resynchronisation complète) :

| Appel | Contenu | Cache |
|---|---|---|
| `GET /api2/scoring/program/{event}/{pitch}` | **programme du terrain** : match courant (`current`), match suivant (`next` : id, n°, heure, équipes), réglages d'affichage de l'événement (§7) | cache HTTP court + `ETag` |
| `GET /api2/scoring/state/{matchId}` | **état complet du match** : score, période, statut, horloges (modèle 4 valeurs), pénalités, faits de match, compositions | cache HTTP court + `ETag` (plan §1.3) |

Puis abonnement **SSE Mercure** (hub embarqué api2, plan §4.6) aux topics du terrain — uniquement
ceux correspondant aux `blocks` de l'URL :

```
/scoring/event/{event}/pitch/{pitch}/score
/scoring/event/{event}/pitch/{pitch}/clock
/scoring/event/{event}/pitch/{pitch}/shotclock
/scoring/event/{event}/pitch/{pitch}/penalty
/scoring/event/{event}/pitch/{pitch}/fact
/scoring/event/{event}/pitch/{pitch}/program   ← toujours souscrit (aiguillage, cf. §6)
```

- **Rejeu natif** : à la reconnexion, `Last-Event-ID` fait rejouer les messages manqués par le hub.
- **Filet de sécurité** : sur reconnexion après une longue coupure, réveil d'onglet (`visibilitychange`)
  ou incohérence de `tick`, la page refait les deux `GET` — c'est peu coûteux (cache HTTP) et ça
  garantit P2.
- **Horloges** : chaque message `clock`/`shotclock`/`penalty` porte le modèle 4 valeurs ; le
  tic-tac est recalculé localement à l'affichage (plan §3.1). La dérive d'horloge locale est
  corrigée à chaque message reçu (offset heure serveur).

## 6. Aiguillage du match : le programme du terrain (fin du polling)

**Aujourd'hui** : l'event-cache-worker écrit `event{e}_pitch{p}.json` (`id_match` courant,
`id_next`), et chaque incrustation **le re-lit en boucle** (polling), comme toutes les pages
legacy (architecture §13).

**Demain** : la **logique de calcul est conservée** (le worker api2 continue de déterminer, par
terrain et à intervalle régulier, le match courant — `GetBestMatch` — et le suivant —
`GetNextMatch` — en fonction de la programmation, des statuts et de l'heure), mais sa
**publication change** :

- le worker écrit le programme dans une table (ou l'état existant) **et dépose une ligne
  d'outbox** quand il change → publication Mercure sur le topic
  `/scoring/event/{e}/pitch/{p}/program` ;
- un **changement de statut de match** saisi à la console (`ON`, `END`…) déclenche **immédiatement**
  un recalcul du programme du terrain (pas d'attente du prochain passage du worker) — c'est le
  gros gain par rapport au legacy, où la bascule attendait la cadence du worker ;
- la page ne **poll jamais** : elle reçoit le nouveau programme par SSE, applique la **séquence
  d'enchaînement** (§7) puis charge l'état du nouveau match (`GET /state` + bascule des topics si
  le match change de terrain — cas normal : le terrain ne change pas, seuls les ids changent).

> Le fichier `event{e}_pitch{p}.json` continue d'être généré **en parallèle** pour les
> incrustations legacy tant qu'elles existent (plan §5, règle « on ne touche à rien tant que le
> neuf ne marche pas »). La nouvelle page n'y touche jamais.

## 7. Enchaînement d'affichage : la séquence, paramétrée par événement

La page déroule automatiquement le **cycle de vie d'un match sur un terrain**. Les **durées et
options de chaque étape ont des valeurs par défaut** (constantes serveur), **surchargeables en
base au niveau de l'événement, puis du terrain** (résolution : défauts → événement → terrain,
le plus spécifique gagne). Les réglages résolus sont servis par `GET /program` et modifiables
dans app4 (supervision TV/événement) ; toute modification est publiée sur le topic `program`
(prise en compte sans rechargement).

**Séquence type** (bloc `score` + `next` actifs) :

```
avant-match ──► match en cours ──► fin de période ──► période suivante … ──► fin de match ──► prochain match
(présentation      (score/chrono/       (score « mi-temps »                    (score final          (présentation,
 du match,          shotclock/faits      affiché X s après                      affiché X s après     puis attente)
 compos)            de jeu)              la fin de période,                     la fin ou au
                                         jusqu'à la reprise)                    statut END)
```

**Réglages** (valeurs par défaut à fixer à l'implémentation, surchargeables par événement puis
par terrain, liste extensible) :

| Réglage | Rôle | Exemple |
|---|---|---|
| `halftimeScoreDelay` | délai d'affichage du score de mi-temps après la fin de la période | 5 s après la fin de `M1`, affiché jusqu'au passage à la période suivante |
| `finalScoreDelay` | délai d'affichage du score final après la fin de la dernière période **ou** dès le passage du statut à `END` | 5 s |
| `finalScoreDuration` | durée d'affichage du score final avant d'enchaîner | 120 s |
| `nextGameDelay` | délai avant la présentation du prochain match (après le score final) | X s |
| `nextGamePresentationDuration` | durée de la présentation du prochain match (compos, heure) | jusqu'au coup d'envoi, ou N s |
| `bg` | fond par défaut de l'événement : `transparent`, `magenta`, couleur | `transparent` (surchargeable par l'URL) |
| `styleId` | style par défaut de l'événement (cf. §10) | `avranches2025` |

Chaque **transition** de la séquence est déclenchée par un **fait serveur** (message Mercure :
fin de période, statut `END`, nouveau programme) **plus** le délai paramétré — jamais par une
horloge locale seule. Ainsi deux incrustations du même terrain (régie + secours) affichent la
même chose au même moment.

## 8. Blocs d'affichage

Chaque bloc est un composant autonome (props down, aucune logique d'abonnement en propre) monté
selon `blocks` :

| Bloc | Contenu | Source |
|---|---|---|
| `score` | bandeau score : équipes (noms courts, logos/drapeaux, couleurs), score, période | topic `score` + état |
| `clock` | chrono de jeu (interpolation locale) | topic `clock` |
| `shotclock` | chronomètre de tir — affiché `--` à l'arrêt, masqué quand le temps restant de la période est inférieur (parité console, PAGE_SCORING §6.5) | topic `shotclock` |
| `penalty` | pénalités en cours par équipe (décomptes, n° joueur) — cartons : vert/jaune/rouge/**noir** (exclusion définitive, PAGE_SCORING §0.9) | topic `penalty` |
| `fact` | faits de jeu (buts/cartons horodatés, nom du joueur) — défilement/apparition selon `variant` | topic `fact` + état |
| `teams` | compositions des deux équipes (n°, noms, capitaine, officiels) | état (`GET /state`) |
| `next` | présentation du prochain match (n°, heure, équipes, compos si disponibles) | programme (§6) |

Le rendu de chaque bloc dépend du **style** (§10) ; la donnée et la logique n'en dépendent
jamais.

## 9. Résilience

| Situation | Comportement |
|---|---|
| Coupure réseau courte | reconnexion SSE automatique + rejeu `Last-Event-ID` ; les horloges continuent en local pendant la coupure |
| Coupure longue / rejeu impossible | re-`GET` programme + état, réabonnement — retour à l'état juste en < 2 s |
| Redémarrage d'OBS / rechargement | démarrage à froid standard (P2) : l'URL suffit |
| Hub Mercure indisponible | la page garde le dernier état affiché + horloges locales ; re-tentatives avec backoff ; bandeau discret en mode `debug` uniquement |
| Match déplacé / programme corrigé dans KPI | nouveau message `program` → la page bascule d'elle-même |

## 10. Styles (css) : reproduire d'abord, refondre ensuite

**Aujourd'hui** : un fichier css par grand groupe de compétition (`live/css/avranches2025.css`,
`cna2022.css`, `deqing2024.css`…) + un **dossier d'assets dédié par événement**
(`live/img_avranches2025/`, `img_cna2022/`, fonds de bandeaux, polices digitales…).

**Phase 1 (lot 4)** — *reproduire* : le paramètre `style` (ou le défaut de l'événement) charge un
**thème** équivalent au couple css + assets actuel, porté en Tailwind/CSS moderne. Objectif :
parité visuelle avec les incrustations existantes pour permettre la bascule terrain par terrain,
sans redessiner.

**Phase 2 (second temps, hors lot 4)** — *refondre le système de styles* :

- un **thème = jeu de design tokens** (couleurs, polices, fonds, dimensions des bandeaux) + jeu
  d'assets (logos, fonds) **géré en base et uploadé depuis app4** (comme les logos d'équipes
  aujourd'hui), plus aucun dossier `img_*` à déployer à la main ;
- **création d'un nouveau style pour une nouvelle compétition sans code** (dupliquer un thème,
  changer tokens et assets, prévisualiser) ;
- **adaptation à d'autres formats d'affichage** (vertical, 4K, LED bord de bassin…) par variantes
  de layout du même thème.

## 11. Ce que la page ne fait pas

- **Aucune écriture, aucun POST** (hors requêtes GET/SSE).
- **Aucune interaction** : pas de raccourcis, pas de clic, pas de plein écran à gérer (OBS s'en
  charge).
- **Pas de son.**
- **Pas d'authentification** ni de données non publiques (les compositions affichées sont celles
  déjà rendues publiques par les incrustations actuelles).
- **Pas de logique d'aiguillage locale** : elle ne décide jamais quel match afficher (P4).

## 12. Vérification de bout en bout

1. **Autonomie** : ouvrir l'URL dans un navigateur nu → la page affiche le bon match de
   l'événement/terrain sans aucune action ; la recharger → même état.
2. **Temps réel** : saisir à la console Scoring (but, chrono run/stop, chronomètre de tir,
   pénalité) → l'incrustation reflète chaque action ; couper le réseau 30 s pendant des buts →
   rattrapage exact à la reconnexion.
3. **Enchaînement** : fin de `M1` → score mi-temps après le délai paramétré ; statut `END` →
   score final puis présentation du prochain match selon les délais de l'événement ; coup d'envoi
   du match suivant → bascule sans intervention.
4. **Isolation** : deux événements simultanés, deux pages ouvertes → aucune fuite d'un flux vers
   l'autre (topics, plan §3.3).
5. **OBS** : source navigateur 1920×1080, fond transparent → alpha correct ; `bg=magenta` →
   chromakey correct.
6. **Parité** : pour chaque famille legacy (§4, grille de migration), comparaison côte à côte sur
   un match réel avant bascule du terrain (plan lot 4, étape 4.6).

## 13. Hors périmètre / plus tard

- **Refonte du système de styles** (§10 phase 2) — second temps.
- **Formats autres que 1920×1080** — avec la refonte des styles.
- **Consommateurs `kp_*` en cours de match** (`FeuilleMatchMulti.php`, app2) : traités par
  l'étape 4.5 du plan (même lot, autre chantier que cette page).
- **Statistiques enrichies à l'écran** (tirs/arrêts…) : dépend de l'extension des faits de match
  (PAGE_SCORING §0.4), non planifié.
