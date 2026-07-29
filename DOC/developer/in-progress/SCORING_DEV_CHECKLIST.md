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
| 3.34 | Bouton **horloge** de l'entête | ouvre `/games/{id}/shotclock` (grand chiffre + rappel du chrono) ; vert = décompte, ambre = suspendu, `--` = à l'arrêt |
| 3.35 | Ouvrir **deux matchs différents** dans deux consoles + leurs affichages | chaque affichage ne reçoit que **son** match (filtrage `matchId`) |
| 3.36 | Ouvrir la console **buildée** sur tablette → menu navigateur | l'application est **installable** (« Ajouter à l'écran d'accueil ») ; lancée depuis l'icône, elle s'ouvre en plein écran (standalone, paysage) |
| 3.37 | Déployer une **nouvelle version** pendant que la console est ouverte | la page se **recharge d'elle-même** dans les 5 min (ou immédiatement au retour sur l'onglet) et sert la nouvelle version — jamais d'app shell périmé |
| 3.38 | Couper le réseau puis **relancer** la PWA installée | l'application démarre (app shell en cache) ; les données du match nécessitent le réseau (file d'écritures offline = lot 7) |
| 3.39 | En **dev** (`make app4_dev`) | **aucun** service worker enregistré (pas de cache des chunks Vite) |

### Reste à livrer sur le lot 3 (tests à ajouter ici au fil de l'eau)

Abonnement Mercure de la console ; mode score seul ; solde §7.8 (statut joueur,
officiels UI, recharge présents, n° court).

---

## Lots suivants (placeholders)

- **Lot 4 — Incrustation** : cf. [PAGE_INCRUSTATION.md §12](../../specs/PAGE_INCRUSTATION.md) (autonomie, temps réel, enchaînement, isolation multi-événements, OBS, parité par famille legacy).
- **Lot 5 — Relais matériel** : jeton machine, ingestion, terrain équipé en parallèle de WSM.
- **Lot 0.3 (rappel)** : validation FrankenPHP + hub Mercure en **preprod puis prod** avant tout usage réel du lot 2 hors dev.
