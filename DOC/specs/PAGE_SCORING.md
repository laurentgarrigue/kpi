# Spécification — Scoring (console de match en direct) dans app4

> Statut : en cours — Phase 0 terminée, Phase 1 en cours (voir §12 Suivi)
> Cible : intégration dans **app4** (Nuxt 4, api2 Symfony)
> Remplace : `sources/admin/FeuilleMarque2.php`, `sources/admin/FeuilleMarque3.php`
>            (legacy jQuery) et le prototype standalone `sources/app3`
> Conserve : `sources/admin/FeuilleMatchMulti.php` (= **PDF de contrôle**, document papier)

## 1. Contexte et objectif

Le **Scoring** est l'outil de gestion du déroulé d'un match de kayak-polo : chronomètre,
shotclock, périodes (M1/M2/P1/P2/TB), saisie des événements (buts, cartons), pénalités,
diffusion vers écrans/incrustations, validation et verrouillage.

L'ancienne appellation « feuille de marque » n'a plus de sens : l'outil n'a quasiment plus
rien d'une feuille (la « feuille » papier est désormais un simple **PDF de contrôle**, cf.
`FeuilleMatchMulti.php`). Réglementairement, le match reste géré en priorité sur **feuille de
marque papier** + **panneau de score**, et **en parallèle ou après coup sur KPI**. L'objectif
KPI est de **tendre vers le zéro papier** :

- **saisie directe sur KPI** (Scoring) avec affichage scoreboard + shotclock ; ou
- **captation des live datas** depuis le matériel de scoring / panneau de score (matériel propriétaire ou
  équivalent) ; puis **diffusion** via WebSocket et **incrustations** (`/live`).

### 1.1 Deux usages

1. **En direct** (table de marque pendant le match) : chrono, shotclock, périodes, événements,
   pénalités, diffusion.
2. **En post-match** : **saisie ou correction** après le déroulement, puis **validation /
   verrouillage** selon le profil.

> **Mode direct vs post-match (décision UI).** En **post-match** (saisie ou simple vérification
> après le déroulement), **la gestion temps réel — horloge chrono live, shotclock, buzzer,
> diffusion scoreboard — est masquée** : elle n'a aucun sens hors-live. La console se réduit alors
> à l'entête match, les officiels, les joueurs A/B, la **saisie/édition des événements horodatés à
> la main**, les scores et la validation/verrouillage.
>
> **Ne pas confondre l'horloge et le champ « temps de l'événement ».** Masquer le chrono masque
> **l'horloge live** (run/stop/RAZ, affichage du temps courant), **mais pas** le champ qui porte
> le **temps de chaque événement** (période + `MM:SS`) : ce champ **reste éditable en post-match**
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
| **Hardware Scoring** | La **captation des live datas** depuis le **matériel** (panneau de score matériel propriétaire ou équivalent). **Qualificatif obligatoire** pour ne pas confondre avec la saisie manuelle. | `useHardwareScoring`, mode « Hardware Scoring » dans l'UI. |
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
| Mode | **Online-first** (api2 + WebSocket). Offline/PWA **non bloquant** → dernière phase. |
| Usages | Direct **et** post-match (saisie/correction + validation/verrouillage par profil). |
| Captation matériel | Mode **Hardware Scoring** (panneau matériel propriétaire ou équivalent via WSM/broker), distinct de la saisie manuelle. Branché en Phase 3. |
| Monétisation | À explorer plus tard. **Aucun Stripe/paywall maintenant.** Exigence unique : isolation **par mandat/organisation côté serveur** + gating par rôle via un composable unique. |
| Langues | **fr/en** uniquement (alignement app4). Le **cn** (présent dans app3) = chantier de suivi séparé sur toute app4. |
| Serveur WS | **broker** interne (même VPS). **Activation/paramètres par événement** via le JSON WSM `event{idEvent}_network.json` (présent → broker actif ; 404 → diffusion locale seule), cf. §6.5. Évolution possible : porter ce réglage dans app4 (par événement/compétition). |

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

