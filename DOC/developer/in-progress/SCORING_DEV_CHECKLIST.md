# Refonte scoring — Checklist dev : commandes à exécuter & tests fonctionnels

> **Document vivant**, enrichi au fil des lots implémentés (branche
> `claude/scoring-refactoring-strategy-3d43ac`). Il liste, lot par lot, **ce qu'il faut
> exécuter en dev** (migrations, redémarrages) puis **ce qu'il faut vérifier
> fonctionnellement** avant de considérer le lot validé.
> Plan : [LIVE_MATCH_SCORING_REFACTORING_PROPOSALS.md](../reference/LIVE_MATCH_SCORING_REFACTORING_PROPOSALS.md) ·
> Spec console : [PAGE_SCORING.md](../../specs/PAGE_SCORING.md) ·
> Spec incrustation : [PAGE_INCRUSTATION.md](../../specs/PAGE_INCRUSTATION.md)

## 0. Préambule (une fois)

```bash
git checkout claude/scoring-refactoring-strategy-3d43ac && git pull
make docker_dev_up            # recrée les conteneurs dont l'env a changé (worker ↔ Mercure, lot 2)
make api2_restart             # FrankenPHP garde le kernel en mémoire : requis après chaque pull
```

Rappels :
- logs api2 : `make api2_logs` (jamais `docker/apachelogs_8/`) ;
- logs worker : `docker logs -f ${APPLICATION_NAME}_event_cache_worker` ;
- `MERCURE_JWT_SECRET` de `docker/.env` doit être **identique** à celui de `sources/api2/.env`.

---

## Lot 1 — État canonique `scoring_live_*`

### Commandes

```bash
# 1) Migration SQL (nouvelles tables + kp_match.uid additif) — idempotente (IF NOT EXISTS)
docker exec -i ${DB_CONTAINER_NAME} sh -c \
  'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' \
  < SQL/migrations/2026-07-27_scoring_live_tables.sql

# 2) Redémarrer api2 (nouveau code contrôleur/service)
make api2_restart

# 3) Tests unitaires des règles pures (aucune dépendance)
php sources/api2/tests/Scoring/scoring_rules_test.php   # attendu : "OK — 62 assertions passed"
```

### Tests fonctionnels

Connexion app4 avec un **profil ≤ 2** (login dev `42054`), ouvrir `/games/{id}/scoring`
sur un match de test **non verrouillé**.

| # | Test | Attendu |
|---|---|---|
| 1.1 | Saisir un but, un carton, lancer/arrêter le chrono, changer de période/statut | chaque action répond `success` ; l'UI se met à jour |
| 1.2 | En base (phpMyAdmin) : `scoring_live_state`, `scoring_live_clock` (kind `GAME`), `scoring_live_event` | les lignes reflètent la saisie ; **`kp_match` / `kp_match_detail` / `kp_chrono` ne bougent PAS** pendant le match |
| 1.3 | `scoring_outbox` | une ligne par écriture, `topic` = `/scoring/event/{e}/pitch/{p}/…` (ou `/scoring/match/{id}/…` si match hors événement), `published_at` NULL tant que le lot 2 ne draine pas |
| 1.4 | `GET /api2/admin/scoring/state/{id}` (avec JWT) | état complet + en-tête `ETag` ; re-GET avec `If-None-Match` → **304** |
| 1.5 | Recharger la page en cours de match ; ouvrir le même match dans un 2ᵉ onglet | statut/période/score et chrono restaurés depuis l'état live (à quelques dixièmes près) |
| 1.6 | Passer le statut à **Terminé** (END) | **consolidation** : `kp_match` (Statut/Periode/ScoreDetail/Heure_fin) et `kp_match_detail` reconstruits depuis le live ; fiche/PDF/classements corrects |
| 1.7 | `PUT /api2/admin/scoring/source/{id}` avec `{"source":"HARDWARE"}` puis re-saisir à la console | la console reçoit **409** (« Another source is active ») + ligne `kp_journal` « Scoring rejeté (source) » ; re-promouvoir `MANUAL` → la saisie repasse |
| 1.8 | `kp_journal` | une ligne par action mutante (statut, période, score, événement, chrono, joueur, officiels, source, consolidation) |
| 1.9 | Ouvrir un **match saisi autrefois en legacy** (lignes `kp_match_detail` existantes) | l'historique s'affiche (fallback lecture) ; à la première modification, les faits sont importés dans `scoring_live_event` et l'édition/suppression par uid fonctionne |
| 1.10 | Match **verrouillé** (`Validation='O'`) et appel **non authentifié** | 400 « Game locked » / 401 |
| 1.11 | Legacy intact : FMV3 et `app_wsm` (`/api/wsm/*`) sur un autre match | fonctionnent comme avant |

