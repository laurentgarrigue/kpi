# Site public (app3) — checklist de test et de déploiement par phase

**Créée le** : 8 octobre 2026 — **À compléter à chaque phase** (une section par phase, cochée au fil de l'eau)
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

| # | Commande / action | Résultat attendu | ☐ |
|---|---|---|---|
| 1 | `make app3_logs` | `Nuxt … ready`, `Local: http://0.0.0.0:3003/`, aucune erreur | ☐ |
| 2 | Navigateur : `https://beta.kpi.localhost` | Bandeau bleu clair « Version bêta… », logo FFCK, barre de navigation marine, « Le kayak-polo en France », 6 événements récents (base de dev) | ☐ |
| 3 | Clic sur « Compétitions » puis Échap | Le sous-menu s'ouvre au clic, se ferme avec Échap ; entrées marquées d'une icône « lien externe » | ☐ |
| 4 | Clic sur « Calendrier » | Ouvre `https://kpi.localhost/kpcalendrier.php?lang=fr` (legacy) | ☐ |
| 5 | Clic sur « EN » | URL `/en`, textes en anglais, `<html lang="en-GB">` ; « Calendar » mène à `…?lang=en` | ☐ |
| 6 | Fenêtre < 1024 px | Bouton « Menu » ; panneau vertical ; groupes dépliables | ☐ |
| 7 | Clic sur une carte d'événement | Ouvre `https://app.kpi.localhost/event/<id>` (app2) | ☐ |
| 8 | `https://beta.kpi.localhost/nimporte-quoi` | Page « Page introuvable » dans le gabarit, statut 404 | ☐ |
| 9 | `curl -sk https://beta.kpi.localhost/healthz` | `{"status":"ok"}` | ☐ |
| 10 | `curl -sk https://beta.kpi.localhost/robots.txt` | `User-agent: *` / `Disallow: /` | ☐ |
| 11 | `curl -skI https://beta.kpi.localhost/ \| grep -i x-robots` | `x-robots-tag: noindex, nofollow` | ☐ |
| 12 | `make app3_test` | `Test Files 9 passed`, `Tests 49 passed` (unit + nuxt + e2e) | ☐ |
| 13 | `make app3_lint` | ESLint et typecheck sans erreur | ☐ |
| 14 | Alerte de certificat sur `beta.kpi.localhost` ? | Non : couvert par `*.kpi.localhost`. Sinon `make dev_certs` puis redémarrer Firefox | ☐ |

**Non-régression locale** : `make dev_status` → legacy, api2, app2, app4 inchangés (mêmes codes qu'avant). ☐

### 1.2 ⌨️ 🌐 PR et CI

```bash
make pr_create              # PR vers main (titre conseillé : « feat: public website socle (app3, phase 1) »)
make pr_checks
```

| Job CI | Attendu | ☐ |
|---|---|---|
| `changes` | `app3=true`, `docker=true` (compose + Makefile modifiés), `legacy=false` | ☐ |
| `lint-nuxt (app3)`, `build-nuxt (app3)`, `audit-npm (app3)` | verts | ☐ |
| `tests-app3` | typecheck + 49 tests verts (le build e2e prend ~1 min) | ☐ |
| `lint-docker`, `trivy-config` | verts (nouveaux services compose) | ☐ |
| `bump-version` | **aucun bump app3** à cette PR (app3 n'existe pas encore sur `main`) ; bump d'app2/app4 seulement s'ils sont touchés (ils ne le sont pas) | ☐ |
| `ci-summary` | vert | ☐ |

Revue : **valider les 4 specs** (statut « Proposée » → « Validée » dans `DOC/specs/public/README.md`). ☐

### 1.3 📦 🌍 Prérequis serveur (une fois, avant ou juste après le merge)

| # | Où | Action | Résultat attendu | ☐ |
|---|---|---|---|---|
| 1 | 🌍 DNS | Créer `beta.preprod.kayak-polo.info` et `beta.kayak-polo.info` → même cible que `preprod.kayak-polo.info` / `kayak-polo.info` (A/AAAA ou CNAME) | `dig +short beta.preprod.kayak-polo.info` et `dig +short beta.kayak-polo.info` renvoient l'IP du VPS | ☐ |
| 2 | 📦 `vps-manager` / `deploy-wrapper.sh` | Dans le rebuild sélectif, quand le diff touche `sources/app3/` **ou** `sources/kpi-layer/` : appeler `make app3_generate_${ENV}` (comme `app2`/`app4`). Si la règle actuelle est un motif `sources/app*`, ajouter explicitement `sources/kpi-layer/` | Lecture du diff du wrapper : la nouvelle condition est présente | ☐ |
| 3 | 📦 `vps-manager` | Déployer la nouvelle version du wrapper sur le VPS (`git -C /data/vps-manager pull`) | `/home/deploy/deploy-wrapper.sh` (symlink) contient la nouvelle condition | ☐ |
| 4 | 📦 `vps-manager` | **Ne pas** ajouter `beta.*` aux `SMOKE_URLS_*` avant la bascule (une panne du beta ne doit pas déclencher de rollback) | `SMOKE_URLS_PREPROD` / `_PRODUCTION` inchangés | ☐ |

> Le certificat TLS est obtenu automatiquement par Traefik (`certresolver=myresolver`) au premier appel, **une
> fois le DNS propagé**. Avant : erreur de certificat sur `beta.*`, sans conséquence pour le reste.

### 1.4 🌐 🖥 Préprod (`/data/kpi_preprod`)

Le merge de la PR sur `main` déclenche « Deploy preprod ». Le diff touchant `docker/`, le wrapper fait un
`docker_preprod_rebuild` (down + build + up) : **courte coupure de toute la préprod**, comme pour tout changement
de `docker/`.

| # | Commande (🖥 dans `/data/kpi_preprod`) | Résultat attendu | ☐ |
|---|---|---|---|
| 1 | 🌐 Actions → « Deploy preprod » | vert, smoke tests OK | ☐ |
| 2 | `docker ps --filter name=kpi_preprod_app3` (nom = `${APPLICATION_NAME}_app3`) | conteneur `Up` (puis `healthy` après le build) | ☐ |
| 3 | `make app3_generate_preprod` *(seulement si le wrapper ne l'a pas fait)* | `✨ Build complete!` puis `✅ kpi_preprod_app3 redémarré` | ☐ |
| 4 | `make app3_logs lines=20` | `Listening on http://0.0.0.0:3000` ; pas de « app3 non construit » | ☐ |
| 5 | `curl -s https://beta.preprod.kayak-polo.info/healthz` | `{"status":"ok"}` | ☐ |
| 6 | `curl -sI https://beta.preprod.kayak-polo.info/ \| grep -iE "^HTTP\|x-robots"` | `HTTP/2 200`, `x-robots-tag: noindex, nofollow` | ☐ |
| 7 | Navigateur `https://beta.preprod.kayak-polo.info` | Rendu identique au dev ; événements de la base de préprod ; liens de menu vers `https://preprod.kayak-polo.info/kp….php` | ☐ |
| 8 | `docker inspect -f '{{.State.Health.Status}}' kpi_preprod_app3` | `healthy` | ☐ |

**Non-régression préprod** : `https://preprod.kayak-polo.info` (WordPress + `kp*.php`), `/admin2`,
`app.preprod.kayak-polo.info`, `/api2/doc` répondent comme avant ; `make media_status` → 6/6. ☐

### 1.5 ⌨️ 🌐 🖥 Production (`/data/kpi`)

```bash
git checkout main && git pull
make release_tag version=X.Y.Z      # tag de release contenant la phase 1
```

🌐 Actions → « Deploy production » → `ref` = `vX.Y.Z` → approuver.

| # | Commande (🖥 dans `/data/kpi`) | Résultat attendu | ☐ |
|---|---|---|---|
| 1 | Workflow « Deploy production » | vert | ☐ |
| 2 | `docker ps --filter name=kpi_app3` | `Up … (healthy)` | ☐ |
| 3 | `make app3_generate_production` *(si non fait par le wrapper)* | build OK + redémarrage | ☐ |
| 4 | `curl -s https://beta.kayak-polo.info/healthz` | `{"status":"ok"}` | ☐ |
| 5 | `curl -s https://beta.kayak-polo.info/robots.txt` | `Disallow: /` | ☐ |
| 6 | Navigateur `https://beta.kayak-polo.info` | Événements réels ; menus vers `https://www.kayak-polo.info/kp….php` | ☐ |

**Non-régression prod** : `www.kayak-polo.info`, `app.kayak-polo.info`, `/admin2`, `/api2/doc`. ☐

### 1.6 Retour arrière

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
Statut : 📝 specs proposées, **en attente de validation** — les lignes « *à préciser* » seront complétées à
l'implémentation.

### 2.0 Avant de coder : décisions à prendre

| # | Décision | Proposition | ☐ |
|---|---|---|---|
| 1 | Q-P2-1 : retirer `r_1_id` / `r_2_id` (licences d'arbitres) de `/group/…/games` et `/event/{id}/games` | Oui (non lus par app2) | ☐ |
| 2 | Q-P2-2 : liens PDF | Aucun en phase 2 | ☐ |
| 3 | Onglet par défaut d'une compétition | `ranking` si terminée, sinon `games` | ☐ |
| 4 | Specs validées (statut « Validée » dans `DOC/specs/public/README.md`) | — | ☐ |

### 2.1 Découpage en PR (chacune mergeable et déployable seule)

| PR | Contenu | Impact hors beta |
|---|---|---|
| **2a** | api2 : fixtures + tests de caractérisation des 4 endpoints existants, **sans changer le code** | aucun |
| **2b** | api2 : `ResultsScope` / service / repository ; endpoints existants délégués (JSON inchangé hors Q-P2-1) | api2 (app2) — **vérifier app2** |
| **2c** | api2 : nouveaux endpoints `/seasons`, `/group/…/competitions`, `/competition/…`, `/event/{id}/competitions` | api2 (ajouts seulement) |
| **2d** | app3 : page Compétitions + menu `competitions-list` `ready: true` | aucun |
| **2e** | app3 : page compétition (onglets) | aucun |
| **2f** | app3 : vues événement / groupe + cartes de l'accueil vers `/events/{id}` | aucun |

### 2.2 ⌨️ Tester en local

```bash
git fetch origin && git checkout <branche de la PR> && git pull
make docker_dev_up
make api2_test              # unit + integration (recharge SQL/fixtures/) = job CI tests-api2
make app3_test              # unit + nuxt + e2e
```

| # | Commande / action | Résultat attendu | ☐ |
|---|---|---|---|
| 1 | `make api2_test` (PR 2a) | vert ; fichiers de référence créés sous `sources/api2/tests/Integration/__snapshots__/` | ☐ |
| 2 | `make api2_test` (PR 2b) | vert **sans modification** des fichiers de référence (hors retrait `r_1_id`/`r_2_id`, visible dans le diff) | ☐ |
| 3 | `curl -sk https://kpi.localhost/api2/seasons` | `{"active":"…","seasons":[…]}` | ☐ |
| 4 | `curl -sk https://kpi.localhost/api2/competition/<saison>/<code>/scorers?limit=5 \| grep -i matric` | aucune sortie (pas de licence) | ☐ |
| 5 | `curl -sk -o /dev/null -w '%{http_code}' https://kpi.localhost/api2/competition/2026/INCONNU` | `404` ; `…/competition/abcd/X` → `400` | ☐ |
| 6 | `https://kpi.localhost/api2/doc` | tag « 3. Site public » avec les nouveaux endpoints | ☐ |
| 7 | app2 `https://app.kpi.localhost` : un événement et un groupe (matchs, tableaux) | inchangé | ☐ |
| 8 | `make app3_test` | vert ; tests nommés `CPL-*`, `CMP-*`, `EVT-*`, `GRP-*`, `AGG-*` | ☐ |
| 9 | `https://beta.kpi.localhost/competitions` | redirige vers la saison active ; menu « Compétitions et résultats » sans icône externe | ☐ |
| 10 | Une compétition CHPT, une CP, une MULTI : tous les onglets | contenu conforme ; onglets utilisables sans JavaScript (désactiver JS) | ☐ |
| 11 | `https://beta.kpi.localhost/events/<id>` et `/groups/<saison>/<code>` | matchs de toutes les compétitions, puces vers chaque compétition | ☐ |
| 12 | Clic sur une équipe / un match | équipe → `kpequipes.php` (legacy) ; match → app2 `/game/<id>` | ☐ |

### 2.3 ⌨️ 🌐 PR et CI

Jobs attendus : `tests-api2`, `phpstan-api2`, `lint-api2`, `smoke-api2` (PR 2a–2c) ; `lint-nuxt`, `build-nuxt`,
`tests-app3` (PR 2d–2f) ; `ci-summary` vert. ☐

### 2.4 🖥 Préprod (`/data/kpi_preprod`)

| # | Commande / action | Résultat attendu | ☐ |
|---|---|---|---|
| 1 | 🌐 « Deploy preprod » (PR touchant `sources/api2/`) | vert ; le wrapper fait composer/cache puis **`make api2_restart`** | ☐ |
| 2 | `make api2_logs_errors lines=50` | aucune erreur nouvelle | ☐ |
| 3 | `curl -s https://preprod.kayak-polo.info/api2/seasons` | JSON avec la saison active | ☐ |
| 4 | app2 préprod : un événement en cours ou récent | matchs et tableaux identiques à avant | ☐ |
| 5 | `make app3_generate_preprod` *(si non fait par le wrapper)* | build OK + redémarrage | ☐ |
| 6 | **Grille de parité** ([PAGE_COMPETITION § 7](../../specs/public/PAGE_COMPETITION.md)) sur une CHPT, une CP, une MULTI + un événement et un groupe ([PAGE_EVENT_GROUP](../../specs/public/PAGE_EVENT_GROUP.md) AGG-03) | mêmes matchs, scores, classements, stats que `kp*.php` | ☐ |
| 7 | Un jour de compétition : page Matchs ouverte | rafraîchissement toutes les 60 s (onglet réseau), arrêt quand l'onglet est masqué | ☐ |

**Non-régression préprod** : `kpclassements.php`, `kpmatchs.php` (legacy), app2, `/admin2`. ☐

### 2.5 Production

Comme le § 1.5 (`make release_tag`, « Deploy production »), puis :
- 🖥 `make api2_restart` si le wrapper ne l'a pas fait (worker FrankenPHP : sinon l'ancien code reste en mémoire) ; ☐
- `curl -s https://www.kayak-polo.info/api2/seasons` ; app2 prod sur un événement ; ☐
- grille de parité réduite (une compétition) sur `beta.kayak-polo.info`. ☐

### 2.6 Retour arrière

| Situation | Action |
|---|---|
| app2 régresse après 2b | Revert de la PR 2b sur `main` (les tests de caractérisation de 2a restent) puis déploiement ; en prod, redéployer le tag précédent ([runbook § 4](../infrastructure/DEPLOYMENT_RUNBOOK.md)) |
| Page app3 en erreur | Repasser l'entrée de menu à `ready: false` (repli legacy) ou `stop app3` (§ 1.6) |

---

## Phase 3 — Transverse (calendrier, ICS, historique, équipes, clubs, recherche)

*À compléter quand les specs seront rédigées.*

## Phase 4a — Éditorial (CMS natif, reprise WordPress)

*À compléter.* Prévoir : migration Doctrine (nouvelles tables), import `app:import-wordpress` en préprod puis prod,
médias dans `HOST_MEDIA_PATH/content`, droits « Rédacteur » (app4).

## Phase 4b — Formulaires

*À compléter.* Prévoir : envoi d'e-mails (SMTP), anti-spam, export.

## Phase 5 — Bascule de `www`

*À compléter.* Prévoir : 🌍 aucun changement DNS (même VPS) ; Traefik : app3 devient le routeur par défaut de
`kayak-polo.info`/`www`, Apache sur liste explicite ; `NUXT_PUBLIC_BETA=false` ; redirections 301 ; ajout de
`/healthz` aux `SMOKE_URLS_*` (📦 `vps-manager`) ; Search Console ; suivi des 404.

## Phase 6 — Décommissionnement

*À compléter.* Prévoir : arrêt de `dbwp` après archive, suppression des `kp*.php`, adoption de `kpi-layer` par app2/app4.

## Phase 7 — Écrans / embeds

*À compléter.*
