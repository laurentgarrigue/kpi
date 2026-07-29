# Spécification — Scoring (console de match en direct) dans app4

> Statut : en cours — Phase 0 terminée, Phase 1 en cours (voir §12 Suivi)
> ⚠️ **Réaligné sur le plan de refonte live — lire §0 en premier** (l'état live migre vers
> `scoring_live_*`, la diffusion passe à Mercure). §0 prime sur le reste en cas de contradiction.
> **Décisions complémentaires du 2026-07-23 en §0.8** (PWA, pauses inter-périodes, shotclock,
> raccourcis paramétrables, relais matériel), **du 2026-07-27 en §0.9** et **correctif
> réglementaire pénalités du 2026-07-29 en §0.10**.
> Cible : intégration dans **app4** (Nuxt 4, api2 Symfony)
> Remplace : `sources/admin/FeuilleMarque2.php`, `sources/admin/FeuilleMarque3.php`
>            (legacy jQuery) et le prototype standalone `sources/app3`
> Conserve : `sources/admin/FeuilleMatchMulti.php` (= **PDF de contrôle**, document papier)

---

## 0. Alignement sur le plan de refonte live — À LIRE EN PREMIER

> Cette section **prime sur le reste du document** partout où il y aurait contradiction. Elle a été
> ajoutée pour réaligner la console Scoring sur la trajectoire globale définie dans
> [LIVE_MATCH_SCORING_REFACTORING_PROPOSALS.md](../developer/reference/LIVE_MATCH_SCORING_REFACTORING_PROPOSALS.md).
> Les sections §1 à §12 restent valables pour tout le **détail fonctionnel et l'UI** ; seuls trois
> points d'architecture changent, listés ici.

**Rappel de la cible globale (le plan) :** *la base porte l'état complet du match*. Toutes les
sources de saisie (matériel propriétaire via relais, **console Scoring**, mode score-seul, import a
posteriori) écrivent le **même état, dans le même format**, par **une seule porte d'entrée**
(`ScoringController` d'api2). Un publieur diffuse les changements via **Mercure** ; les incrustations
lisent l'état au démarrage puis suivent Mercure. **La console Scoring de cette spec est la première
source à alimenter ce nouveau modèle — c'est le cœur des lots 1 et 3 du plan.**

### 0.1 Ce qui change (3 points)

| # | La spec disait (§ concernées) | Décision alignée sur le plan |
|---|---|---|
| **A** | La console écrit directement dans **`kp_match` / `kp_match_detail` / `kp_chrono`** (§4.3, §6.2, §12). | La console écrit dans des tables **`scoring_live_*` dédiées** (état live). **`kp_*` n'est alimenté que par consolidation en fin de match.** Voir §0.2. |
| **B** | La console **régénère les fichiers cache JSON** legacy à chaque action (§6.2 « Génération des JSON », les 3 `// TODO (Phase 3)`, §6.5). | Plus de fichiers générés par la console. Chaque écriture **dépose une ligne dans `scoring_outbox`** (même transaction) qu'un **worker publie sur Mercure**. Voir §0.3. |
| **C** | Diffusion temps réel via le **broker WebSocket personnel** résolu par `event{id}_network.json` (§6.5, §3). | Diffusion via **Mercure** (SSE, rejeu natif des messages ratés). Le broker personnel est voué à disparaître. Voir §0.3. |

**Ce qui NE change PAS** : toute l'UI (`scoring.vue`, `components/scoring/*`, `scoringStore`,
permissions, i18n, mode direct/post-match, historique symétrique, badges cycliques), le
`ScoringController` **comme porte d'entrée unique** (auth JWT, scope par mandat, journalisation
`kp_journal`), et le **modèle d'horloge** déjà implémenté (`max_time`/`start_time`/`start_time_server`
+ restauration serveur). Cette UI parle à des endpoints ; elle est **indépendante de la table
derrière**. Le gros du travail déjà fait (Phase 0 + Phase 1) est donc **conservé** ; seule la couche
de stockage backend est réorientée.

### 0.2 Point A — l'état live vit dans `scoring_live_*`, `kp_*` consolidé en fin de match

**Pourquoi.** Le plan veut que **toutes les sources écrivent le même état** pour que (1) Mercure
diffuse un état unique, (2) changer de source en cours de match (console → matériel → score-seul) ne
perde rien, (3) le shotclock et les pénalités soient **enfin persistés**. Si la console écrit dans
`kp_match_detail` et que le futur relais matériel écrit ailleurs, on a **deux modèles d'état** et ce
bénéfice disparaît. La console doit donc écrire dans le **modèle live commun**.

**Nouvelles tables** (nommées par leur rôle, « v2 » banni — cf. plan §4.5) :

| Table | Porte | Remplace, pour le live, l'écriture directe dans |
|---|---|---|
| `scoring_live_state` | score, période, statut, `active_source`, `tick` | `kp_match` (colonnes live) |
| `scoring_live_clock` | N horloges : chrono de jeu, **shotclock**, **pénalités** (`init_ms`, `elapsed_ms`, `started_at` **client**, `running`) | `kp_chrono` (qui ne portait que le chrono principal) |
| `scoring_live_event` | buts / cartons horodatés (`uid`, période, temps, joueur, code, motif) | `kp_match_detail` (pour le live) |
| `scoring_outbox` | messages de diffusion à publier (cf. §0.3) | — (nouveau) |

**Consolidation en fin de match.** Au passage `Statut → END` (clôture), un service api2 **recopie**
l'état live consolidé vers `kp_*` — exactement comme la clôture actuelle écrit déjà `ScoreA/ScoreB`,
`Statut`, `Heure_fin`. C'est le **seul** moment où `kp_*` est écrit par le Scoring. Conséquences :
le **reporting existant** (fiche, classements, PDF, `FeuilleMatchMulti.php`) n'est **jamais** impacté
pendant le match, et il n'y a **aucune double écriture continue** à maintenir.

> ⚠️ **Impact de transition à traiter (2026-07-27).** Certains consommateurs de `kp_*` affichent
> aujourd'hui le **déroulement du match y compris en cours** (le legacy écrit `kp_match_detail` en
> live) : le **PDF `FeuilleMatchMulti.php`** imprimé pendant un match, et **app2** (affichage
> public du détail des matchs). Quand la console écrira dans `scoring_live_*`, ces consommateurs ne
> verront plus rien avant la consolidation de fin de match. **Prévoir leur évolution** (lecture de
> l'état live via `GET /scoring/state` / Mercure, ou tout autre mécanisme) — planifiée au
> [plan, lot 4](../developer/reference/LIVE_MATCH_SCORING_REFACTORING_PROPOSALS.md) (étape
> « consommateurs kp_* en cours de match »). Tant que cette évolution n'est pas faite, la bascule
> de la saisie vers `scoring_live_*` dégrade l'affichage « en cours » de ces consommateurs.

> **Impact sur le code déjà écrit (Phase 1).** Le `ScoringController` écrit aujourd'hui dans `kp_*`
> (vérifié : `gameParam`→`kp_match`, `gameEvent`→`kp_match_detail`, `gameTimer`→`kp_chrono`). Ces
> écritures sont **re-routées** vers `scoring_live_*`. La **forme des endpoints ne change pas**
> (`PUT /admin/scoring/gameParam|gameEvent|gameTimer|playerStatus`, mêmes payloads) → **le
> `scoringStore` et l'UI ne bougent pas**. C'est un remplacement de la couche SQL du contrôleur, plus
> l'ajout de la consolidation fin de match. Le modèle d'horloge déjà validé se transpose tel quel de
> `kp_chrono` vers `scoring_live_clock`, et **s'étend** au shotclock et aux pénalités (qui résout le
> risque §10.10 « shotclock/pénalités non persistés à la reprise »).

### 0.3 Points B & C — diffusion par outbox + Mercure, plus de cache JSON généré

**Le mécanisme.** Chaque écriture d'état par le `ScoringController` dépose, **dans la même
transaction**, une ligne dans `scoring_outbox` (match, type, payload, `tick`). Un **worker draine**
cette table et **publie sur Mercure**. La diffusion réelle est donc **hors du chemin critique** : si
Mercure est lent ou tombé, l'écriture d'état n'est ni bloquée ni perdue, et le message se rejoue.

**Le worker.** On **étend le worker api2 existant** `app:event-cache-worker`
(`EventCacheWorkerCommand` / `EventCacheService`, déjà présents) pour drainer l'outbox — **pas** un
nouveau paradigme (pas de Symfony Messenger). Cf. plan lot 2.

**Ce que deviennent les 3 `// TODO (Phase 3): generate broadcast cache here`** du `ScoringController` :
ils ne génèrent **plus de fichiers JSON**. Ils **insèrent dans `scoring_outbox`**. La parité cache
JSON décrite en §6.2 n'est **plus un objectif** de la console — le cache JSON fichier est une brique
que le plan supprime (il ne survit que pour les ~20 incrustations PHP legacy, alimentées par le
worker de cache existant, indépendamment de la console).

**Mercure vs broker.** La diffusion cible est **Mercure** (SSE, rejeu natif via `Last-Event-ID`),
pas le broker WebSocket personnel. Le §6.5 (résolution du broker par `event{id}_network.json`) et le
§3 (schéma « WebSocket broker ») décrivent l'**ancien** mécanisme : ils restent pour mémoire mais
la cible est Mercure. **Où tourne le hub Mercure** (conteneur dédié ou embarqué dans FrankenPHP) est
un détail d'infrastructure tranché dans le plan §4.6 — sans impact sur la console.

### 0.4 Terminologie — deux « événements » à ne jamais confondre

Le mot « événement » recouvre **deux notions distinctes** dans KPI. La documentation doit les
nommer différemment.

| Terme retenu | Désigne | Échelle | Exemples |
|---|---|---|---|
| **Événement KPI** (ou « événement » tout court, `idEvent`, `event{id}_*`) | un **rassemblement** sur un même site : plusieurs compétitions/catégories, réparties sur 2–5 jours et plusieurs journées/phases | macro (multi-matchs) | clé d'échange `event{id}_pitch{p}`, `event{idEvent}_network.json` |
| **Fait de match** (`scoring_live_event`, « fait de jeu ») | un **fait ponctuel survenu pendant un match**, horodaté (période + temps), rattaché à un match et souvent à un joueur | micro (dans un match) | but, carton ; **plus tard** : tir, passe, interception, arrêt du gardien… |

> **Convention.** Quand la spec parle du **fait de match** (but, carton…), on dit **« fait de
> match »** ou **« fait de jeu »**, jamais « événement » seul. Le mot « événement » **seul** est
> réservé à l'**événement KPI** (rassemblement multi-journées). En code, la table et le type
> gardent le nom `scoring_live_event` / `ScoringEvent` (« event » y désigne sans ambiguïté le fait
> de match, par opposition à `idEvent` = événement KPI) — mais **les libellés lus par un humain**
> (UI, doc, commentaires) suivent la convention « fait de match ».

**Portée extensible du fait de match.** Aujourd'hui, seuls **but** et **carton** (`B/V/J/R/D`) sont
saisis. Le modèle `scoring_live_event` est conçu pour accueillir **d'autres types de faits utiles
aux statistiques** (tir, passe, interception, arrêt du gardien, faute, temps mort…) **sans changement
de schéma** : le type de fait est une **valeur de colonne**, pas une table par type. Cela évite de
refaire le modèle quand les stats avancées arriveront (cf. §0.5, colonne `kind`).

> **À reporter dans les docs de refonte.** Cette distinction et l'extensibilité du fait de match
> valent aussi pour
> [LIVE_MATCH_SCORING_REFACTORING_PROPOSALS.md](../developer/reference/LIVE_MATCH_SCORING_REFACTORING_PROPOSALS.md)
> et [LIVE_MATCH_WEBSOCKET_ARCHITECTURE.md](../developer/reference/LIVE_MATCH_WEBSOCKET_ARCHITECTURE.md),
> qui emploient « événement » dans les deux sens.

### 0.5 Schéma indicatif des tables `scoring_live_*`

Détail de cadrage (colonnes à affiner à l'implémentation, cf. §0.2). Trois décisions structurantes y
sont figées : **prolongations non bornées** (`Px`), **N horloges** (shotclock + jusqu'à 4 pénalités),
**fait de match extensible** (`kind`).

**`scoring_live_state`** — un enregistrement par match.

| Colonne | Type | Rôle |
|---|---|---|
| `id_match` | int (PK) | match (= `kp_match.Id_match`, conservé en int — cf. décision §0.9/plan §4.13 : un `uid` public court **additif** est prévu côté match pour les usages offline/import futurs, sans toucher aux clés legacy) |
| `score_a` / `score_b` | int | score courant |
| `periode` | varchar | code de période (voir §0.6 : `M1`/`M2`/`P1`/`P2`/`P3`… **non borné**) |
| `statut` | enum `ATT`/`ON`/`END` | statut live |
| `active_source` | enum `MANUAL`/`HARDWARE`/`SCORE_ONLY`/`IMPORT` | source autorisée à écrire (plan §4.1) |
| `tick` | bigint | numéro de version, incrémenté à chaque écriture (diffusion/resync) |
| `updated_at` | datetime | pour le cache HTTP (`ETag`) et le contrôle de fraîcheur |

**`scoring_live_clock`** — **N lignes par match** (une par horloge active). Porte le modèle
d'horloge déjà validé (`init_ms`/`elapsed_ms`/`started_at`/`running`, cf. §6.4), généralisé.

| Colonne | Type | Rôle |
|---|---|---|
| `id` | **uuid (PK)** | identifiant généré côté émetteur (console/relais) — permet l'idempotence et la création hors ligne sans compteur central (décision §0.9 ; table neuve, zéro impact legacy) |
| `id_match` | int | match |
| `kind` | enum `GAME`/`SHOTCLOCK`/`PENALTY`/`BREAK` | type d'horloge (`BREAK` = pause inter-périodes indicative, cf. §7.5) |
| `team` | enum `A`/`B`/NULL | équipe (pénalités) ; NULL pour le chrono de jeu et le shotclock |
| `slot` | tinyint | pour les pénalités : **1 ou 2** (au plus 2 exclusions concurrentes **par équipe**) ; `0`/NULL pour `GAME` et `SHOTCLOCK` |
| `id_player` | varchar/NULL | joueur exclu (licence), si applicable |
| `card_code` | varchar/NULL | carton d'origine de la pénalité (`V`/`J`/`R` — **jamais `D`**, qui ne crée pas d'horloge, cf. §0.10) : `V`/`J` = levable sur but encaissé, `R` = 2 min complètes (cf. §7.4) |
| `init_ms` | int | durée de départ (600000 = 10 min ; 60000 ou 40000 = shotclock ; **120000 = exclusion 2 min**) |
| `elapsed_ms` | int | temps écoulé figé au dernier arrêt |
| `started_at` | datetime(3)/NULL | horodatage **client** du dernier `run` (NULL si arrêté), cf. plan §4.2 |
| `running` | bool | en marche / arrêté |

> **Cardinalité (règle métier, cf. §7.4).** Une seule ligne `GAME`, une seule `SHOTCLOCK` et au
> plus une `BREAK` par match. Pour les pénalités : **au plus 2 par équipe** (`slot` ∈ {1, 2}), soit **4 au maximum au
> total** (2×A + 2×B). Ce n'est **pas** un plafond arbitraire mais une **contrainte de jeu** : une
> équipe présente 5 joueurs à l'engagement et **ne peut descendre sous 3** sur le terrain — donc au
> plus 2 exclusions concurrentes par équipe. Contrainte d'unicité recommandée : `UNIQUE(id_match,
> team, slot)`. Résout le risque §10.10 (shotclock et pénalités enfin persistés et restaurés à la
> reprise).

**`scoring_live_event`** — **N lignes par match** (les faits de match, cf. §0.4).

| Colonne | Type | Rôle |
|---|---|---|
| `uid` | varchar (PK) | identifiant du fait (généré client, aligne optimiste/serveur/édition — déjà en place) |
| `id_match` | int | match |
| `kind` | enum `GOAL`/`CARD`/… | **type de fait, extensible** (demain : `SHOT`/`PASS`/`STEAL`/`SAVE`… sans changer le schéma) |
| `code` | varchar/NULL | pour `CARD` : `V`/`J`/`R`/`D` ; pour `GOAL` : `B` (compat legacy) |
| `periode` | varchar | période du fait |
| `temps` | time | temps de jeu (`MM:SS`, cf. note format §12) |
| `team` | enum `A`/`B` | équipe |
| `id_player` | varchar | joueur (licence ; `0` = fait au niveau équipe) |
| `motif` | varchar/NULL | motif (cartons) |
| `created_at` | datetime | ordre d'insertion |

**`scoring_outbox`** — file de diffusion (plan lot 2).

| Colonne | Type | Rôle |
|---|---|---|
| `id` | bigint (PK, auto) | ordre |
| `id_match` | int | match |
| `topic` | varchar | **URI Mercure de destination** événement/terrain/bloc, ex. `/scoring/event/236/pitch/2/score` (isole chaque flux — cf. [plan §3.3](../developer/reference/LIVE_MATCH_SCORING_REFACTORING_PROPOSALS.md)) |
| `payload` | json | message `{ type, tick, … }` à pousser sur Mercure |
| `tick` | bigint | version de l'état au moment de l'écriture |
| `created_at` | datetime | |
| `published_at` | datetime/NULL | marqué par le worker une fois publié (NULL = à publier) |

### 0.6 Prolongations non bornées (but en or) — pas de plafond `P1`/`P2`

**Décision (aligne §7.5).** Le legacy plafonne à deux prolongations (`P1`, `P2`), ce qui **viole le
règlement** (but en or = autant de prolongations que nécessaire). Le modèle `scoring_live_*` gère une
**série non bornée de prolongations identifiées** :

- Codes de période : `M1`, `M2` (mi-temps réglementaires), puis **`P1`, `P2`, `P3`, … `Pn`** pour
  les prolongations successives — **sans borne**. Optionnellement `TB` (tirs au but) si la
  compétition l'autorise.
- La colonne `scoring_live_state.periode` est un **varchar** (pas un enum figé) : elle accepte
  n'importe quel `P{n}`. Chaque prolongation est **identifiée** (P1 ≠ P2 ≠ P3) pour l'affichage et
  l'historique.
- Le **sélecteur de période** (`ScoringPeriodSelector`) propose **« prolongation suivante »**
  (incrémente `n`) tant que le score est à égalité ; le **premier but clôt** immédiatement (but en
  or). Plus de deux boutons figés `P1`/`P2`.

> **Côté frontend (`types/scoring.ts`).** Le type actuel `Period = 'M1'|'M2'|'P1'|'P2'|'TB'` est
> **remplacé** par un type ouvert sur les prolongations : `Period = 'M1' | 'M2' | \`P${number}\` | 'TB'`
> (gabarit littéral TypeScript). `PeriodDurations` porte les durées de `M1`/`M2`/`TB` + une **durée
> de prolongation générique** (toutes les `P{n}` partagent la même durée par défaut, ajustable).
> Ce changement remplace le plafond `P1`/`P2` **sans casser** l'existant (`P1`/`P2` restent des
> `P{number}` valides).