- `pages/games/[id]/scoring.vue` — console (chrono, score, événements, joueurs A+B, périodes,
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
  `Penalty`, `Period='M1'|'M2'|'P1'|'P2'|'TB'` (legacy ; **à généraliser pour des prolongations
  non bornées**, cf. §7.5), `MatchStatus='ATT'|'ON'|'END'`.

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
| `periodDurations` | durées par période (s) | `{ M1:600, M2:600, P1:180, P2:180, TB:180 }` | §6.4, §7.5 |
| `halftimeDuration` | chrono de mi-temps indicatif (s) | `180` (3 min) | §7.5 |
| `shotclockDurations` | `{ full, offensiveRebound }` (s) | `{ full: 60, offensiveRebound: 40 }` | §6.5 |
| `shotclockOffensiveReboundEnabled` | le reset 40 s est-il actif | `false` jusqu'à l'entrée en vigueur | §6.5 |
| `allowTimerAdjustWhileRunning` | ajustement fin du chrono **autorisé chrono en marche** (= synchro avec un chrono Hardware Scoring) | `false` (sinon : ajustement seulement à l'arrêt) | §6.4 |
| `penaltyDuration` | durée d'une pénalité de carton (s) | `120` (2 min) | §7.4 |
| `overtimeUnlimited` | prolongations non bornées (but en or) | `true` (règlement) | §7.5 |
| `shootoutEnabled` | tirs au but autorisés | `false` (option tournoi) | §7.5 |
| `stopClockOnGoal` | arrêt chrono auto sur but | `false` | §6.5 |
| `federationProfile` | profil de durées (ICF/FFCK) | indicatif | §6.4 |

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

Le scoring legacy **régénère, à chaque action, des fichiers JSON dans `sources/live/cache/`** qui
alimentent les **incrustations vidéo (`/live`)**, le scoreboard et les clients distants. Le
Scoring api2 **doit produire les mêmes fichiers** (le contrat est consommé par l'existant `/live`,
**ne pas le casser**). Générateur legacy = classe **`CacheMatch`** (`live/create_cache_match.php`),
appelée par `setChrono.php`, `StatutPeriode.php`, `evt_match.php`, `ajax_updateChrono.php`,
`getNextGame.php`. Fichiers par match (vérifié sur des exemples réels) :

| Fichier | Contenu | Régénéré sur |
|---|---|---|
| `{idMatch}_match_global.json` | entête (catég, journée, phase, terrain, date/heure, n°, validation, statut, arbitres) + **compositions** `equipe1/equipe2` (id, nom, club, logo, couleurs, joueurs) | compo/officiels/statut |
| `{idMatch}_match_score.json` | `periode`, `score1`/`score2`, **liste `event`** (détail `kp_match_detail` enrichi nom/prénom) | tout événement, changement de score/période |
| `{idMatch}_match_chrono.json` | `action` (run/stop), `start_time`, `start_time_server`, `run_time`, `max_time`, `shotclock`, `penalties` (JSON), `tick` | toute action chrono/shotclock/pénalité |

> **Cadrage phase.** Ces TODO sont marqués **Phase 3** dans `ScoringController`
> (`// TODO (Phase 3): generate broadcast cache here`, ×3 : gameParam, gameEvent, gameTimer) et
> alignés sur le mécanisme broker (§6.5) — **c'est là qu'ils seront implémentés**, calqués sur
> `CacheMatch` (et sur `AdminEventWorkerController`/`AdminTvController` qui écrivent déjà dans
> `live/cache/` côté api2). **Décision : la journalisation `kp_journal` est dès la Phase 1** (peu
> coûteuse, cohérence d'audit) ; **la génération des JSON d'incrustation reste en Phase 3** avec la
> diffusion. À garder en tête dès maintenant pour ne pas avoir à re-router les écritures plus tard.

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
jeu courant). Le **champ « temps de l'événement »** (période + `MM:SS`, avec ses boutons
d'ajustement −60/−10/−1/+1/+10/+60) **reste présent en post-match** : il est indispensable pour
**saisir ou corriger le temps d'un événement à la main** (cf. §7.3). En post-match, ce champ
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
dur deux profils fédération — ICF prolongations 5 min, FFCK 3 min, via `duree_prolongations` —
qu'on remplacera par un réglage porté par la compétition, en **hydratant `store.config`**).

### 6.5 Temps réel & captation matériel

- **Diffusion locale (Phase 2)** : port de `app3/composables/useBroadcast.ts` (canal
  `kpi_channel`, contrat `timer/timer_status/shotclock/period/teams/scores/penA/penB`).
  **BroadcastChannel est same-origin** → on **porte le markup** de `scoreboard.php` +
  `v2/scoreboard.js` en routes Nuxt (`scoreboard.vue`/`shotclock.vue`), ouvertes même origine
  via `window.open`.
- **Shotclock — temps d'action de but (Phase 2)** : compte à rebours, buzzer à 0
  (`targetAchieved`), suit le chrono (pause quand le chrono s'arrête), **caché quand le temps de
  jeu restant est inférieur au shotclock** (`shotClockShow`), affiché `--` sinon. Ajustements
  **−10 / −1 / +1 / +10 s** et **remise à zéro**, autorisés **seulement chrono à l'arrêt**
  (`allowShotclockUpdateWhileRunning = false`). Le shotclock ne concerne que le **mode direct**.

  > **Deux durées de shotclock (évolution réglementaire — saison prochaine).** Les règlements
  > international (ICF) **et** français (FFCK) introduisent **deux durées** :
  > - **60 s** : durée à l'**engagement** (nouvelle possession après but, sortie, faute…) ;
  > - **40 s** : durée **réduite** lorsque **l'équipe qui vient de tenter un tir vers le but
  >   récupère le ballon** (rebond offensif conservé).
  >
  > Le système doit gérer ces **deux valeurs** : un **reset à 60 s** (possession « normale ») et
  > un **reset à 40 s** (rebond offensif). Les deux durées vivent dans **`ScoringConfig`**
  > (`shotclockDurations = { full: 60, offensiveRebound: 40 }`, cf. §6.2 — même endroit que les
  > durées de période, à terme paramétrable par compétition) et **deux commandes de reset
  > distinctes** (UI + raccourcis clavier dédiés, cf. ci-dessous). Tant que le règlement actuel
  > s'applique, seul le **60 s** est utilisé (`shotclockOffensiveReboundEnabled = false` → le 40 s
  > reste masqué/désactivé jusqu'à l'entrée en vigueur).

  > **Déclenchement du shotclock indépendant du chrono principal (important).** Au coup d'envoi
  > de **chaque période**, **le chrono principal démarre mais le shotclock NE démarre PAS** : il
  > reste à l'arrêt tant qu'**aucune équipe n'a pris la première possession**. Le shotclock n'est
  > lancé qu'**au premier reset** (déclenché par la **touche de reset du shotclock** quand une
  > équipe récupère le ballon) — **sans bouton de démarrage supplémentaire** : le reset _est_ le
  > démarrage. Conséquence sur le modèle (vs legacy, où le shotclock suivait directement le start
  > du chrono) : découpler l'état « shotclock armé/lancé » de l'état « chrono lancé ». Tant que le
  > shotclock n'a pas été lancé pour la période, l'afficher **inactif** (`--`), même chrono en
  > marche. Ensuite, il **suit le chrono** (pause/reprise) normalement.

- **Raccourcis clavier (Phase 2, avec le shotclock)** : `Espace` = run/stop du chrono,
  `0` = **reset/lancement shotclock à 60 s** (engagement — c'est aussi ce qui **arme** le
  shotclock en début de période, cf. ci-dessus), **`.` (point du pavé numérique) = reset/lancement
  shotclock à 40 s** (rebond offensif ; choisi pour sa **proximité du `0`** sur le pavé numérique),
  `+` / `−` = shotclock ±1 s (legacy fm3_C.js). Neutralisés quand le focus est dans un champ de
  saisie (temps d'événement, commentaires…).
- **Arrêt du chrono sur but (option, Phase 2)** : paramètre `arret_chrono_sur_but` (legacy) — un
  but déclenche un stop chrono automatique (temps mort). **Désactivé par défaut** (l'était aussi
  en legacy), à exposer comme option.
- **WebSocket broker (Phase 3)** : port de `app3/composables/useWebSocket.ts` (format
  `{p:"eventId_terrain", t:type, v:value}`). Mirroring des diffusions vers le broker →
  incrustations `/live` + clients distants. Implémenter en parallèle la **génération des JSON de
  diffusion** (`live/cache/{idMatch}_match_{global,score,chrono}.json` — les
  `// TODO (Phase 3): generate broadcast cache here` ×3 du contrôleur, cf. §6.2 « Génération des
  JSON » pour le contrat exact), calquée sur `CacheMatch` legacy et `AdminEventWorkerController`.

  > **Activation du broker — mécanisme existant (à reprendre puis faire évoluer).** Aujourd'hui,
  > l'URL/credentials du broker **ne sont pas** une config globale : ils sont définis **par
  > événement** dans un JSON généré par le **WebSocket Manager (WSM)**, p.ex.
  > `sources/live/cache/event{idEvent}_network.json` :
  > ```json
  > {"network":{"global":{"stomp":false,"url":"wss://broker.kayak-polo.info",
  >   "password":"…","topic":"broker"}}}
  > ```
  > Le legacy (`fm3_C.js` `checkWebSocket`) **POST** sur ce fichier : s'il **existe** → on se
  > connecte au broker avec ces paramètres ; s'il renvoie **404** → **pas de broker**, on retombe
  > sur la seule diffusion locale `BroadcastChannel`. Le WebSocket n'est donc **activable que si
  > le JSON est présent pour l'événement**. Le Scoring doit reproduire ce comportement
  > (résolution par événement + fallback local sans broker).
  >
  > **Piste d'évolution (à évaluer plus tard).** Plutôt que ce JSON par-événement généré par le
  > WSM, **définir l'activation/les paramètres du broker dans app4** — vraisemblablement **par
  > événement, ou par compétition** — via un réglage exposé côté api2 (et non plus un fichier de
  > cache). Non tranché ; le MVP Phase 3 peut d'abord **consommer le JSON existant** pour ne rien
  > casser, l'évolution venant ensuite.
- **Hardware Scoring (Phase 3)** : `useHardwareScoring.ts` reçoit les live datas du matériel
  (panneau matériel propriétaire ou équivalent) via le broker (WSM) et **alimente le `scoringStore`** au lieu
  de la saisie manuelle. Même store, même diffusion ; seule la **source** des données change
  (humain vs matériel). Un sélecteur de mode (« Scoring » / « Hardware Scoring ») bascule la
  source.

### 6.6 i18n

Namespace `scoring.*` dans `i18n/locales/fr.json` et `en.json` (périodes, statuts, codes
d'événements, motifs de cartons, libellés chrono/shotclock/scoreboard, messages de
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
| `ScoringEventButtons` | Boutons d'événements (but, V/J/R/D) + sélection joueur/motif + **champ temps de l'événement** (période + `MM:SS`, ajustements) — **présent en direct ET post-match** (≠ `ScoringTimer`, cf. §1.1/§6.4) | — |
| `ScoringEventHistory` | Historique éditable (table symétrique A | Temps | B) — **masqué par défaut, ouvert à la demande** (collapse/offcanvas, cf. §7.1) | — |

## 7. Déroulement d'un match (workflow de la table de marque)

> Cette section décrit le **parcours fonctionnel attendu** de la console Scoring, calqué sur la
> feuille de marque en ligne V3 (FMV3) et complété par les nouveautés (chrono de mi-temps,
> prolongations « but en or », alertes cartons). **Principe directeur : la page Scoring doit
> exposer, d'une manière ou d'une autre, toutes les informations présentes sur le PDF de
> contrôle `FeuilleMatchMulti.php`** (entête match, officiels, joueurs A/B, détail des
> événements, scores mi-temps/final, commentaires, heures début/fin).

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
   - **Officiels** : secrétaire, chronométreur, chronométreur temps d'action de but, arbitre
     principal, arbitre secondaire, juges de ligne (×2) — **cliquer pour modifier** ; rappel des
     infos d'organisation (club organisateur, resp. organisation, délégué, chef des arbitres, RC).
   - **Équipe A / Équipe B** : liste des joueurs présents (N°, statut, nom, prénom, licence,
     catégorie), suppression d'un joueur, **« Recharger les joueurs présents »** (depuis la
     feuille de présence). Le **numéro de maillot** et le **statut capitaine** (`-`/`C`/`E`) sont
     **modifiables tant que le match n'est pas verrouillé** (`canManagePlayers = !isLocked && …`,
     cf. §6.3) ; une fois verrouillé → **lecture seule**. (Édition inline → `playerStatus`,
     cf. §7.8.)
2. **Vue Déroulement** (pendant le match) — chrono, shotclock, score live, sélecteur de
   statut/période, boutons d'événements (but, cartons vert/jaune/rouge/définitif), zone
   pénalités, historique des événements (table A | Temps | B), commentaires.

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
>   (`+`/`+` par équipe) puis boutons d'événements (but, V/J/R/D) puis zone temps
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
période, boutons d'événements**. Le reste apparaît à la demande :
- **Listes de joueurs** : pas besoin de voir les noms en continu. On les fait apparaître **au
  moment d'attribuer un événement** (sélection du joueur) ou **pour contrôler les événements
  déjà saisis** — sinon repliées/escamotées (offcanvas ou panneau collapsible par équipe).
- **Historique des événements** : masqué par défaut, **ouvert explicitement** par l'utilisateur
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
  étendre à la saisie d'événements : sélection joueur par **numéro tapé**, validation par `Entrée`,
  annulation par `Échap`, comme le legacy `#time_evt` validant sur `Entrée`).
