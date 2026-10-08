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

Specs : `PAGE_COMPETITIONS`, `PAGE_COMPETITION`, `PAGE_EVENT`, `PAGE_GROUP`, `PUBLIC_API_RESULTS` (api2).
Statut : 📝 specs proposées, **en attente de validation** — sections ci-dessous à compléter à l'implémentation.

### 2.1 ⌨️ Tester en local
| # | Commande / action | Résultat attendu | ☐ |
|---|---|---|---|
| 1 | `make api2_test` | suites unit + integration vertes, dont les nouveaux tests `Public*Controller` / services | ☐ |
| 2 | `make app3_test` | tous les tests verts, critères `CPL-*`, `CMP-*`, `EVT-*`, `GRP-*` couverts | ☐ |
| 3 | Comparaison legacy ↔ app3 sur 3 compétitions de référence (une CHPT, une CP, une multi) | mêmes matchs, scores, classements, stats (grille de parité de `PAGE_COMPETITION.md` § 7) | ☐ |
| … | *à compléter* | | |

### 2.2 Déploiement
- Le diff touche `sources/api2/` → le wrapper relance composer/migrations/cache et **`api2_restart`** (worker FrankenPHP). ☐
- Entrées de menu passées à `ready: true` : « Compétitions et résultats ». ☐
- *à compléter*

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
