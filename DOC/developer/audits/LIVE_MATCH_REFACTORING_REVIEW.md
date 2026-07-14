# Revue critique — Refonte du scoring live (harmonisation, simplification, consolidation)

> **Audit** des documents [LIVE_MATCH_SCORING_REFACTORING_PROPOSALS.md](../reference/LIVE_MATCH_SCORING_REFACTORING_PROPOSALS.md)
> et [LIVE_MATCH_WEBSOCKET_ARCHITECTURE.md](../reference/LIVE_MATCH_WEBSOCKET_ARCHITECTURE.md),
> vérifié contre le code réel (`ScoringController`, `EventCacheWorkerCommand`, `wsMixin.js`,
> `composer.json` api2). Objectif du projet : **harmoniser le legacy, simplifier et consolider
> l'architecture**. Cette revue évalue les propositions à l'aune de cet objectif et des principes
> **DRY, SOLID, DDD, TDD** — exigence justifiée par la volumétrie du legacy à migrer
> (~20 pages d'incrustation PHP, 3 chemins d'écriture scoring, 2 paradigmes de workers).

---

## 1. Verdict global

**Le diagnostic est excellent, la cible est la bonne, mais la trajectoire initialement proposée
risquait de produire l'inverse de l'objectif : empiler un troisième système de scoring à côté des
deux existants, et différer les trois décisions réellement difficiles.** Les documents de
référence ont été **amendés en conséquence** (voir §6 — liste des corrections appliquées).