- **Enchaînement minimal** pour un événement courant : viser **but = joueur + touche** (le temps
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
- **Saisie d'un événement** (legacy fm3_C.js) : sélectionner **un joueur** (ou l'équipe pour un
  carton d'équipe), **un type d'événement** (but / carton V/J/R/D), saisir le **temps**
  (pré-rempli depuis le chrono en direct, ajustable −60/−10/−1/+1/+10/+60 s ; **tapé à la main en
  post-match**), et le **motif** pour un carton (modal, cf. 7.4). Pour un but, **attribution à un
  joueur** obligatoire.

#### Édition et suppression d'un événement déjà saisi (MVP Phase 1)

L'historique des événements (table A | Temps | B) est **éditable** : un clic sur une ligne la
charge dans la zone de saisie (période, temps, joueur, type, motif) et permet de la **modifier**
(`updateEvent` → `PUT /scoring/gameEvent` action=`update`) ou de la **supprimer**
(`removeEvent`, action=`remove`). L'édition **recalcule le score** (retrait du but de l'ancienne
ligne, ajout du nouveau) et **met à jour les marqueurs visuels** du joueur (but/carton).

> **Central pour la saisie/correction post-match** (cf. §1.1) : c'est par cette édition de ligne
> qu'on **corrige un match déjà joué** sans rejouer le chrono. C'est pourquoi l'édition complète
> est au périmètre **P1**, et pas seulement add/remove.