---

## Lot 2 — Diffusion Mercure (outbox → worker → hub)

### Commandes

```bash
# L'env du conteneur worker a changé (MERCURE_URL/MERCURE_JWT_SECRET) → recréation
make docker_dev_up
docker logs -f ${APPLICATION_NAME}_event_cache_worker   # surveiller le drainage
```

### Tests fonctionnels

| # | Test | Attendu |
|---|---|---|
| 2.1 | Saisir à la console, puis `SELECT * FROM scoring_outbox ORDER BY id DESC LIMIT 5` | `published_at` renseigné en ~1 s |
| 2.2 | Banc **app4 → Operations → Mercure**, s'abonner au topic `/scoring/event/{e}/pitch/{p}/score` (ou `/scoring/match/{id}/score`) puis saisir | chaque changement arrive en SSE, avec `id: urn:kpi:scoring:{n}` et `event:` = type du payload |
| 2.3 | S'abonner aussi à `…/clock` et `…/fact` ; chrono + buts | seuls les topics concernés reçoivent (sélection par bloc) |
| 2.4 | **Coupure abonné** : fermer le banc 30 s pendant des saisies, se réabonner avec le dernier `Last-Event-ID` | le hub **rejoue** les messages manqués dans l'ordre |
| 2.5 | **Coupure hub** : `make api2_restart` pendant des saisies | les écritures console continuent (jamais bloquées) ; l'outbox s'accumule ; au retour du hub le worker draine tout, dans l'ordre |
| 2.6 | **Arrêt worker** : `docker stop …_event_cache_worker`, saisir, redémarrer | idem : rattrapage complet au redémarrage |
| 2.7 | Sans **aucune** config Event Cache Manager active | le drainage fonctionne quand même (indépendant du cache) |
| 2.8 | Après 1 h | les lignes publiées anciennes sont purgées (la table reste petite) |

---

## Lot 3 — Console Scoring (tranche livrée : prolongations non bornées, cartons 2027, config centralisée)

### Commandes

```bash
make app4_npm_install          # nouvelles sources front (utils/scoringRules.ts…)
make app4_dev                  # ou le serveur dev habituel
# Lint (contrôle) :
#   container app4 : npm run lint
```

### Tests fonctionnels

