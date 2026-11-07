# Refonte scoring — checklist de déploiement (remise à niveau, validation, preprod, prod)

**Créée le** : 27 juillet 2026 (sous le nom `SCORING_DEV_CHECKLIST.md`) — **renommée et refondue le 5 novembre 2026**.
**À tenir au fil de l'eau** : on coche ☑ chaque ligne **quand on l'a réellement exécutée**, et on note la date
dans la colonne prévue quand elle existe. Une ligne non cochée = non vérifiée, quoi qu'en dise le reste de la doc.
**Contexte** : plan [LIVE_MATCH_SCORING_REFACTORING_PROPOSALS.md](LIVE_MATCH_SCORING_REFACTORING_PROPOSALS.md) ·
spec console [PAGE_SCORING.md](../../specs/PAGE_SCORING.md) · spec incrustation [PAGE_INCRUSTATION.md](../../specs/PAGE_INCRUSTATION.md) ·
branche `claude/scoring-refactoring-strategy-3d43ac`.

> **Convention** (comme le [runbook](../infrastructure/DEPLOYMENT_RUNBOOK.md)) : ⌨️ poste de dev ·
> 🖥 VPS (SSH) · 🌐 navigateur · 🗄 base de données (phpMyAdmin ou `make db_bash`).

**Ordre imposé** : R0 → R1 → R2 → R3 → R4 → R5. Chaque étape conditionne la suivante ; aucun nouveau lot
(5 à 9) ne démarre avant que R1 et R2 soient cochés (plan §0.4).

---

## Où on en est (relevé du 2026-11-05)

| Constat | Preuve | Conséquence |
|---|---|---|
| Les 4 migrations **ont tourné en dev** (juillet puis octobre) | tables `scoring_live_*` présentes dans les sauvegardes `my_database_20260831` et `my_database_20261029` | le code a déjà rencontré MariaDB : le §0.2 du plan (« jamais exécuté ») est corrigé |
| Des matchs ont **réellement été saisis** à la console | `my_database_20260831` : 26 faits sur 2 matchs, horloges GAME / SHOTCLOCK / PENALTY ; `my_database_20261029` : 6 faits le 19/10 | les tests 1.1–1.2e, 3.1–3.30 ont des traces ; les correctifs du 31/07 au 02/08 en sont issus |
| `scoring_outbox` est **vide** dans les deux sauvegardes | 0 ligne, alors que des faits ont été écrits | la file a très probablement été drainée puis purgée par le worker → diffusion du lot 2 probablement fonctionnelle en dev, **à reconfirmer** (2.1) |
| La base courante **`my_database` n'a plus aucune table scoring** | rechargée depuis une sauvegarde plus récente | **la console répond 500 en dev tant que R0 n'est pas fait** |
| Tests automatisés **verts** | `make app4_test` 186/186 · `make api2_test_unit` 335 · `make api2_test_integration` 340 (dont `ScoringClosureTest`, `ScoringStatusSyncTest`) — relevé du 2026-11-06 | socle sain pour reprendre |
| Jamais vérifié | clôture et consolidation `kp_*` (1.6), source active / 409 (1.7), coupures Mercure (2.4–2.6), écrans plein écran (3.32+), PWA buildée (3.36+), multi-terminal (3.40+), score seul & Paramètres (3.47+), **tout le lot 4** (incrustation, OBS), preprod/prod | c'est l'objet de R1 |

---

## R0 — Revenir à niveau en dev (≈ 15 min)

### R0.1 ⌨️ Code et conteneurs

```bash
git fetch origin && git checkout claude/scoring-refactoring-strategy-3d43ac && git pull
make docker_dev_up            # recrée les conteneurs dont l'env a changé (worker ↔ Mercure)
make api2_restart             # FrankenPHP garde le kernel en mémoire : requis après chaque pull
```

| # | Commande / action | Résultat attendu | ☑ |
|---|---|---|---|
| 1 | `git log -1 --oneline` | le dernier commit de la branche | ☑ |
| 2 | `make dev_status` | api2, app4, legacy répondent (200/302) | ☑ |
| 3 | `grep MERCURE_JWT_SECRET docker/.env sources/api2/.env` | **la même valeur** dans les deux fichiers (sinon publication 403) | ☑ |

### R0.2 ⌨️ 🗄 Les tables scoring sur la base courante

```bash
# Les 4 migrations, idempotentes (IF NOT EXISTS / CREATE OR REPLACE), dans l'ordre chronologique
for f in SQL/migrations/2026-07-27_scoring_live_tables.sql \
         SQL/migrations/2026-07-29_scoring_display_settings.sql \
         SQL/migrations/2026-07-29_scoring_display_token.sql \
         SQL/migrations/2026-07-29_scoring_live_compat_views.sql; do
  make db_migrate file=$f || break
done
make api2_restart
```

| # | Commande / action | Résultat attendu | ☑ |
|---|---|---|---|
| 4 | La boucle ci-dessus | 4 lignes `✔ … appliqué`, aucune erreur | ☑ |
| 5 | 🗄 `SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND (table_name LIKE 'scoring%' OR table_name LIKE 'v_match%');` | 8 lignes : `scoring_live_state`, `scoring_live_clock`, `scoring_live_event`, `scoring_outbox`, `scoring_display_token`, `scoring_display_settings`, `v_match_detail`, `v_match_live` | ☑ |
| 6 | 🗄 `SHOW COLUMNS FROM kp_match LIKE 'uid';` | 1 ligne (colonne additive du lot 1) | ☑ |

> ⚠️ **À refaire après chaque rechargement de la base de dev** depuis une sauvegarde de prod (c'est ce qui
> a effacé les tables entre octobre et novembre). Si un script casse : lire l'erreur et corriger la
> migration, **jamais** la base à la main.

### R0.3 ⌨️ Tests automatisés (du moins coûteux au plus coûteux)

```bash
docker exec kpi_node_app4 sh -c "npm run lint"   # (make app4_lint exige un terminal interactif)
make app4_test
make api2_test_unit
make api2_test_integration                        # recrée kpi_fixtures_test à chaque fois
```

| # | Commande | Résultat attendu | ☑ |
|---|---|---|---|
| 7 | lint app4 | aucune erreur | ☑ |
| 8 | `make app4_test` | `Test Files 18 passed`, `Tests 212 passed` (dont `sseStream`, `scoringRules`, `scoringRoster`, `scoringReadiness`, `scoringTimeline`, `scoringShortcuts`, `scoringLiveConnection`) — *chiffres relevés le 2026-11-07 (après ajout de `sseStream`)* | ☐ |
| 9 | `make api2_test_unit` | `OK (335 tests …)` | ☑ |
| 10 | `make api2_test_integration` | `Tests: 340`, aucune *failure* (2 *deprecations* connues, hors scoring) — *ré-exécuter : `ScoringStatusSyncTest` ajouté le 2026-11-06* | ☐ |