### 7.4 Pénalités (cartons)

- Un **carton** déclenche une **pénalité de 2 minutes** pour le joueur sanctionné, dont le
  **décompte suit le chrono** (se met en pause quand le chrono est arrêté).
- Une pénalité peut être **supprimée manuellement**, ou **suite à un but de l'équipe adverse**
  (**après confirmation de l'opérateur**).
- Si une équipe a **deux pénalités en cours**, c'est la **plus ancienne** qui est susceptible
  d'être supprimée par un but adverse.
- **Motif de carton** (optionnel) à définir à la saisie. Motifs existants (réutilisés de FMV3,
  clés i18n) : `r_pad` (Pagaie), `r_kt` (Éperonnage), `r_ht` (Poussée / Accrochage), `r_p`
  (Possession), `r_o` (Obstruction), `r_un` (antijeu/non sportif), `r_rep` (Remplacement),
  `unknown` (autre/non précisé).
- **Règle de progression des cartons (alerte)** : un même joueur **ne peut pas** faire l'objet
  de **deux cartons de la même couleur** dans un même match. Le deuxième carton est
  **obligatoirement au minimum de la couleur supérieure** dans l'ordre
  **vert → jaune → rouge → rouge définitif**. **Déclencher une alerte** si l'opérateur tente de
  saisir un carton qui ne respecte pas cette progression.

### 7.5 Périodes, mi-temps et prolongations

> **Nommage des états de période (important — clarifié à la lecture du legacy).** Les codes du
> store (`Periode` en base, boutons FMV3) sont : **`M1` / `M2` = les deux mi-temps** du temps
> réglementaire ; **`P1` / `P2` = les prolongations** (overtime), affichées seulement pour un
> match de type E ; **`TB` = tirs au but**. Ne pas confondre `P1`/`P2` (prolongations) avec les
> mi-temps.

- **Match de classement (type C)** : **2 mi-temps de 10 minutes** (`M1`/`M2`), durée
  **paramétrable** (`periodDurations`, défaut 600 s). Égalité possible (pas de prolongation).
- **Chrono de mi-temps (nouveauté)** : à la fin du temps de **`M1`**, **signal sonore** (buzzer),
  puis déclenchement automatique d'un **chrono de mi-temps de 3 minutes**. Ce décompte est
  **indicatif** pour l'arbitre (repère pour reprendre), il ne bloque pas.
- **Match éliminatoire (type E)** : en cas d'**égalité à la fin du temps réglementaire**,
  enchaîner **autant de prolongations que nécessaire** jusqu'au **premier but marqué (but en or)**
  → fin immédiate (règlements FFCK **et** ICF). **Durée des prolongations dépendante de la
  fédération** : ICF = 5 min, FFCK = 3 min (legacy `duree_prolongations`, override PHP forçant
  3 min). Pour le MVP : durée par défaut + ajustable manuellement ; cible = réglage porté par la
  compétition (cf. §6.4).

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
  (`P1`/`P2` legacy → généraliser en série `OT{n}`), `TB` (tirs au but, **optionnel par
  compétition**) ; le **chrono de mi-temps** est un état dérivé (countdown indicatif entre `M1`
  et `M2`), pas un code de période persisté.

> **Compatibilité base.** Le champ `kp_match.Periode` stocke aujourd'hui `M1/M2/P1/P2/TB`. La
> généralisation des prolongations devra soit réutiliser/étendre ce codage (`P{n}` ou `OT{n}`),
> soit s'appuyer sur un champ complémentaire ; **à trancher** lors de l'implémentation des
> prolongations (le MVP P1 peut se limiter à `M1/M2` + `P1/P2` existants, la série illimitée
> arrivant avec le travail prolongations/but-en-or).

### 7.6 Clôture du match

À la fin du match, la table de marque :

1. indique l'**heure de fin** (`Heure_fin`),
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
| Détail des événements : période, temps, n° joueur, motif, but / V / J / R / D, par équipe | `kp_match_detail` | Vue Déroulement (historique) |
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
| **Drapeaux de nation A/B** | `FeuilleMarque3.php` (`paysA`/`paysB` dérivés des 3 premiers car. du `Code_club`, fallback `FRA` si numérique) | P1 (entête) / P2 (scoreboard) | Logique de dérivation à porter. |
| **Couleurs équipes (maillots)** | `kp_competition_equipe` (`color1/2`, `colortext`) | P2 | Pour le scoreboard et les pastilles A/B. |
| **Buzzer / test son** | `FeuilleMarque3.php` (`buzzer`, audio) | P2 | Avec le shotclock + fin de période. |
| **Stats du match** | `fm3_D.js` (`FeuilleMarque2stats.php`) | P2+ | Bouton « Stats » ; à recâbler sur l'équivalent app4/api2. |
| **Tirs / arrêts** (events `T`/`A`, compteurs `nb_tirs`/`nb_arrets`) | `FeuilleMarque3.php` (markup **commenté**) | — | **Désactivés** dans l'UI legacy actuelle → **hors périmètre** sauf décision contraire. |

## 8. Plan par phases

- **Phase 0 — Échafaudage** : dep `easytimer.js` ; `types/scoring.ts` ; coquilles
  `scoringStore.ts` + `useScoringPermissions.ts` ; clés i18n `scoring.*`.
- **Phase 1 — MVP online (livrable principal)** : page `scoring.vue` (chargement match+joueurs),
  store branché sur api2 (score/événements/chrono/statut/joueurs), permissions client+serveur,
  **renommage `WsmController` → `ScoringController` + sécurisation `/scoring`** (firewall JWT +
  `ROLE_SCORER` + scope mandat), validation/verrouillage via l'endpoint existant, **ajout du
  lien « Scoring »** dans games/index.vue (vue tableau + vue carte) **en conservant V2/V3**.
  Inclut aussi : **mode direct / post-match** (masquage chrono hors-live, cf. §1.1) ;
  **édition complète d'un événement** existant (`updateEvent`, cf. §7.3) ; **ajustement fin du
  chrono** ±1/±10 s (cf. §6.4) ; **édition inline officiels / n° / statut joueur**,
  **suppression / recharge des présents**, **publication privé/public**, **charge d'un autre
  match par ID#/n° court** (cf. §7.8) ; **journalisation `kp_journal`** de toutes les actions
  mutantes via `AdminLoggableTrait` (cf. §6.2 « Journalisation ») ; **ergonomie responsive
  (affichage à la demande : joueurs/historique en collapse/offcanvas) + saisie optimisée
  clavier-first** (cf. §7.1) ; **sélecteurs statut/période en badge cyclique** (calqués sur
  `competitions/index.vue`, confirmation + durée non standard à part, cf. §7.1).
- **Phase 2 — Diffusion locale** : `useBroadcast` + `useTimer`, `scoreboard.vue` +
  `shotclock.vue`, **UI pénalités**, **shotclock** (ajustements + reset), **raccourcis clavier**
  (Espace/0/+/−), **buzzer / test son**, **arrêt chrono sur but** (option), couleurs/drapeaux
  équipes, « Match suivant… » (cf. §6.5, §7.8).
- **Phase 3 — WebSocket broker + cache + Hardware Scoring** : `useWebSocket` (broker),
  **génération des JSON de diffusion `live/cache/{idMatch}_match_{global,score,chrono}.json`**
  (parité `CacheMatch` legacy, alimentent les incrustations `/live` — cf. §6.2 « Génération des
  JSON »), incrustations `/live`, `useHardwareScoring` (captation panneau matériel propriétaire ou équivalent).
- **Phase 4 — Offline/PWA (reporté)** : file d'attente d'écritures IndexedDB derrière le store,
  service worker. Uniquement après un online-first solide.

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

1. **Logique chrono/shotclock/pénalités de `fm3_C.js`** — plus gros effort de portage
   (type de match C vs E, expiration des pénalités → messages `penA/penB`).
2. **Génération des JSON de diffusion api2** (`{idMatch}_match_{global,score,chrono}.json`, les
   3 TODO de `ScoringController`) — parité `CacheMatch` legacy, consommés par `/live` (contrat à
   respecter, cf. §6.2 « Génération des JSON »). Reporté en **Phase 3**.
3. **broker / Hardware Scoring** — serveur WS interne maîtrisé ; protocole de captation du
   matériel (matériel propriétaire ou équivalent) à formaliser (format des live datas entrantes). Risque
   maîtrisé (propriété interne).
4. **cn** — hors périmètre MVP ; chantier de suivi séparé sur toute app4.
5. **Prolongations non bornées (but en or)** — le legacy plafonne à 2 (`P1`/`P2`) ; généraliser
   en série illimitée impose de revoir le **codage de `kp_match.Periode`** (extension `P{n}`/`OT{n}`
   ou champ complémentaire) et la logique de fin (clôture immédiate au 1ᵉʳ but). À traiter dans le
   lot prolongations (cf. §7.5), pas au MVP P1.
6. **Tirs au but optionnels par compétition** — nécessite un **paramètre de compétition**
   (activation + format) non encore disponible ; reporté.
7. **Contrôle fin des droits** — point dédié à venir (cf. §6.3) ; règle déjà fixée :
   `canLock = profile ≤ 6`.
8. **Activation du broker par événement** — aujourd'hui via le JSON WSM
   `event{idEvent}_network.json` (présence = broker actif, 404 = fallback local) ; **évolution à
   évaluer** : porter ce réglage dans app4 (par événement ou par compétition) plutôt qu'un fichier
   de cache (cf. §6.5). Phase 3 : consommer l'existant d'abord.
9. **Double shotclock 60 s / 40 s + déclenchement indépendant** (évolution réglementaire saison
   prochaine, cf. §6.5) — écart de modèle vs legacy (le shotclock ne suit plus automatiquement le
   start du chrono : il est **armé au premier reset** de la période). Impacte `useTimer`, le store
   (deux durées + état « armé »), l'UI (double reset) et les raccourcis clavier (touche dédiée au
   reset 40 s). À cadrer en Phase 2 ; durées à terme paramétrables par compétition.