| Élément | Évaluation |
|---------|------------|
| Diagnostic §2 (4 responsabilités de `app_wsm`, seul le transport doit être sur le LAN) | ✅ Correct, structurant — c'est la bonne décomposition SRP |
| Modèle « horloge » §7.8 (persister l'état, dériver le tic-tac) | ✅ Correct, **vérifié dans `wsMixin.js`** : la base n'est écrite qu'aux transitions run/stop |
| Outbox transactionnelle (état + message dans une transaction) | ✅ Bon pattern d'atomicité |
| Ordre P1 (état canonique) avant P3 (déplacer le relais) | ✅ Indispensable — sinon on déménage la fragilité |
| Stratégie « module parallèle » vis-à-vis du legacy PHP | ✅ Justifiée… mais pas *à l'intérieur* d'api2 (voir §3.2) |
| Trajectoire P0 (durcir WSM navigateur avec du code) | ❌ Détour — investir du code dans une architecture condamnée |
| Nommage « v2 » (tables + routes) | ❌ Déjà surchargé deux fois dans la base de code |
| Recommandation agent « smart » | ⚠️ Contestée — le « semi-dumb » sert mieux DRY (voir §3.4) |
| Messenger recommandé sans bilan des paradigmes de workers | ⚠️ Ajoute un 2ᵉ modèle async à exploiter |
| Arbitrage multi-source, timestamps offline, auth de l'agent | ❌ Différés alors que ce sont les seuls points durs |
| Périmètre : chemin de **lecture** (20 incrustations PHP) | ❌ Absent du plan alors que c'est le gros du legacy |

---

## 2. Lecture par principes

### 2.1 DRY — les duplications que le plan initial laissait vivre (ou créait)

| Duplication | État | Exigence |
|-------------|------|----------|
| **Logique de traduction propriétaire** | 1 implémentation (`wsMixin.js`, navigateur) ; l'agent « smart » l'aurait **dupliquée sur N boîtiers** à maintenir à distance | **Une seule implémentation, côté serveur** (agent « semi-dumb », §3.4). La logique propriétaire est versionnée et déployée avec le reste, jamais sur le terrain |
| **Chemins d'écriture scoring** | 3 aujourd'hui (`/api/wsm/*`, `v2/*.php`, `ScoringController`) ; le plan initial en créait un **4ᵉ** (`/admin/scoring/v2/*`) | Faire évoluer `ScoringController` **en place** ; toute route dupliquée doit avoir une date de mort écrite |
| **~20 pages d'incrustation PHP** | `score.php` × 9 variantes (nations/clubs × suffixes `_o/_e/_s`/HD), `next_game*`, `teams*`, `multi_score*`… quasi identiques, toutes sur `live/js/match.js` | **Phase dédiée de consolidation** en une page paramétrée (ou vues app_live). C'est la condition réelle de la mort du cache JSON fichier |
| **Paradigmes de workers** | `event_worker.php` (CLI legacy) + `EventCacheWorkerCommand` (api2) ; le plan initial ajoutait `messenger:consume` | **Un seul modèle opérationnel** : étendre le pattern worker api2 existant (drainage d'outbox dans la même boucle), ou tout migrer sur Messenger — pas les deux indéfiniment |
| **Mécanisme de resync** | `tick` de version + détection de trous conçus à la main | Comparer à **Mercure** (`Last-Event-ID` natif) avant de re-coder ce que l'écosystème fournit (§3.5) |

### 2.2 SOLID

- **SRP** : le diagnostic §2 des propositions est une application exemplaire — `app_wsm` cumule
  transport, traduction, persistance et UI dans un onglet ; seule la première responsabilité est
  contrainte géographiquement (LAN). La refonte doit **préserver cette séparation dans le code**,
  pas seulement dans le déploiement : le worker publieur ne doit pas ré-absorber l'aiguillage,
  la traduction et la génération de cache dans un monolithe CLI de 2000 lignes (le risque naturel
  du « jumeau de l'Event Worker »). Un process unique, oui ; des **classes/services distincts**
  à l'intérieur, obligatoire.
- **OCP / DIP** : le concept d'**adaptateurs de source** (Hardware, Console, Score-seul, Import)
  vers une **API d'ingestion unique** est du DIP correct : le domaine (state machine du match)
  définit le **port** (commandes normalisées `goal`, `card`, `clock run/stop`…), les sources sont
  des implémentations. Exigence : le port doit être un **contrat versionné et testé** (schéma de
  commande avec clé d'idempotence, timestamp client, source, tick) — pas une collection d'endpoints
  ad hoc qui recréerait le couplage actuel `/api/wsm/*`.
- **ISP** : l'adaptateur « Score-seul » ne doit dépendre que du sous-ensemble score du contrat.
  Si le mode E oblige à envoyer des payloads chrono vides, le contrat est mal découpé.
- **LSP** : toute source doit être substituable sans que le publieur ou la supervision ne testent
  `if (source === HARDWARE)`. Le Faker devient le test de conformité du contrat (voir §2.4).

### 2.3 DDD

- **Bounded context.** Le « Live Scoring » est un contexte borné distinct du contexte « Résultats /
  Classements » (tables `kp_*`, reporting, PDF). La décision structurante qui manquait au plan :
  **`kp_*` reste le modèle canonique du contexte Résultats** ; les tables d'état live
  (`scoring_live_*`) sont l'agrégat du contexte Live, **consolidé dans `kp_*` en fin de match**.
  Cela borne la migration : le reporting legacy n'est jamais impacté, et le contexte Live peut
  être itéré librement. L'alternative (les tables live deviennent la vérité, migrer tout le
  reporting) est un chantier d'un autre ordre de grandeur — à écarter explicitement.
- **Agrégat.** `LiveMatch` est l'agrégat racine : score, période, N horloges (jeu, shotclock,
  pénalités), joueurs actifs, liste d'événements, `active_source`, `tick`. **Une seule frontière
  de cohérence** = une seule transaction par commande = l'outbox y trouve naturellement sa place.
- **Anti-corruption layer.** Le protocole propriétaire (trames STOMP, scores cumulés, `PEN_H1`,
  `chronoName`…) est un modèle **externe** qui ne doit jamais fuiter dans le domaine : l'adaptateur
  Hardware est un ACL qui traduit propriétaire → commandes du domaine. Corollaire DRY : cet ACL vit **une
  fois, côté serveur** (pas sur les boîtiers), ce qui tranche le débat smart/dumb (§3.4).
- **Value objects.** L'horloge `{init_ms, elapsed_ms, started_at, running}` est un value object
  avec sa logique de dérivation — testable sans DB, sans réseau, sans matériel. Idem pour la
  période (`M1`/`P1`) et le code carton (`V/J/R/D`).
- **Langage ubiquitaire.** À figer dans le glossaire du contrat : *commande*, *source active*,
  *promotion de source*, *horloge*, *transition*, *tick*, *consolidation*. Et **bannir « v2 »**,
  qui désigne déjà deux choses différentes dans la base de code (`sources/live/v2/*.php` et
  FeuilleMarque V2).

### 2.4 TDD — le levier le plus rentable vu la volumétrie

Le legacy à migrer est massif et **non testé**. La refonte est l'occasion de renverser cela, et le
domaine s'y prête exceptionnellement bien (logique pure, entrées/sorties sérialisables) :

1. **Tests de caractérisation d'abord.** Avant de porter la traduction propriétaire côté serveur,
   enregistrer des **sessions STOMP réelles** (le Faker de `Manager.vue` fournit déjà les payloads
   de référence, §9 de l'archi) et capturer les écritures `/api/wsm/*` produites par le `wsMixin.js`
   actuel. Ces paires (trames → écritures) deviennent des **golden files** : le port serveur de la
   traduction doit les reproduire à l'identique avant toute amélioration. C'est la seule protection
   réaliste contre les régressions sur un protocole propriétaire non documenté.
2. **La state machine en TDD strict.** L'agrégat `LiveMatch` (transitions ATT→ON→END, arbitrage de
   source, idempotence, horloges) est de la logique pure : à développer test-first, sans DB
   (répliquer les cas piégeux connus : match resté en `ATT`, annulation de but par score cumulé
   décroissant, resync après perte de baseline, replay de commandes pré-bascule).
3. **Le Faker devient une suite de contrat.** Chaque adaptateur (Hardware, Console, Score-seul)
   passe la **même suite** de scénarios rejouables en HTTP — c'est le test LSP du port d'ingestion.
4. **Tests de replay du buffer.** Le scénario « coupure WAN 10 min → replay » doit être un test
   automatisé (commandes horodatées client, idempotence, rejet post-bascule), pas une découverte
   en production un samedi de finale.
5. **Critère de décommission mesurable.** P4 ne démarre que lorsque la nouvelle chaîne a passé
   **N événements réels** avec zéro divergence entre l'état live consolidé et la saisie de
   référence — critère chiffré, daté, écrit dans le doc de phase.

---

## 3. Les cinq corrections de fond

### 3.1 Trancher les trois décisions difficiles *avant* le code

Le plan initial les rangeait en « points à trancher » de fin de document. Ce sont les seuls
points durs ; tout le reste est de l'exécution :

1. **Arbitrage multi-source (O7).** Décision : source active **exclusive** (pas de last-write-wins).
   Chaque commande porte `{source, client_ts, idempotency_key}` ; les commandes d'une source non
   active sont journalisées en shadow, jamais appliquées ; les commandes **antérieures à la
   promotion** de la source sont rejetées au replay. Divergence base ↔ console de marque (le hardware a son
   propre état, incorrigible à distance hors set-teams) : **pas de résolution automatique** — le
   hardware reste autoritaire tant qu'il est actif, la supervision reçoit une alerte.
2. **Timestamps sous replay offline.** `started_at` **ne peut pas** être un timestamp serveur : la
   force de l'agent est de bufferiser pendant une coupure WAN, où aucun timestamp serveur n'existe.
   Décision : timestamp **client** (agent sous NTP obligatoire, kit boîtier), le serveur ne servant
   que de tie-breaker et de contrôle de dérive.
3. **Authentification machine de l'agent.** `/admin/scoring/*` est aujourd'hui derrière JWT +
   mandat **humains** (`ROLE_ADMIN`). Un boîtier non surveillé dans un gymnase exige un credential
   machine : token **scopé événement + terrain**, durée de vie bornée à l'événement, révocable
   depuis app4. Section sécurité à part entière, pas une note de bas de page.

### 3.2 Pas de « troisième système » : évoluer `ScoringController` en place

L'isolation parallèle est justifiée **vis-à-vis du legacy PHP** (`/api/wsm/*`, `v2/*.php`), pas à
l'intérieur d'api2 qui est neuf, authentifié et journalisé. Créer `/admin/scoring/v2/*` à côté du
`ScoringController` existant aurait : (a) obligé à re-migrer la console Scoring app4 juste après sa
livraison, (b) créé un 4ᵉ chemin d'écriture, (c) surchargé une 3ᵉ fois le nom « v2 ». Corrections :

- Nouvelles tables nommées par leur rôle (`scoring_live_state`, `scoring_live_clock`,
  `scoring_live_event`), **pas** « v2 » ;
- Extension du `ScoringController` existant (nouvelles routes d'état/horloges sous la même
  surface, mêmes auth/logging) ;
- `kp_*` reste canonique : consolidation en fin de match (cf. §2.3), donc **aucune double écriture
  continue** à maintenir pendant la transition ;
- Topic broker de test distinct : conservé (c'est le bon mécanisme de bascule/rollback).

### 3.3 P0 réduit à zéro code

Le durcissement WSM (option 3) ne doit contenir **que de l'exploitation** : kiosque Chrome, veille
désactivée, `databaseSync` forcé ON par défaut, heartbeat simple. Le volet Service Worker / PWA est
**écarté** : investir du code dans une architecture qu'on a décidé de tuer contredit frontalement
l'objectif de consolidation, pour un gain que le document lui-même qualifiait de partiel (un SW
n'est pas un démon et ne redémarre pas seul).

### 3.4 Agent « semi-dumb », pas « smart »

La recommandation « smart » (traduction propriétaire embarquée sur le boîtier) optimisait le buffer au
prix d'une **duplication de la logique métier sur une flotte de boîtiers** — la faiblesse fleet
management que le document identifiait lui-même. Le binaire smart/dumb était faux : l'agent
**semi-dumb** filtre les ticks de chrono (ne transmet que les transitions — ce que `wsMixin.js`
fait déjà implicitement) et relaie les trames **brutes filtrées** ; la traduction propriétaire vit une
seule fois, côté serveur, dans l'ACL. Le buffer reste petit (transitions uniquement), le boîtier
devient quasi-firmware (jamais de mise à jour métier), et DRY est respecté. Prévoir la **clé 4G**
dans le kit boîtier (le « ne dépend pas du réseau du site » du comparatif supposait quand même une
sortie Internet — Wi-Fi captif et filtrage sortant existent).

### 3.5 Deux choix d'infrastructure à instruire, pas à hériter

- **Broker.** `laurentgarrigue/broker` était reconduit par axiome alors que le plan conçoit à la
  main un `tick` de version avec détection de trous et resync — exactement ce que **Mercure**
  (hub SSE idiomatique Symfony, JWT cohérent avec api2, `Last-Event-ID` = replay natif des messages
  manqués) fournit sans code. Le broker actuel reste défendable (les incrustations le parlent
  déjà) ; la décision doit être **prise sur comparaison écrite**, pas héritée.
- **Messenger.** Ni `symfony/messenger` ni `symfony/doctrine-messenger` ne sont installés dans
  api2 (vérifié `composer.json` — Symfony **7.4**, pas 7.3 comme l'indiquaient les documents).
  Pour quelques messages/seconde sur 5 terrains, un drainage d'outbox dans le **worker api2
  existant** (`EventCacheWorkerCommand`) fait la même chose avec un seul modèle opérationnel. Si
  Messenger est retenu malgré tout, s'engager à y migrer aussi le cache worker — ne pas exploiter
  deux paradigmes async en régime permanent.

---

## 4. Périmètre manquant : le chemin de lecture

Les propositions consolidaient le chemin d'**écriture** mais laissaient intactes les **~20 pages
d'incrustation PHP** quasi dupliquées (familles `score*`, `score_club*`, `next_game*`, `teams*`,
`multi_score*`, `matchs.php`, `presentation*` — recensées en §13.3 de l'archi), traitées uniquement
comme contrainte de compatibilité du cache. Or :

- c'est **le plus gros gisement d'harmonisation du legacy** identifié par ces documents eux-mêmes ;
- c'est la **condition de la mort du cache JSON fichier** : tant que ces pages existent, la
  génération de cache est « obligatoire de toute façon » — condition qui, sans plan, reste vraie
  pour toujours ;
- la duplication est mécanique (mêmes données, variantes nations/clubs × 4 suffixes d'affichage) :
  une **page paramétrée** (ou des vues app_live pilotées par query params) couvre la famille.

→ Ajout d'une phase dédiée dans la trajectoire (voir §5, phase P3bis).

## 5. Trajectoire corrigée

| Phase | Contenu | Garde-fous |
|-------|---------|------------|
| **P0 — Exploitation seule** | Kiosk + veille off + `databaseSync` forcé + heartbeat. **Zéro code applicatif** (SW/PWA écartés). | Jetable par construction |
| **P1 — État canonique + contrat** | Tables `scoring_live_*`, extension `ScoringController` en place, **contrat de commande** (idempotence, `client_ts`, source), state machine **en TDD**, golden files propriétaire, publieur outbox dans le worker api2 existant. Décisions §3.1 tranchées et écrites. | `kp_*` reste canonique ; consolidation fin de match |
| **P2 — Adaptateurs & arbitrage** | Adaptateurs sur le contrat (Console déjà branchée, Score-seul, Import), `active_source` + promotion, suite de contrat commune (ex-Faker). | Test LSP : même suite pour toutes les sources |
| **P3 — Agent semi-dumb** | Boîtier filtrant (transitions uniquement), buffer replay testé, auth machine scopée, NTP + 4G au kit. Traduction propriétaire = ACL serveur unique. | Golden files = non-régression du protocole |
| **P3bis — Consolidation lecture** | Famille des ~20 incrustations PHP → page(s) paramétrée(s) ; le cache fichier passe en cache HTTP sur `GET /state`. | Prérequis à la mort du cache |
| **P4 — Décommission** | `/api/wsm/*`, `v2/*.php`, FeuilleMarque V2/V3, onglet WSM, génération de cache fichier. | **Critère chiffré** : N événements réels sans divergence, daté |

## 6. Corrections appliquées aux documents de référence

Dans le cadre de cette revue, les deux documents ont été amendés :

- **LIVE_MATCH_SCORING_REFACTORING_PROPOSALS.md** : P0 réduit à zéro code (§6, §9) ; nommage
  « v2 » remplacé par `scoring_live` et extension en place de `ScoringController` (§7.10) ;
  recommandation agent basculée sur « semi-dumb » (§5.1bis) ; arbitrage précisé — source exclusive,
  idempotence, rejet pré-bascule, divergence hardware (§7.4) ; `started_at` corrigé en timestamp
  client + NTP (§7.8.2) ; comparaison Mercure ajoutée et Messenger reconditionné à l'unification
  des workers (§7.9) ; décisions structurantes remontées en tête de la trajectoire et phase P3bis
  lecture ajoutée (§9) ; version Symfony corrigée (7.4).
- **LIVE_MATCH_WEBSOCKET_ARCHITECTURE.md** : renvoi vers la présente revue ; la duplication des
  ~20 incrustations PHP explicitement marquée comme cible de consolidation (§13.3).
