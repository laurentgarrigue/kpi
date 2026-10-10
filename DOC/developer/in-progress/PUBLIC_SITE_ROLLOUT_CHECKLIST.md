# Site public (app3) — checklist de test et de déploiement par phase

**Créée le** : 8 octobre 2026 — **À compléter à chaque phase** (une section par phase, cochée au fil de l'eau)
**Validée** jusqu'au § 3.4 inclus le 10/10/2026 (phases 1 et 2 complètes, phase 3 jusqu'à la préprod ; reste la production de la phase 3, § 3.5).
**Contexte** : [PUBLIC_SITE_REDESIGN_STRATEGY.md](plans/PUBLIC_SITE_REDESIGN_STRATEGY.md) ·
specs [DOC/specs/public/](../../specs/public/README.md) · socle [SITE_PLATFORM.md](../../specs/public/SITE_PLATFORM.md)

> **Convention** (comme le [runbook](../infrastructure/DEPLOYMENT_RUNBOOK.md)) : ⌨️ poste de dev ·
> 🖥 VPS (SSH, en tant que `laurent` sauf mention) · 🌐 interface GitHub · 🌍 hébergeur DNS ·
> 📦 autre dépôt (`vps-manager`, privé).

**Règle générale** : app3 n'est servi que sur `beta.*`. À chaque étape, on vérifie **aussi** que le site actuel,
app2, app4 et api2 n'ont pas bougé (section « Non-régression » de chaque phase).

---

## Phase 1 — Socle (template, menus, accueil, `beta.*`)

Branche : `claude/public_site_redesign_strategy` · Specs : SITE_PLATFORM, SITE_LAYOUT, SITE_NAVIGATION, PAGE_HOME.

### 1.1 ⌨️ Tester en local

```bash
git fetch origin && git checkout claude/public_site_redesign_strategy && git pull
make app3_npm_ci            # 1re fois : dépendances via un conteneur temporaire (node_modules à votre UID)
make docker_dev_up          # crée kpi_node_app3 (image docker/node) et démarre nuxt dev
make dev_status             # ligne « app3 site public (Nuxt) » → 200 (attendre ~15 s au 1er démarrage)
```

| # | Commande / action | Résultat attendu | ☑ |
|---|---|---|---|
| 1 | `make app3_logs` | `Nuxt … ready`, `Local: http://0.0.0.0:3003/`, aucune erreur | ☑ |
| 2 | Navigateur : `https://beta.kpi.localhost` | Bandeau bleu clair « Version bêta… », logo FFCK, barre de navigation marine, « Le kayak-polo en France », 6 événements récents (base de dev) | ☑ |
| 3 | Clic sur « Compétitions » puis Échap | Le sous-menu s'ouvre au clic, se ferme avec Échap ; entrées marquées d'une icône « lien externe » | ☑ |
| 4 | Clic sur « Calendrier » | Ouvre `https://kpi.localhost/kpcalendrier.php?lang=fr` (legacy) | ☑ |
| 5 | Clic sur « EN » | URL `/en`, textes en anglais, `<html lang="en-GB">` ; « Calendar » mène à `…?lang=en` | ☑ |
| 6 | Fenêtre < 1024 px | Bouton « Menu » ; panneau vertical ; groupes dépliables | ☑ |
| 7 | Clic sur une carte d'événement | Ouvre `https://app.kpi.localhost/event/<id>` (app2) | ☑ |
| 8 | `https://beta.kpi.localhost/nimporte-quoi` | Page « Page introuvable » dans le gabarit, statut 404 | ☑ |
| 9 | `curl -sk https://beta.kpi.localhost/healthz` | `{"status":"ok"}` | ☑ |
| 10 | `curl -sk https://beta.kpi.localhost/robots.txt` | `User-agent: *` / `Disallow: /` | ☑ |
| 11 | `curl -skI https://beta.kpi.localhost/ \| grep -i x-robots` | `x-robots-tag: noindex, nofollow` | ☑ |
| 12 | `make app3_test` | `Test Files 9 passed`, `Tests 49 passed` (unit + nuxt + e2e) | ☑ |
| 13 | `make app3_lint` | ESLint et typecheck sans erreur | ☑ |
| 14 | Alerte de certificat sur `beta.kpi.localhost` ? | Non : couvert par `*.kpi.localhost`. Sinon `make dev_certs` puis redémarrer Firefox | ☑ |