10. **Reprise / persistance partielle** (cf. §6.4 « Reprise d'un match en cours ») — le chrono
    principal est restauré depuis `kp_chrono` (failover terminal OK), mais **shotclock et
    pénalités ne sont pas persistés** → re-saisie après reprise. **Évolution future à évaluer** :
    persister shotclock + pénalités côté serveur pour une reprise complète. Non bloquant MVP.

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
- **Phase 2** : ouvrir scoreboard + shotclock en 2ᵉ fenêtre → synchro live.
- **Phase 3** : connecter le broker + une incrustation `/live` → réception via `{p,t,v}` ;
  brancher un panneau matériel propriétaire (ou équivalent) en mode Hardware Scoring → le store se met à jour
  depuis le matériel.

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
| `pages/games/[id]/scoring.vue` | **Créée.** Console : header match, score, sélecteurs statut/période, chrono (run/stop/RAZ → api2), listes joueurs A/B (sélection), boutons d'événements (but/cartons), liste des événements, verrouillage. Gatée `useScoringPermissions` (≤ 2). |
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

**Reste à faire en Phase 1** (avant de clore le MVP) :
- Test fonctionnel complet **authentifié** (profil ≤ 2) via l'UI : saisie réelle + vérification
  base + restauration visuelle du chrono au rechargement + vérif 403 hors mandat.
- Motifs de cartons (modal) et statut joueur (capitaine/coach) depuis la console.
- **Mode direct / post-match** (cf. §1.1) : sélecteur + masquage du chrono/shotclock hors-live,
  pré-positionné selon le statut (`END` → post-match).
- **Édition complète d'un événement** existant (clic ligne → modifier période/temps/joueur/motif,
  `updateEvent` action=`update`), avec recalcul du score (cf. §7.3).
- **Ajustement fin du chrono** ±1/±10 s + réglage durée de période (cf. §6.4).
- **Édition inline officiels / n° maillot**, **suppression / recharge des présents**,
  **publication privé/public**, **charge d'un autre match par ID#/n° court** (cf. §7.8).
- **Alertes de progression des cartons** (même couleur 2×, seuils par joueur/équipe — cf. §7.4).
- **Journalisation `kp_journal`** : `ScoringController` doit `use AdminLoggableTrait` et appeler
  `logActionForMatch(...)` sur **chaque endpoint mutant** (gameParam/gameEvent/gameTimer/
  playerStatus). **Actuellement absent** → aucune action de scoring n'est tracée (cf. §6.2
  « Journalisation »). À faire avant de clore le MVP.
- Shotclock (time-shoot), buzzer, raccourcis clavier, « Match suivant… » + diffusion broadcast
  → relèvent de la **Phase 2**.
- **Génération des JSON `live/cache/{idMatch}_match_*.json`** (incrustations) → **Phase 3** avec le
  broker (cf. §6.2 « Génération des JSON », §6.5).