| # | Test | Attendu |
|---|---|---|
| 3.1 | Match **type E** à égalité, statut ON : bouton « Période suivante » depuis M2, puis depuis P1, P2… | propose P1, puis P2, **P3, P4… sans plafond** tant que le score est à égalité ; libellés « Prolongation n » |
| 3.2 | Match **type E** avec un vainqueur à la fin de M2 | plus d'avance possible (pas de prolongation) |
| 3.3 | Match **type C** | séquence M1 → M2 seulement ; jamais de prolongation ; TB absent (option compétition désactivée) |
| 3.4 | Passer en P1 (chrono affiché) | le chrono se re-prime à **5 min** (300 s — ICF/FFCK) |
| 3.5 | **But en or** : but saisi en P{n} d'un match E | modale « But en or … Clore le match ? » ; confirmer → statut END + consolidation `kp_*` |
| 3.6 | **Progression des cartons** : jaune puis jaune au même joueur ; jaune puis vert ; rouge puis n'importe quoi ; noir en premier | alertes « carton identique/inférieur », « joueur déjà exclu » ; **premier carton jaune ou rouge accepté sans alerte** ; noir accepté à tout moment ; « Enregistrer quand même » force la saisie |
| 3.7 | Carton `D` | libellé « **Carton noir (exclusion définitive)** », bouton neutre (noir), token **⬛** dans l'historique — plus aucun « rouge définitif » à l'écran |
| 3.8 | Zone de saisie d'un carton | motif pré-sélectionné « **Autre/Non précisé** » — un carton se valide en un geste ; les buts n'enregistrent pas de motif |
| 3.9 | Sélecteur de période de la zone de saisie (édition post-match) | liste M1/M2 + toutes les prolongations utilisées +1 ; une période TB héritée d'un vieux match reste sélectionnable |
| 3.10 | Édition d'un fait `P3` d'un match ancien | s'affiche et s'édite normalement (type `P{number}` non borné) |

### Tests fonctionnels — 2ᵉ tranche (shotclock, pauses, buzzer, raccourcis)

| # | Test | Attendu |
|---|---|---|
| 3.11 | Démarrer le **chrono principal** (mode direct) | le shotclock reste à `--` (il ne démarre **jamais** avec le chrono) |
| 3.12 | Bouton **60 s** (ou `Entrée`) | le shotclock charge 60 et décompte ; re-appuyer recharge 60 (le départ EST un reset) ; **40 s** (ou `.`) recharge 40 |
| 3.13 | **Arrêter le chrono principal** pendant que le shotclock tourne | le shotclock se **suspend** automatiquement (ambre) ; relancer le chrono → il repart tout seul |
| 3.14 | Bouton **Arrêt** (ou `0`) | retour à `--` (état initial) — ce n'est pas une pause : 60/40 requis pour repartir |
| 3.15 | Shotclock à **0** | buzzer ; l'affichage reste à 0 jusqu'à une commande |
| 3.16 | **±1 s** | actifs uniquement chrono arrêté (shotclock suspendu) ; inopérants pendant le décompte |
| 3.17 | `SELECT * FROM scoring_live_clock WHERE kind='SHOTCLOCK'` puis **recharger la page** | ligne persistée (init/elapsed/running) ; après rechargement le shotclock revient dans le même état (RAZ en base après « Arrêt ») |
| 3.18 | Laisser le chrono principal atteindre **0** en fin de M1 | buzzer + décompte de **pause 3 min** affiché (1 min entre prolongations) ; buzzer en **fin de pause** ; « Terminer la pause » l'interrompt ; changer de période la clôt aussi |
| 3.19 | **Raccourcis** : `Espace` chrono, `Entrée`/`.`/`0`/`+`/`−` shotclock | agissent partout **sauf** quand le focus est dans un champ de saisie ; inactifs en post-match |
| 3.20 | Modale **Raccourcis clavier** (engrenage) : réassigner une touche déjà utilisée | l'autre action perd sa touche (une touche = une action) ; persiste au rechargement (localStorage) ; « Valeurs par défaut » restaure |
| 3.21 | Masquage : temps de jeu restant **inférieur** au shotclock | le shotclock affiche `--` (règle legacy `shotClockShow`) |

### Tests fonctionnels — 3ᵉ tranche (pénalités, règles §0.10)