**Non-régression locale** : `make dev_status` → legacy, api2, app2, app4 inchangés (mêmes codes qu'avant). ☑

### 1.2 ⌨️ 🌐 PR et CI

```bash
make pr_create              # PR vers main (titre conseillé : « feat: public website socle (app3, phase 1) »)
make pr_checks
```

| Job CI | Attendu | ☑ |
|---|---|---|
| `changes` | `app3=true`, `docker=true` (compose + Makefile modifiés), `legacy=false` | ☑ |
| `lint-nuxt (app3)`, `build-nuxt (app3)`, `audit-npm (app3)` | verts | ☑ |
| `tests-app3` | typecheck + 49 tests verts (le build e2e prend ~1 min) | ☑ |
| `lint-docker`, `trivy-config` | verts (nouveaux services compose) | ☑ |
| `bump-version` | **aucun bump app3** à cette PR (app3 n'existe pas encore sur `main`) ; bump d'app2/app4 seulement s'ils sont touchés (ils ne le sont pas) | ☑ |
| `ci-summary` | vert | ☑ |

Revue : **valider les 4 specs** (statut « Proposée » → « Validée » dans `DOC/specs/public/README.md`). ☑

### 1.3 📦 🌍 Prérequis serveur (une fois, avant ou juste après le merge)

| # | Où | Action | Résultat attendu | ☑ |
|---|---|---|---|---|
| 1 | 🌍 DNS | Créer `beta.preprod.kayak-polo.info` et `beta.kayak-polo.info` → même cible que `preprod.kayak-polo.info` / `kayak-polo.info` (A/AAAA ou CNAME) | `dig +short beta.preprod.kayak-polo.info` et `dig +short beta.kayak-polo.info` renvoient l'IP du VPS | ☑ |
| 2 | 📦 `vps-manager` / `deploy-wrapper.sh` | Dans le rebuild sélectif, quand le diff touche `sources/app3/` **ou** `sources/kpi-layer/` : appeler `make app3_generate_${ENV}` (comme `app2`/`app4`). Si la règle actuelle est un motif `sources/app*`, ajouter explicitement `sources/kpi-layer/` | Lecture du diff du wrapper : la nouvelle condition est présente |☑ |
| 3 | 📦 `vps-manager` | Déployer la nouvelle version du wrapper sur le VPS (`git -C /data/vps-manager pull`) | `/home/deploy/deploy-wrapper.sh` (symlink) contient la nouvelle condition | ☑ |
| 4 | 📦 `vps-manager` | **Ne pas** ajouter `beta.*` aux `SMOKE_URLS_*` avant la bascule (une panne du beta ne doit pas déclencher de rollback) | `SMOKE_URLS_PREPROD` / `_PRODUCTION` inchangés | ☑ |

> **Ligne 2** : version modifiée de `deploy-wrapper.sh` fournie le 09/10/2026 (brique `app3` déclenchée par
> `sources/app3/` **ou** `sources/kpi-layer/`, **non bloquante**, reconstruite au rollback ; un changement d'app3
> seul ne régénère plus app2/app4). À déposer sur `vps-manager`, puis ligne 3.

> Le certificat TLS est obtenu automatiquement par Traefik (`certresolver=myresolver`) au premier appel, **une
> fois le DNS propagé**. Avant : erreur de certificat sur `beta.*`, sans conséquence pour le reste.

### 1.4 🌐 🖥 Préprod (`/data/kpi_preprod`)

Le merge de la PR sur `main` déclenche « Deploy preprod ». Le diff touchant `docker/`, le wrapper fait un
`docker_preprod_rebuild` (down + build + up) : **courte coupure de toute la préprod**, comme pour tout changement
de `docker/`.

| # | Commande (🖥 dans `/data/kpi_preprod`) | Résultat attendu | ☑ |
|---|---|---|---|
| 1 | 🌐 Actions → « Deploy preprod » | vert, smoke tests OK | ☑ |
| 2 | `docker ps --filter name=kpi_preprod_app3` (nom = `${APPLICATION_NAME}_app3`) | conteneur `Up` (puis `healthy` après le build) | ☑ |
| 3 | `make app3_generate_preprod` *(seulement si le wrapper ne l'a pas fait)* | `✨ Build complete!` puis `✅ kpi_preprod_app3 redémarré` | ☑ |
| 4 | `make app3_logs lines=20` | `Listening on http://0.0.0.0:3000` ; pas de « app3 non construit » | ☑ |
| 5 | `curl -s https://beta.preprod.kayak-polo.info/healthz` | `{"status":"ok"}` | ☑ |
| 6 | `curl -sI https://beta.preprod.kayak-polo.info/ \| grep -iE "^HTTP\|x-robots"` | `HTTP/2 200`, `x-robots-tag: noindex, nofollow` | ☑ |
| 7 | Navigateur `https://beta.preprod.kayak-polo.info` | Rendu identique au dev ; événements de la base de préprod ; liens de menu vers `https://preprod.kayak-polo.info/kp….php` | ☑ |
| 8 | `docker inspect -f '{{.State.Health.Status}}' kpi_preprod_app3` | `healthy` | ☑ |

**Non-régression préprod** : `https://preprod.kayak-polo.info` (WordPress + `kp*.php`), `/admin2`,
`app.preprod.kayak-polo.info`, `/api2/doc` répondent comme avant ; `make media_status` → 6/6. ☑

### 1.5 ⌨️ 🌐 🖥 Production (`/data/kpi`)

```bash
git checkout main && git pull
make release_tag version=X.Y.Z      # tag de release contenant la phase 1
```

🌐 Actions → « Deploy production » → `ref` = `vX.Y.Z` → approuver.

| # | Commande (🖥 dans `/data/kpi`) | Résultat attendu | ☑ |
|---|---|---|---|
| 1 | Workflow « Deploy production » | vert | ☑ |
| 2 | `docker ps --filter name=kpi_app3` | `Up … (healthy)` | ☑ |
| 3 | `make app3_generate_production` *(si non fait par le wrapper)* | build OK + redémarrage | ☑ |
| 4 | `curl -s https://beta.kayak-polo.info/healthz` | `{"status":"ok"}` | ☑ |
| 5 | `curl -s https://beta.kayak-polo.info/robots.txt` | `Disallow: /` | ☑ |
| 6 | Navigateur `https://beta.kayak-polo.info` | Événements réels ; menus vers `https://www.kayak-polo.info/kp….php` | ☑ |

**Non-régression prod** : `www.kayak-polo.info`, `app.kayak-polo.info`, `/admin2`, `/api2/doc`. ☑

### 1.6 Retours de recette du 08/10/2026 (PR suivante)

Specs mises à jour et **validées** : SITE_LAYOUT (lien admin2 dans l'en-tête, tous les liens app2 en nouvel onglet),
PAGE_HOME (titre « en France et à l'international », section « Prochains événements »), SITE_NAVIGATION (app2 en
nouvel onglet). api2 : `/events/{mode}` expose en plus `start` / `end` (ajout seulement, app2 non concerné).

| # | Commande / action | Résultat attendu | ☑ |
|---|---|---|---|
| 1 | `make app3_test` | vert (60 tests) ; nouveaux critères `LAY-09`, `HOME-07`, `HOME-08` | ☑ |
| 2 | `make api2_test` | vert ; `testEventsExposeStartAndEndDates` | ☑ |
| 3 | `https://beta.kpi.localhost` | « Le kayak-polo, en France et à l'international » ; « Prochains événements » (le plus proche d'abord, badge « En cours ») puis « Événements récents » ; dates sur les cartes | ☑ |
| 4 | En-tête | « ⚙ Administration » → `https://kpi.localhost/admin2/` (icône seule sous 640 px) | ☑ |
| 5 | Clic « En direct », bouton « Suivre un événement en direct », pied de page, carte d'événement | app2 s'ouvre dans un **nouvel onglet** | ☑ |
| 6 | app2 (`https://app.kpi.localhost`) : liste des événements | inchangée | ☑ |
| 7 | Préprod / prod | comme § 1.4 / § 1.5 ; le diff touche `sources/api2/` → **`make api2_restart`** si le wrapper ne l'a pas fait | ☑ |

### 1.7 Retour arrière

| Situation | Action |
|---|---|
| app3 en erreur, le reste fonctionne | 🖥 `docker compose -f docker/compose.<env>.yaml stop app3` — seul `beta.*` est coupé |
| Build app3 KO pendant un déploiement | Corriger sur `main` (PR) ; en attendant, `stop app3` |
| Problème hors app3 lié à la release | Procédure standard du [runbook § 4](../infrastructure/DEPLOYMENT_RUNBOOK.md) |

---

## Phase 2 — Résultats (compétitions, événements, groupes)

Specs : [API_PUBLIC_RESULTS](../../specs/public/API_PUBLIC_RESULTS.md) (api2, critères `API-*`),
[PAGE_COMPETITIONS](../../specs/public/PAGE_COMPETITIONS.md) (`CPL-*`),
[PAGE_COMPETITION](../../specs/public/PAGE_COMPETITION.md) (`CMP-*`),
[PAGE_EVENT_GROUP](../../specs/public/PAGE_EVENT_GROUP.md) (`EVT-*`, `GRP-*`, `AGG-*`).
Statut : ✅ specs **validées** le 08/10/2026 (retours intégrés), 🛠 **implémentée** sur la branche, à livrer.

### 2.0 Décisions (prises le 08/10/2026)

| # | Décision | Retenu | ☑ |
|---|---|---|---|
| 1 | D-P2-1 : `r_1_id` / `r_2_id` (licences d'arbitres) | **retirés** de `/group/…/games` et `/event/{id}/games` | ☑ |
| 2 | D-P2-2 : liens PDF | PDF publics appelés en GET (`S`, `Compet`, `lang`, `idEvenement`, `listMatch`) — déjà supportés, vérifiés | ☑ |
| 3 | Onglet par défaut d'une compétition | `ranking` si terminée, sinon `games` | ☑ |
| 4 | Déroulement / Phases | **un seul onglet** `progress`, bascule horizontal / vertical | ☑ |
| 5 | Médailles | rangs 1–3 si `END` et tour final (`Code_tour = 10`) | ☑ |
| 6 | Statistiques | endpoint extensible `/stats/{kind}` (buteurs en phase 2) | ☑ |
| 7 | Accès à l'événement | encarts sur Compétitions (100 % du groupe) et Compétition (≥ 75 %) | ☑ |

### 2.1 Contenu (branche `claude/public_site_redesign_strategy`, une PR, commits relisibles séparément)

| Commit | Contenu | Impact hors beta |
|---|---|---|
| test(api2) | fixtures « résultats » + tests de caractérisation (snapshots JSON) des 4 endpoints d'app2, **avant** tout changement | aucun |
| refactor(api2) | `ResultsScope` / `PublicResultsService` / repository ; endpoints d'app2 délégués, snapshots **inchangés** | api2 (app2) — **vérifier app2** |
| fix(api2) | D-P2-1 (licences d'arbitres retirées) et D-P2-3 (rien de non publié dans les tableaux) : snapshots modifiés par **suppressions seulement** | app2 : plus de `r_1_id`/`r_2_id`, plus de phases de journées non publiées |
| feat(api2) | 10 endpoints publics (tag « 7. Site public ») | ajouts seulement |
| feat(app3) | pages Compétitions, compétition (6 onglets), événement, groupe ; menu `competitions-list` `ready: true` ; accueil → `/events/{id}` | aucun (beta) |

### 2.2 ⌨️ Tester en local

```bash
git fetch origin && git checkout <branche de la PR> && git pull
make docker_dev_up
make api2_test              # unit + integration (recharge SQL/fixtures/) = job CI tests-api2
make app3_test              # unit + nuxt + e2e
```

| # | Commande / action | Résultat attendu | ☑ |
|---|---|---|---|
| 1 | `make api2_test` | unit 48 + integration 50 tests verts (dont `PublicResultsCharacterizationTest`, `PublicCompetitionEndpointsTest`) | ☑ |
| 2 | `git log -p -- sources/api2/tests/Integration/__snapshots__` | le commit refactor ne touche pas les snapshots ; le commit fix ne fait que des suppressions | ☑ |
| 3 | `curl -sk https://kpi.localhost/api2/seasons` | `{"active":"…","seasons":[…]}` | ☑ |
| 4 | `curl -sk https://kpi.localhost/api2/competition/<saison>/<code>/stats/scorers?limit=5 \| grep -i matric` | aucune sortie (pas de licence) | ☑ |
| 5 | `curl -sk -o /dev/null -w '%{http_code}' https://kpi.localhost/api2/competition/2026/INCONNU` | `404` ; `…/competition/abcd/X` → `400` | ☑ |
| 6 | `https://kpi.localhost/api2/doc` | tag « 7. Site public » avec les nouveaux endpoints | ☑ |
| 7 | app2 `https://app.kpi.localhost` : un événement et un groupe (matchs, tableaux) | inchangé | ☑ |
| 8 | `make app3_test` | 144 tests verts (unit 69 dont kpi-layer, nuxt 61, e2e 14) ; tests nommés `CPL-*`, `CMP-*`, `EVT-*`, `GRP-*`, `AGG-*` | ☑ |
| 9 | `https://beta.kpi.localhost/competitions` | redirige vers la saison active ; menu « Compétitions et résultats » sans icône externe | ☑ |
| 10 | Une compétition CHPT, une CP, une MULTI : tous les onglets | contenu conforme ; onglets utilisables sans JavaScript (désactiver JS) | ☑ |
| 11 | `https://beta.kpi.localhost/events/<id>` et `/groups/<saison>/<code>` | matchs de toutes les compétitions, puces vers chaque compétition | ☑ |
| 12 | Clic sur une équipe / un match | équipe → `kpequipes.php` (legacy) ; match → app2 `/game/<id>` (nouvel onglet) | ☑ |
| 13 | Liens PDF (classement, liste des matchs, feuille de marque) | le PDF s'ouvre, **sans session** legacy (navigation privée) | ☑ |
| 14 | Accueil : carte d'un événement | ouvre `/events/<id>` (vue événement du site) | ☑ |
| 15 | Compétition terminée du tour final (préprod) | médailles 1-2-3 dans la liste et l'onglet Classement | ☑ |

### 2.3 ⌨️ 🌐 PR et CI

Jobs attendus : `tests-api2`, `phpstan-api2`, `lint-api2`, `smoke-api2` ; `lint-nuxt`, `build-nuxt`,
`tests-app3` ; `ci-summary` vert. ☑

### 2.4 🖥 Préprod (`/data/kpi_preprod`)

| # | Commande / action | Résultat attendu | ☑ |
|---|---|---|---|
| 1 | 🌐 « Deploy preprod » (PR touchant `sources/api2/`) | vert ; le wrapper fait composer/cache puis **`make api2_restart`** | ☑ |
| 2 | `make api2_logs_errors lines=50` | aucune erreur nouvelle | ☑ |
| 3 | `curl -s https://preprod.kayak-polo.info/api2/seasons` | JSON avec la saison active | ☑ |
| 4 | app2 préprod : un événement en cours ou récent | matchs et tableaux identiques à avant | ☑ |
| 5 | `make app3_generate_preprod` *(si non fait par le wrapper)* | build OK + redémarrage | ☑ |
| 6 | **Grille de parité** ([PAGE_COMPETITION § 7](../../specs/public/PAGE_COMPETITION.md)) sur une CHPT, une CP, une MULTI + un événement et un groupe ([PAGE_EVENT_GROUP](../../specs/public/PAGE_EVENT_GROUP.md) AGG-03) | mêmes matchs, scores, classements, stats que `kp*.php` | ☑ |
| 7 | Un jour de compétition : page Matchs ouverte | rafraîchissement toutes les 60 s (onglet réseau), arrêt quand l'onglet est masqué | ☑ |

**Non-régression préprod** : `kpclassements.php`, `kpmatchs.php` (legacy), app2, `/admin2`. ☑

### 2.5 Production

Comme le § 1.5 (`make release_tag`, « Deploy production »), puis :
- 🖥 `make api2_restart` si le wrapper ne l'a pas fait (worker FrankenPHP : sinon l'ancien code reste en mémoire) ; ☑
- `curl -s https://www.kayak-polo.info/api2/seasons` ; app2 prod sur un événement ; ☑
- grille de parité réduite (une compétition) sur `beta.kayak-polo.info`. ☑

### 2.6 Retour arrière

| Situation | Action |
|---|---|
| app2 régresse (matchs / tableaux) | Revert des commits refactor/fix api2 sur `main` (les tests de caractérisation restent) puis déploiement ; en prod, redéployer le tag précédent ([runbook § 4](../infrastructure/DEPLOYMENT_RUNBOOK.md)) |
| Page app3 en erreur | Repasser l'entrée de menu à `ready: false` (repli legacy) ou `stop app3` (§ 1.7) |

---

## Phase 3 — Transverse (calendrier, ICS, historique, équipes, clubs, recherche)

Specs : [API_PUBLIC_TRANSVERSE](../../specs/public/API_PUBLIC_TRANSVERSE.md) (`API3-*`),
[PAGE_CALENDAR](../../specs/public/PAGE_CALENDAR.md) (`CAL-*`), [PAGE_HISTORY](../../specs/public/PAGE_HISTORY.md) (`HIS-*`),
[PAGE_TEAM](../../specs/public/PAGE_TEAM.md) (`TEA-*`), [PAGE_CLUBS](../../specs/public/PAGE_CLUBS.md) (`CLB-*`),
[FEATURE_SEARCH](../../specs/public/FEATURE_SEARCH.md) (`SRC-*`).
Statut : ✅ specs **validées** le 09/10/2026 (décisions ci-dessous), 🛠 **implémentée** sur la branche, à livrer.

### 3.0 Décisions (prises le 09/10/2026)

| # | Question | Retenu | ☑ |
|---|---|---|---|
| 1 | Q-P3-1 moteur de recherche | SQL `LIKE`, sans moteur dédié (à revoir au-delà de 200 ms) | ☑ |
| 2 | Q-P3-2 e-mail des clubs | conservé (structure), en texte | ☑ |
| 3 | Q-P3-3 photo d'équipe | **couleurs et photo d'équipe maintenues** (comme `kpequipes.php`) jusqu'à l'étude RGPD | ☑ |
| 4 | Q-P3-4 vue du calendrier | agenda + grille mensuelle sur grand écran, sans FullCalendar | ☑ |
| 5 | Q-P3-5 fond de carte | Leaflet auto-hébergé, tuiles OSM chargées après un clic | ☑ |

### 3.1 Contenu (branche `claude/public_site_redesign_strategy`, commits relisibles séparément)

| Commit | Contenu | Impact hors beta |
|---|---|---|
| docs | décisions Q-P3, specs validées | aucun |
| feat(api2) | 11 endpoints publics (tag « 7. Site public ») : calendrier, ICS, historique, équipes, clubs, recherche ; fixtures SQL (clubs, comités, équipes, saison 2998) | **`/groups/{season}`** (app2) : sections calculées par un helper partagé, sortie identique ; **`trusted_proxies`** (X-Forwarded-For du réseau privé seulement) ; **`PUBLIC_SITE_URL`** ajouté aux 3 compose → `docker/` modifié |
| feat(app3) | pages Calendrier, Historique, Équipes, Clubs (carte Leaflet au clic), Recherche (en-tête + page) ; menus `ready: true` ; liens d'équipe internes ; abonnements ICS dans l'onglet Infos ; `leaflet` ajouté | aucun (beta) |

Écarts assumés par rapport au legacy (détaillés dans les specs) : historique **par groupe strict** (plus d'agrégation
`N…` / `CF…`) ; composition incluant les joueurs **sans statistique** ; club **sans équipe** jamais publié.

### 3.2 ⌨️ Tester en local

```bash
git fetch origin && git checkout claude/public_site_redesign_strategy && git pull
make docker_dev_up          # docker/ a changé : api2 reçoit PUBLIC_SITE_URL
make api2_test              # recharge SQL/fixtures (nouvelles tables kp_club, kp_cd, kp_cr, kp_equipe)
make app3_npm_ci            # nouvelle dépendance leaflet
make app3_test && make app3_lint
```

| # | Commande / action | Résultat attendu | ☑ |
|---|---|---|---|
| 1 | `make api2_test` | unit 66 + integration 74 (dont `PublicSiteEndpointsTest` 24, tests `API3-*`) | ☑ |
| 2 | `make app3_test` | 190 tests verts (unit 87, nuxt 85, e2e 18) ; tests nommés `CAL-*`, `HIS-*`, `TEA-*`, `CLB-*`, `SRC-*` | ☑ |
| 3 | `curl -sk 'https://kpi.localhost/api2/calendar?start=2026-06-01&end=2026-06-30' \| head -c 300` | JSON des journées publiées | ☑ |
| 4 | `curl -sk https://kpi.localhost/api2/competition/<saison>/<code>/calendar.ics` | `BEGIN:VCALENDAR`, un `VEVENT` par journée, aucune donnée personnelle | ☑ |
| 5 | `curl -sk https://kpi.localhost/api2/team/<numéro>/roster/<saison>/<code> \| grep -iE "matric\|sexe\|naiss"` | aucune sortie | ☑ |
| 6 | `curl -sk https://kpi.localhost/api2/club/<code d'un club sans équipe>` | `404` | ☑ |
| 7 | `https://beta.kpi.localhost/calendar` | mois courant, navigation mois précédent / suivant, flèches mois et année, filtres section (couleur par section) et groupe filtré par section (désactiver JS : fonctionnent) ; grille du mois à partir de 1024 px | ☑ |
| 8 | Une compétition → onglet Infos | « S'abonner au calendrier » (`webcal://`), « Télécharger (.ics) », « Ajouter à mon agenda » par journée | ☑ |
| 9 | `https://beta.kpi.localhost/history` | redirige vers le 1er groupe national ; podiums avec médailles, classement complet repliable | ☑ |
| 10 | `https://beta.kpi.localhost/teams?q=<nom>` puis une équipe | suggestions au clavier ; fiche : club, couleurs et photo d'équipe (si présentes), palmarès, composition (sélecteur) | ☑ |
| 11 | Une page de résultats : clic sur une équipe | ouvre `/teams/{n}?season=…&competition=…` (plus de `kpequipes.php`) | ☑ |
| 12 | `https://beta.kpi.localhost/clubs` puis « Carte » | aucune requête vers `tile.openstreetmap.org` avant le clic sur « Afficher la carte » (onglet réseau) | ☑ |
| 13 | Champ de recherche de l'en-tête | suggestions groupées après 2 caractères, Échap ferme ; Entrée → `/search?q=…` | ☑ |
| 14 | 31 recherches en moins d'une minute depuis une IP publique (préprod) | la 31e affiche « Trop de recherches… » ; en dev (IP privée) pas de limite | ☑ |

**Non-régression locale** : app2 (liste des groupes d'une saison, `/groups/{saison}`), `/admin2`, legacy. ☑

### 3.3 ⌨️ 🌐 PR et CI

Jobs attendus : `tests-api2`, `phpstan-api2`, `lint-api2` ; `lint-nuxt`, `build-nuxt`, `audit-npm`, `tests-app3` ;
`lint-docker`, `trivy-config` (compose modifiés) ; `ci-summary` vert. ☑

### 3.4 🖥 Préprod (`/data/kpi_preprod`)

| # | Commande / action | Résultat attendu | ☑ |
|---|---|---|---|
| 1 | 🌐 « Deploy preprod » | vert. Le diff touche `docker/` (compose) → **`docker_preprod_rebuild`** : courte coupure de toute la préprod | ☑ |
| 2 | `docker exec kpi_preprod_api2 printenv PUBLIC_SITE_URL` | `https://beta.preprod.kayak-polo.info` | ☑ |
| 3 | `make app3_generate_preprod` *(si le wrapper 📦 n'est pas encore à jour, § 1.3)* | build OK + redémarrage | ☑ |
| 4 | Abonnement ICS d'une compétition dans Google Agenda, Apple Calendrier, Thunderbird | journées en « toute la journée », lien vers la page de la compétition sur `beta.preprod…` | ☑ |
| 4b | Onglet Infos : « Copier le lien d'abonnement » puis tutoriel « Comment s'abonner ? » | lien copié ; étapes Google / Outlook / Apple | ☑ |
| 4c | `https://beta.kpi.localhost/events` puis un événement | menu « Événements » ; bandeau marine « Événement », panneau « n journées sur total », bouton rouge « Suivre en direct », onglet Infos en premier, vainqueurs en gras dans Matchs | ☑ |
| 5 | Grille de parité avec `kpcalendrier.php`, `kphistorique.php`, `kpequipes.php`, `kpclubs.php` | mêmes journées, palmarès, compositions, clubs (aux écarts assumés du § 3.1 près) | ☑ |
| 6 | Recherche depuis un poste extérieur : 31 requêtes / minute | 429 à la 31e (preuve que l'IP du visiteur traverse Traefik → app3 → api2) | ☑ |

**Non-régression préprod** : app2 (événements, groupes), `/admin2`, legacy `kp*.php`. ☑

### 3.5 Production

Comme le § 1.5 (`make release_tag`, « Deploy production » — rebuild de la stack car `docker/` change), puis
`docker exec kpi_api2 printenv PUBLIC_SITE_URL` → `https://beta.kayak-polo.info` ; parité réduite sur
`beta.kayak-polo.info` (un mois du calendrier, un palmarès, une équipe, un club). ☐

### 3.6 Retour arrière

| Situation | Action |
|---|---|
| Une page phase 3 en erreur | Repasser son entrée de menu à `ready: false` (repli legacy) ; `teamLink` peut revenir au legacy de la même façon |
| Limite de débit trop stricte / IP mal relayée | Retirer temporairement `trusted_proxies` n'est pas la solution (limite globale) : augmenter `SearchThrottle::LIMIT` |
| Problème hors app3 lié à la release | Procédure standard du [runbook § 4](../infrastructure/DEPLOYMENT_RUNBOOK.md) |

## Phase 4a — Éditorial (CMS natif, reprise WordPress)

Specs : [FEATURE_CMS](../../specs/public/FEATURE_CMS.md) (`CMS-*`), [PAGE_NEWS](../../specs/public/PAGE_NEWS.md)
(`NEWS-*`), [PAGE_CONTENT](../../specs/public/PAGE_CONTENT.md) (`CNT-*`). Statut : 📝 **brouillon** (10/10/2026), à
valider (questions Q-P4-1 à Q-P4-5).

*Sections de test à compléter à l'implémentation.* Prévoir : script `SQL/migrations/…_cms.sql`, GD `--with-webp`
(`Dockerfile.api2` → rebuild), `symfony/html-sanitizer` 7.4, secret `APP3_PURGE_TOKEN`, import `app:import-wordpress` en préprod puis prod,
médias dans `HOST_MEDIA_PATH/content`, droits « Rédacteur » (app4).

## Phase 4b — Formulaires

Spec : [FEATURE_FORMS](../../specs/public/FEATURE_FORMS.md) (`FRM-*`). Statut : 📝 **brouillon** (10/10/2026), à
valider (questions Q-P4-6 à Q-P4-9).

*Sections de test à compléter à l'implémentation.* Prévoir : tâche planifiée `app:forms:purge`, envoi d'e-mails (SMTP), anti-spam, export.

## Phase 5 — Bascule de `www`

*À compléter.* Prévoir : 🌍 aucun changement DNS (même VPS) ; Traefik : app3 devient le routeur par défaut de
`kayak-polo.info`/`www`, Apache sur liste explicite ; `NUXT_PUBLIC_BETA=false` ; redirections 301 ; ajout de
`/healthz` aux `SMOKE_URLS_*` (📦 `vps-manager`) ; Search Console ; suivi des 404.

## Phase 6 — Décommissionnement

*À compléter.* Prévoir : arrêt de `dbwp` après archive, suppression des `kp*.php`, adoption de `kpi-layer` par app2/app4.

## Phase 7 — Écrans / embeds

*À compléter.*