### 0.7 Impact sur le plan par phases (§8)

Le découpage §8 reste, avec ces **substitutions** :

- **Phase 1** — inchangée fonctionnellement, mais les écritures visent `scoring_live_*` (Point A).
  Le re-routage backend + la consolidation fin de match rejoignent le périmètre Phase 1.
- **Phase 3** — n'est **plus** « générer les JSON de diffusion (parité `CacheMatch`) + broker », mais
  **« outbox → worker → Mercure »** (Points B & C). Le `useHardwareScoring` (captation matériel)
  reste en Phase 3 : le matériel devient **une source de plus** écrivant le même `scoring_live_*`.
- Le reste (Phase 2 diffusion locale `BroadcastChannel`/scoreboard, Phase 4 offline) est inchangé.

> **Ordre à respecter (plan : lot 1 avant lot 5).** L'état canonique `scoring_live_*` (Point A)
> est le **prérequis** de tout le reste : c'est lui qui permet à Mercure de diffuser un état unique
> et au futur relais matériel de se brancher sans perte. À faire **avant** de toucher au transport
> matériel.

### 0.8 Décisions complémentaires (2026-07-23)

Tranchées lors du passage du plan en **stratégie + plan d'action**
([plan §4.7–§4.12](../developer/reference/LIVE_MATCH_SCORING_REFACTORING_PROPOSALS.md)). Les
sections concernées de cette spec ont été mises à jour ; ce tableau sert d'index :