| # | Test | Attendu |
|---|---|---|
| 3.22 | Carton **vert/jaune/rouge** en direct | pénalité **2:00** créée pour l'équipe du joueur (token 🟢/🟡/🔴 + n° maillot), décompte **suivant le chrono** (gel quand le chrono s'arrête) ; ligne `scoring_live_clock` kind `PENALTY` (team/slot/playerId/cardCode) |
| 3.23 | Carton **noir** (`D`) | **aucune pénalité créée** ; message « exclusion définitive, pas de 2 min, aucun remplacement » ; joueur marqué **⬛** dans l'effectif |
| 3.24 | **3ᵉ carton** pour la même équipe pendant 2 exclusions en cours | avertissement « déjà 2 exclusions » — aucune 3ᵉ horloge (jamais < 3 joueurs) |
| 3.25 | **But encaissé** par l'équipe en infériorité (pénalité V/J en cours) | modale « lever la pénalité la plus ancienne ? » ; confirmer → l'horloge disparaît + RAZ en base |
| 3.26 | **But encaissé** avec **seulement une pénalité `R`** en cours | **aucune proposition de levée** : les 2 min continuent (règle §0.10) |
| 3.27 | Pénalités `R` (plus ancienne) **+** `J` en cours, but encaissé | c'est la **`J`** qui est proposée à la levée, jamais la `R` |
| 3.28 | **Expiration** d'une pénalité (2:00 écoulées, chrono en marche) | buzzer + toast : `V`/`J` → « le joueur revient » ; `R` → « remplacement autorisé, le joueur ne revient pas » ; horloge retirée + RAZ en base |
| 3.29 | **Rechargement** de la page avec pénalités en cours | les décomptes reviennent (équipe/slot/n°/carton) dans le bon état run/suspendu |
| 3.30 | Croix de **retrait manuel** d'une pénalité | horloge retirée + RAZ en base (correction d'erreur de saisie) |
| 3.31 | Joueur avec carton `R` | marqué **🔴** dans l'effectif (remplacement à l'issue) ; avec `D` → **⬛** |

### Tests fonctionnels — 4ᵉ tranche (affichages plein écran, PWA)

> ⚠️ La PWA ne s'active **qu'en build** (service worker désactivé en dev, volontairement,
> pour ne pas mettre en cache les chunks Vite). Pour les tests 3.36+ :
> `make app4_generate_dev` (ou `npm run build` dans le conteneur) puis servir `.output/public`.

| # | Test | Attendu |
|---|---|---|
| 3.32 | Bouton **TV** de l'entête (mode direct) | ouvre `/games/{id}/scoreboard` dans une fenêtre : équipes, score, période, chrono, chronomètre de tir et pénalités s'affichent **immédiatement** (handshake `ready` → snapshot complet) |
| 3.33 | Saisir un but / lancer le chrono / lancer le shotclock / créer une pénalité | le tableau de score suit **en direct**, sans réseau (débrancher le Wi-Fi pour vérifier : la fenêtre reste synchronisée) |
| 3.34 | Bouton **horloge** de l'entête | ouvre `/games/{id}/shotclock` : chiffre **occupant tout l'écran**, blanc sur noir ; atténué quand suspendu ou à l'arrêt (`--`) ; chrono principal en petit dessous |
| 3.34a | **Redimensionner** la fenêtre du chronomètre de tir (très large, très haute, carrée) et la **faire pivoter** (tablette) | le chiffre se **remet à l'échelle** à chaque fois pour occuper la surface — jamais de débordement ni de marge perdue, sans rechargement |
| 3.34b | Laisser le chronomètre de tir descendre **sous 10 s** | affichage en **dixièmes** (`9.9` … `0.0`) et **la taille du chiffre ne change pas** au passage (calcul sur les candidats `60`/`8.8`) |
| 3.34c | Ouvrir `/games/{id}/shotclock?theme=light` puis `?clock=0` | noir sur blanc ; chrono principal masqué (le chiffre reprend toute la hauteur) |
| 3.34d | Ouvrir le scoreboard sur un écran **portrait** ou une fenêtre étroite | bascule en **disposition colonne** (équipe A / score / équipe B empilés) ; `?theme=light` et `?shotclock=0` fonctionnent |
| 3.35 | Ouvrir **deux matchs différents** dans deux consoles + leurs affichages | chaque affichage ne reçoit que **son** match (filtrage `matchId`) |
| 3.36 | Ouvrir la console **buildée** sur tablette → menu navigateur | l'application est **installable** (« Ajouter à l'écran d'accueil ») ; lancée depuis l'icône, elle s'ouvre en plein écran (standalone, paysage) |
| 3.37 | Déployer une **nouvelle version** pendant que la console est ouverte | la page se **recharge d'elle-même** dans les 5 min (ou immédiatement au retour sur l'onglet) et sert la nouvelle version — jamais d'app shell périmé |
| 3.38 | Couper le réseau puis **relancer** la PWA installée | l'application démarre (app shell en cache) ; les données du match nécessitent le réseau (file d'écritures offline = lot 7) |
| 3.39 | En **dev** (`make app4_dev`) | **aucun** service worker enregistré (pas de cache des chunks Vite) |