### R0.4 🌐 Premier contact

Connexion app4 avec un **profil ≤ 2** (login dev `42054`), puis `https://kpi.localhost/admin2/games` → bouton
« Scoring » d'un match de test **non verrouillé** d'un événement en cours.

| # | Action | Résultat attendu | ☑ |
|---|---|---|---|
| 11 | Ouvrir la console | la page s'affiche (pas de 500) ; `make api2_logs lines=50` sans `TableNotFoundException` | ☑ |
| 12 | Console du navigateur | aucune erreur rouge | ☑ |

---

## R1 — Porte de validation en dev (V1 → V3)

**Où va un nouveau test** (rappel [CLEAN_CODE.md](../guides/CLEAN_CODE.md) : le test s'écrit **avant** le code) :

| Ce qu'on ajoute | Où le test va | Commande |
|---|---|---|
| une règle de jeu (période, carton, pénalité, chronomètre de tir) | **les deux** : `sources/api2/tests/Unit/Scoring/ScoringRulesTest.php` **et** `sources/app4/tests/unit/scoringRules.spec.ts` — suites jumelles (plan §4.15) | `make api2_test_unit` + `make app4_test` |
| un helper pur d'app4 (`utils/`) | `sources/app4/tests/unit/<helper>.spec.ts` | `make app4_test` |
| un service api2 sans base | `sources/api2/tests/Unit/<Domaine>/` | `make api2_test_unit` |
| une route api2 | `sources/api2/tests/Integration/` + lignes dans `SQL/fixtures/` | `make api2_test_integration` |
| un critère d'affichage d'un composant | test de rendu `@nuxt/test-utils` (⚠️ **pas encore en place sur app4** — dette V5) | `make app4_test` |

> **Ajouter un test qui touche une nouvelle table `kp_*`** : l'ajouter à `SQL/fixtures/schema.sql` (et ses
> lignes à `data.sql`), sinon l'endpoint répond **500** `TableNotFoundException`. Les fixtures sont un
> sous-ensemble volontairement minimal du schéma réel.

### V1 — La base existe vraiment

Couverte par R0.2 et R0.3. ☐

### V2 — Un match complet, de bout en bout

Jouer sur **un même match de test** les tests du lot 1 (1.1 → 1.11) puis ceux du lot 3 (dont 3.74 → 3.86), **jusqu'à la
clôture** (3.59 → 3.66).
**Attendu** : pendant le match, seules les tables `scoring_live_*` bougent ; à la clôture, `kp_match` /
`kp_match_detail` reçoivent la consolidation, et la feuille de match PDF affiche le même déroulement que
la console. ☐

### V3 — La diffusion marche

Tests **2.1 → 2.8**, puis **3.40 → 3.46** (deux terminaux), puis **4.1 → 4.13** (incrustation ouverte en
parallèle).
**Attendu** : `scoring_outbox` se vide dans l'ordre, la console d'un second terminal et la page
d'incrustation reflètent la saisie sans rechargement. ☐

---

### Lot 1 — État canonique `scoring_live_*`