| Décision | Résumé | Sections mises à jour |
|---|---|---|
| **PWA installable d'abord** | La console est une PWA installable dès sa livraison (manifest + service worker + app shell) ; la saisie reste online-first, la **file d'écritures offline** reste en dernière phase | §3, §8 (Phases 2 et 4) |
| **Pauses inter-périodes** | 3 min entre `M1`–`M2`, 3 min entre `M2`–`P1`, 1 min entre chaque prolongation — décomptes **indicatifs**, durées dans `ScoringConfig`. **Pas de temps mort d'équipe** | §6.2, §7.5, §0.5 (horloge `BREAK`) |
| **Shotclock : départ manuel** | Jamais lancé par le démarrage du chrono principal ; une fois lancé, suit le chrono (suspension auto). ~~Pause manuelle indépendante, départ/reprise ≠ reset~~ → **remplacé le 2026-07-27 par le modèle « 3 commandes »** (le départ EST un reset, l'arrêt remet à `--`), cf. §0.9 | §6.5, §10.9 |
| **Raccourcis paramétrables** | Préférence par poste/utilisateur ; défauts `Espace`/`Entrée`/`0` — affectations **revues le 2026-07-27** avec le modèle 3 commandes, cf. §0.9 et §6.5 | §6.5 |
| **Canaux d'affichage** | Confirmé : **BroadcastChannel** pour les fenêtres locales du même poste, **Mercure** pour les écrans distants et incrustations | §6.5 |
| **Relais matériel** | Un seul composant relais Stomp (brut, traduction serveur), déployable **côté serveur KPI** (site avec redirection de port vers la passerelle) **ou en boîtier local** (optionnel, selon le site) | plan §4.7 ; impacte `useHardwareScoring` (Phase 3) |

### 0.9 Décisions complémentaires (2026-07-27)

Deuxième passe de revue ; les sections concernées ont été mises à jour, ce tableau sert d'index :

| Décision | Résumé | Sections mises à jour |
|---|---|---|
| **Terminologie shotclock** | Traduction française : **« chronomètre de tir »**. Ne plus employer « temps d'action de but » ni « temps d'action de jeu » (UI, doc, i18n). Le terme technique `shotclock` reste en code | §6.5, §7.1 |
| **Shotclock : 3 commandes** | Le départ **est** un reset : ① **départ/reset 60 s** (indépendant du chrono principal), ② **départ/reset 40 s**, ③ **arrêt** (affiche `--`, retour à l'état initial en attente d'un départ — ce n'est pas une pause). Raccourcis revus | §6.5, §10.9 |
| **Shotclock 40 s actif immédiatement** | Le nouveau système applique d'emblée le règlement 2027 : `shotclockOffensiveReboundEnabled = true` par défaut. Le legacy (fin de saison 2026) reste inchangé | §6.2, §6.5 |
| **Carton d'exclusion définitive (noir)** | `D` devient **« carton d'exclusion définitive »** (EN : *Ejection card*), couleur **noire** — règlement 2027, appliqué immédiatement dans le nouveau système ; le legacy conserve « rouge définitif » jusqu'à la fin de saison 2026 | §7.4, i18n |
| **Progression des cartons** | Ordre vert → jaune → rouge : un joueur ne peut pas recevoir un 2ᵉ/3ᵉ carton **identique ou inférieur** au précédent ; un jaune (ou rouge) **peut être le premier** carton ; l'exclusion définitive est applicable **à tout moment** | §7.4 |
| **Levée anticipée — rouge pour cumul** | ~~Sur but encaissé le `R` peut être remplacé avant la fin des 2 min~~ → **corrigé le 2026-07-29, cf. §0.10** : le `R` va toujours au terme de ses 2 min (remplacement à l'issue seulement) ; le `D` n'a **aucune** pénalité | §0.10, §7.4 |
| **Motif de carton par défaut** | Pré-sélectionné à **« Autre/Non précisé »** (`unknown`) pour une saisie rapide sans étape supplémentaire | §7.3, §7.4 |
| **Prolongations : 5 min** | Durée des prolongations = **5 minutes** dans les règlements ICF **et** FFCK (correction de l'ancienne mention 3 min FFCK). Défaut `P{n}` = 300 s | §6.2, §6.4, §7.5 |
| **Clôture : heure de fin pré-remplie** | `Heure_fin` proposée à l'**heure réelle au moment de la clôture**, modifiable | §7.6 |
| **Logos / drapeaux d'équipe** | Charger le **logo de l'équipe** ; à défaut seulement, dériver le drapeau depuis le code club | §7.8 |
| **Buzzer** | Sonne aussi en **fin de pause inter-périodes** (en plus de la fin de période et du shotclock) | §7.5, §7.8 |
| **Tirs / arrêts = stats du match** | C'est la même fonctionnalité ; **hors périmètre** pour l'instant (les deux lignes de §7.8 fusionnent) | §7.8 |
| **Identifiants (uid)** | `kp_match.Id_match` (int) **conservé** — le remplacer casserait tout le legacy ; on ajoute un **`uid` public court additif** côté match pour les usages futurs (offline, import). `scoring_live_clock` passe en **PK UUID** (table neuve, zéro impact) | §0.5 ; plan §4.13 |
| **PWA : mise à jour immédiate** | Le service worker doit garantir que l'utilisateur bénéficie **immédiatement de la dernière version** (détection + activation immédiate + rechargement) ; mécanisme à réutiliser sur **app2** | §3, §8 ; plan §4.9 |
| **Zéro papier — responsable d'équipe (profil 7)** | Étapes ultérieures : validation/ajustement de la **compo d'équipe** avant match (délai réglementaire) et **consultation + réclamation** après match (délai réglementaire) | §1, plan lot 9 |

### 0.10 Correctif réglementaire — pénalités des cartons rouge et noir (2026-07-29)

Précision apportée après la 2ᵉ passe, qui **remplace** la ligne « Levée anticipée — rouge pour
cumul » du §0.9 :

| Carton | Pénalité 2 min ? | Levée sur but encaissé ? | Qui revient à la fin ? |
|---|---|---|---|
| **Vert / Jaune** (`V`/`J`) | oui | **oui** (après confirmation opérateur) | le **joueur sanctionné** revient |
| **Rouge** (`R`, cumul) | oui | **NON — jamais** : les 2 minutes vont à leur terme même si un ou plusieurs buts sont encaissés | un **remplaçant**, uniquement à l'issue des 2 min (le joueur sanctionné ne revient jamais) |
| **Noir** (`D`, exclusion définitive) | **NON — aucune pénalité** | sans objet (pas d'horloge) | **personne** : aucun remplacement jusqu'à la fin du match, l'équipe termine à effectif réduit |

Conséquences : `scoring_live_clock.card_code` ∈ `V`/`J`/`R` seulement (§0.5) ; la levée sur but
encaissé cible la **plus ancienne pénalité levable** (`V`/`J`) ; règles portées par
`ScoringRules::cardCreatesPenaltyClock()` / `penaltyLiftableOnGoal()` (PHP + miroir TS, testées).

---

## 1. Contexte et objectif

Le **Scoring** est l'outil de gestion du déroulé d'un match de kayak-polo : chronomètre,
shotclock, périodes (M1/M2/P1/P2/TB), saisie des faits de jeu (buts, cartons), pénalités,
diffusion vers écrans/incrustations, validation et verrouillage.

L'ancienne appellation « feuille de marque » n'a plus de sens : l'outil n'a quasiment plus
rien d'une feuille (la « feuille » papier est désormais un simple **PDF de contrôle**, cf.
`FeuilleMatchMulti.php`). Réglementairement, le match reste géré en priorité sur **feuille de
marque papier** + **panneau de score**, et **en parallèle ou après coup sur KPI**. L'objectif
KPI est de **tendre vers le zéro papier** :

- **saisie directe sur KPI** (Scoring) avec affichage scoreboard + shotclock ; ou
- **captation des live datas** depuis le matériel de scoring / panneau de score (matériel propriétaire) ; puis **diffusion** via la stack Mercure et **incrustations** (`/live`).

> **Vers le zéro papier — étapes ultérieures « responsable d'équipe » (profil 7), décision §0.9.**
> Au-delà de la console, l'objectif zéro papier passera par des fonctionnalités dédiées au
> **responsable d'équipe** (profil 7), planifiées au
> [plan, lot 9](../developer/reference/LIVE_MATCH_SCORING_REFACTORING_PROPOSALS.md) :
> - **Avant match** : valider/ajuster **sa composition d'équipe pour chaque match**, à partir de la
>   feuille de présence, **dans un délai fixé réglementairement** (ex. jusqu'à 30 min avant le
>   match) : possibilité de **supprimer** des joueurs absents pour ce match uniquement, et — selon
>   le règlement, donc **selon les paramètres de la compétition** — de **changer les numéros** de
>   maillot et le **capitaine**.
> - **Après match** : **consulter le déroulement numérique** du match et **déposer une
>   réclamation**, immédiatement et **dans un délai fixé au règlement** (ex. jusqu'à 30 min après
>   la fin du match), horodatée et journalisée.
>
> Ces deux volets dépendent du paramétrage par compétition (délais, droits) et ne font **pas**
> partie du périmètre de la console décrite ici.

### 1.1 Deux usages

1. **En direct** (table de marque pendant le match) : chrono, shotclock, périodes, faits de jeu,
   pénalités, diffusion.
2. **En post-match** : **saisie ou correction** après le déroulement, puis **validation /
   verrouillage** selon le profil.

> **Mode direct vs post-match (décision UI).** En **post-match** (saisie ou simple vérification
> après le déroulement), **la gestion temps réel — horloge chrono live, shotclock, buzzer,
> diffusion scoreboard — est masquée** : elle n'a aucun sens hors-live. La console se réduit alors
> à l'entête match, les officiels, les joueurs A/B, la **saisie/édition des faits de jeu horodatés à
> la main**, les scores et la validation/verrouillage.
>
> **Ne pas confondre l'horloge et le champ « temps du fait de jeu ».** Masquer le chrono masque
> **l'horloge live** (run/stop/RAZ, affichage du temps courant), **mais pas** le champ qui porte
> le **temps de chaque fait de jeu** (période + `MM:SS`) : ce champ **reste éditable en post-match**
> pour pouvoir **saisir ou corriger le temps d'un but/carton à la main** (cf. §6.4, §7.3). En
> direct il est pré-rempli depuis le chrono ; en post-match il est **tapé directement**.
>
> Le mode est déterminé par un **sélecteur explicite** (« En direct » / « Post-match »),
> pré-positionné par défaut selon le statut du match (`ATT`/`ON` → direct, `END` → post-match),
> et l'opérateur peut le changer (corriger un match `END` sans ressortir l'horloge ; reprendre un
> match interrompu peut au contraire la réafficher).

### 1.2 Convention de nommage (décidée)

| Terme | Désigne | Usage code/UI |
|---|---|---|
| **Scoring** | La **console de saisie KPI** (saisie manuelle : chrono, score, buts/cartons). | Nom unique partout : route `/games/[id]/scoring`, `scoringStore`, api2 `ScoringController` / `/scoring`, libellé UI « Scoring ». |
| **Hardware Scoring** | La **captation des live datas** depuis le **matériel** (panneau de score propriétaire). **Qualificatif obligatoire** pour ne pas confondre avec la saisie manuelle. | `useHardwareScoring`, mode « Hardware Scoring » dans l'UI. |
| **WSM** (WebSocket Manager) | Brique `app_wsm` de **relai** matériel ↔ KPI (transport WebSocket). | Nom technique inchangé. |
| **broker** | Serveur WebSocket interne (https://github.com/laurentgarrigue/broker), même VPS que KPI. | Nom technique inchangé ; paramètres résolus **par événement** (JSON WSM), cf. §6.5. |
| **Feuille de match** | Le **PDF de contrôle** papier (`FeuilleMatchMulti.php`). | Réservé au document imprimé, jamais à l'outil live. |

> Renommage : `WsmController` (annoté « Web Score Management » — traduction erronée de
> WebSocket Manager) sera renommé **`ScoringController`** car il sert la console de saisie, pas
> le relai matériel. Le relai matériel conserve l'appellation **WSM/broker**.

## 2. Pourquoi app4

- app4 possède déjà **JWT + rôles/mandats** (`stores/authStore.ts`, en-tête
  `X-Active-Mandate`), utilise **api2** et gère **games / teams / presence / rankings**.
- Le **backend de scoring existe déjà** dans api2 :
  `sources/api2/src/Controller/WsmController.php` (→ futur `ScoringController`) → `gameParam`
  (score/statut/période), `gameEvent` (buts/cartons B/V/J/R/D dans `kp_match_detail`),
  `gameTimer` (run/stop/RAZ via `kp_chrono`), `playerStatus`, `stats`, `eventNetwork`.
- Le **profil 9 « Table de marque » (`ROLE_SCORER`)** existe déjà dans
  `sources/api2/config/packages/security.yaml`.
- La **validation/verrouillage est déjà câblée** : `AdminGamesController::toggleValidation`
  (PATCH `/admin/games/{id}/validation`, bascule `Validation` O/N, journalisée) + actions bulk
  dans `app4/pages/games/index.vue`. Le Scoring **réutilise** ce mécanisme.
- Pattern de permissions réutilisable : `app4/composables/usePresencePermissions.ts`
  (mode `match`, seuils `authStore.profile`, verrou). Le Scoring est le **jumeau structurel**
  de la « presence sheet » (même modèle matchId/teamCode).

## 3. Cadrage retenu

| Sujet | Décision |
|---|---|
| Mode | **Online-first** (api2 + Mercure), **PWA installable dès la livraison** (manifest + service worker + app shell, cf. §0.8) avec **mise à jour immédiate garantie** : détection de nouvelle version, activation immédiate (`skipWaiting`/`clients.claim`) et rechargement, pour que l'utilisateur soit toujours sur la dernière version (cf. §0.9 — mécanisme à réutiliser sur **app2**). La **file d'écritures offline** reste non bloquante → dernière phase. |
| Usages | Direct **et** post-match (saisie/correction + validation/verrouillage par profil). |
| Captation matériel | Mode **Hardware Scoring** (panneau propriétaire via relais), **source de plus** écrivant le même `scoring_live_*` (cf. §0.2). Branché en Phase 3. |
| Monétisation | À explorer plus tard. **Aucun Stripe/paywall maintenant.** Exigence unique : isolation **par mandat/organisation côté serveur** + gating par rôle via un composable unique. |
| Langues | **fr/en** uniquement (alignement app4). Le **cn** (présent dans app3) = chantier de suivi séparé sur toute app4. |
| Serveur temps réel | **Mercure** (cf. §0.3). ~~broker interne résolu par `event{idEvent}_network.json`~~ = mécanisme legacy décrit au §6.5, remplacé par Mercure dans la cible. |

## 4. Point de sécurité (vérifié)

Les endpoints `/wsm` d'**api2** (futur `/scoring`) tombent actuellement dans le firewall
`main` (**publics, sans JWT**, cf. `security.yaml`) → n'importe qui pourrait modifier un score.
**À corriger dès la Phase 1.**

**Vérification effectuée** : les consommateurs existants (`app_wsm`, `app_wsm_dev`,
`app_wsm_dev/src/network/liveApi.js`) appellent **`/api/wsm/...`** = **API legacy PHP**
(backend distinct), **pas** le `/wsm` d'api2. Sécuriser/renommer le `/wsm` d'api2 **n'impacte
aucun consommateur existant** (il n'est utilisé nulle part aujourd'hui). **Zéro régression.**

## 5. Architecture cible

```
app4 (Nuxt 4 SPA, origine /admin2)
 ├─ pages/games/[id]/scoring.vue      ← console Scoring (orchestrateur léger ; direct + post-match)
 ├─ pages/games/[id]/scoreboard.vue   ← affichage public (route Nuxt, même origine)
 ├─ pages/games/[id]/shotclock.vue    ← affichage shotclock (route Nuxt)
 ├─ components/scoring/               ← UI découpée en composants réutilisables (cf. §6.6bis)
 │    ├─ ScoringHeader.vue · ScoringSettingsPanel.vue · ScoringOfficials.vue
 │    ├─ ScoringTeamRoster.vue · ScoringTimer.vue · ScoringShotclock.vue
 │    ├─ ScoringPenalties.vue · ScoringEventButtons.vue · ScoringEventHistory.vue
 │    └─ ScoringScore.vue · ScoringStatusBadge.vue · ScoringPeriodSelector.vue · …
 ├─ stores/scoringStore.ts            ← état du match (port app3 → api2)
 ├─ composables/useScoringPermissions.ts
 ├─ composables/useTimer.ts | useBroadcast.ts | useWebSocket.ts (port app3)
 ├─ composables/useHardwareScoring.ts ← mode Hardware Scoring (Phase 3)
 └─ types/scoring.ts

       │ écritures (online-first, useApi + X-Active-Mandate)
       ▼
api2 ScoringController (ex-WsmController) → kp_match / kp_match_detail / kp_chrono / kp_stats
 + AdminGamesController::toggleValidation (verrouillage)
       │
       ├─ BroadcastChannel 'kpi_channel' (même origine) → scoreboard/shotclock locaux
       └─ WebSocket broker (interne) → incrustations /live + clients distants + matériel matériel propriétaire
```

## 6. Détail fonctionnel

### 6.1 Routing & placement

- `pages/games/[id]/scoring.vue` — console (chrono, score, faits de jeu, joueurs A+B, périodes,
  pénalités, validation/verrouillage). Calquée sur le pattern presence
  (`pages/presence/match/[matchId]/team/[teamCode].vue`) mais **les deux équipes sur une page**.
- `pages/games/[id]/scoreboard.vue` et `…/shotclock.vue` — affichages plein écran
  (`definePageMeta({ layout: false })`), remplacent `scoreboard.php`/`shotclock.php`.

#### Cohabitation transitoire (V2 / V3 / Scoring)

**Pendant toute la durée du développement, de l'expérimentation et jusqu'à la validation
définitive du Scoring, les liens vers les feuilles de marque V2 et V3 doivent persister.** On
**ajoute** un nouveau lien « Scoring » à côté, sans toucher aux deux existants.

Dans `app4/pages/games/index.vue`, les liens par match sont rendus à deux endroits :
- **vue tableau** (~ lignes 2095-2104) : boutons « V2 » / « V3 » appelant `openScoresheet(g.id, 2|3)` ;
- **vue carte/mobile** (~ lignes 2818-2824) : mêmes boutons en footer de carte.

À chacun de ces deux endroits, **ajouter un bouton « Scoring »** (après V2/V3), gaté sur
`canScore` + `g.authorized`, qui fait `navigateTo('/games/${g.id}/scoring')`. Le helper
`openScoresheet(gameId, version: 2 | 3)` (~ ligne 1419, ouvre les PHP legacy `FeuilleMarque2/3.php`
dans une fenêtre nommée) **reste inchangé**. Le lien « PDF » (`FeuilleMatchMulti.php`) reste
également inchangé.

Après validation définitive (hors périmètre de ce plan), les boutons V2/V3 et le helper
`openScoresheet` pourront être retirés ; le PDF de contrôle est conservé.

### 6.2 État — `stores/scoringStore.ts`

Port de `app3/stores/matchStore.ts`, en :
- **retirant** IndexedDB/dexie/uuid/toRaw/`saveMatchToLocal` (offline reporté) ;
- **chargeant** le match via `GET /admin/games/{id}` (forme camelCase déjà fournie par
  `AdminGamesController::get`) et les joueurs via `GET /admin/matches/{id}/players?teamCode=A|B`
  (déjà utilisé par `presenceStore.initMatchMode`) ;
- faisant que **chaque action mutante POSTe vers api2 Scoring** via `useApi` (mise à jour
  optimiste + rollback sur erreur, cf. `togglePublication`/`toggleValidation` de
  games/index.vue) :

| Action store | Endpoint api2 (Scoring) |
|---|---|
| `setPeriod` | `PUT /scoring/gameParam` (param `Periode`) |
| score | `PUT /scoring/gameParam` (param `ScoreA`/`ScoreB`/`ScoreDetailA`/`ScoreDetailB`) |
| `setStatus` | `PUT /scoring/gameParam` (param `Statut` ATT/ON/END, `Heure_fin`) |
| `addEvent` / `updateEvent` / `removeEvent` | `PUT /scoring/gameEvent` (action add/**update**/remove, code B/V/J/R/D) |
| chrono | `PUT /scoring/gameTimer` (run/stop/RAZ → `kp_chrono`) |
| statut joueur | `PUT /scoring/playerStatus` |
| **valider/verrouiller** | `PATCH /admin/games/{id}/validation` (réutilise l'existant) |

- `isLocked` dérivé de `validation === 'O'`.
- Nouveau `types/scoring.ts` (miroir de `types/presence.ts`) : `ScoringMatch`, `ScoringPlayer`,
  `ScoringEvent` (code `'B'|'V'|'J'|'R'|'D'`, period, tpsJeu, player, number, team, reason),
  `Penalty`, `Period` (**cible : `'M1' | 'M2' | \`P${number}\` | 'TB'`** — prolongations non bornées,
  cf. §0.6 ; l'ancien `'P1'|'P2'` figé est remplacé), `MatchStatus='ATT'|'ON'|'END'`.

#### Configuration du match centralisée (`ScoringConfig`)

**Toutes les valeurs réglables sont regroupées en un seul objet** `ScoringConfig` dans
`types/scoring.ts`, porté par un **unique champ `config` du `scoringStore`** (initialisé depuis
un `DEFAULT_SCORING_CONFIG`). C'est le **point de paramétrage unique** en attendant le réglage
par compétition — **aucune constante de durée éparpillée** dans les composants ou composables
(le legacy les dispersait en `const` de haut de page : `mainTimerDefault`, `shotclockDefault`,
`penDefault`, `duree_prolongations`, `arret_chrono_sur_but`… → **à rassembler ici**).

Contenu (à compléter à l'implémentation) :

| Clé | Valeur(s) | Aujourd'hui | Cf. |
|---|---|---|---|
| `periodDurations` | durées par période (s) | `{ M1:600, M2:600, P:300, TB:180 }` — prolongations `P{n}` = **300 s (5 min, ICF et FFCK**, cf. §0.9**)** | §6.4, §7.5 |
| `breakDurations` | pauses inter-périodes indicatives (s) : `{ halftime, beforeOvertime, betweenOvertimes }` | `{ halftime: 180, beforeOvertime: 180, betweenOvertimes: 60 }` | §7.5, §0.8 |
| `shotclockDurations` | `{ full, offensiveRebound }` (s) | `{ full: 60, offensiveRebound: 40 }` | §6.5 |
| `shotclockOffensiveReboundEnabled` | le départ/reset 40 s est-il actif | **`true`** — le nouveau système applique d'emblée le règlement 2027 (cf. §0.9) ; le legacy reste inchangé | §6.5 |
| `allowTimerAdjustWhileRunning` | ajustement fin du chrono **autorisé chrono en marche** (= synchro avec un chrono Hardware Scoring) | `false` (sinon : ajustement seulement à l'arrêt) | §6.4 |
| `penaltyDuration` | durée d'une pénalité de carton (s) | `120` (2 min) | §7.4 |
| `overtimeUnlimited` | prolongations non bornées (but en or) | `true` (règlement) | §7.5 |
| `shootoutEnabled` | tirs au but autorisés | `false` (option tournoi) | §7.5 |
| `stopClockOnGoal` | arrêt chrono auto sur but | `false` | §6.5 |
| `defaultCardReason` | motif de carton pré-sélectionné | `unknown` (Autre/Non précisé — saisie rapide, cf. §0.9) | §7.3, §7.4 |
| `federationProfile` | profil de durées (ICF/FFCK) | indicatif — les durées de prolongation sont désormais **identiques** (5 min) dans les deux règlements (§0.9) | §6.4 |

> **Cible (évolution).** Quand la **compétition** portera ces réglages, on **hydrate
> `store.config`** depuis la réponse api2 du match/compétition (au lieu de `DEFAULT_SCORING_CONFIG`)
> — **sans changer aucun point d'appel** : composants et `useTimer` lisent toujours `store.config`.
> Le défaut reste le fallback si la compétition ne précise rien. **Une seule source de vérité.**

> **Note implémentation (Phase 0 → à étendre).** Le store actuel n'expose que `periodDurations`
> (cf. `DEFAULT_PERIOD_DURATIONS`, `state.periodDurations`). Le passage à `ScoringConfig` doit
> **englober `periodDurations`** dans le nouvel objet (ou le conserver comme sous-champ) pour
> éviter d'avoir deux endroits de réglage.

#### Journalisation des actions (`kp_journal`) — obligatoire

**Toute action mutante du Scoring côté api2 doit être tracée** dans `kp_journal`, **exactement
comme les autres actions utilisateurs**. `AdminGamesController` le fait déjà via
**`AdminLoggableTrait::logActionForMatch(action, season, competition, gamedayId, matchId, details)`**
(insère `Dates, Users, Actions, Saisons, Competitions, Journees, Matchs, Journal`) — p.ex.
« Validation match », « Publication match », « Statut match ».

> **État actuel (vérifié) : `ScoringController` n'utilise PAS le trait → ses actions ne sont pas
> journalisées.** À corriger : faire `use AdminLoggableTrait` et appeler `logActionForMatch(...)`
> **en tête de chaque endpoint mutant** : `gameParam` (score/statut/période → « Scoring score »,
> « Scoring statut », « Scoring période »), `gameEvent` (« Scoring événement » add/update/remove
> avec code+joueur+temps en `details`), `gameTimer` (« Scoring chrono » run/stop/RAZ),
> `playerStatus`. La validation/verrouillage passe déjà par `AdminGamesController` (donc déjà
> tracée). Le `season`/`competition`/`gamedayId` se résolvent depuis le match (déjà fait par
> `assertMatchAuthorized`, mutualiser la requête). Échec de log **silencieux** (ne casse pas
> l'action), comme le trait existant.

#### Génération des JSON de diffusion/incrustation — parité legacy

> ⚠️ **Caduc — voir §0.3.** La console ne génère **plus** de fichiers cache JSON. Les 3
> `// TODO (Phase 3): generate broadcast cache here` **insèrent dans `scoring_outbox`** (→ worker →
> Mercure), pas des fichiers. La sous-section ci-dessous documente le contrat legacy **pour mémoire**
> (il reste consommé par les incrustations PHP existantes, alimentées par le worker de cache
> indépendamment de la console).

Le scoring legacy **régénère, à chaque action, des fichiers JSON dans `sources/live/cache/`** qui
alimentent les **incrustations vidéo (`/live`)**, le scoreboard et les clients distants. ~~Le
Scoring api2 doit produire les mêmes fichiers~~ — **non : la console n'en produira jamais**
(cf. bannière ci-dessus et §0.3) ; le contrat est décrit ici uniquement parce que les incrustations
PHP legacy le consomment encore, alimentées par le worker de cache existant jusqu'à leur
remplacement par la page d'incrustation unique
([PAGE_INCRUSTATION.md](PAGE_INCRUSTATION.md), plan lot 4).
Générateur legacy = classe **`CacheMatch`** (`live/create_cache_match.php`),
appelée par `setChrono.php`, `StatutPeriode.php`, `evt_match.php`, `ajax_updateChrono.php`,
`getNextGame.php`. Fichiers par match (vérifié sur des exemples réels) :

| Fichier | Contenu | Régénéré sur |
|---|---|---|
| `{idMatch}_match_global.json` | entête (catég, journée, phase, terrain, date/heure, n°, validation, statut, arbitres) + **compositions** `equipe1/equipe2` (id, nom, club, logo, couleurs, joueurs) | compo/officiels/statut |
| `{idMatch}_match_score.json` | `periode`, `score1`/`score2`, **liste `event`** (détail `kp_match_detail` enrichi nom/prénom) | tout événement, changement de score/période |
| `{idMatch}_match_chrono.json` | `action` (run/stop), `start_time`, `start_time_server`, `run_time`, `max_time`, `shotclock`, `penalties` (JSON), `tick` | toute action chrono/shotclock/pénalité |

> **Cadrage phase (mis à jour 2026-07-27).** Les 3 TODO du `ScoringController`
> (`// TODO (Phase 3): generate broadcast cache here`, ×3 : gameParam, gameEvent, gameTimer) seront
> implémentés en Phase 3 sous la forme **« insérer dans `scoring_outbox` »** (→ worker → Mercure,
> cf. §0.3) — **il n'y aura jamais de génération de fichiers JSON par la console**, l'ancienne
> formulation (« parité `CacheMatch` en Phase 3 ») est caduque. **La journalisation `kp_journal`
> est dès la Phase 1** (peu coûteuse, cohérence d'audit — fait).

### 6.3 Permissions & monétisation-readiness

> **À cadrer dans un point dédié (à venir).** Le **contrôle fin des droits d'accès et de
> modification** d'un match depuis la page Scoring (qui peut voir / scorer / gérer les joueurs /
> valider / **verrouiller**, en croisant profil, mandat, et statut du match) fera l'objet d'une
> **revue spécifique** avant l'ouverture au-delà de l'expérimentation. Les seuils ci-dessous sont
> ceux de la phase d'expérimentation (restrictifs) ; la cible est esquissée mais **non figée**.
>
> **Règle déjà arrêtée** : le **verrouillage / déverrouillage** (`canLock`) est **réservé au
> profil ≤ 6** (jamais au-delà, y compris après ouverture). Le profil 9 « Table de marque »
> pourra scorer mais **pas verrouiller**.

- Nouveau `composables/useScoringPermissions.ts`, miroir de `usePresencePermissions`,
  signature `(isLocked: Ref<boolean>)`.
- **Restriction DEV-ONLY (en cours de développement)** : le bouton « Scoring » dans /games
  n'est visible que pour **le seul login `42054`** (constante `SCORING_DEV_USER` dans
  `pages/games/index.vue` : `canScoring = authStore.user?.id === '42054' && profile <= 2`).
  C'est un **masquage UI uniquement** (choix assumé : pas de restriction par login côté serveur).
  À retirer pour revenir à `profile <= 2` quand la fonctionnalité s'ouvre au bureau.
- **Accès restreint au profil ≤ 2 pour l'instant** (phase d'expérimentation : réservé aux
  admins/bureau, pas encore ouvert au profil 9 « Table de marque »). Cohérent avec le bouton V3
  existant déjà gaté `profile <= 2` dans la vue carte. Seuils :
  - `canView = profile <= 2` (accès à la console + visibilité du lien « Scoring »)
  - `canScore = profile <= 2 && !isLocked` (buts/cartons/chrono)
  - `canManagePlayers = profile <= 2 && !isLocked`
  - `canValidate / canLock = profile <= 2`
- **Cible post-validation** (à élargir une fois la fonctionnalité validée, non implémenté
  maintenant, **à confirmer dans le point dédié**) : `canScore = (profile <= 6 || profile === 9)`,
  `canValidate = profile <= 6`, **`canLock = profile <= 6` (plafond ferme : le profil 9 ne
  verrouille jamais)**, alignés sur la règle score de games/index.vue et le rôle `ROLE_SCORER`
  (profil 9).
- **Côté serveur** : appliquer le **même seuil restrictif (≤ 2)** dans `ScoringController`
  pendant l'expérimentation (jamais se fier au gating client), élargi en même temps que le
  client lors de l'ouverture. Profil 9 = `ROLE_SCORER` déjà présent dans la hiérarchie, prêt
  pour l'élargissement futur.
- **Isolation par mandat** : `useApi` envoie déjà `X-Active-Mandate` ; en faisant respecter ce
  scope dans `ScoringController` (restreindre les matchs modifiables au périmètre du mandat,
  via le filtrage `allowedJournees` déjà utilisé par `AdminGamesController`), le Scoring est
  **isolé par organisation dès le départ**. Monétisation future = gater `canScore`/l'accès
  route derrière un flag de mandat, **sans toucher aux points d'appel** (le seuil de profil et
  le flag de mandat se combinent dans le même composable `useScoringPermissions`). Aucune table de
  facturation, aucun paywall maintenant.

### 6.4 Chrono

Réutiliser **easytimer.js** (comme app3/fm3 ; absent de app4 → ajouter aux deps). Port de
`app3/composables/useTimer.ts` (countdown principal + shotclock + buzzer `targetAchieved`,
précision `secondTenths`). **Le chrono devient autoritatif côté serveur** via `gameTimer` →
un rechargement reconstruit l'horloge depuis `kp_chrono` (upgrade clé vs app3).

#### Reprise d'un match en cours (résilience / failover terminal)

Au **chargement d'un match déjà en cours**, le **chrono principal et son statut (run/stop)** sont
**recalculés depuis l'état serveur** (`kp_chrono` : `action`, `start_time`, `start_time_server`,
`run_time`, `max_time` + heure serveur) pour se **resynchroniser automatiquement** — déjà
implémenté (cf. §6.4 « modèle de chrono », §12). La précision est **à quelques dixièmes de seconde
près**, **acceptable** dans ce contexte.

**Cas d'usage (déjà fonctionnel en legacy) : reprise sans interruption du jeu.** Si le terminal
qui gère le match a un **problème technique** (plantage, batterie, navigateur fermé…), on peut
**rouvrir le match sur un autre onglet / navigateur / terminal** et le chrono **repart synchronisé**
sans arrêter le jeu. C'est une **exigence de résilience** à préserver dans le Scoring.

> **Limite actuelle à connaître (et évolution future à noter).** Seul le **chrono principal** est
> persisté côté serveur. À la reprise :
> - le **flux WebSocket** est momentanément indisponible (reconnexion broker, cf. §6.5) ;
> - le **shotclock** et les **pénalités en cours** **ne sont PAS restaurés** : ils ne sont **pas
>   sauvegardés côté serveur** à ce stade (en legacy ils transitaient dans le cache chrono /
>   messages, pas dans un état serveur fiable pour la reprise). L'opérateur les **re-saisit**
>   après reprise (relancer le shotclock au prochain reset, recréer les pénalités actives).
>
> **Évolution future (à évaluer) :** **persister shotclock + pénalités côté serveur** (étendre
> `kp_chrono` ou table dédiée) pour les restaurer aussi à la reprise. Non bloquant pour le MVP ;
> noté pour ne pas l'oublier.

**Le chrono n'est affiché qu'en mode direct** (cf. §1.1) : masqué en post-match. **Distinction
importante** : ce qui est masqué, c'est **l'horloge live** (run/stop/RAZ + affichage du temps de
jeu courant). Le **champ « temps du fait de jeu »** (période + `MM:SS`, avec ses boutons
d'ajustement −60/−10/−1/+1/+10/+60) **reste présent en post-match** : il est indispensable pour
**saisir ou corriger le temps d'un fait de jeu à la main** (cf. §7.3). En post-match, ce champ
n'est plus pré-rempli depuis le chrono (qui n'existe pas) mais **tapé/édité directement**.

**Ajustement fin du chrono (Phase 1).** Comme en legacy (`adjustTimer`, fm3_A.js / fm3_C.js) :
boutons **−10 / −1 / +1 / +10 s** sur le temps de jeu, plus **réglage de la durée de période**
(dialog d'ajustement). Utile dès le MVP pour la **correction post-déroulement**.

> **Ajustement chrono en cours = synchronisation, optionnel et désactivé par défaut.** En legacy,
> `allowMainTimerUpdateWhileRunning = true` permet d'ajuster le temps de jeu **même chrono en
> marche**. Cet usage n'est **pas** un usage courant : il sert à **resynchroniser manuellement**
> le **chrono d'incrustation KPI** avec le **chrono de bord de terrain géré en Hardware Scoring**
> (les deux horloges peuvent dériver). Pour le nouveau Scoring, cette possibilité doit être
> **optionnelle et désactivée par défaut** : nouveau paramètre **centralisé dans `ScoringConfig`**
> (`allowTimerAdjustWhileRunning: false`, cf. §6.2). Par défaut, l'ajustement fin du chrono n'est
> donc possible **que chrono à l'arrêt** ; on n'active le réglage live que dans les configs
> Hardware Scoring qui en ont besoin. L'ajustement du **shotclock** reste, lui, **toujours
> réservé à l'arrêt** (`allowShotclockUpdateWhileRunning = false`, cf. §6.5).

**Durées des périodes.** `periodDurations` vit dans `ScoringConfig` (cf. §6.2,
« Configuration du match centralisée »), valeurs par défaut (M1/M2 = 600 s ; P1/P2/TB cf. §7.5)
et **ajustable manuellement à la volée** par période (réglage de la durée dans le dialog
d'ajustement chrono). **Évolution prévue (hors MVP)** : ces durées (comme **toutes** les valeurs
de `ScoringConfig`) deviennent **paramétrables au niveau de la compétition** (le legacy code en
dur deux profils fédération via `duree_prolongations` — devenu sans objet : les prolongations
sont à **5 min dans les règlements ICF et FFCK** (cf. §0.9) — qu'on remplacera par un réglage
porté par la compétition, en **hydratant `store.config`**).

### 6.5 Temps réel & captation matériel

- **Diffusion locale (Phase 2)** : port de `app3/composables/useBroadcast.ts` (canal
  `kpi_channel`, contrat `timer/timer_status/shotclock/period/teams/scores/penA/penB`).
  **BroadcastChannel est same-origin** → on **porte le markup** de `scoreboard.php` +
  `v2/scoreboard.js` en routes Nuxt (`scoreboard.vue`/`shotclock.vue`), ouvertes même origine
  via `window.open`. **Décision confirmée (§0.8)** : le canal **local** (fenêtres du même poste)
  reste BroadcastChannel — zéro réseau, zéro latence, fonctionne sans Internet ; les écrans
  **distants** et incrustations consomment **Mercure**.
- **Shotclock — chronomètre de tir (Phase 2)** (terminologie §0.9 : « chronomètre de tir », ne
  plus employer « temps d'action de but ») : compte à rebours, buzzer à 0 (`targetAchieved`),
  suit le chrono (pause quand le chrono s'arrête), **caché quand le temps de jeu restant est
  inférieur au shotclock** (`shotClockShow`), affiché `--` quand il est à l'arrêt. Ajustements
  **−10 / −1 / +1 / +10 s**, autorisés **seulement chrono à l'arrêt**
  (`allowShotclockUpdateWhileRunning = false`). Le shotclock ne concerne que le **mode direct**.

  > **Deux durées de shotclock (règlement 2027 — appliqué d'emblée dans le nouveau système,
  > cf. §0.9).** Les règlements international (ICF) **et** français (FFCK) définissent **deux
  > durées** :
  > - **60 s** : durée à l'**engagement** (nouvelle possession après but, sortie, faute…) ;
  > - **40 s** : durée **réduite** lorsque **l'équipe qui vient de tenter un tir vers le but
  >   récupère le ballon** (rebond offensif conservé).
  >
  > Les deux durées vivent dans **`ScoringConfig`**
  > (`shotclockDurations = { full: 60, offensiveRebound: 40 }`, cf. §6.2 — même endroit que les
  > durées de période, à terme paramétrable par compétition). **Le 40 s est actif par défaut dans
  > le nouveau Scoring** (`shotclockOffensiveReboundEnabled = true`) ; seul le legacy, qui reste
  > sur l'ancien règlement jusqu'à la fin de saison 2026, ne le connaît pas.

  > **Pilotage du shotclock — trois commandes, le départ EST un reset (décision 2026-07-27,
  > cf. §0.9 et plan §4.11).** Au coup d'envoi de **chaque période**, **le chrono principal
  > démarre mais le shotclock NE démarre PAS** : il reste **à l'arrêt** (`--`), même chrono en
  > marche, tant que l'opérateur ne l'a pas lancé (première possession). Le shotclock a
  > **exactement trois commandes** :
  > - **Départ/reset 60 s** : charge 60 s **et lance** le décompte (engagement, nouvelle
  >   possession) — indépendant du départ du chrono principal ;
  > - **Départ/reset 40 s** : charge 40 s **et lance** le décompte (rebond offensif) ;
  > - **Arrêt** : **ce n'est pas une pause** — le shotclock repasse à l'affichage `--` et revient
  >   à l'**état initial**, en attente d'un nouveau départ 60 s ou 40 s.
  >
  > Une fois lancé, le shotclock **suit le chrono principal** : arrêt du chrono ⇒ suspension
  > automatique du décompte, reprise du chrono ⇒ reprise automatique (c'est la seule « pause »
  > qui existe, et elle est **suiveuse**, jamais manuelle). Conséquence sur le modèle : trois
  > états seulement — **arrêté** (`--`), **en décompte**, **suspendu par le chrono** — et l'état
  > « shotclock lancé » reste découplé de l'état « chrono lancé ».

- **Raccourcis clavier (Phase 2, avec le shotclock) — paramétrables** (décisions 2026-07-23 et
  2026-07-27, cf. §0.8/§0.9) : les touches sont une **préférence par poste/utilisateur** (écran
  de réglage dans la console, persistée en localStorage), avec ces valeurs par défaut :

  | Action | Touche par défaut |
  |---|---|
  | Chrono principal : départ / arrêt | `Espace` |
  | Shotclock : **départ/reset 60 s** (engagement) | `Entrée` |
  | Shotclock : **départ/reset 40 s** (rebond offensif) | `.` (pavé numérique, proche de `Entrée` et `0`) — proposé, à valider |
  | Shotclock : **arrêt** (retour à `--`, état initial) | `0` |
  | Shotclock : ±1 s | `+` / `−` (legacy fm3_C.js) |

  Neutralisés quand le focus est dans un champ de saisie (temps de fait de jeu, commentaires…).
- **Arrêt du chrono sur but (option, Phase 2)** : paramètre `arret_chrono_sur_but` (legacy) — un
  but déclenche un stop chrono automatique (temps mort). **Désactivé par défaut** (l'était aussi
  en legacy), à exposer comme option.
- ~~**WebSocket broker (Phase 3)**~~ — **caduc, cf. §0.3** (la diffusion cible est Mercure via
  `scoring_outbox`, **aucun** port de `useWebSocket.ts`, **aucune** génération de JSON par la
  console — cf. §6.2 « Cadrage phase »). Le descriptif ci-dessous (format
  `{p:"eventId_terrain", t:type, v:value}`, mirroring vers le broker) est conservé **pour
  mémoire** uniquement.

  > **Activation du broker — mécanisme legacy, pour mémoire (caduc dans la cible : l'abonnement
  > ciblé Mercure, plan §3.3, remplace toute résolution par fichier réseau).** Aujourd'hui,
  > l'URL/credentials du broker **ne sont pas** une config globale : ils sont définis **par
  > événement** dans un JSON généré par le **WebSocket Manager (WSM)**, p.ex.
  > `sources/live/cache/event{idEvent}_network.json` :
  > ```json
  > {"network":{"global":{"stomp":false,"url":"wss://broker.kayak-polo.info",
  >   "password":"…","topic":"broker"}}}
  > ```
  > Le legacy (`fm3_C.js` `checkWebSocket`) **POST** sur ce fichier : s'il **existe** → on se
  > connecte au broker avec ces paramètres ; s'il renvoie **404** → **pas de broker**, on retombe
  > sur la seule diffusion locale `BroadcastChannel`.
  >
  > **Tranché (cf. §0.3 et plan §3.3)** : dans la cible, il n'y a **ni broker ni JSON réseau par
  > événement** — la console publie via `scoring_outbox` → Mercure (topics
  > `/scoring/event/{e}/pitch/{p}/…`), et le fallback local reste `BroadcastChannel`.
- **Hardware Scoring (Phase 3, réaligné — cf. §0.8 et plan §4.7/lot 5)** : le matériel est **une
  source de plus** écrivant le même `scoring_live_*` **côté serveur** (relais Stomp — déployé sur
  le serveur KPI ou en boîtier local selon le site — → ingestion api2 → traduction serveur). **La
  captation ne passe plus par le navigateur.** Côté console, le mode « Hardware Scoring » devient
  une **supervision** : `useHardwareScoring.ts` bascule la **source active** du terrain
  (promotion, plan §4.1), affiche l'état reçu (GET /state + Mercure) et alerte en cas de
  divergence base/panneau ; la saisie manuelle est désactivée tant que le matériel est la source
  active.

### 6.6 i18n

Namespace `scoring.*` dans `i18n/locales/fr.json` et `en.json` (périodes, statuts, codes
de faits de jeu, motifs de cartons, libellés chrono/shotclock/scoreboard, messages de
verrouillage, libellés « Scoring » / « Hardware Scoring »). Wording FR sourcé de
`FeuilleMarque3.php` + `v2/fm3_*.js`. **cn hors périmètre.**

### 6.7 Découpage en composants (réutilisabilité)

`scoring.vue` ne doit **pas** être un monolithe : la page est un **orchestrateur léger** qui
assemble des **composants `components/scoring/*` autonomes et réutilisables**. Objectif :
réutiliser les briques communes ailleurs (scoreboard, shotclock, futures vues Hardware Scoring,
voire d'autres pages de gestion de match) et tester chaque brique isolément.

Principes :
- **Props down / events up** : chaque composant reçoit ses données (props issues du `scoringStore`)
  et **émet des intentions** (`@add-event`, `@toggle-timer`, …) ; les **mutations passent par le
  store**, pas dans les composants. Pas d'appel `useApi` direct dans un composant d'affichage.
- **Permissions injectées** : le résultat de `useScoringPermissions` est passé en props (`canScore`,
  `canManagePlayers`, `canLock`…) pour que chaque composant se mette en lecture seule sans
  re-dériver les droits.
- **Découplage temps réel** : le **chrono** et le **shotclock** sont des composants qui consomment
  `useTimer` et n'apparaissent qu'en **mode direct** (cf. §1.1) ; le **scoreboard/shotclock plein
  écran** réutilisent les mêmes composants d'affichage que la console.
- **Montage adaptatif (responsive)** : un même composant (joueurs, historique, pénalités) doit
  pouvoir être monté **inline** (desktop) ou dans un **conteneur à la demande** (collapse /
  offcanvas / modale, mobile-tablette) **sans changer sa logique** (cf. §7.1 « Ergonomie
  responsive »). Le conteneur est décidé par la page/un layout responsive, pas par le composant.

Découpage indicatif (à affiner à l'implémentation) :

| Composant | Rôle | Réutilisé par |
|---|---|---|
| `ScoringHeader` | Entête match (compétition, n°, date, terrain, bascule Paramètres/Déroulement) | — |
| `ScoringScore` | Score A/B (encadré, provisoire vs officiel) | scoreboard |
| `ScoringSettingsPanel` | Vue Paramètres (type **lecture seule**, publication **lecture seule**, validation, contrôle, charge match) | — |
| `ScoringOfficials` | Officiels éditables + rappel organisation | — |
| `ScoringTeamRoster` | Liste joueurs d'une équipe (n°/capitaine **éditables si non verrouillé**, sélection, suppression, recharge) — **à la demande pendant le déroulement** (offcanvas/collapse, cf. §7.1) | ×2 (A et B) |
| `ScoringTimer` | Chrono (run/stop/RAZ + ajustement fin) — **mode direct** | scoreboard |
| `ScoringShotclock` | Shotclock (ajustement + **double reset 60 s / 40 s**, déclenchement indépendant du chrono — cf. §6.5) — **mode direct, Phase 2** | shotclock |
| `ScoringPenalties` | Zone pénalités A/B (timers, +/−, suppression) — **Phase 2** | scoreboard |
| `ScoringStatusBadge` | Statut du match en **badge cyclique** (`ATT→ON→END→ATT`), calqué sur le badge statut de `competitions/index.vue` (cf. §7.1) | — |
| `ScoringPeriodSelector` | **Avancer à la période suivante** selon le type (C : `M1→M2` ; E : `M1→M2→OT…→TB?`), confirmation + durée non standard à part (cf. §7.1/§7.5) ; accès direct optionnel | — |
| `ScoringEventButtons` | Boutons de faits de jeu (but, V/J/R/D) + sélection joueur/motif + **champ temps du fait de jeu** (période + `MM:SS`, ajustements) — **présent en direct ET post-match** (≠ `ScoringTimer`, cf. §1.1/§6.4) | — |
| `ScoringEventHistory` | Historique éditable (table symétrique A | Temps | B) — **masqué par défaut, ouvert à la demande** (collapse/offcanvas, cf. §7.1) | — |

## 7. Déroulement d'un match (workflow de la table de marque)

> Cette section décrit le **parcours fonctionnel attendu** de la console Scoring, calqué sur la
> feuille de marque en ligne V3 (FMV3) et complété par les nouveautés (pauses inter-périodes,
> prolongations « but en or », alertes cartons). **Principe directeur : la page Scoring doit
> exposer, d'une manière ou d'une autre, toutes les informations présentes sur le PDF de
> contrôle `FeuilleMatchMulti.php`** (entête match, officiels, joueurs A/B, détail des
> faits de jeu, scores mi-temps/final, commentaires, heures début/fin).

### 7.1 Structure d'écran (deux vues)

La console s'organise en deux vues commutables (cf. captures FMV3 : bouton « Déroulement du
match… » ↔ « Paramètres du match… ») :

1. **Vue Paramètres** (préparation, avant coup d'envoi) — sous-onglets :
   - **Paramètres** : type de match (classement / élimination) **en lecture seule**, publication
     (privé/public) **en lecture seule**, accès Stats / PDF / langue, score officiel + score
     provisoire (calculé = nb de buts), bouton « Valider ce score », contrôle match (Ouvert /
     Verrouillé), chargement d'un autre match (par ID# ou n°), « Match suivant… ».

   > **Écart assumé vs legacy.** Contrairement à FMV3, le **type de match** et la **publication**
   > sont **affichés mais non modifiables** depuis le Scoring : ces réglages relèvent de la
   > gestion du match / de la compétition (où ils sont déjà éditables, cf. games/index.vue), pas
   > de la table de marque. La console les **montre pour contrôle** (s'assurer du bon type avant
   > coup d'envoi) sans permettre de les changer ici.
   - **Officiels** : secrétaire, chronométreur, chronométreur de tir (shotclock), arbitre
     principal, arbitre secondaire, juges de ligne (×2) — **cliquer pour modifier** ; rappel des
     infos d'organisation (club organisateur, resp. organisation, délégué, chef des arbitres, RC).
   - **Équipe A / Équipe B** : liste des joueurs présents (N°, statut, nom, prénom, licence,
     catégorie), suppression d'un joueur, **« Recharger les joueurs présents »** (depuis la
     feuille de présence). Le **numéro de maillot** et le **statut capitaine** (`-`/`C`/`E`) sont
     **modifiables tant que le match n'est pas verrouillé** (`canManagePlayers = !isLocked && …`,
     cf. §6.3) ; une fois verrouillé → **lecture seule**. (Édition inline → `playerStatus`,
     cf. §7.8.)
2. **Vue Déroulement** (pendant le match) — chrono, shotclock, score live, sélecteur de
   statut/période, boutons de faits de jeu (but, cartons vert/jaune/rouge/définitif), zone
   pénalités, historique des faits de jeu (table A | Temps | B), commentaires.

> **Layout legacy observé (FeuilleMarque3.php, match en cours, capté via Playwright).** Détails
> à reproduire ou réinterpréter :
> - **Entête** sur une ligne : `Compétition (lieu/cat) – Match n°X – date à heure – Terrain N`,
>   avec à droite le bouton de bascule « Déroulement du match… » ↔ « Paramètres du match… ».
> - **Vue Paramètres** : les **sous-onglets équipes affichent le score** dans leur libellé
>   (`A 🇫🇷 Saint-Grégoire I [4]`, `B 🇫🇷 Condé-sur-Vire I [6]`) avec **drapeau de nation** ;
>   « Score officiel » est **grisé/lecture seule**, « Score provisoire (calculé nb de buts) » à
>   côté, puis « Valider ce score » ; « Contrôle match » = toggle Ouvert/Verrouillé + cadenas.
> - **Vue Déroulement** : trois colonnes **Équipe A | zone centrale | Équipe B** ; chaque équipe
>   liste ses joueurs (n° maillot, nom, `(Cap.)`) et son **score encadré**. Zone centrale =
>   chrono (`−10/−1 [MM:SS] +1/+10`, Run/Reset) puis shotclock (boutons **son / ouvrir scoreboard
>   / ouvrir shotclock / refresh** groupés, `−10/−1 [60] +1/+10`, Reset) puis **Pénalités**
>   (`+`/`+` par équipe) puis boutons de faits de jeu (but, V/J/R/D) puis zone temps
>   (`−60/−10/−1 [MM:SS] +1/+10/+60`, Annuler/Liste).
> - **Historique** : table symétrique `V|J|R | Équipe A | B | Temps | B | Équipe B | V|J|R`, triée
>   période ↓ puis temps ↑, icônes buts/cartons, **motif affiché inline** entre parenthèses
>   (ex. `(Antisportif)`). **Commentaires** en bas (zone éditable « cliquez pour modifier »).

#### Ergonomie responsive — afficher la bonne information au bon moment

Le legacy est pensé **desktop large** (3 colonnes denses, tout visible en permanence). Le Scoring
doit être **utilisable sur tablette et smartphone** (la table de marque est souvent sur tablette
au bord du bassin). Principe : **n'afficher en permanence que l'indispensable du moment**, et
faire apparaître le reste **à la demande** via les patterns modernes adaptés (modale, **collapse**,
**offcanvas**, **tabs**, bottom sheet…), selon le composant et la taille d'écran.

Pendant le **déroulement** (mode direct), l'indispensable permanent = **chrono, shotclock, score,
période, boutons de faits de jeu**. Le reste apparaît à la demande :
- **Listes de joueurs** : pas besoin de voir les noms en continu. On les fait apparaître **au
  moment d'attribuer un fait de jeu** (sélection du joueur) ou **pour contrôler les faits de jeu
  déjà saisis** — sinon repliées/escamotées (offcanvas ou panneau collapsible par équipe).
- **Historique des faits de jeu** : masqué par défaut, **ouvert explicitement** par l'utilisateur
  (bouton « Liste/Historique » → collapse/offcanvas), comme le `liste_evt` legacy mais en modèle
  responsive.
- **Officiels / Paramètres / compositions** : déjà derrière la bascule « Paramètres », non visibles
  pendant le déroulement.

> Le **découpage en composants** (§6.7) sert directement cette ergonomie : chaque brique
> (joueurs, historique, pénalités…) peut être montée dans un conteneur adapté (inline desktop,
> offcanvas/collapse mobile) **sans dupliquer la logique**. Les seuils responsive s'appuient sur
> Tailwind (cohérence app4).

#### Saisie optimisée — peu de clics, clavier-first

Les saisies **pendant et après le match doivent minimiser le nombre de clics** et, autant que
possible, **être réalisables sans souris** (rapidité de la table de marque, fiabilité au bord du
bassin) :
- **Raccourcis clavier** pour les actions fréquentes (chrono, shotclock — déjà prévus §6.5 ; à
  étendre à la saisie de faits de jeu : sélection joueur par **numéro tapé**, validation par `Entrée`,
  annulation par `Échap`, comme le legacy `#time_evt` validant sur `Entrée`).
- **Enchaînement minimal** pour un fait de jeu courant : viser **but = joueur + touche** (le temps
  étant pré-rempli par le chrono en direct), sans étapes superflues.
- **Pré-remplissage intelligent** : temps depuis le chrono (direct), période courante, dernier
  contexte ; **focus** placé automatiquement sur le bon champ.
- Les composants exposent des **cibles de saisie clavier** (tabindex cohérent, `@keydown`),
  neutralisées quand le focus est dans un champ texte libre (cf. §6.5).

> Objectif mesurable à garder en tête : **saisir un but ou un carton en 1–2 actions** maximum, et
> pouvoir **piloter le chrono + la saisie au clavier** de bout en bout.

#### Sélecteur de statut et de période — bouton cyclique (pas une liste de tous les états)

Le legacy affiche **tous** les statuts (En attente / En cours / Terminé) et **toutes** les périodes
en boutons simultanés. Pour le Scoring (smartphone, tablette, mais **aussi PC**), c'est superflu :
ces états **se succèdent**. On réutilise le **pattern « badge cyclique »** déjà en place dans
`pages/competitions/index.vue` (`cycleStatus` : `statusMap { ATT→ON, ON→END, END→ATT }`, badge
coloré cliquable + libellé i18n + couleur par statut).

- **Statut du match** (`ScoringStatusBadge`) : **un seul badge** affichant le statut courant,
  **clic / touche = passe au suivant** (`ATT → ON → END`, puis retour possible `END → ATT` pour
  corriger). Couleurs et libellés calqués sur le badge statut compétition. Gain : un seul élément
  au lieu de trois, lisible et tappable au doigt.

- **Période** (`ScoringPeriodSelector`) : **avancer à la période suivante** selon le **type de
  match** plutôt qu'afficher toutes les périodes —
  - **type C (classement)** : `M1 → (mi-temps) → M2 → fin` ;
  - **type E (élimination)** : `M1 → M2 → OT1 → OT2 → … → (TB si activé)` (prolongations non
    bornées, cf. §7.5).
  Un **bouton « Période suivante »** (ou le badge période cliquable) déclenche le passage.

  > **Confirmation + durée non standard.** Changer de période est une action **engageante**
  > (remet le chrono à la durée de la nouvelle période) → demander une **confirmation via une
  > modale**. La **durée de période** prend par défaut la valeur de
  > `ScoringConfig.periodDurations` (cf. §6.2) : **ne demander la durée que si elle n'est pas
  > standard**, et **après coup** (le match peut démarrer sur la durée par défaut, l'ajustement
  > fin restant possible via le dialog chrono, cf. §6.4). Objectif : ne pas alourdir le geste
  > courant ; l'exception (durée non standard) se règle à part.

- **Accès direct** (échappatoire) : conserver la **possibilité d'aller à un statut/période précis**
  (utile en post-match/correction) via un menu déroulant secondaire, sans encombrer l'UI directe.

> Composants concernés : `ScoringStatusBadge` (nouveau, calqué sur le badge statut compétition) et
> `ScoringPeriodSelector` (cf. §6.7), qui passe d'« afficher M1 M2 [P1 P2 TB] » à « avancer selon
> le type + accès direct optionnel ».

### 7.2 Préparation (avant le coup d'envoi)

La table de marque, sur la **vue Paramètres**, doit :

1. **Vérifier l'identité du match** : équipes qui se présentent, terrain et horaire (entête =
   `kp_match` : `Libelle`/intitulé, `Terrain`, `Date_match`/`Heure_match`, équipes A/B), pour
   être sûr d'avoir le bon match. Un chargement par **ID# ou n° d'ordre** permet de corriger.
2. **Vérifier et compléter les officiels** (onglet Officiels) : secrétaire, chronométreurs,
   arbitres, juges de ligne. Champs « cliquer pour modifier ».
3. **Vérifier les joueurs présents** pour chaque équipe (onglets A/B) : ajuster les **numéros**,
   désigner le **capitaine**, **supprimer** des joueurs si nécessaire, ou **recharger depuis la
   présence**.
4. **Passer le match en jeu** : faire avancer le **badge de statut** `ATT → ON` (cf. §7.1),
   période sur **M1**, prêt pour le coup d'envoi.

### 7.3 Conduite du match (suivant les instructions de l'arbitre)

La table suit l'arbitre pour : **démarrer / arrêter le chrono**, saisir les **buts** et les
**cartons**.

- **Chrono** (autoritatif côté serveur, cf. §6.4) : run / stop / RAZ. Le shotclock (temps
  d'action de but) suit le chrono.
- **But** : incrémente le score de l'équipe, horodaté `période + temps de jeu`, attribué à un
  joueur. Peut déclencher la **suppression d'une pénalité adverse** (cf. 7.4).
- **Carton** : déclenche une **pénalité de 2 minutes** pour le joueur (cf. 7.4).
- **Saisie d'un fait de jeu** (legacy fm3_C.js) : sélectionner **un joueur** (ou l'équipe pour un
  carton d'équipe), **un type de fait de jeu** (but / carton V/J/R/D), saisir le **temps**
  (pré-rempli depuis le chrono en direct, ajustable −60/−10/−1/+1/+10/+60 s ; **tapé à la main en
  post-match**), et le **motif** pour un carton (modal, cf. 7.4 — **pré-sélectionné à
  « Autre/Non précisé »** pour ne pas ralentir la saisie, cf. §0.9). Pour un but, **attribution à
  un joueur** obligatoire.

#### Édition et suppression d'un fait de jeu déjà saisi (MVP Phase 1)

L'historique des faits de jeu (table A | Temps | B) est **éditable** : un clic sur une ligne la
charge dans la zone de saisie (période, temps, joueur, type, motif) et permet de la **modifier**
(`updateEvent` → `PUT /scoring/gameEvent` action=`update`) ou de la **supprimer**
(`removeEvent`, action=`remove`). L'édition **recalcule le score** (retrait du but de l'ancienne
ligne, ajout du nouveau) et **met à jour les marqueurs visuels** du joueur (but/carton).

> **Central pour la saisie/correction post-match** (cf. §1.1) : c'est par cette édition de ligne
> qu'on **corrige un match déjà joué** sans rejouer le chrono. C'est pourquoi l'édition complète
> est au périmètre **P1**, et pas seulement add/remove.

### 7.4 Pénalités (cartons)

- Un carton **vert, jaune ou rouge** (`V`/`J`/`R`) déclenche une **exclusion de 2 minutes** du
  joueur sanctionné, dont le **décompte suit le chrono** (se met en pause quand le chrono est
  arrêté). Pendant l'exclusion, **le joueur n'est PAS remplacé** : l'équipe joue en **infériorité
  numérique** (situation de *powerplay* pour l'adversaire). **Le carton noir (`D`) ne déclenche
  AUCUNE pénalité de 2 minutes** (correctif §0.10) : exclusion immédiate et définitive, **sans
  remplacement jusqu'à la fin du match** — l'équipe termine à effectif réduit.
- **Cardinalité (règle de jeu) :** une équipe présente **5 joueurs** à l'engagement et **ne peut
  descendre sous 3** sur le terrain → **au plus 2 exclusions concurrentes par équipe**, soit **4 au
  maximum au total**. C'est ce qui borne `scoring_live_clock.slot` à {1, 2} par équipe (cf. §0.5).
- **Nommage des cartons (règlement 2027 — décision §0.9).** `D` devient le **« carton
  d'exclusion définitive »** (EN : *Ejection card*), de couleur **noire** (token ⬛ dans l'UI).
  Le nouveau Scoring applique **immédiatement** cette appellation et cette couleur (i18n
  `scoring.*`) ; le **legacy n'est pas touché** et conserve « rouge définitif » jusqu'à la fin de
  saison 2026. Le code `D` est conservé en base (compat).
- **Levée anticipée sur but encaissé** (fin de l'infériorité — **correctif réglementaire
  §0.10, 2026-07-29**) : si l'équipe en infériorité **encaisse un but** pendant une exclusion,
  seule une pénalité de carton **vert ou jaune** est **levée** — **après confirmation de
  l'opérateur** — et **le joueur sanctionné revient en jeu**. La pénalité d'un carton
  **rouge** (`R`) **n'est jamais levée par un but** : ses **2 minutes vont à leur terme quoi
  qu'il arrive** (même si un ou plusieurs buts sont encaissés) ; **à l'issue seulement**,
  l'équipe peut faire entrer un **remplaçant** (le joueur sanctionné ne revient jamais). Le
  carton **noir** (`D`) n'a pas d'horloge du tout (voir ci-dessus). La colonne `card_code` de
  l'horloge de pénalité (§0.5) porte donc bien un **comportement différencié** : levable sur
  but (`V`/`J`) ou non (`R`).
- Si une équipe a **deux exclusions en cours**, c'est la **plus ancienne des pénalités
  levables (`V`/`J`)** qui est levée par un but encaissé ; une pénalité `R` plus ancienne
  n'est pas levée (elle court jusqu'au bout).
- **Motif de carton** à définir à la saisie, **pré-sélectionné à « Autre/Non précisé »**
  (`unknown`) pour permettre une validation immédiate sans étape supplémentaire (décision §0.9 ;
  `ScoringConfig.defaultCardReason`). Motifs existants (réutilisés de FMV3, clés i18n) : `r_pad`
  (Pagaie), `r_kt` (Éperonnage), `r_ht` (Poussée / Accrochage), `r_p` (Possession), `r_o`
  (Obstruction), `r_un` (antijeu/non sportif), `r_rep` (Remplacement), `unknown`
  (autre/non précisé — **défaut**).
- **Règle de progression des cartons (alerte — précisée §0.9)** : ordre
  **vert → jaune → rouge**. Un joueur **ne peut pas** recevoir un 2ᵉ ou 3ᵉ carton **identique ou
  inférieur** au précédent (après un jaune : seulement rouge ; après un rouge : plus aucun carton
  de progression). En revanche, **rien n'impose de commencer par un vert** : un jaune — ou un
  rouge — peut être le **premier** carton selon la gravité. Le **carton d'exclusion définitive**
  (`D`, noir) est applicable **à tout moment**, quelle que soit la progression. **Déclencher une
  alerte** si l'opérateur tente de saisir un carton qui ne respecte pas cette règle.

### 7.5 Périodes, mi-temps et prolongations

> **Nommage des états de période (important — clarifié à la lecture du legacy).** Les codes du
> store (`Periode` en base, boutons FMV3) sont : **`M1` / `M2` = les deux mi-temps** du temps
> réglementaire ; **`P1` / `P2` = les prolongations** (overtime), affichées seulement pour un
> match de type E ; **`TB` = tirs au but**. Ne pas confondre `P1`/`P2` (prolongations) avec les
> mi-temps.

- **Match de classement (type C)** : **2 mi-temps de 10 minutes** (`M1`/`M2`), durée
  **paramétrable** (`periodDurations`, défaut 600 s). Égalité possible (pas de prolongation).
- **Pauses inter-périodes (nouveauté, généralisée — décision 2026-07-23, cf. §0.8)** : à la fin
  du temps d'une période, **signal sonore** (buzzer), puis déclenchement automatique d'un
  **chrono de pause indicatif** (repère pour l'arbitre pour reprendre, ne bloque rien) :
  **3 min** entre `M1` et `M2` (mi-temps), **3 min** entre `M2` et `P1` (avant prolongations),
  **1 min** entre chaque prolongation (`P1`→`P2`, `P2`→`P3`, …). Le **buzzer sonne aussi en fin
  de pause** (signal de reprise — décision §0.9). Durées dans `ScoringConfig.breakDurations`
  (cf. §6.2), à terme paramétrables par compétition. **Pas de temps mort d'équipe** en
  kayak-polo : rien d'autre à modéliser de ce côté.
- **Match éliminatoire (type E)** : en cas d'**égalité à la fin du temps réglementaire**,
  enchaîner **autant de prolongations que nécessaire** jusqu'au **premier but marqué (but en or)**
  → fin immédiate (règlements FFCK **et** ICF). **Durée des prolongations : 5 minutes dans les
  deux règlements (ICF et FFCK — correction §0.9**, l'ancienne mention « FFCK = 3 min » et
  l'override legacy `duree_prolongations` sont caducs**)**. Défaut `P{n}` = 300 s, ajustable
  manuellement ; cible = réglage porté par la compétition (cf. §6.4).

  > **Écart vs legacy à corriger (important).** FMV3 ne propose que **deux** boutons de
  > prolongation figés (`P1`, `P2`) → **maximum 2 prolongations**, ce qui **viole le règlement**
  > (but en or = autant de prolongations que nécessaire). Le Scoring doit modéliser les
  > prolongations comme une **série non bornée** : au lieu de deux codes figés, gérer un
  > **numéro de prolongation incrémental** (`OT1`, `OT2`, `OT3`, …) ou un compteur
  > `overtimeIndex` associé au code générique de prolongation. Le sélecteur de période propose
  > « + prolongation suivante » plutôt que deux boutons. Tant que le score reste à égalité, on
  > peut ajouter une prolongation ; le premier but la **clôt immédiatement** (but en or).
  > L'affichage et l'historique doivent distinguer les prolongations successives.

- **Tirs au but (`TB`)** : **hors règlements FFCK/ICF** (qui imposent le but en or). Le `TB` ne
  concerne que des **règlements de tournoi / compétitions locales**. À ce titre, il **n'est pas
  proposé par défaut** : son activation (et son format) sera un **paramètre de la compétition**
  (à implémenter **plus tard**). Le store conserve l'état `TB` pour compatibilité, mais l'UI ne
  l'expose que si la compétition l'autorise.
- États de période à gérer dans le store : `M1`/`M2` (mi-temps), **prolongations non bornées**
  (série **`P{n}`** — `P1`, `P2`, `P3`… **sans plafond**, cf. §0.6 qui tranche ce codage), `TB`
  (tirs au but, **optionnel par compétition**) ; les **pauses inter-périodes** sont des états
  dérivés (countdown indicatif entre deux périodes, porté par l'horloge `BREAK` de
  `scoring_live_clock`, cf. §0.5), pas des codes de période persistés.

> **Codage tranché (cf. §0.6).** L'ancien « à trancher (`P{n}` ou `OT{n}`) » est **décidé** : c'est
> **`P{n}`**, porté par `scoring_live_state.periode` (varchar, pas d'enum figé) — donc **pas de
> plafond** `P1`/`P2`, chaque prolongation identifiée. Le champ legacy `kp_match.Periode` n'est
> écrit qu'à la **consolidation fin de match** (cf. §0.2) et accepte déjà `P{n}` (varchar).

### 7.6 Clôture du match

À la fin du match, la table de marque :

1. indique l'**heure de fin** (`Heure_fin`) — **pré-remplie à l'heure réelle au moment de la
   clôture** (décision §0.9), modifiable si besoin,
2. saisit les **commentaires** éventuels (capitaines et arbitre — `Commentaires_officiels`),
3. **valide le score** (passe `Statut` → `END`), puis **verrouille** si le profil l'autorise
   (`Validation = 'O'`, via `PATCH /admin/games/{id}/validation`).

Le **score provisoire** (calculé = nombre de buts par équipe) et le **score officiel** doivent
être distingués dans l'UI (cf. captures FMV3 : « Score officiel » vs « Score provisoire (calculé
nb de buts) ») ; un écart entre les deux est signalé (le PDF affiche `Provisoire` vs `Final`).

### 7.7 Parité avec le PDF de contrôle (`FeuilleMatchMulti.php`)

Checklist des informations du PDF qui doivent être **présentes et éditables/consultables** dans
la console (source : §lecture de `FeuilleMatchMulti.php`) :

| Bloc PDF | Source | Présence console |
|---|---|---|
| Entête : compétition, organisateur, saison, R1/délégué, lieu/dpt, date/heure, terrain, phase, n° match, intitulé | `kp_match` + `kp_journee` + `GetCompetition` | Vue Paramètres (entête + onglet Officiels) |
| Arbitres 1/2, secrétaire, chrono, time-shoot, lignes | `kp_match` (`Arbitre_*`, `Secretaire`, `Chronometre`, `Timeshoot`, `Ligne1/2`) | Onglet Officiels |
| Joueurs A/B : n°, nom, prénom, licence, catégorie, capitaine/entraîneur | `kp_match_joueur` + `kp_licence` | Onglets Équipe A/B |
| Couleurs équipes (ColorA/B + color1/2) | `kp_competition_equipe` | Affichage scoreboard (Phase 2) |
| Détail des faits de jeu : période, temps, n° joueur, motif, but / V / J / R / D, par équipe | `kp_match_detail` | Vue Déroulement (historique) |
| Score mi-temps A/B, score final A/B, type (Provisoire/Final) | dérivé des buts `kp_match_detail` + `ScoreA/B` | Score live + vue Paramètres |
| Commentaires officiels | `kp_match.Commentaires_officiels` | Clôture (7.6) |
| Heure début / heure fin | `kp_match.Heure_fin` | Clôture (7.6) |
| Type match (C/E), « vainqueur obligatoire » / « pas de prolongation » | `kp_match.Type` | Vue Paramètres (type de match) |

> Note : les **signatures** et le **QR code** du PDF restent propres au document papier ; la
> console n'a pas à les reproduire (le PDF demeure le document de contrôle, cf. §1).

### 7.8 Autres fonctions de la FMV3 à reprendre (parité legacy)

Fonctions présentes dans `FeuilleMarque3.php` + `v2/fm3_*.js` non couvertes ailleurs, à porter
(la phase indiquée est la cible) :

| Fonction | Source legacy | Phase | Note |
|---|---|---|---|
| **Publication privé / public** | `fm3_B.js` (`#prive`/`#public` → `StatutPeriode` type=`Publication`) | P1 (affichage) | **Lecture seule** dans le Scoring (cf. §7.1) : on **affiche** l'état (privé/public) mais on **ne le modifie pas** ici. La modification reste dans games/index.vue (`togglePublication`). |
| **Type de match (classement / élimination)** | `fm3_C.js` (`#typeMatch*` → `StatutPeriode` type=`Type`) | P1 (affichage) | **Lecture seule** dans le Scoring (cf. §7.1). Affiché pour contrôle ; modifié ailleurs. |
| **Charger un autre match par ID# ou n° court** | `fm3_D.js` (`getShortGame.php`, n° ≤ 5 chiffres ↔ ID 8-9 chiffres) | P1 | Champ « Game ID# or number » dans la vue Paramètres. |
| **« Match suivant… »** | `fm3_D.js` (`getNextGame.php`) | P2 | Pré-charge le match suivant (n°, terrain, date, équipes) pour enchaîner. |
| **Édition inline officiels / arbitres** (autocomplete) | `fm3_C.js` (jEditable `saveOfficiel`/`saveArbitres`) | P1 | « Cliquer pour modifier » sur chaque officiel ; autocomplete licence/arbitre. |
| **Édition n° maillot / statut joueur** (capitaine / coach) | `fm3_C.js` (`saveNo`/`saveStatut`) | P1 | Déjà listé §7.2 ; mappe sur `playerStatus`. |
| **Suppression joueur / recharger présents** | `fm3_C.js` (`delJoueur`/`initPresents`) | P1 | « Recharger les joueurs présents » = re-init depuis la feuille de présence. |
| **Logos / drapeaux de nation A/B** | `FeuilleMarque3.php` (`paysA`/`paysB` dérivés des 3 premiers car. du `Code_club`, fallback `FRA` si numérique) | P1 (entête) / P2 (scoreboard) | **Décision §0.9 : charger d'abord le logo de l'équipe** ; la dérivation du drapeau depuis le `Code_club` n'est qu'un **fallback** en l'absence de logo. |
| **Couleurs équipes (maillots)** | `kp_competition_equipe` (`color1/2`, `colortext`) | P2 | Pour le scoreboard et les pastilles A/B. |
| **Buzzer / test son** | `FeuilleMarque3.php` (`buzzer`, audio) | P2 | Avec le shotclock, la fin de période **et la fin de pause inter-périodes** (§0.9). |
| **Stats du match** (= « tirs / arrêts » : events `T`/`A`, compteurs `nb_tirs`/`nb_arrets`, `FeuilleMarque2stats.php`) | `fm3_D.js` + markup **commenté** de `FeuilleMarque3.php` | — | **Une seule et même fonctionnalité** (décision §0.9) : **hors périmètre pour l'instant**. Le modèle `scoring_live_event.kind` reste prêt à l'accueillir (`SHOT`/`SAVE`, cf. §0.4). |

## 8. Plan par phases

- **Phase 0 — Échafaudage** : dep `easytimer.js` ; `types/scoring.ts` ; coquilles
  `scoringStore.ts` + `useScoringPermissions.ts` ; clés i18n `scoring.*`.
- **Phase 1 — MVP online (livrable principal)** : page `scoring.vue` (chargement match+joueurs),
  store branché sur api2 (score/événements/chrono/statut/joueurs), permissions client+serveur,
  **renommage `WsmController` → `ScoringController` + sécurisation `/scoring`** (firewall JWT +
  `ROLE_SCORER` + scope mandat), validation/verrouillage via l'endpoint existant, **ajout du
  lien « Scoring »** dans games/index.vue (vue tableau + vue carte) **en conservant V2/V3**.
  Inclut aussi : **mode direct / post-match** (masquage chrono hors-live, cf. §1.1) ;
  **édition complète d'un fait de jeu** existant (`updateEvent`, cf. §7.3) ; **ajustement fin du
  chrono** ±1/±10 s (cf. §6.4) ; **édition inline officiels / n° / statut joueur**,
  **suppression / recharge des présents**, **publication privé/public**, **charge d'un autre
  match par ID#/n° court** (cf. §7.8) ; **journalisation `kp_journal`** de toutes les actions
  mutantes via `AdminLoggableTrait` (cf. §6.2 « Journalisation ») ; **ergonomie responsive
  (affichage à la demande : joueurs/historique en collapse/offcanvas) + saisie optimisée
  clavier-first** (cf. §7.1) ; **sélecteurs statut/période en badge cyclique** (calqués sur
  `competitions/index.vue`, confirmation + durée non standard à part, cf. §7.1).
- **Phase 2 — Diffusion locale** : `useBroadcast` + `useTimer`, `scoreboard.vue` +
  `shotclock.vue`, **UI pénalités**, **shotclock nouveau modèle 3 commandes** (départ/reset 60 s,
  départ/reset 40 s actif d'emblée, arrêt `--`, suivi auto du chrono — cf. §6.5), **pauses
  inter-périodes** (cf. §7.5), **raccourcis clavier paramétrables** (défauts Espace/Entrée/0 —
  cf. §6.5),
  **buzzer / test son**, **arrêt chrono sur but** (option), couleurs/drapeaux équipes,
  « Match suivant… » (cf. §7.8), **coquille PWA installable** (manifest + service worker + cache
  de l'app shell, saisie toujours online-first — cf. §0.8).
- **Phase 3 — Diffusion Mercure + Hardware Scoring** (réaligné, cf. §0.3/§0.4) : chaque écriture
  d'état dépose dans **`scoring_outbox`** → le **worker api2 existant** (`app:event-cache-worker`,
  étendu) **publie sur Mercure** ; la page d'incrustation unique lit `GET /state` puis suit Mercure ;
  `useHardwareScoring` branche le **matériel comme source de plus** sur `scoring_live_*`.
  ~~génération des JSON `live/cache/*` (parité `CacheMatch`) + broker WebSocket~~ = **abandonné**
  (le cache fichier et le broker personnel sont supprimés par le plan de refonte).
- **Phase 4 — Offline complet (reporté)** : file d'attente d'écritures IndexedDB derrière le
  store + resynchronisation à la reconnexion. La **coquille PWA** (installable) est déjà livrée
  en Phase 2 (cf. §0.8) ; cette phase n'ajoute que la couche de synchro. Uniquement après un
  online-first solide.

## 9. Fichiers critiques

| Fichier | Action |
|---|---|
| `sources/app4/pages/games/[id]/scoring.vue` | **Créer** — console Scoring |
| `sources/app4/stores/scoringStore.ts` | **Créer** — port app3 → api2 + useApi |
| `sources/api2/src/Controller/WsmController.php` | **Renommer** en `ScoringController.php` + rôle/mandat (P1) ; cache TODO (P3) |
| `sources/api2/config/packages/security.yaml` | **Modifier** — firewall + access_control `^/scoring` (`ROLE_SCORER`) |
| `sources/app4/composables/useScoringPermissions.ts` | **Créer** — miroir usePresencePermissions |
| `sources/app4/pages/games/index.vue` | **Modifier** — **ajouter** un lien « Scoring » (vue tableau ~2095-2104 + vue carte ~2818-2824) ; **conserver V2/V3** le temps de la validation |

Secondaires à créer : **`components/scoring/*`** (UI découpée et réutilisable, cf. §6.7),
`composables/useTimer.ts`, `useBroadcast.ts`, `useWebSocket.ts`, `useHardwareScoring.ts` (P3),
`pages/games/[id]/scoreboard.vue`, `…/shotclock.vue`, `types/scoring.ts`.
Références à porter : `sources/admin/v2/fm3_C.js` (chrono/shotclock/pénalités, ~1384 lignes,
**plus gros effort**), `sources/admin/v2/scoreboard.js`.

## 10. Risques / inconnues

> Mis à jour 2026-07-27 avec les décisions actées (§0.8/§0.9) : les anciens risques 2 (JSON), 3
> (broker), 5 (prolongations) et 8 (activation broker) sont **clos**.

1. **Logique chrono/shotclock/pénalités de `fm3_C.js`** — plus gros effort de portage
   (type de match C vs E, expiration des pénalités → messages `penA/penB`).
2. ~~Génération des JSON de diffusion api2~~ — **clos (décision §0.3)** : la console ne génère
   **aucun** fichier JSON ; les 3 TODO du contrôleur insèrent dans `scoring_outbox` → Mercure.
   Le cache fichier reste alimenté par le worker legacy pour les seules incrustations PHP,
   jusqu'à leur remplacement ([PAGE_INCRUSTATION.md](PAGE_INCRUSTATION.md), plan lot 4).
3. **Hardware Scoring** — ~~broker~~ **clos côté transport (décisions §4.6/§4.7 du plan)** :
   Mercure diffuse, le relais Stomp (serveur ou boîtier) capte. Reste le **protocole
   propriétaire à formaliser** (fichiers de référence, plan lot 0.5/1.8) — risque contenu par la
   méthode d'enregistrement de sessions réelles.
4. **cn** — hors périmètre MVP ; chantier de suivi séparé sur toute app4.
5. ~~Prolongations non bornées~~ — **clos (décisions §0.6/§0.9)** : codage `P{n}` non borné porté
   par `scoring_live_state.periode` (varchar), `kp_match.Periode` n'est écrit qu'à la
   consolidation et accepte déjà `P{n}` ; durée unique 5 min (ICF = FFCK). Reste l'implémentation
   front (type `P${number}`, sélecteur « prolongation suivante », clôture au but en or).
6. **Tirs au but optionnels par compétition** — nécessite un **paramètre de compétition**
   (activation + format) non encore disponible ; reporté (plan lot 6).
7. **Contrôle fin des droits** — point dédié à venir (cf. §6.3) ; règle déjà fixée :
   `canLock = profile ≤ 6`. S'élargira avec le lot 9 (profil 7 : compo + réclamation).
8. ~~Activation du broker par événement~~ — **clos (décision §0.3/plan §3.3)** : plus de broker ni
   de JSON réseau par événement ; l'abonnement ciblé Mercure (topics événement/terrain/bloc) rend
   le mécanisme sans objet.
9. **Shotclock nouveau modèle 3 commandes** (cf. §6.5, décisions §0.9) — écart de modèle vs
   legacy : départ/reset 60 s et 40 s (le départ **est** un reset, indépendant du chrono
   principal), arrêt = retour à `--` (pas de pause manuelle), suspension automatique par l'arrêt
   du chrono. Impacte `useTimer`, le store (deux durées + trois états), l'UI et les raccourcis
   paramétrables. À cadrer en Phase 2 ; durées à terme paramétrables par compétition.
10. **Reprise / persistance partielle** — **résolu par la cible** (`scoring_live_clock` persiste
    chrono + shotclock + pénalités + pause, plan lot 1) ; reste vrai **tant que le re-routage
    n'est pas fait** (aujourd'hui seul le chrono principal est restauré depuis `kp_chrono`,
    shotclock/pénalités re-saisis après reprise).
11. **Transition des consommateurs `kp_*` en cours de match** (nouveau, cf. §0.2) —
    `FeuilleMatchMulti.php` et app2 affichent le déroulement depuis `kp_*` y compris pendant le
    match ; sans leur évolution (plan lot 4), la bascule vers `scoring_live_*` dégrade leur
    affichage « en cours ».

## 11. Vérification de bout en bout

- **Phase 1** : lancer app4 + api2, se connecter avec un **profil ≤ 2**, vérifier que le lien
  « Scoring » apparaît (à côté de V2/V3, eux toujours présents), ouvrir `/games/{id}/scoring`,
  saisir buts/cartons, lancer/arrêter le chrono, changer de période, **corriger un match
  post-déroulement puis le valider/verrouiller** ; vérifier en base (`kp_match`,
  `kp_match_detail`, `kp_chrono`) via phpMyAdmin ; recharger → horloge restaurée ; **reprise
  multi-terminal** : chrono lancé sur un onglet, **ouvrir le même match dans un 2ᵉ onglet/navigateur**
  → l'horloge **repart synchronisée** (à quelques dixièmes près) sans arrêter le jeu, le statut
  run/stop est respecté (cf. §6.4 « Reprise ») ; **vérifier la journalisation** : chaque action
  laisse une ligne dans `kp_journal` ; match verrouillé → lecture seule ; **profil > 2 → lien
  masqué + accès refusé (UI + 403)** ; appel non authentifié à `PUT /api2/scoring/gameParam/{id}`
  → 401 ; vérifier que `app_wsm` legacy (`/api/wsm/`) fonctionne toujours.
- **Phase 2** : ouvrir scoreboard + shotclock en 2ᵉ fenêtre → synchro live (BroadcastChannel) ;
  vérifier le shotclock 3 commandes (60/40/arrêt), les pauses inter-périodes et la mise à jour
  immédiate de la PWA (nouvelle version → rechargement).
- **Phase 3 (réaligné §0.3)** : saisir à la console → les messages apparaissent sur les topics
  Mercure `/scoring/event/{e}/pitch/{p}/…` (banc app4 Operations → Mercure), rejeu après coupure
  (`Last-Event-ID`) ; brancher un panneau propriétaire via le relais Stomp → l'état
  `scoring_live_*` se met à jour depuis le matériel et la console (mode supervision) le reflète.

## 12. Suivi des développements

> Cette section est enrichie au fil des développements avec le détail des décisions et de
> l'implémentation effective.

### Phase 0 — Échafaudage ✅ (terminée)

Artefacts créés/modifiés dans `sources/app4` :

| Fichier | Détail |
|---|---|
| `types/scoring.ts` | **Créé.** `Period`, `MatchStatus`, `MatchType`, `TeamSide`, `ScoringEventCode`, `ScoringMatch` (calé sur la réponse réelle de `AdminGamesController::get` — champs camelCase : id, validation, statut, type, periode, scoreA/B, scoreDetailA/B, idEquipeA/B, equipeA/B…), `ScoringPlayer`, `ScoringEvent`, `Penalty`, `PeriodDurations`. |
| `stores/scoringStore.ts` | **Créé** (coquille). Store options-API `defineStore('scoring', …)`. State (match, playersA/B, events, penalties, periodDurations, loading). Getters `hasMatch`, `isLocked` (`validation === 'O'`), `currentPeriodDuration`. Durées par défaut M1/M2=600s, P1/P2/TB=180s. **Actions de chargement/mutation → Phase 1.** |
| `composables/useScoringPermissions.ts` | **Créé.** Signature `(isLocked)`. **Accès gaté profil ≤ 2** via constante `SCORING_ACCESS_MAX_PROFILE = 2`. Retourne `canView`, `canScore`, `canManagePlayers`, `canValidate`, `canLock`. Cible post-validation documentée en commentaire (relever la constante + le contrôle serveur). |
| `i18n/locales/fr.json`, `en.json` | **Modifiés.** Namespace `scoring.*` ajouté (title, link, hardware, status ATT/ON/END, period M1/M2/P1/P2/TB, event goal/cards, timer, scoreboard, locked). |
| `package.json` + `package-lock.json` | **Modifiés.** `easytimer.js@^4.6.0` ajouté (même version qu'app3/fm3). |

**Note environnement** : le container `kpi_node_app4` avait un `node_modules` partiellement
détenu par `root` (~7175 entrées, install antérieure en root) + 80 artefacts temporaires
d'installs avortées, qui bloquaient `npm install` (EACCES puis ENOTEMPTY). Corrigé hors
périmètre feature : `chown -R node:node /app/node_modules` (via root) + suppression des
artefacts `.*-RANDOM`. À garder en tête si d'autres installs échouent.

### Phase 1 — MVP online (en cours)

> ⚠️ **À réaligner (cf. §0.2).** Tout le backend décrit ci-dessous écrit dans **`kp_*`**
> (`kp_match`/`kp_match_detail`/`kp_chrono`). La cible est **`scoring_live_*`** + consolidation en
> fin de match. Les endpoints, l'auth, le scoping mandat, la journalisation et le modèle d'horloge
> **sont conservés** ; seule la couche SQL du contrôleur est re-routée. Ce re-routage rejoint le
> périmètre Phase 1. L'UI (frontend ci-dessous) n'est **pas** impactée.

#### Conformité de l'existant à la feuille de route (audit)

Bilan de ce qui a été développé, confronté au plan de refonte et aux décisions §0. **Rien n'est à
jeter** : la structure est bonne, seule la couche de stockage et deux détails sont à réaligner.

| Brique développée | Conforme ? | Action |
|---|---|---|
| UI complète (`scoring.vue`, `components/scoring/*`, store, permissions, i18n, mode direct/post-match, historique symétrique, badges cycliques) | ✅ **Oui** | Indépendante du stockage (parle à des endpoints). **Aucune reprise.** |
| `ScoringController` = porte d'entrée unique (auth JWT `^/admin`, scope mandat, journal `kp_journal`) | ✅ **Oui** | C'est le lot 1 du plan. **Conservé tel quel.** |
| Modèle d'horloge (`max_time`/`start_time`/`start_time_server` + restauration serveur) | ✅ **Oui**, partiel | Le modèle est le bon (plan §3.1). **Le transposer** de `kp_chrono` vers `scoring_live_clock` et **l'étendre** au shotclock + pénalités (≤ 2 par équipe, §0.5). |
| Écritures `gameParam`→`kp_match`, `gameEvent`→`kp_match_detail`, `gameTimer`→`kp_chrono` | ❌ **Non** | **Re-router** vers `scoring_live_*` + consolidation fin de match (§0.2). Endpoints/payloads inchangés → UI intacte. |
| Type `Period = 'M1'\|'M2'\|'P1'\|'P2'\|'TB'` (front) + `Periode` figé | ❌ **Non** | Plafonne à P2. **Remplacer** par `P{number}` non borné (§0.6). |
| Les 3 `// TODO (Phase 3): generate broadcast cache here` | ⚠️ **À réorienter** | Deviennent « insérer dans `scoring_outbox` » (§0.3), **pas** générer des fichiers JSON. |
| `gameEvent` limité à but/carton (`B/V/J/R/D`) | ✅ **Conforme, extensible** | Le fait de match extensible (`kind` : tir/passe/arrêt…) est une **évolution**, pas un manque (§0.4). |

> **Conclusion.** Le travail déjà fait **reste conforme à la feuille de route** au niveau structurel
> (porte d'entrée unique, UI, auth, journal, modèle d'horloge). Le réalignement est un **re-routage
> SQL du contrôleur** (kp_* → scoring_live_*) + l'**extension du modèle d'horloge** (shotclock,
> pénalités) + le **type Period non borné** + la **réorientation des 3 TODO** vers l'outbox. Aucune
> de ces actions ne remet en cause l'UI ni les endpoints. **Ce qui a été validé (chrono, événements,
> journal, scope mandat) n'est pas à re-tester dans sa logique, seulement dans sa nouvelle cible de
> stockage.**

**Backend api2 :**

| Fichier | Détail |
|---|---|
| `src/Controller/ScoringController.php` | **Créé** (repris de `WsmController`). Routes sous **`/admin/scoring/...`** (gameParam, gameEvent, playerStatus, gameTimer, stats) → automatiquement derrière le firewall JWT `^/admin`. Classe annotée **`#[IsGranted('ROLE_ADMIN')]`** = profil ≤ 2 (mapping `User::getRoles()` : niveau ≤ 2 → `ROLE_ADMIN`). Conserve le verrou `Validation != 'O'`. |
| `src/Controller/WsmController.php` | **Supprimé.** N'était consommé par personne (vérifié : app_wsm/legacy utilisent `/api/wsm/`, backend distinct) et exposait `/wsm` en **public** (firewall `main`). Suppression = élimination de la surface non authentifiée. |

> **Décision** : routes sous `/admin/scoring` plutôt que `/scoring` (spec initiale) — réutilise
> le firewall JWT existant sans en créer un nouveau, cohérent avec `useApi` qui parle déjà à
> `/admin/*`. Le contrôle fin de rôle reste dans le contrôleur (`ROLE_ADMIN` = profil ≤ 2,
> à élargir en `ROLE_SCORER` à l'ouverture).

**Vérifié** : `PUT /api2/admin/scoring/gameParam/1` sans token → **401** ; ancien
`PUT /api2/wsm/gameParam/1` → **404**.

**Frontend app4 :**

| Fichier | Détail |
|---|---|
| `stores/scoringStore.ts` | **Complété.** Actions : `load` (GET `/admin/games/{id}` + 2× `/admin/matches/{id}/players?teamCode=A\|B`), `setParam`/`setStatus`/`setPeriod` (PUT gameParam, optimiste + rollback), `addEvent`/`removeEvent` (PUT gameEvent + maj score pour les buts), `setTimer` (PUT gameTimer), `toggleValidation` (PATCH `/admin/games/{id}/validation`). Getters `scoreA`/`scoreB`. |
| `pages/games/[id]/scoring.vue` | **Créée.** Console : header match, score, sélecteurs statut/période, chrono (run/stop/RAZ → api2), listes joueurs A/B (sélection), boutons de faits de jeu (but/cartons), liste des événements, verrouillage. Gatée `useScoringPermissions` (≤ 2). |
| `pages/games/index.vue` | **Modifiée.** Ajout `canScoring` (profil ≤ 2) + helper `openScoring` + **bouton « Scoring »** dans la vue tableau (à côté de V2/V3) et la vue carte. **V2/V3 conservés inchangés.** |
| `i18n/locales/fr.json`, `en.json` | Clés `scoring.*` complétées (field, history, not_found, select_player_first, no_access). |

**Vérifié** : route `/games/{id}/scoring` compile (HTTP 302 → middleware auth, comme les
autres routes protégées) ; ESLint OK sur tous les fichiers Scoring ; aucune erreur dans les
logs du dev server.

**Chrono temps réel ✅ (terminé) :**

| Fichier | Détail |
|---|---|
| `composables/useTimer.ts` | **Créé.** Wrapper Vue réactif autour d'easytimer.js. Countdown du temps de jeu, précision `secondTenths`. Expose `display` (MM:SS.d), `gameTime` (MM:SS, pour horodater les événements), `elapsed`, `isRunning`, et `setPeriod` / `start` / `stop` / `reset` / `restoreFromServer`. Buzzer/stop auto sur `targetAchieved`. |
| `src/Controller/ScoringController.php` | **Ajout** `GET /admin/scoring/gameTimer/{matchId}` : renvoie l'état persisté de `kp_chrono` (action, start_time, start_time_server, run_time, max_time) + `nowServer` (heure serveur en s % 86400) pour le calcul de restauration. |
| `stores/scoringStore.ts` | **Ajout** `loadTimerState()`. `setTimer` persiste `startTime = elapsed` (secondes écoulées dans la période) + `maxTime`. |
| `pages/games/[id]/scoring.vue` | Affichage live du chrono (vert si running) ; `onMounted` appelle `loadTimerState()` → `restoreFromServer()` si un état existe, sinon `setPeriod()` ; les événements sont horodatés via `gameTime` ; changement de période reconfigure le countdown. |

**Modèle de chrono retenu** (plus simple que l'encodage fm3) : `max_time` = durée période,
`start_time` = secondes écoulées au dernier run/stop, `start_time_server` = heure serveur du
dernier run. Restauration : si `action='run'`, `realElapsed = elapsed + (nowServer − startTimeServer)`
(gère le passage de minuit).

**Vérifié** (test DB de bout en bout, match #127) : état `run` inséré (elapsed=120s, démarré
il y a 30s, période 600s) → lecture `kp_chrono` correcte → calcul de restauration
`realElapsed = 150s`, **remaining = 450s → 07:30**. État de test nettoyé. Bug corrigé au
passage : mauvais namespace `IsGranted` (`Bundle\SecurityBundle` → `Component\Security\Http`,
signalé par l'IDE).

**Scoping par mandat ✅ (terminé) :**

`ScoringController` — méthode privée `assertMatchAuthorized(int $matchId)` (miroir de
`AdminGamesController::assertJourneeAuthorized`) : résout la journée du match et vérifie
`User::getAllowedJournees()` (null = accès total ; sinon la journée doit être dans la liste du
mandat actif, déjà résolu depuis `X-Active-Mandate` par la couche auth). Appelée en tête de
**tous** les endpoints : gameParam, gameEvent, playerStatus, gameTimer (GET+PUT), stats
(via `$data->game`). Retourne 404 si match inconnu, 403 si hors périmètre.

**Vérifié** : `PUT/GET /admin/scoring/...` sans token → **401** (auth JWT avant scoping). Le
scoping 403 ne s'évalue que pour un utilisateur authentifié hors de son périmètre.

**Journalisation `kp_journal` ✅ (terminé) :**

`ScoringController` `use AdminLoggableTrait`. Le contrôleur expose désormais `$this->connection`
(DBAL, requis par le trait, initialisé depuis `EntityManager` dans le constructeur) et toutes les
écritures passent par `$this->connection` (plus de `$this->entityManager->getConnection()` épars).
`assertMatchAuthorized()` résout et **met en cache** le contexte du match
(`Id_journee`/`Code_saison`/`Code_competition`, via un JOIN `kp_journee`), réutilisé par l'aide
privée `logScoring(action, matchId, details)` → `logActionForMatch(...)`. Actions tracées :
« Scoring statut/période/score » (`gameParam`), « Scoring événement » add/update/remove
(`gameEvent`), « Scoring chrono » run/stop/RAZ (`gameTimer`), « Scoring joueur » (`playerStatus`).
Échec de log silencieux (comportement du trait). La validation/verrouillage reste tracée par
`AdminGamesController`.

**Chargement + édition complète des événements ✅ (terminé) :**

| Fichier | Détail |
|---|---|
| `src/Controller/ScoringController.php` | **Ajout** `GET /admin/scoring/events/{matchId}` : liste les événements de `kp_match_detail` enrichis nom/prénom (`LEFT JOIN kp_licence`), triés `Periode DESC, Temps ASC, date_insert ASC` (calqué sur `ReportController`). **Ajout action `update`** dans `gameEvent` (édition en place par `uid`, requiert `uid`). **`remove` accepte un `uid`** (suppression précise) avec fallback sur l'ancien delete par champs. |
| `stores/scoringStore.ts` | `load()` charge aussi les événements (3ᵉ appel parallèle). `addEvent` génère un `uid` client (helper `genUid`) pour aligner état optimiste / ligne serveur / éditions futures. **Nouveau `updateEvent(uid, patch)`** (optimiste + rollback) qui **recalcule scoreA/scoreB** depuis les buts (`recomputeScoresFromEvents`, gère un but déplacé entre équipes) et persiste les deux scores. `removeEvent` cible par `uid` quand connu. Types : `ScoringEvent` porte `nom?`/`prenom?` (enrichis au chargement). |
| `pages/games/[id]/scoring.vue` | Historique **éditable** (clic crayon → charge la ligne dans la zone de saisie ; corbeille → suppression). Zone de saisie commune **présente en direct ET post-match** : sélecteur **période**, **champ temps `MM:SS`** avec ajustements −60/−10/−1/+1/+10/+60, **sélecteur motif** (cartons). Les boutons d'événement font **add** (joueur sélectionné) ou **update** (ligne en édition). |

**Mode direct / post-match ✅ (terminé)** (cf. §1.1) : sélecteur « En direct / Post-match » dans
l'entête, **pré-positionné selon le statut** (`END` → post-match). En post-match, **l'horloge live**
(affichage + run/stop/RAZ + ajustements chrono) est **masquée** ; le **champ temps du fait de jeu**
reste éditable (tapé à la main). En direct, le champ temps **suit le chrono** (sauf pendant l'édition
d'une ligne existante).

**Ajustement fin du chrono ✅ (terminé)** (cf. §6.4) : boutons −10/−1/+1/+10 s sous l'horloge
(mode direct), re-priment le countdown via `useTimer.setPeriod(duration, elapsed)` et persistent
l'état dans `kp_chrono` (en conservant l'état run/stop courant).

**Motifs de cartons ✅ (terminé)** : sélecteur de motif dans la zone de saisie (clés i18n
`scoring.reason.*` : `r_pad`/`r_kt`/`r_ht`/`r_p`/`r_o`/`r_un`/`r_rep`/`unknown` + `none`),
enregistré dans `kp_match_detail.motif`, affiché inline entre parenthèses dans l'historique.

**Sélecteurs statut/période en badge cyclique ✅ (terminé)** (cf. §7.1) :

| Fichier | Détail |
|---|---|
| `components/scoring/StatusBadge.vue` | **Créé** (`<ScoringStatusBadge>`). Badge **unique** cyclique `ATT → ON → END → ATT` (clic / Entrée / Espace), couleurs calquées sur le badge statut de `competitions/index.vue`. Props `status`/`canCycle`, émet `change(next)` (props down / events up — la page mute le store). |
| `components/scoring/PeriodSelector.vue` | **Créé** (`<ScoringPeriodSelector>`). **Avance à la période suivante** selon le type (C : `M1→M2` ; E : `M1→M2→P1→P2→TB`) via un bouton « Période suivante », + **accès direct** (USelect escape hatch). Changer de période ouvre une **confirmation** (`AdminConfirmModal`, rappelle que le chrono est réinitialisé) ; émet `change(period)` seulement après confirmation. Props `period`/`type`/`canChange`. Les prolongations non bornées (but en or, §7.5) restent un lot ultérieur — codes `M1/M2/P1/P2/TB` conservés. |
| `pages/games/[id]/scoring.vue` | Remplace les deux rangées de boutons statut/période par `<ScoringStatusBadge>` + `<ScoringPeriodSelector>`. |

**Historique symétrique (visuel PDF) ✅ (terminé)** (cf. §7.1) :

| Fichier | Détail |
|---|---|
| `components/scoring/EventHistory.vue` | **Créé** (`<ScoringEventHistory>`). Deux rendus de la même liste : **table symétrique** sur `md+` (PC / grande tablette) reproduisant le PDF/FMV3 — **équipe A à gauche, équipe B à droite, période + temps au centre** (mirroir calqué sur `fm3_C.js` : A = `[token][#][nom](motif) | période temps | vide` ; B = `vide | période temps | (motif)[nom][#][token]`) ; **liste verticale compacte** sur mobile (`md:hidden`). Tri **période ↓ puis temps ↑**. Clic ligne = éditer ; corbeille au survol = supprimer. Tokens emoji (🥅/🟢/🟡/🔴/🟥) au lieu des PNG legacy. Props down (`events`, `teamAName/B`, `editingUid`, `canScore`) / events up (`edit`/`remove`). |
| `pages/games/[id]/scoring.vue` | Remplace le `<ul>` historique inline par `<ScoringEventHistory>`. |

> **Format du temps de fait de jeu (`kp_match_detail.Temps` = colonne `TIME`).** La console
> travaille en **`MM:SS`** (une mi-temps ≤ 10 min, pas d'heures). La colonne `Temps` étant un
> `TIME`, MySQL lirait « 01:28 » comme **1h28** : le legacy (`v2/evt_match.php`) **préfixe `00:`**
> avant insertion → stocke `00:MM:SS`. Le `ScoringController` reproduit ce comportement
> (`normalizeTemps()` à l'add **et** l'update) et **reformate en `MM:SS` au GET**
> (`TIME_FORMAT(Temps,'%i:%s')`). Le composant `EventHistory` a aussi un `fmtTime()` défensif
> (retire un segment heures `00:` résiduel). **Bug corrigé** : sans le préfixe, les buts/cartons
> saisis étaient stockés avec une heure erronée et l'historique affichait `M2 00:01:28`. |

**Vérifié** : ESLint OK (via container node:22 — le `kpi_node_app4` en Node 20 ne peut pas exécuter
le flat-config actuel, `Object.groupBy` absent < Node 21) ; `php -l ScoringController.php` OK.

**Re-routage `scoring_live_*` ✅ (plan lot 1, première tranche — 2026-07-27) :**

| Fichier | Détail |
|---|---|
| `SQL/migrations/2026-07-27_scoring_live_tables.sql` | **Créé.** `scoring_live_state` (score/période/statut/`heure_fin`/`active_source`/`promoted_at`/`tick`), `scoring_live_clock` (PK **UUID**, `kind` GAME/SHOTCLOCK/PENALTY/BREAK, unicité `(id_match,kind,team,slot)`), `scoring_live_event` (uid PK, `kind` extensible), `scoring_outbox`, + `kp_match.uid` additif (§4.13 du plan). **À exécuter en dev.** |
| `src/Service/ScoringLiveService.php` | **Créé.** Porte d'écriture unique de l'état live : chaque mutation = **une transaction** (écriture + `tick`+1 + dépôt `scoring_outbox` avec topic événement/terrain/bloc résolu via `kp_evenement_journee` + `Terrain`, fallback `/scoring/match/{id}/…`). Garde « source active » (§4.1). `ensureState()` **seed depuis `kp_*`** au premier contact (transition : match commencé en legacy conservé, `kp_match_detail` importé une fois dans `scoring_live_event`). `consolidateToKp()` au passage `END` : état → `kp_match`, faits → reconstruction `kp_match_detail` (uid partagés). |
| `src/Controller/ScoringController.php` | **Re-routé** (endpoints/payloads inchangés) : `gameParam` (Statut/Periode/ScoreDetail/Heure_fin → live ; **ScoreA/ScoreB officiels restent sur `kp_match`**, cf. §7.6), `gameEvent` → `scoring_live_event` (fallback lecture legacy si table vide), `gameTimer` GET/PUT → `scoring_live_clock` kind GAME (contrat s conservé, stockage ms ; PUT accepte déjà `kind`/`team`/`slot`/`playerId`/`cardCode` pour shotclock/pénalités/pause en Phase 2 ; fallback lecture `kp_chrono`). **Nouveaux** : `GET /admin/scoring/state/{id}` (état complet + **ETag = tick**, 304), `PUT /admin/scoring/source/{id}` (promotion §4.1). Toute écriture d'une source non active → **409** + journal « Scoring rejeté (source) ». Les 3 TODO Phase 3 sont réalisés : chaque écriture dépose dans l'outbox. |
| `stores/scoringStore.ts` | `load()` appelle aussi `GET /admin/scoring/state/{id}` et **superpose l'état live** (statut/période/scores) au snapshot `kp_match` de `/admin/games/{id}` — pendant un match, `kp_*` n'est plus écrit avant la consolidation. |
| `src/Scoring/ScoringRules.php` + `tests/Scoring/scoring_rules_test.php` | **Créés (lot 1.2, test-first).** Règles pures sans base ni réseau : périodes `P{n}` non bornées + but en or (§0.6/§7.5), durées et pauses inter-périodes (§4.10), **progression des cartons 2027** (§7.4 : jamais identique/inférieur, premier carton libre, noir à tout moment, plus rien après `R`/`D`), slots de pénalité ≤ 2/équipe + levée du plus ancien + `playerReturnsAfterPenalty` (§0.9), **machine à états shotclock 3 commandes** (start60/start40/stop + suspension auto par le chrono — il n'existe **pas** de commande pause). 62 assertions, runner autonome (`php tests/Scoring/scoring_rules_test.php`), exécuté par le job CI `lint-api2` ; à migrer vers PHPUnit quand api2 aura un test pack. À porter en TS (miroir) pour la console en Phase 2. |

Reste sur ce lot : fichiers de référence matériel (1.8, dépend de l'action 0.5), recharge
présents/n° court (1.5), et l'exécution de la migration + test de bout en bout en dev
(cf. [SCORING_DEV_CHECKLIST.md](../developer/in-progress/SCORING_DEV_CHECKLIST.md)).

**Prolongations non bornées + cartons 2027 côté console ✅ (plan lot 3, 1ʳᵉ tranche — 2026-07-27) :**

| Fichier | Détail |
|---|---|
| `types/scoring.ts` | `Period = 'M1' \| 'M2' \| `P${number}` \| 'TB'` (non borné, §0.6) ; `PeriodDurations` avec durée `P` **partagée** (300 s) ; nouveaux `BreakDurations`, `ShotclockDurations`, **`ScoringConfig`** (§6.2). |
| `utils/scoringRules.ts` | **Créé** — miroir TS de `api2/src/Scoring/ScoringRules.php` (référence testée 62 assertions) : périodes/but en or/durées/pauses, progression des cartons, pénalités, transitions shotclock 3 commandes. Sert déjà la console, servira la Phase 2. |
| `stores/scoringStore.ts` | `config: ScoringConfig` **centralisée** (remplace `periodDurations` épars) : P{n} = 300 s (ICF/FFCK §0.9), pauses 3'/3'/1', shotclock 60/40 **actif**, `defaultCardReason = 'unknown'`… `currentPeriodDuration` via `periodDurationOf`. |
| `components/scoring/PeriodSelector.vue` | Avance **non bornée** (type E, tant que le score est à égalité — `scoreLevel` en prop), TB seulement si `shootoutEnabled`, libellés `scoring.period.overtime {n}`, accès direct incluant P1..P{n+1}. |
| `pages/games/[id]/scoring.vue` | **Alerte progression des cartons** (modale, contournable — §7.4), **modale but en or** (propose Statut → END après un but en P{n} d'un match E), sélecteur de période de saisie dynamique (P{n} + TB hérité), **motif pré-sélectionné** `unknown` (les buts n'envoient pas de motif), carton `D` = **noir** (couleur neutre). |
| `components/scoring/EventHistory.vue` | Token `D` : 🟥 → **⬛** ; libellé `card_black`. |
| `i18n/locales/fr.json` / `en.json` | `card_red_def` → **`card_black`** (« Carton noir (exclusion définitive) » / « Black card (ejection) »), `period.overtime`, `card_progression.*`, `golden_goal.*`. |

Vérifié : ESLint OK sur tous les fichiers modifiés (node 22) ; les 62 assertions PHP des
règles restent la référence. Tests fonctionnels à dérouler :
[SCORING_DEV_CHECKLIST.md §lot 3](../developer/in-progress/SCORING_DEV_CHECKLIST.md).

**Shotclock 3 commandes + pauses + buzzer + raccourcis ✅ (plan lot 3, 2ᵉ tranche — 2026-07-27) :**

| Fichier | Détail |
|---|---|
| `composables/useShotclock.ts` | **Créé.** Modèle 3 commandes (§6.5) : `start(s)` (le départ EST un reset, 60 ou 40), `stopToIdle()` (retour `--`), `suspend()`/`resume()` (**seule** pause, pilotée par le chrono). Décompte par horodatage (sans dérive), gel à 0 + `onExpired` (buzzer), `restore()` depuis `scoring_live_clock`, `elapsedSeconds` pour la persistance. Transitions = miroir des règles testées (`shotclockTransition`). |
| `composables/useBuzzer.ts` | **Créé.** Web Audio (aucun asset, compatible PWA offline) : `beep()` + `test()`. Sonne en fin de période, à l'expiration du shotclock et en **fin de pause** (§0.9). |
| `composables/useScoringShortcuts.ts` | **Créé.** Raccourcis **paramétrables par poste** (localStorage `kpi.scoring.shortcuts`), défauts §0.9 : `Espace`/`Entrée`/`.`/`0`/`+`/`−`. Une touche = une action (réassignation vole la touche), neutralisés dans les champs éditables, désactivables (post-match, modale ouverte). |
| `components/scoring/Shotclock.vue` | **Créé** (`<ScoringShotclock>`). Affichage `--`/secondes (vert = décompte, ambre = suspendu), boutons 60 s / 40 s (si actif) / Arrêt / ±1 s (suspendu seulement) / test son. Masquage `shotClockShow` (temps restant < shotclock) via prop `masked`. |
| `components/scoring/ShortcutsModal.vue` | **Créé** (`<ScoringShortcutsModal>`). Réglage des touches (capture au clavier, Échap annule), remise aux défauts — pattern modal maison (admin/ConfirmModal). |
| `pages/games/[id]/scoring.vue` | Câblage : suivi auto chrono↔shotclock (watch `isRunning`), persistance kind `SHOTCLOCK` (run/stop/RAZ) + **restauration** depuis `store.liveClocks`, **pause inter-périodes automatique** en fin de période (durée via `breakDurationBefore`, persistance kind `BREAK`, restauration, bouton « Terminer la pause », clôturée au changement de période), buzzer, bouton réglages raccourcis (engrenage). |
| `stores/scoringStore.ts` / `types/scoring.ts` | `LiveClock` typé, `liveClocks` alimenté par l'overlay `GET /state`, `setTimer` accepte `kind`/`team`/`slot`/`playerId`/`cardCode`. |
| Locales fr/en | `scoring.shotclock.*`, `scoring.break.*`, `scoring.shortcuts.*`, `scoring.sound_test`. |

**Pénalités ✅ (plan lot 3, 3ᵉ tranche — règles §0.10) :** `composables/usePenalties.ts`
(≤ 2 horloges/équipe, suivi du chrono, expiration → buzzer + message « retour » ou
« remplacement » selon le carton, `liftCandidate` = plus ancienne **levable**),
`components/scoring/Penalties.vue` (décomptes A/B, retrait manuel), câblage dans
`scoring.vue` (création auto sur `V`/`J`/`R`, message dédié pour `D` **sans horloge**,
modale de levée sur but encaissé, marqueurs 🔴/⬛ dans les effectifs), persistance
`kind PENALTY` + restauration via `GET /state`.

**Affichages plein écran + PWA ✅ (plan lot 3, 4ᵉ tranche — 2026-07-29) :**

| Fichier | Détail |
|---|---|
| `composables/useScoringBroadcast.ts` | **Créé.** Émetteur (`useScoringBroadcast`) et récepteur (`useScoringDisplay`) sur le canal **`kpi_channel`** — contrat legacy conservé (`timer`, `timer_status`, `shotclock`, `period`, `teams`, `scores`, `penA`/`penB`) enrichi de `shotclock_state`, du **`matchId`** (deux matchs sur un même poste) et du **handshake `ready`** (un affichage qui s'ouvre réclame un instantané complet). **Same-origin, zéro réseau** (§6.5). |
| `pages/games/[id]/scoreboard.vue` | **Créée.** Tableau de score plein écran (`layout: false`) : équipes, score géant, période, chrono, chronomètre de tir, pénalités par équipe. Remplace `admin/scoreboard.php`. |
| `pages/games/[id]/shotclock.vue` | **Créée.** Chronomètre de tir plein écran (chiffre géant + rappel du chrono). Remplace `admin/shotclock.php`. |
| `pages/games/[id]/scoring.vue` | Boutons **TV** / **horloge** dans l'entête (mode direct) ; `watch` sur chrono/shotclock/score/période/pénalités → diffusion à chaque changement ; instantané complet au chargement. |
| `nuxt.config.ts` + `package.json` | **`@vite-pwa/nuxt`** ajouté : `registerType: 'autoUpdate'`, `skipWaiting` + `clientsClaim`, `cleanupOutdatedCaches`, manifest `standalone`/paysage scope `/admin2/`, `navigateFallbackDenylist` sur `/api2` et `/api` (le shell ne répond jamais à un appel API), **service worker désactivé en dev**. |
| `composables/usePwaUpdate.ts` | **Créé.** Boucle de fraîcheur : vérification au chargement, au retour d'onglet et toutes les 5 min ; **rechargement automatique** quand le nouveau worker prend la main (sans reload au premier enregistrement). Sûr car online-first : tout est déjà persisté serveur. **À réutiliser tel quel sur app2** (§0.9). |

Vérifié : `nuxt build` OK — `sw.js` + `manifest.webmanifest` générés (111 entrées de
precache, `skipWaiting`/`clientsClaim` présents) ; ESLint OK. Tests :
[SCORING_DEV_CHECKLIST.md §lot 3](../developer/in-progress/SCORING_DEV_CHECKLIST.md)
(2ᵉ tranche 3.11–3.21, 3ᵉ 3.22–3.31, 4ᵉ 3.32–3.39).

**Reste à faire en Phase 1** (avant de clore le MVP) :
- Test fonctionnel complet **authentifié** (profil ≤ 2) via l'UI : saisie réelle + vérification
  base + restauration visuelle du chrono au rechargement + vérif 403 hors mandat + **vérif des
  lignes `kp_journal`** (chaque action mutante).
- **Statut joueur** (capitaine/coach) éditable depuis la console (mappe `playerStatus`, endpoint
  déjà journalisé). Réglage durée de période non standard (dialog).
- **Édition inline officiels / n° maillot**, **suppression / recharge des présents**,
  **publication privé/public** (lecture seule), **charge d'un autre match par ID#/n° court** (cf. §7.8).
- **Alertes de progression des cartons** (même couleur 2×, seuils par joueur/équipe — cf. §7.4).
- **Durée de période non standard** : dialog d'ajustement (le badge période réinitialise à la
  durée standard via confirmation ; reste à exposer le réglage de durée à part, cf. §7.1/§6.4).
- Shotclock (time-shoot), buzzer, raccourcis clavier, « Match suivant… » + diffusion broadcast
  → relèvent de la **Phase 2**.
- **Génération des JSON `live/cache/{idMatch}_match_*.json`** (incrustations) → **Phase 3** avec le
  broker (cf. §6.2 « Génération des JSON », §6.5).