### Tests fonctionnels — 5ᵉ tranche (console abonnée à Mercure)

> Prérequis : lots 1 et 2 déployés en dev (tables + worker qui draine l'outbox).

| # | Test | Attendu |
|---|---|---|
| 3.40 | Ouvrir la console d'un match en mode direct | badge **antenne verte** dans l'entête (`scoring.sync.connected`) |
| 3.41 | Ouvrir **le même match sur un 2ᵉ terminal** (autre navigateur/poste), y saisir un but | le 1ᵉʳ terminal se met à jour **sans rechargement** en ~1 s (score + historique), le badge **clignote** (« modification reçue d'un autre terminal ») |
| 3.42 | Lancer/arrêter le **chrono** depuis le 2ᵉ terminal | le 1ᵉʳ terminal reprend le chrono **synchronisé** (dérive compensée via `GET /gameTimer`) ; idem shotclock et pause |
| 3.43 | Saisir **uniquement** sur le 1ᵉʳ terminal (rafale d'actions) | **aucun** refetch parasite : l'écho de nos propres écritures est ignoré (fenêtre 2 s) — vérifier dans l'onglet Réseau qu'il n'y a pas de `GET /state` après chaque écriture |
| 3.44 | Couper le réseau 30 s puis le rétablir | le badge passe **orange** puis revient vert ; l'état se resynchronise tout seul (rejeu `Last-Event-ID` + refetch) |
| 3.45 | Promouvoir la source sur `HARDWARE` depuis un autre client, puis écrire depuis la console | la console reçoit **409** (déjà couvert en 1.7) **et** reflète les changements poussés par la source active |
| 3.46 | Match **hors événement KPI** (sans terrain) | l'abonnement utilise le topic de repli `/scoring/match/{id}/{type}` et fonctionne pareil |

### Tests fonctionnels — 6ᵉ tranche (mode score seul, vue Paramètres)

| # | Test | Attendu |
|---|---|---|
| 3.47 | Activer **« Score seul »** dans l'entête | les listes de joueurs et la zone de saisie des faits **disparaissent** ; des boutons **±1** apparaissent de part et d'autre du score ; `scoring_live_state.active_source` passe à **`SCORE_ONLY`** |
| 3.48 | **+1** puis **−1** pour une équipe | le score monte puis redescend ; en base, un fait `GOAL` avec `id_player = '0'` est créé puis supprimé (fait d'équipe, sans attribution) |
| 3.49 | Revenir en mode complet | la source repasse à **`MANUAL`**, listes et zone de saisie reviennent ; la saisie détaillée fonctionne toujours |
| 3.50 | Bouton **« Paramètres du match… »** | bascule vers la vue Paramètres ; le bouton propose alors « Déroulement du match… » |
| 3.51 | Vue Paramètres : **type de match** et **publication** | affichés en **lecture seule** (badges), avec la mention « modifiable depuis la gestion du match » |
| 3.52 | Modifier un **officiel** (secrétaire, chronométreur de tir, arbitres, juges de ligne) puis **Enregistrer** | `kp_match` mis à jour, ligne « Scoring officiels » dans `kp_journal`, toast de confirmation |
| 3.53 | Modifier un **n° de maillot** et un **statut capitaine** | `kp_match_joueur` mis à jour (endpoint presence, déjà journalisé) ; annulation propre si l'appel échoue |
| 3.54 | **Supprimer** un joueur puis **« Recharger les présents »** | le joueur disparaît, puis la composition est **réinitialisée depuis la feuille de présence** |
| 3.55 | **Charger un autre match** par **n° court** (≤ 5 chiffres) | résolution serveur (même journée → même compétition → même événement) et navigation vers ce match |
| 3.56 | Charger par **ID#** complet (8-9 chiffres) | navigation directe |
| 3.57 | N° court **inconnu** ou **ambigu** (présent dans plusieurs matchs du même périmètre) | message « aucun match trouvé (ou plusieurs correspondances) » — **aucune** navigation hasardeuse |
| 3.58 | Match **verrouillé** | vue Paramètres en lecture seule (officiels et joueurs non modifiables) |

### Reste à livrer sur le lot 3

Rien : le périmètre du lot est couvert. Les évolutions sont renvoyées aux lots suivants
(paramétrage par compétition = lot 6, file d'écritures offline = lot 7).

---

---

## Lot 4 — Page d'incrustation unique (1ʳᵉ tranche)

### Commandes

```bash
DB="docker exec -i ${DB_CONTAINER_NAME} sh -c 'mysql -u\"\$MYSQL_USER\" -p\"\$MYSQL_PASSWORD\" \"\$MYSQL_DATABASE\"'"

# 1) Réglages d'affichage (défauts → événement → terrain)
eval $DB < SQL/migrations/2026-07-29_scoring_display_settings.sql
# 2) Jetons d'affichage (accès des incrustations)
eval $DB < SQL/migrations/2026-07-29_scoring_display_token.sql
# 3) Vues de compatibilité legacy ↔ live (FeuilleMatchMulti, app2, fiches publiques)
eval $DB < SQL/migrations/2026-07-29_scoring_live_compat_views.sql

make api2_restart          # contrôleur public + services programme/accès
make docker_dev_up         # le worker publie désormais le programme des terrains
```

Créer un jeton d'affichage pour l'événement 236 (tous terrains ; ajouter `pitch` pour le
restreindre à un seul) :

```sql
INSERT INTO scoring_display_token (token, id_event, pitch, label, expires_at)
VALUES (SHA2(UUID(), 256), 236, NULL, 'Régie test', DATE_ADD(NOW(), INTERVAL 7 DAY));
SELECT token FROM scoring_display_token WHERE label = 'Régie test';
```

URL type (source navigateur OBS, 1920×1080) :
`https://kpi.localhost/admin2/live/overlay?event=236&pitch=2&token=<jeton>&blocks=score,clock,shotclock,penalty,fact,next&debug`

### Tests fonctionnels

| # | Test | Attendu |
|---|---|---|
| 4.1 | Ouvrir l'URL **avec jeton** dans un navigateur non connecté (fenêtre privée) | l'incrustation s'affiche : **aucune session** requise, aucune interaction possible |
| 4.1a | Ouvrir la **même URL sans `token`** (ou avec un jeton inventé) | **401** côté API et message « Jeton d'affichage invalide, expiré ou révoqué » à l'écran — pas d'incrustation vide muette |
| 4.1b | `UPDATE scoring_display_token SET revoked_at = NOW()` puis recharger | l'accès est **coupé immédiatement** (idem après `expires_at` dépassé) |
| 4.1c | Jeton **restreint à un terrain** (`pitch='2'`) utilisé sur `pitch=3` | refusé (401) — la portée est respectée |
| 4.1d | Vérifier le **JWT d'abonné Mercure** : dans `?debug`, l'état SSE doit passer à `live` | l'abonnement fonctionne ; ⚠️ **c'est ce test qui compte en preprod/prod** (`MERCURE_ANONYMOUS=0`) — en dev l'anonyme masquerait le problème |
| 4.2 | `GET /api2/scoring/program/236/2?token=…` et `GET /api2/scoring/state/{id}?token=…` | JSON + `ETag` ; re-appel avec `If-None-Match` → **304** ; `/program` répond `Cache-Control: private` (il contient un JWT) |
| 4.2a | Appel **cross-site** simulé (en-tête `Origin: https://exemple.tld`) | **403** (défense en profondeur ; ce n'est pas la serrure — le jeton l'est) |
| 4.3 | Saisir un but / lancer le chrono à la console | l'incrustation suit en ~1 s (score, chrono, chronomètre de tir, pénalités, faits) |
| 4.4 | **Couper le réseau** de la machine d'incrustation 30 s pendant que le chrono tourne | les horloges **continuent de tourner** (interpolation locale) ; au retour, l'état se recale tout seul |
| 4.5 | Recharger la page (ou redémarrer OBS) | retour à l'état correct en une requête — l'URL suffit |
| 4.6 | Passer le match courant à **Terminé** | score final affiché après le délai configuré, puis **présentation du match suivant** — sans intervention |
| 4.7 | Le match suivant passe **En cours** | l'incrustation **bascule d'elle-même** sur ce match (topic `…/program`), sans rechargement |
| 4.8 | Vérifier la latence de bascule | quasi immédiate (recalcul du programme au changement de statut), **pas** au rythme du worker |
| 4.9 | Deux événements simultanés, deux incrustations ouvertes | chacune ne reçoit **que** son terrain de son événement (topics) |
| 4.10 | `blocks=score` seul, puis `blocks=fact` | seuls les blocs demandés s'affichent |
| 4.11 | `bg=magenta` puis `bg=transparent` dans OBS | fond chromakey / alpha correct |
| 4.12 | Insérer une ligne `scoring_display_settings` pour l'événement, puis une pour le terrain | les délais du **terrain** l'emportent ; une colonne `NULL` **hérite** (jamais « zéro ») |
| 4.13 | Comparer avec l'incrustation legacy du même terrain (cache JSON toujours généré) | mêmes valeurs affichées — condition de bascule terrain par terrain |

### Tests fonctionnels — compatibilité legacy ↔ live (étape 4.5, vues)

| # | Test | Attendu |
|---|---|---|
| 4.14 | **Pendant** un match saisi à la console (non terminé), imprimer le **PDF `FeuilleMatchMulti.php`** | buts/cartons, score, statut et période **à jour** (lecture via `v_match_detail` / `v_match_live`) |
| 4.15 | Même match : fiche publique / app2 (endpoints legacy `report`/`public`) | déroulement **à jour en cours de match** |
| 4.16 | Match **jamais touché** par la nouvelle console (données legacy uniquement) | affichage **inchangé** — les vues retombent sur `kp_*` |
| 4.17 | Après **clôture** (Statut → END, consolidation) | mêmes valeurs qu'avant la bascule ; **aucun doublon** (la vue exclut `kp_match_detail` dès qu'un état live existe) |
| 4.18 | Classements / exports / autres écrans lisant `kp_*` | **inchangés** (ils ne consomment pas les vues ; `kp_*` reste la vérité des résultats) |

### Reste à livrer sur le lot 4

Validation en parallèle terrain par terrain (4.6) ; UI de gestion des **jetons d'affichage**
et de réglage des **délais** dans app4 (avec le lot 6) ; refonte du système de styles
(spec §10, second temps).

---

## Lots suivants (placeholders)
- **Lot 5 — Relais matériel** : jeton machine, ingestion, terrain équipé en parallèle de WSM.
- **Lot 0.3 (rappel)** : validation FrankenPHP + hub Mercure en **preprod puis prod** avant tout usage réel du lot 2 hors dev.