| # | Test | Attendu | Exécuté le | ☑ |
|---|---|---|---|---|
| 1.1 | Saisir un but, un carton, lancer/arrêter le chrono, changer de période, donner le coup d'envoi | chaque action répond `success` ; l'UI se met à jour | | ☑ |
| 1.1a | Console du navigateur ouverte pendant toute la saisie | **aucune erreur** ; en particulier pas de `<SelectItem /> must have a value prop that is not an empty string` ni de `Must be called at the top of a \`setup\` function` | | ☑ |
| 1.1b | Badge de statut | affiché **une seule fois** (sur le tableau de score) ; **cliquable uniquement « En attente »** (coup d'envoi) ; « En cours » et « Terminé » ne réagissent pas au clic. L'entête n'affiche plus qu'un badge **Verrouillé**, et seulement si le match l'est | | ☑ |
| 1.1c | **Titre de l'onglet** du navigateur | « Scoring — Match N°{numéro} · Terrain {n} · {équipe A} - {équipe B} » : le **numéro de match**, pas l'id interne | | ☑ |
| 1.1d | Depuis **Matchs**, cliquer « Scoring » sur un match, puis sur **un autre** | **un seul onglet** de console, réutilisé | | ☑ |
| 1.1e | **Mode sombre** sur toute la page | aucun texte délavé (entête, score, effectifs, historique, chronos, pénalités, panneau de clôture, sélecteur par numéro) | | ☑ |
| 1.2 | 🗄 `scoring_live_state`, `scoring_live_clock` (kind `GAME`), `scoring_live_event` | les lignes reflètent la saisie ; **`kp_match` / `kp_match_detail` / `kp_chrono` ne bougent PAS** pendant le match | | ☑ |
| 1.2a | 🗄 `scoring_live_clock` : arrêter le chrono sur un dixième non nul (ex. `07:42.3`) | `elapsed_ms` enregistré **au dixième** (`…300`) ; idem `SHOTCLOCK` et `PENALTY` | | ☑ |
| 1.2b | Recharger la page chrono en marche | le temps restauré conserve le dixième | | ☑ |
| 1.2c | Arrêter sur `09:06.5`, **rouvrir dans un 2ᵉ onglet**, **relancer** depuis cet onglet | repart **exactement de 09:06.5** | | ☑ |
| 1.2d | **5 arrêts/relances** en notant `elapsed_ms` | partie décimale **stable** ; idem pénalités | | ☑ |
| 1.2e | Pause inter-période (`kind='BREAK'`) | décompte à la seconde, indicatif — pas de dixième attendu (spec §6.4) | | ☑ |
| 1.3 | 🗄 `scoring_outbox` | une ligne par écriture, `topic` = `/scoring/event/{e}/pitch/{p}/…` (ou `/scoring/match/{id}/…`) | | ☑ |
| 1.4 | `GET /api2/admin/scoring/state/{id}` (avec JWT) | état complet + `ETag` ; re-GET avec `If-None-Match` → **304** | | ☑ |
| 1.5 | Recharger la page en cours de match ; ouvrir le même match dans un 2ᵉ onglet | statut/période/score et chrono restaurés depuis l'état live | | ☑ |
| 1.6 | **Clore le match** (bouton « Clore le match… », cf. 3.59+) | **consolidation** : `kp_match` (Statut/Periode/ScoreDetail/Heure_fin/Commentaires_officiels) et `kp_match_detail` reconstruits depuis le live ; fiche/PDF/classements corrects | | ☑ |
| 1.7 | `PUT /api2/admin/scoring/source/{id}` avec `{"source":"HARDWARE"}` puis re-saisir | **409** « Another source is active » + `kp_journal` « Scoring rejeté (source) » ; re-promouvoir `MANUAL` → la saisie repasse | | ☐ |
| 1.8 | 🗄 `kp_journal` | une ligne par action mutante (statut, période, score, événement, chrono, joueur, officiels, commentaires, source, consolidation) | | ☑ |
| 1.9 | Ouvrir un **match saisi autrefois en legacy** | l'historique s'affiche ; à la première modification, les faits sont importés dans `scoring_live_event` | | ☑ |
| 1.10 | Match **verrouillé** et appel **non authentifié** | 400 « Game locked » / 401 | | ☐ |
| 1.11 | Legacy intact : FMV3 et `app_wsm` (`/api/wsm/*`) sur un autre match | fonctionnent comme avant | | ☐ |

### Lot 2 — Diffusion Mercure (outbox → worker → hub)

```bash
make docker_dev_up                                       # l'env du worker porte MERCURE_URL / MERCURE_JWT_SECRET
docker logs -f ${APPLICATION_NAME}_event_cache_worker    # surveiller le drainage
```

**Observer la diffusion** : banc **app4 → Opérations → Mercure**, champ **Topics** (un ou plusieurs
topics réels, séparés par des espaces ; seule la *publication* du banc est confinée à `test/`). Chaque
message affiche son **type** (`event:`) et son **id**. Solution de repli en ligne de commande (dev,
abonnement anonyme autorisé) :

```bash
# tout le terrain ({type} = gabarit) ; ou plusieurs ?topic=…&topic=…
curl -kN 'https://kpi.localhost/api2/.well-known/mercure?topic=/scoring/event/229/pitch/1/{type}'
# reprise après coupure : EN-TÊTE Last-Event-ID (le paramètre ?lastEventID= est ignoré par le hub 1.13)
curl -kN -H 'Last-Event-ID: urn:kpi:scoring:575' 'https://kpi.localhost/api2/.well-known/mercure?topic=/scoring/event/229/pitch/1/{type}'
```

| # | Test | Attendu | Exécuté le | ☑ |
|---|---|---|---|---|
| 2.0 | `docker inspect kpi_event_cache_worker --format '{{.Config.Cmd}}'` puis `docker stats --no-stream kpi_event_cache_worker` à quelques minutes d'écart | commande avec **`--no-debug`** ; mémoire stable (~70 Mo). Sans ce drapeau, le profilage Doctrine du mode debug accumule chaque requête et le worker meurt en `Allowed memory size … exhausted` (corrigé le 2026-11-07) | 2026-11-07 | ☑ |
| 2.1 | Saisir, puis 🗄 `SELECT * FROM scoring_outbox ORDER BY id DESC LIMIT 5` | `published_at` renseigné en ~1 s | | ☑ |
| 2.2 | Banc (ou `curl`), topic `/scoring/event/{e}/pitch/{p}/score`, puis saisir un but | chaque changement arrive, id `urn:kpi:scoring:{n}`, type `state` (le topic `…/score` porte le bloc statut/période/score ; `…/clock`, `…/shotclock`, `…/penalty` portent le type `clock`, `…/fact` le type `fact`) | | ☑ |
| 2.3 | Topics `…/clock …/fact` (sans `…/score`), puis saisir un but et lancer le chrono | seuls les topics demandés reçoivent : messages `clock` et `fact`, aucun message `state` (topic `…/score` non demandé) | | ☑ |
| 2.4 | **Coupure abonné** : noter le dernier id reçu, **Se déconnecter** (ou Ctrl+C sur `curl`), saisir 30 s, puis « Reprendre depuis un id » → **Se connecter** (ou `curl -H 'Last-Event-ID: …'`) | le hub **rejoue** les messages manqués, dans l'ordre, badgés « Rejoué » ; `curl -D -` montre l'en-tête `mercure-last-event-id`. ⚠️ avec `?lastEventID=` dans l'URL **rien** n'est rejoué (paramètre ignoré par le hub 1.13, constaté le 2026-11-07). Un id **absent de l'historique** (sorti des `size` derniers updates, ou fichier `mercure.db` recréé) ne rejoue **rien** : partir d'un id reçu pendant la session | | ☑ |
| 2.5 | **Coupure hub** : `make api2_restart` pendant des saisies | écritures jamais bloquées ; l'outbox s'accumule puis se draine dans l'ordre ; un abonné qui reprend avec `Last-Event-ID` reçoit aussi ce qui a été publié avant le redémarrage (mesuré le 2026-11-07 — la note « ne survit pas au redémarrage » de juillet ne vaut plus) | | ☑ |
| 2.6 | **Arrêt worker** : `docker stop kpi_event_cache_worker`, saisir, redémarrer | rattrapage complet | | ☑ |
| 2.7 | Sans **aucune** config Event Cache Manager active | le drainage fonctionne quand même | | ☑ |
| 2.8 | Après 1 h | les lignes publiées anciennes sont purgées | | ☐ |

### Lot 3 — Console Scoring

```bash
make app4_dev                  # si le serveur dev n'est pas déjà lancé
```

#### Périodes, cartons, configuration

| # | Test | Attendu | Exécuté le | ☑ |
|---|---|---|---|---|
| 3.1 | Match **type E** à égalité, « Période suivante » depuis M2, puis P1, P2… | P1, P2, **P3, P4… sans plafond** tant que le score est à égalité | | ☐ |
| 3.2 | Match **type E** avec un vainqueur à la fin de M2 | pas de prolongation proposée | | ☐ |
| 3.3 | Match **type C** | M1 → M2 seulement ; jamais de prolongation ; TB absent | | ☐ |
| 3.4 | Passer en P1 | le chrono se re-prime à **5 min** | | ☐ |
| 3.4a | Modale de changement de période | le bouton de confirmation dit « **Passer en {période}** » (et non « Enregistrer ») | | ☐ |
| 3.5 | **But en or** en P{n} d'un match E | modale « But en or … Clore le match ? » ; confirmer → ouvre le **panneau de clôture** (3.60) | | ☐ |
| 3.6 | **Progression des cartons** : jaune puis jaune ; jaune puis vert ; rouge puis n'importe quoi ; noir en premier | alertes « carton identique/inférieur », « joueur déjà exclu » ; premier jaune/rouge sans alerte ; noir à tout moment ; bouton « **Enregistrer quand même** » force la saisie | | ☐ |
| 3.7 | Carton `D` | « **Carton noir (exclusion définitive)** », bouton neutre, token **⬛** | | ☐ |
| 3.8 | Zone de saisie d'un carton | motif pré-sélectionné « **Autre/Non précisé** » ; les buts n'ont pas de motif | | ☐ |
| 3.9 | Sélecteur de période (édition post-match) | M1/M2 + prolongations utilisées +1 ; TB hérité sélectionnable | | ☐ |
| 3.10 | Édition d'un fait `P3` d'un match ancien | s'affiche et s'édite normalement | | ☐ |

#### Enchaînement de saisie

Un clic sur un bouton de fait **n'enregistre rien** : il *arme* le fait, place le **focus sur le temps**
(pré-rempli par le chrono, texte sélectionné) et attend une **validation explicite**.

| # | Test | Attendu | Exécuté le | ☑ |
|---|---|---|---|---|
| 3.10a | **But** puis joueur, **ou** joueur puis **But** (ordre libre à la souris) | rien n'est enregistré ; le fait armé et le joueur s'affichent en tête du panneau, le focus va sur le temps quand les deux sont choisis, bouton **Enregistrer : But** | | ☐ |
| 3.10b | Corriger le temps puis **`Entrée`** | fait enregistré **avec le temps corrigé** | | ☐ |
| 3.10c | Joueur → **Carton** → changer le **motif** → `Entrée` | le motif choisi est enregistré | | ☐ |
| 3.10d | **`Échap`** (ou **Désélectionner**) pendant une saisie | rien n'est enregistré ; fait, joueur et motif sont levés | | ☐ |
| 3.10e | `Entrée` dans le champ temps | valide le fait — **ne déclenche pas** le shotclock 60 s | | ☐ |
| 3.10f | Clic sur un fait de l'historique | ligne surlignée, joueur re-sélectionné, focus sur le temps, bouton **Enregistrer** | | ☐ |
| 3.10g | Modifier le temps puis **Enregistrer** | mise à jour **sur place** (pas de doublon) | | ☐ |
| 3.10h | Temps **en chiffres seuls** : `750`, `0800`, `139`, `12` | → `07:50`, `08:00`, `01:39`, `00:12` | | ☐ |
| 3.10i | `09:09` ou `0170` | `09:09` inchangé ; `0170` → `02:10` | | ☐ |

#### Chrono, shotclock, pauses, raccourcis

| # | Test | Attendu | Exécuté le | ☑ |
|---|---|---|---|---|
| 3.11 | Démarrer le **chrono principal** | le shotclock reste à `--` | | ☐ |
| 3.11a | **60 s / 40 s chrono principal arrêté** | le shotclock charge la valeur mais ne défile pas (ambre) | | ☐ |
| 3.12 | **60 s** (ou `Entrée`) puis **40 s** (ou `.`) | charge 60 et décompte ; re-appuyer recharge | | ☐ |
| 3.13 | **Arrêter le chrono** pendant que le shotclock tourne | le shotclock se **suspend** ; relancer → il repart | | ☐ |
| 3.14 | **Arrêt** (ou `0`) | retour à `--` | | ☐ |
| 3.15 | Shotclock à **0** | buzzer ; reste à 0 | | ☐ |
| 3.16 | Shotclock **±1 s** | actifs uniquement chrono arrêté | | ☐ |
| 3.17 | 🗄 `SELECT * FROM scoring_live_clock WHERE kind='SHOTCLOCK'` puis recharger | ligne persistée ; même état après rechargement | | ☐ |
| 3.18 | Chrono principal à **0** en fin de M1 | buzzer + pause **3 min** (1 min entre prolongations) ; buzzer en fin de pause ; « Terminer la pause » l'interrompt | | ☐ |
| 3.18a | Pendant la pause, **changer de période** | le décompte de pause **continue** ; le shotclock repasse à `--` | | ☐ |
| 3.18b | Pendant la pause, **lancer le chrono** | la pause disparaît ; ligne `BREAK` supprimée | | ☐ |
| 3.18c | **−10 / −1 / +1 / +10** du chrono principal, **chrono arrêté**, match **En cours** | dans le sens de l'**affichage** : `+1` ajoute une seconde au temps restant ; bornés à `00:00.0` et à la durée de la période ; une **rafale** de clics rapides compte chaque clic | | ☐ |
| 3.19 | **Raccourcis** : `Espace`, `Entrée` / `.` / `0` / `+` / `−` | agissent partout **sauf** dans un champ de saisie ; inactifs en post-match | | ☐ |
| 3.20 | Modale **Raccourcis clavier** : réassigner une touche déjà utilisée | l'autre action perd sa touche ; persiste au rechargement ; « Valeurs par défaut » restaure. La liste contient les **5 faits de jeu** (FR `B V J R N`, EN `G E Y R K`) | | ☐ |
| 3.21 | Temps de jeu restant **inférieur** au shotclock | le shotclock affiche `--` | | ☐ |

#### Pénalités

| # | Test | Attendu | Exécuté le | ☑ |
|---|---|---|---|---|
| 3.22 | Carton **vert/jaune/rouge** en direct | pénalité **2:00** affichée **sur le tableau de score, sous le score de l'équipe** (token + n° maillot), décompte suivant le chrono ; ligne `scoring_live_clock` kind `PENALTY` | | ☐ |
| 3.23 | Carton **noir** (`D`) | aucune pénalité ; message « exclusion définitive… » ; joueur marqué **⬛** | | ☐ |
| 3.24 | **3ᵉ carton** pour une équipe pendant 2 exclusions | avertissement « déjà 2 exclusions » ; aucune 3ᵉ horloge | | ☐ |
| 3.25 | **But encaissé** en infériorité (pénalité V/J) | modale « lever la pénalité la plus ancienne ? » ; confirmer → l'horloge disparaît + RAZ en base | | ☐ |
| 3.26 | But encaissé avec **seulement une pénalité `R`** | aucune proposition de levée | | ☐ |
| 3.27 | Pénalités `R` (plus ancienne) **+** `J`, but encaissé | c'est la **`J`** qui est proposée | | ☐ |
| 3.28 | **Expiration** d'une pénalité | buzzer + message : V/J « le joueur revient » ; R « remplacement autorisé » ; horloge retirée | | ☐ |
| 3.29 | **Rechargement** avec pénalités en cours | les décomptes reviennent dans le bon état | | ☐ |
| 3.30 | Croix de **retrait manuel** sur une pastille de pénalité du tableau de score | horloge retirée + RAZ en base | | ☐ |
| 3.31 | Joueur avec carton `R` / `D` | marqué **🔴** / **⬛** dans l'effectif | | ☐ |

#### Affichages plein écran, PWA

> La PWA ne s'active **qu'en build** (service worker désactivé en dev). Pour 3.36+ :
> `make app4_generate_dev` puis ouvrir `https://kpi.localhost/admin2/`.

| # | Test | Attendu | Exécuté le | ☑ |
|---|---|---|---|---|
| 3.32 | Menu **⋯ → Ouvrir le tableau de score** | `/games/{id}/scoreboard` : équipes, score, période, chrono, shotclock, pénalités **immédiatement** | | ☐ |
| 3.33 | Saisir but / chrono / shotclock / pénalité | le tableau de score suit **en direct**, même Wi-Fi coupé | | ☐ |
| 3.34 | Menu **⋯ → Ouvrir le chronomètre de tir** | chiffre plein écran, blanc sur noir ; atténué suspendu/arrêté | | ☐ |
| 3.34a | Redimensionner / faire pivoter | le chiffre se remet à l'échelle sans débordement | | ☐ |
| 3.34b | Sous **10 s** | dixièmes, taille constante | | ☐ |
| 3.34c | `?theme=light` puis `?clock=0` | noir sur blanc ; chrono principal masqué | | ☐ |
| 3.34d | Scoreboard en **portrait** | disposition colonne | | ☐ |
| 3.35 | Deux matchs, deux consoles + affichages | chaque affichage ne reçoit que **son** match | | ☐ |
| 3.36 | Console **buildée** sur tablette | installable ; s'ouvre en plein écran paysage | | ☐ |
| 3.37 | Nouvelle version déployée pendant que la console est ouverte | bandeau « Une nouvelle version est disponible » + Recharger ; **pas** de rechargement automatique | | ☐ |
| 3.37a | Bandeau affiché, continuer à saisir | la saisie fonctionne | | ☐ |
| 3.37b | `grep -o '.\{40\}skipWaiting.\{40\}' sources/app4/.output/public/sw.js` | `skipWaiting()` seulement dans un `addEventListener("message", …)` | | ☐ |
| 3.38 | Couper le réseau puis relancer la PWA installée | l'application démarre ; les données du match exigent le réseau | | ☐ |
| 3.39 | En **dev** | aucun service worker enregistré | | ☐ |

#### Console abonnée à Mercure (multi-terminal)

| # | Test | Attendu | Exécuté le | ☑ |
|---|---|---|---|---|
| 3.40 | Ouvrir la console en mode direct | badge **antenne verte** dans l'entête | | ☐ |
| 3.41 | Même match sur un **2ᵉ terminal**, y saisir un but | le 1ᵉʳ se met à jour en ~1 s ; le badge clignote | | ☐ |
| 3.42 | Chrono depuis le 2ᵉ terminal | le 1ᵉʳ reprend le chrono synchronisé ; idem shotclock et pause | | ☐ |
| 3.43 | Rafale sur le 1ᵉʳ terminal seul | **aucun** `GET /state` parasite (onglet Réseau) | | ☐ |
| 3.44 | Couper le réseau 30 s puis le rétablir | badge orange aussitôt, **bandeau rouge « Connexion perdue »** (immédiat si le navigateur se sait hors ligne, sinon après **10 s** sans réabonnement) ; retour au vert, bandeau retiré, état resynchronisé (onglet Réseau : un `GET /state` au réabonnement) | | ☐ |
| 3.44a | Console ouverte **15 min** en direct sans rien toucher | `make api2_logs lines=50` montre « Subscriber disconnected » puis « New subscriber » ~5 s après (recyclage normal du hub) ; le badge passe brièvement orange, **le bandeau rouge n'apparaît pas** | | ☐ |
| 3.45 | Source `HARDWARE` depuis un autre client, puis écrire | **409** et reflet des changements de la source active | | ☐ |
| 3.46 | Match **hors événement** | topic de repli `/scoring/match/{id}/{type}` | | ☐ |

#### Mode score seul, vue Paramètres

| # | Test | Attendu | Exécuté le | ☑ |
|---|---|---|---|---|
| 3.47 | Mode **« Score seul »** | listes et zone de saisie disparaissent ; **±1** de part et d'autre du score ; pénalités en cours toujours visibles sur le tableau de score ; `active_source = SCORE_ONLY` | | ☐ |
| 3.48 | **+1** puis **−1** | un `GOAL` avec `id_player = '0'` créé puis supprimé | | ☐ |
| 3.49 | Revenir en mode complet | source `MANUAL` ; listes et saisie reviennent | | ☐ |
| 3.50 | Onglet **Paramètres** | bascule vers la vue Paramètres | | ☐ |
| 3.51 | Type de match et publication | en **lecture seule** + « modifiable depuis la gestion du match » | | ☐ |
| 3.52 | Modifier un **officiel** puis **Enregistrer** | `kp_match` mis à jour, `kp_journal` « Scoring officiels », message de confirmation | | ☐ |
| 3.53 | Modifier un **n° de maillot** et un **statut** (liste « — / Capitaine / Staff », colonne « Statut ») | `kp_match_joueur` mis à jour | | ☐ |
| 3.54 | **Supprimer** un joueur puis **« Recharger les présents »** | composition réinitialisée depuis la feuille de présence | | ☐ |
| 3.55 | Charger par **n° court** | résolution serveur et navigation | | ☐ |
| 3.56 | Charger par **ID#** | navigation directe | | ☐ |
| 3.57 | N° court inconnu ou ambigu | message « aucun match trouvé… », aucune navigation | | ☐ |
| 3.58 | Match **verrouillé** | vue Paramètres en lecture seule | | ☐ |

#### Revue UX du 2026-11-05 — clôture, sûreté de saisie, vitesse, clarté

Spec : [PAGE_SCORING.md](../../specs/PAGE_SCORING.md) §7.1, §7.2, §7.3, §7.6 (encarts « 2026-11-05 »).

| # | Test | Attendu | Exécuté le | ☑ |
|---|---|---|---|---|
| 3.59 | Match « En attente » : cliquer le badge | passe **En cours**, période **M1** ; cliquer à nouveau le badge « En cours » ne fait **rien** (plus de clôture accidentelle) | | ☐ |
| 3.60 | Bouton **« Clore le match… »** (sur le tableau de score) | panneau : score affiché / buts saisis par équipe, **heure de fin = heure courante**, commentaires (pré-remplis s'il y en a), case « Verrouiller » **seulement** pour un profil autorisé à valider | | ☐ |
| 3.61 | 🗄 `UPDATE scoring_live_state SET score_a = score_a + 1 WHERE id_match = {id}`, recharger, ouvrir la clôture | écart signalé en ambre ; « **Aligner le score sur les buts** » remet le score affiché au nombre de buts | | ☐ |
| 3.62 | Vider l'heure de fin | bouton « Clore le match » désactivé | | ☐ |
| 3.63 | Clore avec chrono et shotclock **en marche** et un commentaire | chronos arrêtés, statut **Terminé**, console en **post-match**, message « Match clos » ; 🗄 `kp_match` : `Statut='END'`, `Heure_fin`, `Commentaires_officiels` ; `kp_journal` « Scoring commentaires » + « Scoring consolidation » ; PDF de la feuille : heure de fin et commentaires | | ☐ |
| 3.64 | Clore avec **« Verrouiller »** coché | `Validation='O'`, console en lecture seule, badge **Verrouillé** | | ☐ |
| 3.65 | Match terminé non verrouillé : **« Rouvrir le match… »** | confirmation, puis **En cours** ; `kp_match` garde le résultat de la 1ʳᵉ clôture **jusqu'à la nouvelle clôture** (attendu) | | ☐ |
| 3.66 | But en or (3.5) → « Clore le match » | le panneau de clôture s'ouvre (pas de clôture directe) | | ☐ |
| 3.67 | Chrono principal **en marche** : boutons −10/−1/+1/+10 | **grisés**, info-bulle « Arrêtez le chrono pour le corriger » ; actifs dès l'arrêt | | ☐ |
| 3.68 | Enregistrer un but | message « Fait de jeu enregistré » (fait · joueur · temps) avec **Annuler** pendant 6 s ; **Annuler** → but retiré, score revenu, message « Saisie annulée » | | ☐ |
| 3.68a | Enregistrer un carton jaune puis **Annuler** | le fait **et** sa pénalité disparaissent ; 🗄 ligne `PENALTY` remise à zéro | | ☐ |
| 3.68b | But encaissé qui ouvre la modale de levée (3.25), puis **Annuler** depuis le message | le but est retiré **et** la modale se ferme | | ☐ |
| 3.69 | Effectifs après quelques faits | à côté du joueur : ⚽ (et **×2** au 2ᵉ but), dernier carton V/J ; pour R/D seul le marqueur d'exclusion | | ☐ |
| 3.70 | *(retiré le 2026-11-06 : la touche `N` « joueur par numéro » est remplacée par la saisie clavier 3.80 → 3.84)* | — | | — |
| 3.71 | Historique en **direct** avec plus de 3 faits | seuls les **3 plus récents** (le temps est un **compte à rebours** : plus petit temps restant = plus récent) + « Tout voir (n) » ; en **post-match** tout est affiché | | ☐ |
| 3.71a | Faits en **P3** et au-delà | triés après P2 (la table de tri s'arrêtait à P2) | | ☐ |
| 3.72 | Vue **Paramètres** | encadré « **À vérifier avant le coup d'envoi** » (ou « Feuille de match prête ») | | ☐ |
| 3.72a | Équipe à **4 joueurs** ; à **9 joueurs** ; avec **4 membres du staff** (statut Staff) | « au moins 5 » ; « 8 au maximum » ; « 3 au maximum » — le **staff ne compte pas** comme joueur (mention « staff non compté ») | | ☐ |
| 3.72b | Deux joueurs au **même numéro** ; aucun **capitaine** | « numéros en double (n) » ; « aucun capitaine désigné » | | ☐ |
| 3.72c | Arbitre secondaire ou chronométreur vides | « Officiels à renseigner : … » (juges de ligne et chronométreur de tir facultatifs) | | ☐ |
| 3.72d | Vue **Déroulement**, match **En attente** avec des points à vérifier | bandeau ambre « n point(s) à vérifier sur les paramètres du match avant le coup d'envoi » + « Voir les paramètres » ; disparaît au coup d'envoi ; le coup d'envoi n'est **jamais** bloqué | | ☐ |
| 3.73 | Mode avion (ou `docker stop kpi_api2`) en direct | bandeau rouge « **Connexion perdue** » sous l'entête (mode avion : immédiat ; `docker stop` : après 10 s) ; disparaît au retour | | ☐ |

#### Révision du 2026-11-06 — ordre de saisie, statut du match, Paramètres

Spec : [PAGE_SCORING.md](../../specs/PAGE_SCORING.md) §0.12.

| # | Test | Attendu | Exécuté le | ☑ |
|---|---|---|---|---|
| 3.74 | Ouvrir un match dont une composition est **vide** (feuille de présence remplie) | la composition se charge **toute seule** depuis la feuille de présence + message « Composition de … chargée » ; match verrouillé : rien n'est chargé | | ☐ |
| 3.75 | Vue Paramètres, compositions | colonne **« Statut »**, valeurs **« Capitaine » / « Staff »** en toutes lettres ; le **staff est listé à part**, sous un intertitre « Staff » ; son **numéro reste modifiable** et fixe l'ordre du staff (la ligne se déplace aussitôt) ; passer un joueur en Staff le déplace dans ce groupe. Dans Déroulement, un membre du staff porte le badge **Staff** | | ☐ |
| 3.76 | Champ d'un **officiel** rempli : croix à droite | le champ se vide, le **focus** y revient et la liste des suggestions (équipe d'arbitrage) s'ouvre ; champ vide ou match verrouillé : pas de croix | | ☐ |
| 3.77 | Match **En attente** avec des points à vérifier : flèche **Réduire** du bandeau | il devient une petite pastille ambre avec le nombre ; un clic dessus le **ré-affiche** (le bandeau n'existe que pour un match En attente) | | ☐ |
| 3.78 | Tableau de score : ordre de la ligne d'état | **statut + Clore/Rouvrir** d'abord, **période** ensuite | | ☐ |
| 3.79 | Match **En attente** (ou **Terminé**) en direct | **Démarrer**, ±1/±10, remise à zéro, **60 s / 40 s / Arrêt** et sélecteur de **période** sont **masqués** (les chiffres du chrono restent sur le tableau de score) ; raccourcis `Espace`, `Entrée`, `.`… sans effet ; tout réapparaît au coup d'envoi | | ☐ |
| 3.79a | ⚠️ après `make api2_restart` : match **En cours** à la console, puis **Matchs** → passer son statut à **En attente** ; revenir à la console | la console affiche **En attente** (en direct si l'abonnement Mercure est actif, sinon au rechargement) ; 🗄 `scoring_live_state.statut = 'ATT'` ; le bandeau « points à vérifier » réapparaît s'il y en a | | ☐ |
| 3.79b | Depuis **Matchs**, passer à **Terminé** un match saisi à la console | `kp_match_detail` reconstruit depuis la console (consolidation), comme une clôture à la console | | ☐ |
| 3.79c | Chrono principal **arrêté** ou **à zéro** | chiffres **rouges** ; **verts** quand il tourne (les affichages plein écran ne changent pas) | | ☐ |
| 3.80 | Clavier, focus hors champ : **`B`** (EN `G`) | le but est armé, le focus va dans **« N° du joueur »** | | ☐ |
| 3.80a | Taper un numéro présent **dans une seule équipe** | le joueur est surligné (ambre) dans l'effectif ; **`Entrée`** le choisit et donne le focus au **temps** ; **`Entrée`** enregistre (flux complet : `B 7 ↵ ↵`) | | ☐ |
| 3.80b | Numéro présent **dans les deux équipes** | les deux surlignés + « ← équipe A, → équipe B » ; **`←`** ou **`→`** garde le bon, puis `Entrée` | | ☐ |
| 3.80c | Numéro absent ; numéro `0` d'un membre du staff | « Aucun joueur avec ce numéro » ; le staff n'est jamais proposé | | ☐ |
| 3.81 | Clavier : **`J`** (carton jaune) | la **liste des motifs s'ouvre** ; choisir un motif (ou `Échap` pour garder « Autre / Non précisé ») → focus sur « N° du joueur » | | ☐ |
| 3.81a | Souris : clic sur un **carton** | la liste des motifs s'ouvre aussi ; elle reste facultative | | ☐ |
| 3.82 | Chrono **en marche**, choisir un fait **ou** un joueur | le **temps du fait se fige** à cet instant (mention « Temps figé… ») alors que le chrono continue ; **Désélectionner** le resynchronise sur le chrono | | ☐ |
| 3.83 | Bouton **Désélectionner** (en tête du panneau, dès qu'un fait ou un joueur est choisi) | tout est levé (fait, joueur, motif, édition) ; le temps suit de nouveau le chrono ; en direct, la période repasse sur celle du match | | ☐ |
| 3.84 | Interface en **anglais**, raccourcis jamais modifiés sur le poste | faits sur `G E Y R K` ; en français `B V J R N` ; `Ctrl+B`/`Cmd+…` gardent leur sens | | ☐ |
| 3.84a | Boutons de fait de jeu | chacun affiche **sa touche** à droite (sous le libellé sur smartphone) ; après réassignation dans Raccourcis clavier, la nouvelle touche s'affiche | | ☐ |
| 3.85 | Chrono **arrêté** : clic sur une pastille de **pénalité** (hors croix) | panneau −10/−1/+1/+10 ; le décompte change, 🗄 ligne `PENALTY` mise à jour ; chrono **en marche** : boutons désactivés + « Arrêtez le chrono pour le corriger » | | ☐ |
| 3.86 | Libellés de période | « **1ère période** » / « **2ème période** » (EN « 1st / 2nd period ») au lieu de « mi-temps » | | ☐ |
| 3.87 | Match **En attente**, puis **Terminé** | panneau de saisie remplacé par « Donnez le coup d'envoi… » / « Match terminé : rouvrez-le… » ; boutons de faits, effectifs, lignes et corbeilles de la chronologie inactifs ; touches `B V J R N` sans effet ; ±1 du mode score seul inactifs | | ☐ |
| 3.87a | Saisie armée (fait + joueur), puis clôture du match (ou passage En attente depuis Matchs) | la saisie est levée, rien n'est enregistré | | ☐ |
| 3.87b | Match terminé → **Rouvrir** → corriger un fait → **Clore** | la correction est enregistrée et `kp_match_detail` reconsolidé | | ☐ |
| 3.88 | Clic sur un fait de la **chronologie** | sous **Enregistrer** : **Supprimer** et **Annuler** ; Supprimer → confirmation → fait retiré (score recalculé pour un but) ; Annuler → rien ne change, le panneau se vide | | ☐ |
| 3.88a | Nouvelle saisie armée | bouton **Annuler** sous « Enregistrer : … » (même effet que Désélectionner) | | ☐ |
| 3.89 | Match **En attente** | pas de bouton « Clore le match… » (seulement pour un match En cours) | | ☐ |
| 3.89a | Clore un match à 16:42, le **rouvrir** vers 17:05, corriger, **clore** à nouveau | le panneau propose **16:42** (heure déjà enregistrée), pas l'heure courante ; 🗄 `kp_match.Heure_fin` reste `16:42:00` | | ☐ |
| 3.90 | Chronologie de la console (match E avec prolongations) puis **feuille de match PDF** (api2), en **français** puis en **anglais** | périodes **P1**, **P2**, puis **PRL1**, **PRL2**… en français, **OVT1**, **OVT2**… en anglais ; dans le PDF, « PRL2 - 00:53 » tient en entier dans la colonne Période ; 🗄 les codes stockés restent `M1`/`M2`/`P1`… | | ☐ |

### Lot 4 — Page d'incrustation unique

Les migrations du lot 4 sont dans R0.2. Puis :

```bash
make api2_restart          # contrôleur public + services programme/accès
make docker_dev_up         # le worker publie le programme des terrains
```

**Créer un jeton d'affichage : app4 → TV → onglet « Incrustations »** (événement choisi dans l'onglet
Chaînes), puis « Copier l'URL ». En SQL si besoin :

```sql
INSERT INTO scoring_display_token (token, id_event, pitch, label, expires_at)
VALUES (SHA2(UUID(), 256), 236, NULL, 'Régie test', DATE_ADD(NOW(), INTERVAL 7 DAY));
SELECT token FROM scoring_display_token WHERE label = 'Régie test';
```

URL type (source navigateur OBS, 1920×1080) :
`https://kpi.localhost/admin2/live/overlay?event=236&pitch=2&token=<jeton>&blocks=score,clock,shotclock,penalty,fact,next&debug`

| # | Test | Attendu | Exécuté le | ☑ |
|---|---|---|---|---|
| 4.1 | URL **avec jeton**, fenêtre privée | l'incrustation s'affiche sans session | | ☐ |
| 4.1a | Sans `token` ou jeton inventé | **401** + « Jeton d'affichage invalide, expiré ou révoqué » | | ☐ |
| 4.1b | 🗄 `UPDATE scoring_display_token SET revoked_at = NOW()` puis recharger | accès **coupé immédiatement** (idem `expires_at` dépassé) | | ☐ |
| 4.1c | Jeton `pitch='2'` utilisé sur `pitch=3` | 401 | | ☐ |
| 4.1d | `?debug` : état SSE | passe à `live` ; ⚠️ **c'est ce test qui compte en preprod/prod** (`MERCURE_ANONYMOUS=0`) | | ☐ |
| 4.2 | `GET /api2/scoring/program/236/2?token=…` et `GET /api2/scoring/state/{id}?token=…` | JSON + `ETag` ; `If-None-Match` → **304** ; `/program` en `Cache-Control: private` | | ☐ |
| 4.2a | En-tête `Origin: https://exemple.tld` | **403** | | ☐ |
| 4.3 | Saisir à la console | l'incrustation suit en ~1 s | | ☐ |
| 4.4 | Couper le réseau de la machine d'incrustation 30 s | les horloges continuent ; recalage au retour | | ☐ |
| 4.5 | Recharger (ou redémarrer OBS) | état correct en une requête | | ☐ |
| 4.6 | **Clore** le match courant | score final après le délai configuré, puis présentation du match suivant | | ☐ |
| 4.7 | Le match suivant passe **En cours** | bascule d'elle-même, sans rechargement | | ☐ |
| 4.8 | Latence de bascule | quasi immédiate | | ☐ |
| 4.9 | Deux événements, deux incrustations | chacune ne reçoit que son terrain | | ☐ |
| 4.10 | `blocks=score`, puis `blocks=fact` | seuls les blocs demandés | | ☐ |
| 4.11 | `bg=magenta` puis `bg=transparent` dans OBS | chromakey / alpha correct | | ☐ |
| 4.12 | 🗄 `scoring_display_settings` événement puis terrain | le terrain l'emporte ; `NULL` hérite | | ☐ |
| 4.13 | Comparer avec l'incrustation legacy du même terrain | mêmes valeurs — condition de bascule terrain par terrain | | ☐ |

**Compatibilité legacy ↔ live (vues)**

| # | Test | Attendu | Exécuté le | ☑ |
|---|---|---|---|---|
| 4.14 | **Pendant** un match saisi à la console, PDF `FeuilleMatchMulti.php` | buts/cartons, score, statut, période **à jour** | | ☐ |
| 4.15 | Même match : fiche publique / app2 | déroulement à jour en cours de match | | ☐ |
| 4.16 | Match jamais touché par la console | affichage inchangé | | ☐ |
| 4.17 | Après **clôture** | mêmes valeurs ; **aucun doublon** | | ☐ |
| 4.18 | Classements / exports | inchangés | | ☐ |

**Administration des incrustations (app4 → TV → Incrustations)**

| # | Test | Attendu | Exécuté le | ☑ |
|---|---|---|---|---|
| 4.19 | Onglet sans événement sélectionné | invite à choisir un événement | | ☐ |
| 4.20 | **Créer un jeton** (tous terrains, 7 jours) puis « Copier l'URL » | jeton listé ; l'URL fonctionne dans OBS | | ☐ |
| 4.21 | Jeton **restreint au terrain 2** | fonctionne sur `pitch=2`, refusé ailleurs | | ☐ |
| 4.22 | **Révoquer** pendant que l'incrustation tourne | « jeton révoqué » au prochain rafraîchissement ; ligne grisée | | ☐ |
| 4.23 | Colonne **Dernière utilisation** | mise à jour à la consommation | | ☐ |
| 4.24 | Délai **événement**, autres champs vides | seul le champ saisi est stocké | | ☐ |
| 4.25 | Même délai pour le **terrain 2** | le terrain 2 prend sa valeur, les autres héritent | | ☐ |
| 4.26 | Vider un champ réglé | retour à l'héritage (`NULL`) | | ☐ |
| 4.27 | 🗄 `kp_journal` | « Incrustation jeton créé / révoqué », « Incrustation réglages » | | ☐ |

---

## R2 — Dette à solder avant tout nouveau lot (V4 → V6)

| # | Dette | Ce qu'il faut produire | Où en est-on | ☑ |
|---|---|---|---|---|
| V4 | Routes d'**écriture** du scoring peu testées | tests d'intégration : source active, **409** sur source non active, incrément du `tick`, dépôt outbox dans la même transaction | **commencé** : `ScoringLiveScoreTest` (score live), `ScoringClosureTest` (commentaires, clôture + consolidation, match verrouillé), `ScoringStatusSyncTest` (statut posé depuis Matchs) | ☐ |
| V5 | Seuls les helpers purs d'app4 sont testés | `@nuxt/test-utils` + tests de rendu sur les critères de la spec (shotclock, pénalités, sélecteur de période, clôture, sélecteur par numéro) | la logique de la revue UX est extraite en helpers testés (`scoringRoster`, `scoringReadiness`) ; rendu non couvert | ☐ |
| V6 | Garde-fou du ménage (action 0.6) non chiffré ; vues de compatibilité non datées | critère écrit, chiffré et daté dans le plan | à faire | ☐ |

---

## R3 — Préproduction

Prérequis : R1 et R2 (V4) cochés. La branche est fusionnée dans `develop` par PR (l'utilisateur fait la PR) ;
le déploiement preprod est automatique, **pas les migrations SQL** (elles sont manuelles).

| # | Où | Commande / action | Résultat attendu | ☑ |
|---|---|---|---|---|
| R3.1 | 🌐 | PR vers `develop`, CI verte (`lint-nuxt (app4)`, `build-nuxt (app4)`, tests api2 unit + integration) | tous les jobs verts | ☐ |
| R3.2 | 🖥 | `cd /data/kpi_preprod && git log -1 --oneline` après le déploiement auto | le commit fusionné | ☐ |
| R3.3 | 🖥 | `make db_migrate file=SQL/migrations/<fichier>` pour les **4** migrations, dans l'ordre de R0.2 | 4 × `✔ … appliqué` | ☐ |
| R3.4 | 🖥 | `grep -c MERCURE_JWT_SECRET docker/.env sources/api2/.env` puis comparer les valeurs | identiques | ☐ |
| R3.5 | 🖥 | `make docker_preprod_up` (env du worker) puis `docker logs --tail 50 kpi_preprod_event_cache_worker` | drainage sans erreur 401/403 | ☐ |
| R3.6 | 🌐 | Rejouer **4.1d** sur `preprod` (incrustation avec jeton, `?debug`) | état SSE `live` **avec `MERCURE_ANONYMOUS=0`** — c'est la validation du lot 0.3 | ☐ |
| R3.7 | 🌐 | Rejouer 1.1, 1.5, 2.1, 3.41, 3.63 sur un match de test preprod | comme en dev | ☐ |
| R3.8 | 🌐 | Non-régression : FMV3 et `app_wsm` sur un autre match ; app2 ; site public | inchangés | ☐ |

## R4 — Production, terrain pilote

| # | Où | Commande / action | Résultat attendu | ☑ |
|---|---|---|---|---|
| R4.1 | 🌐 | PR `develop` → `main`, déploiement prod manuel (cf. [runbook](../infrastructure/DEPLOYMENT_RUNBOOK.md)) | déploiement OK | ☐ |
| R4.2 | 🖥 | Les 4 migrations sur `/data/kpi` (comme R3.3) | 4 × `✔` | ☐ |
| R4.3 | 🖥 | Comme R3.4 et R3.5 sur la prod | idem | ☐ |
| R4.4 | 🌐 | Un **terrain pilote** d'un vrai événement tenu à la console, **en parallèle** du dispositif legacy (FMV3 / WSM sur une autre table) | mêmes scores, même déroulement, aucune perte au rechargement ; feuille PDF identique après clôture | ☐ |
| R4.5 | 🌐 | Incrustation unique sur ce terrain dans OBS, comparée à l'incrustation legacy (4.13) | mêmes valeurs ; bascule possible terrain par terrain | ☐ |

## R5 — Lots suivants (non commencés)

- **Lot 5 — Relais matériel** : ⚠️ **bloqué par l'action 0.5** (captures de vraies sessions STOMP).
- **Lot 6 — Paramétrage par compétition**, **lot 7 — offline complet**, **lot 8 — ménage** (bloqué par V6),
  **lot 9 — zéro papier**.
