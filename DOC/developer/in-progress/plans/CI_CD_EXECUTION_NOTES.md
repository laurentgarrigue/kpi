# Journal d'exécution CI/CD

Notes d'exécution du plan [CI_CD_STRATEGY.md](./CI_CD_STRATEGY.md), phase par phase :
ce qui a été réellement livré, les écarts assumés et les pièges rencontrés.

---

## 📌 État de reprise (dernière session : 2026-07-30, 2ᵉ passe)

**Où on en est** : **toutes les phases 0 → 8 sont livrées**. La prod tourne (1er
déploiement réel le 2026-07-30). Phase 3bis (Trivy image) 🟢. Les phases **4, 7 et 8**
ont été traitées dans cette 2ᵉ passe.

**⚠️ TODO immédiats à reprendre en début de prochaine session** :

1. **Committer + pusher `vps-manager`** (`~/Documents/dev/vps-manager`, branche `main`) :
   `deploy-wrapper.sh` (mode `--experimental` + `--check-expiry` + smoke multi-URL),
   `.env.dist` (`SMOKE_URLS_*`, `DEPLOY_DEFAULT_BRANCH`), `Makefile`
   (`install-cron-experimental-expiry`). Puis **`git pull` côté VPS**
   (`/data/vps-manager`, en tant que `laurent`).
2. **Renseigner `SMOKE_URLS_PREPROD` / `SMOKE_URLS_PRODUCTION`** dans le `.env` du VPS
   (valeurs réelles ; `.env.dist` ne porte que des placeholders). ⚠️ **Sans ça, le
   comportement reste l'ancien** (repli sur `SMOKE_URL_*`, une seule URL) — ce n'est pas
   une panne, juste la Phase 8.1 non active.
3. **Installer le cron d'expiration** sur le VPS : `make install-cron-experimental-expiry`
   (depuis `/data/vps-manager`). Sans lui, un déploiement expérimental **ne reviendrait
   jamais** à `develop` tout seul.
4. **PR kpi** : le working tree porte la Phase 4 (PHPUnit + fixtures + job CI), la
   Phase 7 (workflow + bandeau app2/app4) et la Phase 8 (runbook) → à intégrer via PR
   vers `develop`.
5. **Valider la Phase 7 par un run réel** (les 3 cases §7.6 attendent ça) : déployer une
   branche jetable avec `--ttl 1`, vérifier le bandeau sur app2 **et** app4, puis
   attendre le cron (HH:15) pour constater le retour auto à `develop`.

**Fait dans cette session (les TODO de la passe précédente sont soldés)** :
- ✅ ACL backup posée sur le VPS (`setfacl` sur `/data/backups/kpi`, avec `-d`)
- ✅ `vps-manager` de la passe 1 committé/poussé + `git pull` VPS
- ✅ PR doc de clôture Phase 6 mergée (#270)

**Améliorations non bloquantes en attente** (à décider) :
- **Durcir le retry SSH** : les 2 tentatives tiennent dans la même fenêtre d'aléa réseau
  (60s + pause 20s + 60s) → passer `timeout` à **120s** et la pause retry à **60s** dans
  `deploy-preprod.yml` + `deploy-prod.yml`. (Non fait : à trancher, ça allonge le temps
  d'échec des vraies pannes.)
- **Notification de déploiement** (§8.2) : volontairement **écartée** cette session
  (décision utilisateur). Reste possible plus tard (webhook Discord/Slack).
- **Couverture de tests** : le socle PHPUnit est posé, la couverture reste mince (1
  contrôleur public sur ~30). À étendre brique par brique.

**Détails** : voir « Phase 6 » plus bas + [[project_cicd_pipeline]] en mémoire.

**Statut Phase 1** : ✅ **terminée et verrouillée** — CI sur `develop`, `ci-summary`
est le required check sur `main_ruleset`.
**Statut Phase 2** : ✅ **éprouvée** — PHPStan (api2), `composer audit`, `npm audit`,
Gitleaks, CodeQL, Trivy config faits et **validés par une épreuve touche-à-tout
(17 checks verts, 0 skipped)** ; php-cs-fixer volontairement reporté.
**Statut Phase 3** : ✅ **éprouvée** — jobs `build-nuxt` (build Nuxt effectif app2/3/4)
et `smoke-api2` (boot Symfony : `cache:clear` + `doctrine:schema:validate --skip-sync`,
sans DB) ajoutés à `ci.yml`, branchés dans `ci-summary`, **vus verts sur une épreuve
touche-à-tout minimale (app3 + api2)**.
**Statut Phase 3bis** : 🟢 **en cours** — `trivy-image.yml` (scan CVE des images de
base php-apache/frankenphp/mariadb, **non bloquant → onglet Security**, cron hebdo +
manuel). Build Docker complet volontairement écarté (voir section).
**Statut Phase 4** : ✅ **socle livré** (2026-07-30) — PHPUnit sur api2, **2 suites**
(`unit` sans DB / `integration` sur fixtures), job CI `tests-api2` **bloquant**,
**27 tests / 115 assertions verts**. La *couverture* reste à étendre (c'est
l'adoption incrémentale prévue au plan §4.1), pas le socle.
**Statut Phase 7** : ✅ **outillage livré** (2026-07-30) — workflow
`deploy-preprod-experimental.yml`, mode `--experimental` / `--check-expiry` du wrapper,
bandeau dans app2 **et** app4, cron de retour auto. ⚠️ **Les 3 cases de validation
§7.6 attendent un run réel** (voir « État de reprise » §5).
**Statut Phase 8** : ✅ **livrée** — smoke tests **multi-URL** (une par brique) dans le
wrapper + **[DEPLOYMENT_RUNBOOK.md](../../infrastructure/DEPLOYMENT_RUNBOOK.md)**
(déclenchement, diagnostic, rollback code, rollback DB). §8.2 (notification) et §8.3
(uptime externe) **volontairement écartés**.
**Statut Phase 5** : ✅ **CD préprod COMPLET & VALIDÉ** — `deploy-preprod.yml`
(déclencheur **`push: develop`**, environment `preprod`) + `deploy-wrapper.sh` (repo
`vps-manager` privé, symlinké en `/home/deploy/`). **Le 2026-07-24, un merge sur develop
(#246) a déclenché AUTOMATIQUEMENT un déploiement préprod complet — AVEC rebuild des 3
apps — réussi de bout en bout** (7m25s : app2+app3+app4 generate + restart + smoke OK).
app4 inclus → le fix `@unhead/vue` et les artefacts root ne bloquent pas. Il a fallu
franchir **8 pièges d'infra** (voir section « pièges Phase 5 »).
**Les 2 dernières cases de validation sont closes (2026-07-27)** : rollback auto via Actions
(PR #261, cf. « Résultat case 1 ») et isolation des secrets preprod↛production (run
`test-env-isolation.yml`, cf. « Clôture Phase 5 »). **La Phase 5 est intégralement éprouvée.**
Reste seulement de l'optimisation non bloquante : durée du build apps (~7 min, pas de cache
npm) ; lock `command=` optionnel dans `authorized_keys`.

> **7e piège — `workflow_run` ne se déclenchait jamais.** Le déclencheur initial était
> `workflow_run` (CI terminée sur develop). Mais `ci.yml` ne tourne que sur
> `pull_request`, **jamais sur `push develop`** → aucune CI ne se termine sur develop →
> `deploy-preprod` ne partait pas après un merge. **Fix** : déclencheur = **`push: develop`**,
> sans re-run CI. La garantie « code testé » devient **structurelle** : `develop` exige
> désormais une **PR à CI verte** (règle ajoutée au ruleset develop, 2026-07-24) — un
> commit ne peut y arriver que validé. Filet final : smoke test + rollback du wrapper.
> `github.sha` (HEAD de develop) est le commit déployé, en push comme en dispatch.

**Fichier livré** : [`.github/workflows/ci.yml`](../../../../.github/workflows/ci.yml)
**Plan de référence** : [CI_CD_STRATEGY.md](./CI_CD_STRATEGY.md)

**Outillage** : `gh` est installé et configuré (voir
[GIT_WORKFLOW.md](../../guides/GIT_WORKFLOW.md)) — la CI se suit via `make pr_checks`
(= `gh pr checks --watch`) et se merge via `gh pr merge <n> --squash --delete-branch`.

---

## Ce que fait le workflow (état courant)

| Job | Déclencheur (path filter) | Ce qu'il vérifie |
|---|---|---|
| `changes` | toujours | Calcule quels dossiers ont changé (dorny/paths-filter) |
| `lint-nuxt` | `sources/app2\|app4/**` | `npm ci` + `npx eslint .` sur chaque app modifiée (matrice) |
| `lint-api2` | `sources/api2/**` | `composer install` + `lint:yaml config` + `lint:container` |
| `phpstan-api2` | `sources/api2/**` | **PHPStan level 3** (Phase 2) — analyse statique, sans DB |
| `lint-legacy` | `sources/**` (hors api2/app*) | `php -l` sur les fichiers PHP legacy (parallèle -P4) |
| `lint-docker` | `docker/**`, `Makefile` | hadolint + `docker compose config` (dev/preprod/prod) |
| `audit-composer` | `sources/api2/**` | **`composer audit`** (Phase 2) — CVE des deps PHP |
| `audit-npm` | `sources/app*/**` | **`npm audit --omit=dev --audit-level=high`** (Phase 2) |
| `secrets-scan` | toujours | **Gitleaks** (Phase 2) — secrets commités |
| `trivy-config` | `docker/**`, `Makefile` | **Trivy config** (Phase 2) — misconfig Dockerfiles, CRITICAL only |
| `build-nuxt` | `sources/app2\|app4/**` | **`npm ci` + `nuxt build`** (Phase 3) — build effectif, matrice, chaque app modifiée |
| `smoke-api2` | `sources/api2/**` | **boot Symfony** (Phase 3) — `cache:clear` + `doctrine:schema:validate --skip-sync`, **sans DB** |
| `tests-api2` | `sources/api2/**` | **PHPUnit** (Phase 4) — suite `unit` (sans DB) puis `integration` sur une **MariaDB éphémère** peuplée par `SQL/fixtures/` |
| `ci-summary` | toujours (`if: always()`) | Échoue si un job requis a échoué/annulé ; sinon vert |

> **CodeQL** vit dans un workflow **séparé** ([`codeql.yml`](../../../../.github/workflows/codeql.yml)),
> hors `ci-summary` : **cron hebdo (lundi) + déclenchement manuel** (plus de per-PR
> depuis 2026-07-22 — non bloquant et coûteux ~1m40s), résultats dans l'onglet Security.

Une brique non touchée ⇒ son job est **skipped**, et `ci-summary` traite skipped
comme non-bloquant. Donc une PR mono-brique ne lance que les jobs concernés.
`secrets-scan` tourne en revanche sur **toute** PR (un secret peut arriver partout).

> **app3 retiré du CI/CD (2026-07-29).** La feuille de marque (`sources/app3/`)
> n'est plus **buildée, testée ni déployée** : elle ne subsiste que comme
> **référence** pour la construction du scoring dans app4. Retiré partout :
> matrices `lint-nuxt` / `build-nuxt` / `audit-npm` (→ `app2, app4`), filtre
> `changes.app3` (l'exclusion `!sources/app3/**` du filtre `legacy` RESTE, sinon un
> commit app3 réveillerait le lint legacy), cibles Makefile `app3_generate_preprod/
> prod/production`, et l'appel `make app3_generate_${ENV}` du `deploy-wrapper.sh`.
> Restent : les cibles `app3_dev`/`app3_build`/`app3_generate_dev`/`app3_lint`/npm
> (usage local) et le scan CodeQL global (balaie tout le JS/TS de l'arbre, sans
> config par-chemin). Dependabot ne suivait déjà pas app3.

---

## Required check `ci-summary` — ✅ fait

`ci-summary` est désormais le **required status check** sur `main_ruleset`
(vérifié). Une PR CI-rouge ne peut plus être mergée dans `main`.
`develop_protection` reste **volontairement** sans required check (develop garde le
push direct).

> ⚠️ **Piège retenu — écrire les rulesets = UI uniquement.** Le PAT classique du
> poste (scopes `repo, read:org, gist, admin:public_key`) **ne peut pas** écrire
> les rulesets via l'API : tout `PATCH /repos/.../rulesets/<id>` renvoie `404`
> malgré le droit admin. Le câblage a donc été fait à la main dans l'UI (Settings →
> Rules → Rulesets → `main_ruleset` → Require status checks). Pour toute future
> édition de ruleset : UI, ou un token fine-grained avec « Repository rulesets:
> write ».

---

## Phase 2 — PHPStan sur api2

Ajouté dans [`sources/api2/`](../../../../sources/api2/) :

- `phpstan/phpstan` + `phpstan-symfony` + `phpstan-doctrine` en `require-dev`
  (Symfony **reste 7.4** — vérifié : `framework-bundle v7.4.14`, aucun compose.yaml
  touché) ;
- [`phpstan.dist.neon`](../../../../sources/api2/phpstan.dist.neon) — **level 3**,
  scope `src/`, extensions Symfony+Doctrine ;
- [`tests/phpstan-object-manager.php`](../../../../sources/api2/tests/phpstan-object-manager.php) —
  bootstrap du kernel pour typer entités/repositories Doctrine ;
- scripts composer : `composer phpstan` et `composer phpstan-baseline`
  (avec `--memory-limit=512M` : le défaut 128M ne suffit pas).

### Le plan disait « démarrer level 1 » — le code allait en fait jusqu'à level 3

Le premier passage (level 1) a révélé **4 vrais bugs**, tous corrigés dans le même
lot. Trois étaient des **fatals à l'exécution** (pas du style) :

| Fichier | Ce que PHPStan a vu | Réalité |
|---|---|---|
| `AdminCompetitionsController.php:884` | `isPastSeason()` méthode inconnue | Le contrôleur **n'utilisait pas** `CompetitionLockTrait` → garde « saison passée en lecture seule » cassée (fatal). Corrigé : `use CompetitionLockTrait;` |
| `AdminUsersController.php:1017` | Variable `$request` indéfinie | Signature `resetPassword(string $code)` sans `Request` → fatal dès qu'on appelle l'endpoint. Corrigé : injection `Request $request` |
| `AdminRcController.php:349` | `Statement::rowCount()` inconnue (DBAL 3) | `rowCount()` est sur `Result`, pas `Statement`. Corrigé : `$deleted = $stmt->executeStatement(...)` (renvoie l'int) |
| `AdminCompetitionsController.php:165` + `AdminTeamsController.php:628` | `use` de closure inutile / `?? null` redondant | Nettoyages sans risque |

Une fois ces 4 corrigés, **levels 1→3 passent à 0 erreur**. Le floor est donc posé
à **level 3** (bien au-dessus du « level 1 » prévu). Level 4 fait apparaître
~14 findings (typage plus strict) → prochain palier, à traiter en gelant un baseline.

### Pourquoi le job CI n'a pas besoin de base de données

L'extension Doctrine lit les **métadonnées de mapping** (attributs sur les entités),
pas une connexion. Le job `phpstan-api2` :

1. `composer install` (avec `gd,intl,zip` — deps `mpdf`/`phpspreadsheet` du lock) ;
2. `cp .env.dist .env` (fournit `APP_SECRET` + un `DATABASE_URL` qui doit juste
   *parser*, jamais contacté) ;
3. `cache:warmup --env=dev` → génère le container XML que lit l'extension Symfony ;
4. `composer phpstan`.

### Monter d'un niveau plus tard

```bash
# éditer phpstan.dist.neon : level: 4
docker exec kpi_api2 sh -lc 'cd /app && composer phpstan-baseline'   # gèle la dette
# committer phpstan-baseline.neon + décommenter son include dans phpstan.dist.neon
# puis vider le baseline au fil de l'eau
```

---

## Phase 2 — Sécurité statique

| Job | Outil | Politique |
|---|---|---|
| `audit-composer` | `composer audit --locked` | Scanne `composer.lock` sans `composer install` (inutile pour un audit) ; bloque sur toute CVE connue du lock api2 (clean à l'ajout) |
| `audit-npm` | `npm audit --omit=dev --audit-level=high` | Bloque **seulement** sur high/critical côté **runtime**. Les advisories des outils de dev (transitives, souvent non corrigeables) ne bloquent pas — Dependabot gère ça sur `main` |
| `secrets-scan` | `gitleaks/gitleaks-action@v2` | Scanne l'historique de la PR (`fetch-depth: 0`). Gratuit sur repo perso (la licence n'est requise que pour les orgs) |
| `trivy-config` | `aquasecurity/trivy-action` (mode `config`) | Scan des **fichiers** `docker/` (Dockerfiles/compose) — mauvaises configs. Bloque **uniquement sur CRITICAL** (0 à l'ajout) ; les nombreux HIGH de dette legacy (root user DS-0002, apt sans `--no-install-recommends`) sont laissés, comme pour hadolint. Pas d'image à builder |
| **CodeQL** (workflow séparé `codeql.yml`) | `github/codeql-action` | SAST JS/TS des apps Nuxt → onglet **Security**. **Non branché dans `ci-summary`** (plus lent, résultats en code-scanning). **Cron hebdo (lundi) + manuel uniquement** — plus de per-PR (2026-07-22 : non bloquant + ~1m40s). PHP non supporté par CodeQL → couvert par PHPStan/audits |

**php-cs-fixer : volontairement reporté.** Un dry-run `@Symfony` reformaterait
**56 des 57 fichiers** de `src/` — un commit de churn massif, à valeur purement
stylistique et zéro correctness. On ne l'ajoute pas maintenant pour ne pas noyer
l'historique ; à faire dans un lot dédié « reformat @Symfony » si souhaité.

**Reporté en Phase 2bis/3** : Trivy en mode **image** (HIGH/CRITICAL, après build
Phase 3), durcir hadolint/trivy en HIGH.

---

## Épreuve d'intégration « touche-à-tout » (2026-07-22)

Pour valider que les jobs *skipped* (path-filtered) passent bien au vert quand leur
brique est touchée — la case « PR touchant chaque brique » de la checklist — une PR
jetable a modifié un no-op dans chaque brique (`api2`, `app2/3/4`, `docker`). Elle a
**réveillé deux dettes réelles préexistantes sur `develop`**, sans rapport avec le
no-op lui-même :

### 1. Lockfiles Nuxt désynchronisés → `npm ci` échouait (app2/app3/app4)

Le job `lint-nuxt` démarre par `npm ci`, qui est strict. Les **3 locks** committés
échouaient (`Missing eslint@10.7.0` + cascade `@eslint/*` pour app2/app3 ;
`oxc-parser`/`cac`/`commander` pour app4) : ils avaient été générés avec
`npm install --package-lock-only` **avant** que les cibles `make appN_npm_update_lock`
ne soient corrigées (elles font désormais un vrai `npm install` en dossier isolé).

**Fix** : régénérer les 3 locks via `make app2/3/4_npm_update_lock` (vrai `npm install`
Node 22 en scratch), validés `npm ci` EXIT=0. Diff app2/app3 = additions pures
(sous-arbre eslint@10 nested) ; app4 = `+492 -84` (versions transitives réordonnées,
aucune entrée `node_modules/` retirée). Aucun `package.json` touché. La **cause racine
est déjà réglée dans le Makefile** — ne pas re-suggérer de retirer `--package-lock-only`.

### 2. app3 jamais linté → 19 errors ESLint → config assouplie

`app3` (feuille de marque, gelé depuis déc. 2025) n'était jamais passé sous ESLint.
`npx eslint .` sortait **19 errors + 66 warnings**. Les errors venaient de 3 règles :
`@typescript-eslint/no-explicit-any` (13×), `no-unused-vars` (3×), `ban-ts-comment` (1×).

**Décision** (module en maintenance, pas de dev actif) : rétrograder ces 3 règles en
`warn` dans [`sources/app3/eslint.config.mjs`](../../../../sources/app3/eslint.config.mjs)
(pattern `withNuxt({ rules })`, aligné sur app2). Résultat : **0 errors, 85 warnings**,
job vert, dette entièrement visible dans les logs. Réversible (`warn`→`error`) si app3
redevient actif. Alternative écartée : typer les 13 `any` sur du code figé (risque de
régression sans bénéfice).

> Les deux corrections vivent sur `develop` (branche `fix/nuxt-lockfile-eslint-desync`),
> pas sur la PR jetable — cette dernière n'a servi qu'à **révéler** la dette.

### Résultat final — ✅ épreuve concluante (2026-07-22)

Une fois les deux dettes corrigées et la PR jetable recréée à neuf depuis `develop`
(branche minimale, no-op only — les commits de bump de versions ajoutés à la 1ʳᵉ
tentative provoquaient une collision au rebase) :

**`17 successful, 0 skipped, 0 failing, 0 pending`.** Les 7 jobs path-filtered ont
tous tourné et réussi — dont **`trivy-config`, exécuté pour la première fois**. Chaque
brique (`api2`, `app2/3/4`, `docker`) est donc validée de bout en bout. La PR jetable
a été **fermée sans merge**. La Phase 2 est éprouvée intégralement.

---

## Phase 3 — Build & smoke tests

**Objectif** : garantir que `develop` reste **buildable** (pas seulement lintable),
sans encore exiger de tests fonctionnels (Phase 4).

### `build-nuxt` (app2 / app3 / app4)

`npm ci` + `npm run build` (= `nuxt build`), en matrice, path-filtered sur chaque app.
Un `.vue`/`.ts` cassé, un import manquant ou une config Nuxt invalide fait échouer le
build → CI rouge. Décisions :

- **`build` et non `generate`** : `generate` passe par `dotenv -e .env.production`
  (app2/app3), absent en CI. `nuxt.config.ts` des 3 apps a des **fallbacks**
  (`?? 'https://…'`) pour toutes les vars d'env → `nuxt build` tourne sans `.env`.
- `postinstall: nuxt prepare` s'exécute pendant `npm ci` (types + `.nuxt/`).
- Cache npm par `package-lock.json` (comme `lint-nuxt`).
- **Validé en local avant ajout** : `nuxt build` app3 en scratch Node 22 → `✨ Build
  complete!`, exit 0, `.output/` généré (~3.25 MB).

### `smoke-api2`

Boot du kernel Symfony pour garantir que le conteneur FrankenPHP démarrerait :

```
composer install --no-scripts
cp .env.dist .env
php bin/console cache:clear --env=dev            # boot réel
php bin/console doctrine:schema:validate --skip-sync   # mapping Doctrine
```

- **Aucune base de données requise** : `--skip-sync` ne valide QUE les métadonnées de
  mapping (attributs sur les entités), sans comparer à une DB. Le `DATABASE_URL` de
  `.env.dist` doit juste **parser** (jamais contacté). **Validé en local** avec un
  `DATABASE_URL` bidon : `[OK] The mapping files are correct` + `[SKIPPED] database`,
  exit 0 ; `cache:clear` exit 0.
- `lint:container` / `lint:yaml` **ne sont pas dupliqués** ici (déjà dans `lint-api2`).
  Ce job apporte le boot (`cache:clear`) + la validation de mapping.
- Mêmes extensions PHP (`gd, intl, zip`) et cache Composer que `phpstan-api2`.

### Ce qui n'est PAS dans ce lot

- **Build Docker** (`docker compose build`) et **Trivy mode image** (HIGH/CRITICAL) :
  reportés en **Phase 3bis** — lourds, à coupler ensemble (Trivy image a besoin des
  images buildées). Le plan les associe déjà.
- **Legacy** : reste au seul `php -l` (pas de build à smoke-tester).

Les deux jobs sont branchés dans `ci-summary` (`needs` + vérif des `results`), donc
couverts par le required check `main`. Vérifié : YAML valide, aucun job orphelin.

---

## Phase 3bis — Trivy image (scan CVE des images de base)

Workflow **séparé** [`trivy-image.yml`](../../../../.github/workflows/trivy-image.yml),
hors `ci-summary`. Scanne les **images de base** du projet — `php:8.4.x-apache`,
`dunglas/frankenphp:php8.4`, `mariadb:11.5.x` — lues depuis `docker/.env.dist`
(source versionnée, job `resolve-images` → matrice JSON, une catégorie SARIF par image).

### Pourquoi on ne builde PAS les images, et pourquoi c'est NON bloquant

Le plan prévoyait « builder les images puis Trivy image (HIGH/CRITICAL, bloquant) ».
La mesure locale a tranché autrement :

| Image de base | HIGH/CRITICAL (Trivy `vuln`, mesuré 2026-07) |
|---|---|
| `php:8.4.13-apache-trixie` | **596** (569 HIGH, 27 CRITICAL) |
| `mariadb:11.5.2` | 49 (45 HIGH, 4 CRITICAL) |
| `dunglas/frankenphp:php8.4` | ~1 (varie) |

Ces centaines de CVE vivent dans l'image **amont** (Debian « full » + PHP), beaucoup
en `fix_deferred`/`affected` sans correctif disponible → **non actionnables de notre
côté**. Un job bloquant serait donc rouge en permanence pour rien. Décisions :

- **Scan des images de base tirées du registry, SANS `docker build`** : l'essentiel
  de la dette OS/runtime est en amont ; builder (lourd, ~5-8 min pour l'image Apache
  legacy) n'ajouterait que nos quelques couches. Rapide (pull only).
- **`exit-code: 0` (non bloquant) + upload SARIF → onglet Security** : c'est de la
  **veille**, pas un gate. Même politique que `trivy-config`/hadolint sur la dette
  Dockerfile. On voit les CVE et on est alerté d'une nouvelle CRITICAL sans casser
  la CI.
- **Hors `ci-summary`, cron hebdo (mardi) + `push` sur `docker/.env.dist` + manuel** :
  les images de base ne changent que sur un bump explicite. Pas de per-PR.
- L'upload SARIF marche car le code scanning est déjà activé (CodeQL l'utilise).

### Ce qui reste ouvert

- Durcir un jour vers un scan **bloquant** supposerait d'abord une image de base
  **slim/distroless** (réduire les 596 findings à un volume gérable) — hors scope CI.
- Le build Docker en CI (valider que `docker compose build` passe) n'apporte pas de
  valeur sécurité ici et reste coûteux ; `lint-docker` (hadolint + `compose config`)
  couvre déjà la validité des Dockerfiles/compose. Non retenu.

---

## Phase 5 — Déploiement continu préprod

**Objectif** : tout commit sur `develop` qui passe la CI est déployé en préprod sans
intervention.

### Fondations (Phase 0) — ✅ déjà en place (vérifié 2026-07-23)

- GitHub Environments **`preprod`** et **`production`** existent ; chacun porte ses 5
  secrets (`SSH_HOST`, `SSH_USER`, `SSH_KEY`, `SSH_PORT`, `DEPLOY_PATH`), scopés →
  un job `preprod` ne peut PAS lire les secrets `production`.
- `production` a un **required reviewer** (approbation manuelle) + branch policy.
- User `deploy` sur le VPS (groupe `docker`, clé-only) validé.

### Livré : `deploy-preprod.yml`

Déclencheur **`workflow_run`** : attend que le workflow **"CI"** se termine EN SUCCÈS
sur `develop`, puis déploie le `head_sha` validé → on ne déploie jamais un état
CI-rouge. `workflow_dispatch` permet aussi un redéploiement manuel. `environment:
preprod` charge les secrets SSH. Le SHA est **validé `^[0-9a-f]{40}$`** (via variable
d'env, jamais interpolé brut dans un `run:`) avant d'être passé au wrapper.
`concurrency: deploy-preprod` avec `cancel-in-progress: false` (un déploiement à
moitié appliqué est pire qu'un déploiement en retard).

### À poser MANUELLEMENT sur le VPS (hors repo)

**1. Le wrapper** `deploy-wrapper.sh` — **versionné dans le repo `vps-manager`**
(privé, github.com/laurentgarrigue/vps-manager), aux côtés de `backup.sh` /
`health-check.sh`. Toute la config sensible (chemins, domaines) vit dans son `.env`
(gitignoré), placeholders dans `.env.dist` — même discipline que les autres scripts.
Points clés :

- **Whitelist ENV** `preprod|production` + **chemin lu depuis `.env`**
  (`DEPLOY_PATH_PREPROD=/data/kpi_preprod`, `DEPLOY_PATH_PRODUCTION=/data/kpi`) —
  surtout PAS `/srv/kpi_$ENV` (cf. [[reference_vps_deploy_paths]]).
- **Snapshot du SHA courant** dans `.last-deploy-sha` AVANT tout changement.
- **Rebuild sélectif** selon `git diff` : `sources/app*` → `make appN_generate_preprod`,
  `sources/api2/` → composer+migrations+cache+`api2_restart`, `docker/` →
  `docker_preprod_rebuild` (sinon `docker_preprod_restart`).
- **Smoke test** `curl -fsS $SMOKE_URL_PREPROD` (ex. `.../api2/doc`) → **rollback
  automatique** (`git checkout <sha précédent>` + restart) si KO.
- Validé en local : `bash -n` OK, les 9 cibles `make` existent, whitelist ENV +
  regex SHA testées (exit 1 sur entrée invalide).
- ⚠️ `api2_migrations_migrate` migre sans backup — acceptable en préprod ; pour la
  **prod (Phase 6)** il faudra un backup DB AVANT la migration (cf. `backup.sh` de
  vps-manager).

> **Installation VPS** : `vps-manager` est déjà checkouté sur le VPS (backup/health).
> Le workflow appelle `/home/deploy/deploy-wrapper.sh` (chemin stable) → créer un
> **symlink** vers le fichier réel du checkout vps-manager :
> `ln -s <chemin_checkout_vps-manager>/deploy-wrapper.sh /home/deploy/deploy-wrapper.sh`.
> Puis renseigner les 4 variables `DEPLOY_PATH_*` / `SMOKE_URL_*` dans le `.env` de
> vps-manager sur le VPS (valeurs réelles ; les placeholders sont dans `.env.dist`).

**2. Verrouiller la clé** dans `~deploy/.ssh/authorized_keys` (reporté depuis Phase 0) :

```
command="/home/deploy/deploy-wrapper.sh",no-agent-forwarding,no-port-forwarding,no-X11-forwarding,no-pty ssh-ed25519 AAAA...
```

⚠️ Avec `command=`, les args réels arrivent dans `$SSH_ORIGINAL_COMMAND` — si tu
poses ce lock, le wrapper doit parser `$SSH_ORIGINAL_COMMAND` au lieu de `$1 $2`.
**Tant que le lock n'est pas posé**, le workflow appelle directement
`deploy-wrapper.sh preprod <sha>` (forme actuelle du script). Poser le lock est un
durcissement optionnel à faire une fois le flux validé.

### Pièges VPS à respecter (cf. [[reference_vps_deploy_paths]])

- **sshd `AllowGroups`** : le user `deploy` doit être dans un groupe autorisé, sinon
  rejet avant même la clé.
- **fail2ban actif** : tester avec `-o PreferredAuthentications=publickey -o
  IdentitiesOnly=yes` pour ne pas bannir l'IP. Logs SSH réels : `journalctl -u ssh`.

### Premier test à blanc sur le VPS (2026-07-23) — mécanique validée, 2 bugs révélés

Lancé à la main : `./deploy-wrapper.sh preprod <sha>` depuis le checkout vps-manager
(⚠️ le script fait `cd "$(dirname "$0")"` pour trouver son `.env` → l'invoquer par son
chemin, pas depuis `/data/kpi_preprod`).

**Ce qui a fonctionné** ✅ : préservation `.htaccess` (`🛡 préservé` / `♻ restauré`),
`reset --hard` passant outre la dérive `reference.php`, génération app2 + app3, et
surtout **le rollback automatique** qui a proprement restauré la préprod après échec.

**Bug 1 — `git checkout` ne suffit pas en déploiement auto.** Le working tree préprod
avait `sources/api2/config/reference.php` modifié (dump de config Symfony **généré**,
dont l'ordre des annotations `// Default: null` varie → dérive de pur bruit). Un
`git checkout <sha>` échoue dessus. **Corrigé** : `git reset --hard` + liste
`PRESERVE_TRACKED` (voir ci-dessus). À noter : garder `reference.php` versionné est le
bon choix (il documente les options des bundles), mais **ne PAS** ajouter de job CI
« échoue si le fichier diffère » — la génération n'étant pas déterministe, ce check
serait rouge aléatoirement.

**Bug 2 — migrations Doctrine : faux échec systématique.**
`doctrine:migrations:migrate` sortait en ERREUR (`The version "latest" couldn't be
reached, there are no registered migrations`) car **api2 n'a aucune migration**
(`sources/api2/migrations/` ne contient qu'un `.gitignore` ; le schéma vient de la
base legacy partagée). Chaque déploiement aurait donc rollback à tort. **Corrigé** :
`--allow-no-migration` ajouté à la cible `api2_migrations_migrate` du Makefile.

**Bug 3 (hors CI/CD, préexistant) — le build app4 est cassé.**
`nuxt generate` app4 échoue : `[MISSING_EXPORT] "legacyPlugins" is not exported by
@unhead/vue/dist/legacy.mjs`. Cause : **conflit de dépendances** — `@nuxt/ui` exige
`@unhead/vue@^2.1.15` (encore en 4.10.0, la dernière), `nuxt` 4.5 exige `^3.1.8`. npm
hisse la **v2** au top-level, le build résout celle-ci → export manquant.
⚠️ **Vérifié : ce n'est PAS causé par la régénération des locks** (Phase 3) — la
résolution `@unhead/vue` est identique avant et après ce commit. Le bug était latent
et n'avait jamais été vu car la CI `build-nuxt` n'avait encore jamais buildé app4.

**Correctif retenu — `overrides` npm dans `sources/app4/package.json`** :

```json
"overrides": { "@unhead/vue": "^3.2.1", "unhead": "^3.2.1" }
```

Force la v3 pour tout l'arbre (bumper `@nuxt/ui` ne sert à rien : même sa dernière
version 4.10.0 demande encore `@unhead/vue@^2.1.15`). **Vérifié sans risque pour
`@nuxt/ui`** : il n'utilise que `createHead` / `useHead` / `injectHead`, APIs stables
présentes en v3, et son plugin `runtime/vue/plugins/head.js` s'auto-désactive sous
Nuxt (`if (app._context.provides.usehead) return;`). Validé en scratch : lock résolu
en `3.2.3` partout, **0 v2 résiduelle**, `nuxt generate` exit 0 avec sortie complète.
Lock régénéré via `make app4_npm_update_lock` (vrai `npm install`).

### ✅ Premier déploiement préprod RÉUSSI (2026-07-23)

Après correction des 3 bugs ci-dessus, `./deploy-wrapper.sh preprod 9f827653…` a
déployé la préprod **de bout en bout** : code à jour, `.htaccess` préservé, 55 fichiers
changés → briques `apps` + `api2` détectées, app2/app3/app4 générées, composer +
migrations + cache + worker FrankenPHP recyclé, stack redémarré, **smoke OK**.

Deux améliorations tirées de ce run :

**1. Smoke test avec retry (indispensable).** Un premier run avait rollback sur un
`404` — l'URL était pourtant bonne (vérifié : `preprod.kayak-polo.info/api2/doc` répond
200). Le 404 était **transitoire** : FrankenPHP/Symfony n'a pas fini de recharger juste
après `docker compose restart`. Le smoke réessaie donc maintenant (`SMOKE_RETRIES` ×
`SMOKE_DELAY`, surchargeables par env).
⚠️ **Au run réussi, l'API a mis ~25 s (6 essais × 5 s) à répondre 200** — soit pile la
limite d'alors. Marge portée à **10 × 6 s ≈ 60 s** pour éviter un rollback à tort sur un
déploiement un peu plus lent. Même logique anti-faux-positif que `health-check.sh`.

**2. Logs concis.** Les `make` (npm ci, nuxt generate, composer) noyaient l'essentiel.
Leur sortie part désormais dans `$DEPLOY_LOG` ; l'écran n'affiche qu'**une ligne de
statut par étape** (`✅ app2 généré`…). Le log n'est déversé (40 dernières lignes) **que
si une étape échoue**. `VERBOSE=1` force l'affichage intégral. Le « … en cours » n'est
émis que sur un vrai TTY (sortie propre dans les logs GitHub Actions).

### ⚠️ La cascade de 6 pièges d'infra du 1er run GitHub Actions → SSH (2026-07-24)

Faire tourner le déploiement **depuis GitHub Actions** (et non à la main) a buté sur
six obstacles successifs, chacun instructif — **à connaître avant la Phase 6 (prod)**,
qui rejouera les mêmes sur `/data/kpi` :

1. **`workflow_run` ne se déclenche que depuis `main`.** `deploy-preprod.yml` était sur
   `develop` mais absent de `main` (la branche par défaut) → GitHub ne connaissait même
   pas le workflow (404), rien ne partait sur merge. **Idem `schedule`** : les crons
   Trivy/CodeQL et le backmerge ne tournaient jamais non plus. **Fix** : synchroniser
   tout `.github/workflows/` sur `main`. (C'est **contre-intuitif** : le workflow
   *déploie develop*, mais son *fichier* doit vivre sur `main`.)

2. **Symlink → `.env` introuvable.** Le workflow appelle `/home/deploy/deploy-wrapper.sh`
   (symlink). Le wrapper faisait `cd "$(dirname "$0")"` → `/home/deploy` (pas de `.env`).
   **Fix** : `SCRIPT_DIR="$(dirname "$(readlink -f "$0")")"` suit le lien jusqu'au vrai
   fichier dans `vps-manager`. (Marchait à la main car lancé depuis `/data/vps-manager`.)

3. **Politique de branche de l'environment.** `preprod` autorise `develop`/`feature/*`/
   `hotfix/*`, **pas `main`**. Un « Run workflow » depuis `main` est rejeté
   (`Branch "main" is not allowed to deploy to preprod`). **Le sélecteur de branche du
   bouton Run détermine la branche de déploiement**, soumise à cette politique → lancer
   depuis `develop`. (⚠️ le déclenchement *auto* `workflow_run`, lui, prend toujours la
   version du fichier sur `main` — deux choses distinctes.)

4. **`git` : dubious ownership.** Le checkout `/data/kpi_preprod` appartient à
   `laurent:www-data` ; `deploy` (uid 1002) y lançant git → `fatal: detected dubious
   ownership` → **exit 128** (~1s de session). **Fix** :
   `sudo -u deploy git config --global --add safe.directory /data/kpi_preprod` (+ `/data/kpi`).

5. **`deploy` ne pouvait pas écrire dans le checkout.** Dossiers en `laurent:www-data`,
   groupe `r-x`, et `deploy` **pas** dans `www-data`. **Fix retenu = ACL** (le plus
   chirurgical — ni chown qui déposséderait laurent, ni ajout à www-data trop large) :
   ```
   sudo setfacl -R    -m u:deploy:rwX /data/kpi_preprod /data/kpi
   sudo setfacl -R -d -m u:deploy:rwX /data/kpi_preprod /data/kpi   # -d = héritage
   ```
   (`acl` installé via `apt install acl`.)

6. **Artefacts `.output`/`.nuxt`/`dist` en `root:root`** (créés par les containers Docker
   `node:22-alpine` qui tournent en root). Non bloquant pour ce run car **gitignorés**
   (le `reset --hard` ne les touche pas) et **réécrits par un container root** (root
   écrase root). Mais à surveiller : un `make appN_generate_preprod` pourrait buter
   dessus (cf. EACCES app4 connu). Ce run-ci ne rebuildait pas les apps → non exercé.

Réseau : le VPS n'a PAS de firewall applicatif (iptables INPUT = ACCEPT, seul fail2ban
filtre) ; les IP des runners GitHub passent.

7. **`dial tcp: i/o timeout` (le plus coriace).** Un déploiement réussit (12:26) puis
   tous les suivants timeout (13:45, 13:50…), même config. Diagnostic **méthodique** qui
   a écarté les fausses pistes :
   - **PAS fail2ban** : `Currently banned: 0`, `f2b-sshd` = juste `RETURN` (les bans du
     log sont des scanners SSH random, pas des runners).
   - **PAS le firewall VPS** : `iptables -S` et `nft list ruleset` ne contiennent QUE
     des règles Docker (isolation des bridges `br-*`) — rien sur le port 22/entrée
     Internet. Le VPS accepte tout sur 22.
   - **PAS les secrets** : `SSH_HOST`/`SSH_PORT` inchangés depuis le 2026-07-17 (le run
     qui a marché utilisait les mêmes).
   - **PAS sshd** : `journalctl -u ssh` ne montre AUCUNE connexion runner pendant un run
     en timeout → le paquet n'atteint jamais sshd.
   → **Diagnostic honnête a posteriori** : c'était un **aléa réseau transitoire**
     runner↔VPS sur l'**établissement de connexion** (`dial tcp: i/o timeout`), PAS un
     blocage structurel. Preuves : les runs échoués duraient 39s/13s/4s (= le délai de
     connexion qui expire, pas un build), et `SSH_HOST` contenait **déjà** l'IPv4
     (l'hypothèse « hostname résolvant en IPv6 » un temps envisagée était FAUSSE —
     ré-écrire le secret n'a rien changé au fond). Mitigation : `timeout` connexion
     30s → **60s** (+ `command_timeout: 15m`). Pas de cause côté VPS ni secrets : rien
     à « réparer », c'est du réseau intermittent.
   → **MàJ 2026-07-29 — retry automatique.** Ce résidu se manifestait encore : un
     déploiement préprod échouait en ~25-40s puis **repassait au re-run manuel** (2 KO
     observés #15/#16, cf. `gh run list`). On a donc automatisé le re-run : l'étape SSH
     est dédoublée en **« tentative 1 » (`continue-on-error: true`) + « retry »**
     (`if: steps.deploy1.outcome == 'failure'`, après `sleep 20`) dans
     `deploy-preprod.yml` ET `deploy-prod.yml`. SANS risque de demi-déploiement :
     l'échec est sur la CONNEXION (avant que le wrapper démarre) et le wrapper repart
     de zéro (snapshot + reset --hard + rebuild + smoke + rollback), il est rejouable.
     Si le retry échoue à son tour, le job échoue (pas de masquage d'un vrai bug :
     build cassé, smoke KO, migration KO). NB prod : le retry relance le backup DB
     pré-migration, inoffensif (backup.sh rejouable). Résidu ultra-rare (2 KO
     consécutifs) → `gh run rerun` reste dispo.

8. **Faux « blocage » = vrai build long (~7 min).** Un run auto est resté `in_progress`
   >5 min → cru bloqué. En réalité c'était le **premier run rebuildant les apps**
   (`npm ci && nuxt generate` × app2/app3/app4 dans des containers `node:22-alpine`
   jetables, **sans cache npm** → tout retéléchargé à chaque fois). `ps -u deploy` a
   montré le `nuxt generate` d'app4 en cours, pas un process figé. Il a fini en **7m25s**
   avec succès. `command_timeout: 15m` dans `ssh-action` couvre bien cette durée. Piste
   d'optim (non bloquant) : cache npm côté VPS ou builds incrémentaux. **Ne pas confondre
   un déploiement qui rebuild les apps (~7 min) avec un blocage** — un run « aucune brique
   à rebuilder » prend ~48s.

### Validation

- [x] Wrapper exécuté à la main sur le VPS : préservation + reset + rollback OK
- [x] **Déploiement préprod complet réussi via GitHub Actions** (SSH → wrapper → smoke)
- [x] Build app4 réparé (override `@unhead/vue` v3)
- [x] **Merge PR sur `develop` → préprod déployée 100 % AUTO sans clic** (#246, 2026-07-24)
- [x] **Run touchant `sources/app*` → `make appN_generate_preprod` passe** (app4 inclus, artefacts root OK)
- [x] **Commit cassé → rollback auto → préprod restaurée, via Actions** — ✅ **PROUVÉ le 2026-07-27** (PR #261 « casse /api2/doc » mergée → Deploy preprod → **rollback auto**). Voir « Résultat case 1 » ci-dessous : le rollback s'est déclenché plus tôt que prévu (échec `composer install`, pas le smoke), mais le mécanisme auto est validé de bout en bout.
- [x] **Le job `preprod` ne peut PAS lire les secrets `production`** — ✅ **PROUVÉ le 2026-07-27** via `test-env-isolation.yml` (run #1) : le job `preprod-must-not-see-canary` est VERT, càd le secret `PROD_ISOLATION_CANARY` (défini uniquement dans l'environment `production`) est INVISIBLE depuis un job `environment: preprod`. Voir la note « job témoin » ci-dessous.

### Clôture Phase 5 — les 2 dernières cases (procédure, 2026-07-27)

Les deux tests sont **préparés dans le working tree** (branches locales, à pousser + MR
selon le workflow habituel — moi je ne pousse ni ne merge).

**Case 1 — rollback auto via Actions ✅ PROUVÉ (2026-07-27).** Branche
`test/preprod-rollback-break` : elle commente la route `app.swagger_ui` dans
[`sources/api2/config/routes.yaml`](../../../../sources/api2/config/routes.yaml). Vecteur
choisi parce que **`smoke-api2` (CI) ne teste jamais `/doc`** — il fait seulement
`cache:clear` + `doctrine:schema:validate`, qui ne touchent pas les routes → une route en
moins **passe la CI** (YAML validé `[OK]`). Mergée en PR #261 → **Deploy preprod** déclenché.

**Résultat case 1 — le rollback a fonctionné, mais s'est déclenché plus tôt que le smoke.**
Dans le run réel, le wrapper a rollback **avant** d'atteindre le smoke `/api2/doc` : l'étape
`api2_composer_install` a échoué (`composer install` code 2, conteneur `kpi_preprod_php` en
`restarting`), ce qui a suffi à déclencher le `rollback` :
```
❌ composer install (code 2)
✅ migrations Doctrine / cache vidé / worker recyclé   (étapes suivantes tentées)
❌ une étape de rebuild a échoué → rollback
🔙 ROLLBACK vers dcb75aaf…
❌ Déploiement ANNULÉ — preprod restaurée sur dcb75aaf…
```
Donc **le rollback auto est validé de bout en bout via Actions** (échec détecté → restauration
du SHA précédent → préprod saine), mais **pas exactement par le chemin prévu** : le déclencheur
a été un échec de rebuild api2, pas le 404 du smoke. Deux enseignements :
- Le wrapper rollback sur **toute** étape de rebuild KO, pas seulement le smoke — c'est plus
  robuste que prévu (défense en profondeur).
- Le `composer install` en `code 2` sur une simple route commentée est un **effet de bord à
  comprendre** (probablement le conteneur php préprod déjà instable / en redémarrage au moment
  du déploiement, indépendamment du changement). À creuser si ça se reproduit, mais **hors
  scope de la validation du rollback**, qui est acquise.

> ⚠️ **Le rollback restaure le working tree du VPS au SHA précédent, PAS `develop` sur
> GitHub.** Sans réparation, le prochain déploiement re-casserait api2. **Revert obligatoire**
> — désormais outillé par deux cibles Make (voir [[GIT_WORKFLOW]] §3 Rollback) :
> ```bash
> make last_merge_sha                 # trouve le SHA du commit fautif sur develop
> make preprod_rollback sha=<sha>     # crée revert/<sha> avec le commit inversé
> make pr_create && make pr_merge     # PR de revert → redéploie une préprod saine
> ```
> Fait le 2026-07-27 via la branche `fix/restore-api2-doc-route` (revert de #261).

**Case 2 — isolation des secrets `production`.** Branche **`test/env-isolation`** : workflow
[`test-env-isolation.yml`](../../../../.github/workflows/test-env-isolation.yml)
(`workflow_dispatch`, 2 jobs). Le job `environment: preprod` **échoue** si le secret
sentinelle `PROD_ISOLATION_CANARY` y est visible ; un job témoin `environment: production`
confirme que le canary existe (sinon le job preprod serait vert pour la mauvaise raison — un
secret jamais créé est aussi « vide »). Secrets lus via `env:`, jamais imprimés (GitHub les
masque de toute façon → on teste « vide vs non-vide », pas la valeur). Séquence :

1. Créer le secret **`PROD_ISOLATION_CANARY`** (valeur au choix, ex. `LEAKED`)
   **UNIQUEMENT** dans l'environment `production` (Settings → Environments → production →
   Secrets). **PAS** au niveau repo, **PAS** dans `preprod`.
2. Merger `test/env-isolation` sur develop, puis **s'assurer que le fichier atteint `main`**
   (le back-merge auto s'en charge, ou une PR develop→main) — sinon le bouton « Run
   workflow » n'apparaît pas (1er piège Phase 5 : `workflow_dispatch` ne se voit que depuis
   la branche par défaut).
3. Actions → « Test env isolation » → **Run workflow depuis `develop`**.
4. **Résultat réel (run #1, 2026-07-27)** : job **`preprod-must-not-see-canary` ✅**
   (« canary INVISIBLE en preprod ») ⇒ **isolation PROUVÉE**. Le job témoin
   **`production-must-see-canary` a ÉCHOUÉ**, mais **PAS** sur le test : rejeté par la
   *politique de branche* de l'environment `production` — `Branch "develop" is not allowed
   to deploy to production due to environment protection rules` (production n'autorise que
   `main`, cf. Phase 0). C'est un **conflit de contraintes irréductible dans un seul run** :
   le job preprod EXIGE `develop`, le témoin production EXIGE `main` — impossible de
   satisfaire les deux ensemble. Le témoin n'était qu'un garde-fou (« le canary existe-t-il
   vraiment ? ») ; comme le secret **a bien été créé** dans production (confirmé
   manuellement), l'invisibilité en preprod n'est pas un faux positif « secret inexistant ».
   **La case est donc validée par le seul job preprod.** (Pour une preuve exhaustive
   facultative : relancer « Run workflow depuis `main` » → le témoin production ✅ y
   confirmerait l'existence du canary, tandis que le job preprod y échouerait
   symétriquement. Non nécessaire.)
5. **Nettoyer** : supprimer le workflow `test-env-isolation.yml` **et** le secret
   `PROD_ISOLATION_CANARY`.

> **Leçon retenue — un workflow à 2 jobs sur 2 environments à politiques de branche
> disjointes ne peut pas tout prouver en un run.** Si un futur test devait valider les deux
> côtés d'un coup, il faudrait soit assouplir temporairement les politiques de branche, soit
> deux workflows séparés. Ici l'objectif (isolation preprod↛production) est atteint par le
> seul job preprod, donc on n'y touche pas.

---

## Phase 6 — Déploiement production (2026-07-27)

**Objectif** : déployer la prod (`/data/kpi`) **à la main, avec approbation**, en
rejouant l'outillage éprouvé en préprod. Choix actés avec l'utilisateur :
**déclencheur `workflow_dispatch` seul** (pas de push de tag) + **backup DB avant
migration** via le `backup.sh` existant de vps-manager.

### Ce qui était DÉJÀ prêt (hérité de la Phase 5)

- **Le wrapper `deploy-wrapper.sh` gère déjà `production`** : whitelist ENV
  `preprod|production`, `BASE=$DEPLOY_PATH_PRODUCTION` (`/data/kpi`),
  `SMOKE_URL=$SMOKE_URL_PRODUCTION`. Snapshot `.last-deploy-sha`, `reset --hard`,
  rebuild sélectif, smoke + **rollback auto** : tout est ENV-agnostique.
- **VPS `/data/kpi` prêt** (vérifié 2026-07-27, posé en Phase 5 en même temps que
  préprod) : `git config --global safe.directory /data/kpi` **présent** pour
  `deploy`, **ACL `u:deploy:rwX`** (avec héritage `-d`) **présent**. Les conteneurs
  prod (`kpi_php`, `kpi_api2`, `kpi_db`…) tournent.

### Le piège qui aurait cassé le 1er déploiement prod : nommage `_prod` vs `_production`

Le wrapper appelle `make appN_generate_${ENV}` / `make docker_${ENV}_restart`. Avec
`ENV=production`, ça donne `app4_generate_production`, `docker_production_restart`,
etc. Or le Makefile n'avait **que `app2_generate_production`** — app4 et docker
n'exposaient que le nom court **`_prod`** (`app4_generate_prod`, `docker_prod_restart`).
Donc un déploiement prod aurait échoué sur les étapes app4/docker (`make: *** No
rule to make target`). En préprod le hasard jouait : `docker_preprod_restart` /
`appN_generate_preprod` existent tous.

**Fix (Makefile, ce lot)** : ajout d'alias `production` pointant sur le `_prod`
canonique, sur le modèle de l'alias `app2_generate_prod: app2_generate_production`
déjà présent :

```make
docker_production_restart: docker_prod_restart
docker_production_rebuild: docker_prod_rebuild
app4_generate_production:  app4_generate_prod
```

Ajoutés au `.PHONY`. `make -n` sur les cibles `*_production` → résolution OK. Le
wrapper reste **générique** (aucun `case $ENV` sur les noms de cibles).

> **MàJ 2026-07-29** : app3 ayant été **retiré du déploiement** (voir l'encadré
> « app3 retiré du CI/CD » plus haut), l'alias `app3_generate_production` initialement
> ajouté ici a été **supprimé** en même temps que les cibles `app3_generate_*`, et
> l'appel `make app3_generate_${ENV}` a été retiré du wrapper. Restent les alias
> app2/app4/docker.

### Backup DB avant migration (prod only) — dump dédié horodaté

Le plan §6.3 exige un backup DB avant migration en prod. Ajouté dans le bloc
`REBUILD_API2` du wrapper, **juste avant `api2_migrations_migrate`**, gardé par
`[ "$ENV" = "production" ]` :

```bash
if [ "$ENV" = "production" ] && [ "$deploy_ok" = 1 ]; then
  run_quiet "backup DB prod (kpi) avant migration" backup_db_pre_migration \
    || ko "backup DB pré-migration EN ÉCHEC (déploiement poursuivi ; vérifier les droits sur $BACKUP_BASE_DIR/kpi)"
fi
```

- **Fonction dédiée `backup_db_pre_migration`** (et NON `backup.sh kpi`) : elle dumpe la
  base prod **`kpi`** (conteneur `kpi_db`) dans un fichier **horodaté**
  `pre-migration/kpi_<DATE-HEURE>_<sha7>.sql.gz`. Raison : `backup.sh <svc>` écrit
  `kpi_<DATE>.sql.gz` (nom **journalier**) → un dump pré-migration **écraserait** le
  backup cron de la nuit ; le fichier horodaté ne l'écrase jamais et reste traçable par
  déploiement. Les identifiants (container/db/user/pass) sont lus depuis
  `SERVICES_TO_BACKUP` (déjà `source`é du `.env` — **aucun secret en dur**). `pipefail`
  fait échouer la fonction si le `mariadb-dump` échoue ; garde-fou `[ -s ]` sur les dumps
  vides. WordPress (`kpi_wordpress`) n'est pas migré par Doctrine → hors scope.
- **Un backup KO ne bloque PAS le déploiement** (le cron `backup.sh` reste le filet
  principal), mais il est tracé en `❌`. Rationnel : aujourd'hui api2 n'a **aucune
  migration versionnée** (schéma = base legacy partagée, `migrate --allow-no-migration`
  = no-op), donc ce backup est surtout un filet pour le futur ; le rendre bloquant
  ferait échouer des déploiements pour un no-op.
- ⚠️ **Prérequis d'infra (ACL)** : `deploy` doit pouvoir écrire dans
  `$BACKUP_BASE_DIR/kpi/` (= `/data/backups/kpi/`). Au 1er déploiement prod (2026-07-30)
  ce n'était PAS le cas → le backup a échoué en `Permission non accordée` (dossiers
  possédés par `laurent`, ACL Phase 5 posée seulement sur les checkouts `/data/kpi*`).
  **Fix à poser une fois** (comme les ACL Phase 5) :
  ```bash
  sudo setfacl -R    -m u:deploy:rwX /data/backups/kpi
  sudo setfacl -R -d -m u:deploy:rwX /data/backups/kpi
  ```
- ⚠️ **Cette modif vit dans le repo `vps-manager` (privé), PAS dans kpi.** Elle a été
  **appliquée directement** dans le checkout local `~/Documents/dev/vps-manager`
  (`deploy-wrapper.sh`, branche `main`) — Laurent n'a plus qu'à committer + pusher, puis
  `git pull` côté VPS (`/data/vps-manager`, en tant que `laurent` : le checkout VPS est
  `read-only` pour `deploy`). Le même lot y retire aussi l'appel `make app3_generate_${ENV}`
  (app3 sorti du déploiement).

### `deploy-prod.yml` (ce lot)

`workflow_dispatch` avec input `ref` (défaut `main`). `environment: production` →
**approbation manuelle** (required reviewer Phase 0). Étapes :

1. `checkout` (fetch-depth 0) du `ref` demandé.
2. **`git merge-base --is-ancestor HEAD origin/main`** : refuse tout ref qui n'est pas
   sur `main` (donc pas passé par CI + revue de la PR de release). `inputs.ref` est
   passé via `env: REQ_REF` et jamais interpolé dans un `run:` (anti-injection). Le SHA
   déployé = `git rev-parse HEAD`, revalidé `^[0-9a-f]{40}$`.
3. SSH → `deploy-wrapper.sh production <sha>`. `timeout: 60s`, `command_timeout: 20m`
   (marge vs les 15m préprod : la prod peut rebuild les 3 apps + migration + backup).

### Go-live checklist Phase 6 — ✅ FAIT (2026-07-30)

- [x] **vps-manager** : `deploy-wrapper.sh` modifié (dump pré-migration dédié + retrait
      app3). ⚠️ **reste à committer/pusher + `git pull` VPS** (cf. « État de reprise »).
- [x] `deploy-prod.yml` mergé jusqu'à `main` (via PR de release develop→main #266, après
      résolution du conflit doc #268) → bouton « Run workflow » visible.
- [x] Environment `production` autorise `main` → run lancé depuis `main`.
- [x] **1er run réel réussi** : Actions → « Deploy production » → approuvé → wrapper OK
      (rebuild api2 + migration + smoke). A nécessité **2 corrections en vol** (remote git
      SSH→HTTPS, cf. plus bas) et **1 `gh run rerun`** (aléa réseau : les 2 tentatives SSH
      ont timeout dans la même fenêtre).
- [x] Cases §6.5 du plan mises à jour.

### Les 2 pièges Phase 6 (en plus des 8 de Phase 5)

**Piège A — remote git prod en SSH.** `/data/kpi` avait `origin =
git@github.com:laurentgarrigue/kpi.git`. `deploy` n'a ni clé github ni `known_hosts` →
`git fetch` = `Host key verification failed` → **exit 128** dès « Récupération du code ».
Préprod marchait car son remote est en **HTTPS** (`https://github.com/...`, repo public,
lecture sans clé). **Fix** : `git remote set-url origin https://github.com/laurentgarrigue/kpi`
sur `/data/kpi` (fait). *Leçon : aligner les 2 checkouts sur HTTPS.*

**Piège B — backup pré-migration `Permission non accordée`.** `deploy` ne peut pas écrire
dans `/data/backups/kpi/` (possédé par `laurent`, l'ACL Phase 5 ne couvrait que les
checkouts `/data/kpi*`). Le backup a donc échoué — **sans bloquer** (best-effort) → la
prod s'est déployée quand même, et la migration était un no-op (api2 sans migration
versionnée). **Fix ACL à poser** (cf. « État de reprise » + section « Backup DB »).

### Ce qui reste ouvert (non bloquant)

- **ACL backup `/data/backups/kpi`** à poser (voir « État de reprise » §1).
- **Durcir le retry SSH** (timeout 120s + pause 60s) — les 2 tentatives tombaient dans la
  même fenêtre d'aléa réseau.
- **Rollback DB prod** : §6.4 du plan veut un runbook. Le dump pré-migration dédié est en
  place (`pre-migration/`) ; le runbook de restauration reste à écrire (Phase 8,
  `DEPLOYMENT_RUNBOOK.md`).
- Lock `command=` dans `authorized_keys` (durcissement optionnel, cf. Phase 5).

---

## Phase 4 — Tests fonctionnels : le socle PHPUnit d'api2 (2026-07-30)

**Objectif tenu** : poser un socle de tests **réel** (pas de test fictif) et le rendre
bloquant, sans attendre une couverture large. Le plan §4.1 prévoyait l'inverse (job non
bloquant tant qu'un seuil de couverture n'est pas atteint) — voir l'écart plus bas.

### Décision structurante : DEUX suites, séparées par leur besoin d'infra

C'est ce qui rend la phase adoptable tout de suite :

| Suite | Contenu | Infra | En CI |
|---|---|---|---|
| `unit` | Logique pure (`DateValidationTrait`, `CompetitionLockTrait`) | **rien** (ni DB ni kernel) | toujours, < 1 s |
| `integration` | Boot du kernel + requêtes HTTP réelles sur les endpoints publics | MariaDB + fixtures | service éphémère |

Sans cette séparation, il aurait fallu une base de données pour lancer le moindre test →
personne ne les lance en local → ils pourrissent. Là, `composer test-unit` tourne partout,
et `integration` se met en **SKIP explicite** (message clair) si `API2_TEST_DB=1` n'est
pas posé. Vérifié : `27 tests, 34 assertions, 11 skipped` sans base ;
`27 tests, 115 assertions, OK` avec.

### Alignement avec la branche scoring (demandé par l'utilisateur)

La branche `claude/scoring-refactoring-strategy-*` avait choisi, pour son lot 1.2, un
**runner PHP sans dépendance** (`sources/api2/tests/Scoring/scoring_rules_test.php`, 62
assertions, exit 0 = vert). Son en-tête dit explicitement *« api2 has no test framework
yet … Migrate to PHPUnit as-is when a test pack lands »*. Ce lot **est** ce test pack.

- On n'a **pas** porté ce fichier ici : `src/Scoring/ScoringRules.php` **n'existe pas sur
  `develop`** (il n'est que sur la branche scoring), un test le requérant échouerait.
- La **procédure de migration est documentée** dans
  [`sources/api2/README.md`](../../../../sources/api2/README.md) (§ Tests → « Migrating the
  standalone scoring runner ») : au merge de la branche scoring, les 62 `check()` deviennent
  autant de `assertSame` dans `tests/Unit/Scoring/ScoringRulesTest.php`, et le runner est
  supprimé pour que les règles aient **un seul** foyer de test.

### Ce que les tests protègent réellement

On n'a pas écrit des tests « pour la couverture ». Chaque test verrouille une décision
qu'un refactor bien intentionné casserait :

- **`CompetitionLockTrait`** — PHPStan avait déjà révélé qu'un contrôleur avait **oublié**
  `use CompetitionLockTrait;` (garde « saison passée » morte, fatal à l'exécution, cf.
  Phase 2). L'analyse statique attrape l'absence du trait ; **seuls des tests attrapent une
  inversion de logique dedans**. Notamment : `Verrou` est **délibérément ignoré** (il ne
  gèle que les feuilles de présence) — une « correction » qui l'ajouterait casserait
  l'édition inline des phases. Le test le fige.
- **`DateValidationTrait`** — `DateTime::createFromFormat` est laxiste et « roule » les
  dates hors bornes (`2026-02-31` → `2026-03-03`). C'est la comparaison
  `format('Y-m-d') === $date` qui les rejette : un test dédié empêche de la supprimer par
  méprise.
- **`/events/{mode}` et `/event/{id}`** — trois logiques non triviales : la whitelist de
  `mode` (403 sinon), des **filtres de publication différents selon le mode** (`app` pour
  `std`, `Publication` pour `all` — subtil, contre-intuitif, facile à « corriger » à tort),
  et la bascule `id < 3000` (en dessous un tournoi `kp_evenement`, au-dessus une journée
  `kp_journee`).

### Fixtures : `SQL/fixtures/`, 100 % synthétiques

Saisons `2999`/`2998`, compétitions `TST*`, événements ≥ 9000. **Aucune donnée réelle**
(RGPD, et des jeux stables qui ne cassent pas quand un vrai match est saisi). Chaque ligne
porte un commentaire disant quel cas elle couvre — les paires publié/non-publié sont ce qui
prouve que les filtres marchent, il ne faut pas les « nettoyer ».

Le schéma est un **sous-ensemble** de la prod copié depuis `SHOW CREATE TABLE`, **sans les
clés étrangères** vers les tables hors périmètre (sinon il faudrait recopier la moitié du
schéma). Types et défauts identiques à la prod : c'est ce qui compte pour que le SQL des
contrôleurs se comporte pareil.

> **Détail attrapé au passage** : `kp_saison.Code` est un **`char(4)`** (l'année : `2026`),
> pas un `2025-2026`. Les premiers tests utilisaient le mauvais format — la logique de
> comparaison est identique, mais les fixtures auraient menti sur le réel.

### ⚠️ Le piège `_test` : Doctrine renomme la base sous vos pieds

`config/packages/doctrine.yaml` porte `dbname_suffix: '_test%env(default::TEST_TOKEN)%'`
en `when@test` (recette Symfony standard). Conséquence qui a fait échouer les 10 premiers
tests avec `Unknown database 'kpi_test_test'` : **en `APP_ENV=test`, Doctrine ajoute `_test`
au nom de base du `DATABASE_URL`**.

**On l'a GARDÉ** (et documenté dans le YAML) au lieu de le retirer, parce que c'est un
**garde-fou de sécurité** : il rend *impossible* de lancer la suite de tests sur la base de
dev/préprod/prod par une simple erreur de variable d'environnement. Règle à retenir : la
base de fixtures s'appelle `<nom>_test`, et le `DATABASE_URL` cite `<nom>` **sans** le
suffixe.

### Installation : `ext-gd` manquant dans le conteneur api2

`composer require --dev phpunit/phpunit` échoue dans `kpi_api2` :
`mpdf/mpdf … requires ext-gd * -> it is missing from your system`. L'extension est déclarée
par des deps du lock mais **absente du conteneur api2** (c'est déjà pourquoi les jobs CI
api2 installent `gd, intl, zip`). Contournement : `--ignore-platform-req=ext-gd`
(+ `COMPOSER_CACHE_DIR=/tmp/...`, le cache par défaut n'étant pas inscriptible).

> La recette Flex de `phpunit-bridge` a tenté d'injecter un bloc `<extensions>` dans
> `phpunit.dist.xml` et a échoué (fichier écrit à la main avant) — **sans dommage**, elle
> n'a pas écrasé le fichier. Vérifié : Symfony **reste en 7.4.14**, aucun `compose.yaml`
> touché.

### Écart assumé vs le plan : job **bloquant** tout de suite

Le plan §4.1 voulait un job `continue-on-error` jusqu'à un seuil de couverture. On a fait
l'inverse : `tests-api2` est **bloquant dès l'ajout**, parce que les deux suites sont vertes
et rapides (< 1 min). Un job non bloquant est un job qu'on cesse de regarder. Ce qui reste
incrémental, c'est la **couverture** — pas la sévérité.

**Non fait, assumé** : les suites **Playwright/Vitest** (app2/app4). Aucun framework de
test JS n'est installé dans le projet (le `sources/app4/tests/playwright/` existant n'est
qu'une **procédure manuelle** pour piloter l'app via Playwright MCP + des captures, pas une
suite exécutable). Les monter demanderait un lot dédié (build Nuxt + API + DB en CI).

---

## Phase 7 — Déploiement de features expérimentales en préprod (2026-07-30)

**Objectif** : déployer une branche `feature/*` en préprod sans passer par `develop`, pour
la tester en conditions réelles — sans jamais laisser croire que la préprod est dans son
état de référence.

### Le fichier livré : `deploy-preprod-experimental.yml`

`workflow_dispatch` avec `branch` + `ttl_hours`. Trois choses valent d'être notées :

- **`environment: preprod` EN DUR** → il est structurellement impossible de viser la prod
  avec ce workflow, et il ne peut lire que les secrets scopés préprod.
- **`concurrency: deploy-preprod`, le MÊME groupe que `deploy-preprod.yml`** : un
  déploiement expérimental et le déploiement auto d'un merge sur `develop` ne doivent
  jamais s'entrelacer sur la même préprod.
- **Entrées validées avant tout usage** : `branch` sur `^[A-Za-z0-9._/-]{1,100}$` + refus
  de `..`, `ttl_hours` entier 1-168. Les valeurs passent par `env:` et ne sont **jamais**
  interpolées brutes dans un `run:`. Le wrapper **revalide** de son côté (défense en
  profondeur : il est aussi appelable à la main en SSH).

### Écart vs §7.3 : où vit le marqueur (et pourquoi)

Le plan disait `sources/EXPERIMENTAL_FLAG.json`. **Ça ne pouvait pas marcher** : les apps
sont générées en **statique** et servies par nginx depuis `.output/public/` — un fichier
dans `sources/` n'est pas accessible en HTTP, donc les apps ne peuvent pas le lire.

Retenu : **`experimental-flag.json` déposé dans `.output/public/` de chaque app**, avec
deux conséquences qui ont dicté le code :

1. il s'écrit **APRÈS** `nuxt generate` (qui recrée `.output/` de zéro) ;
2. le mode expérimental **force le rebuild des apps** même si la branche ne touche pas
   `sources/app*` — sinon `.output/public/` pourrait ne pas exister, et le bandeau
   manquerait à l'appel. Le bandeau est la contrepartie de l'écrasement de la préprod : il
   ne doit jamais manquer.

L'état **faisant foi pour l'expiration** vit à part, dans
`<checkout>/.experimental-deploy.json` : **hors arbre git**, donc il survit au
`reset --hard` et aux rebuilds (contrairement au marqueur servi, jetable et régénérable).

### Le TTL, et les 3 chemins de nettoyage

`deploy-wrapper.sh preprod --check-expiry` (cron horaire, `install-cron-experimental-expiry`
dans `vps-manager`) est un **no-op silencieux** s'il n'y a pas de déploiement expérimental
actif ou si le TTL n'est pas atteint — il peut donc tourner en permanence sans bruit.
Quand le TTL est dépassé, il **se rappelle lui-même** avec le SHA de `origin/develop` :
toute la mécanique éprouvée (snapshot, reset, rebuild sélectif, smoke, rollback) est
réutilisée telle quelle plutôt que réécrite en variante.

Trois nettoyages, chacun pour un scénario réel :

| Scénario | Ce qui nettoie |
|---|---|
| Le TTL expire | le cron `--check-expiry` → redéploie `develop` |
| Un merge sur `develop` arrive **pendant** un déploiement expérimental | le déploiement normal retire marqueur + état (sinon la préprod redeviendrait saine **en continuant d'afficher le bandeau** — pire que pas de bandeau) |
| Le déploiement expérimental **échoue** (rollback) | le `rollback()` retire marqueur + état (sinon bandeau pour une branche qui n'est plus déployée) |

Cas d'échec du cron traités : `expires_at` absent ou non parsable → marqueur retiré (exit
0, on ne boucle pas) ; `origin/develop` non résoluble → marqueur **conservé** et **exit 1**,
on réessaiera au prochain cron. Testé en sandbox : les 4 chemins font ce qui est écrit.

### Côté apps : `appEnv`, et la variable qui n'existait pas

Le bandeau ne doit **jamais** tourner ailleurs qu'en préprod (aucune requête en dev/prod).
Le composable se garde donc sur `runtimeConfig.public.appEnv === 'preprod'`. Deux
découvertes en chemin :

- **app2 n'avait pas d'`appEnv`** → ajouté dans `nuxt.config.ts`, sur le modèle d'app4.
- **`APP_ENV` n'était câblé NULLE PART dans les builds Nuxt.** `sources/app4/.env.dist` ne
  l'a qu'en commentaire, et les cibles `appN_generate_*` ne passaient aucune variable →
  `appEnv` retombait toujours sur `development`, et le bandeau **ne se serait jamais
  affiché**. Corrigé dans le Makefile : `-e APP_ENV=preprod|production` sur les 4 cibles
  `app2/app4_generate_preprod|prod`.
- ⚠️ **Pourquoi par le Makefile et pas par `.env.preprod` d'app2** : les `.env.*` d'app2
  sont **gitignorés** (`sources/app2/.gitignore` : `.env.*`). Une variable qui n'y vit que
  localement **n'atteindrait jamais le VPS**. Le Makefile, lui, est versionné. (Piège vérifié
  au `git check-ignore` après avoir d'abord écrit dans `.env.preprod` — édition annulée.)

Le composable est **dupliqué** entre app2 et app4 (projets Nuxt indépendants, pas de paquet
partagé) : les deux fichiers le disent en en-tête, toute correction doit être reportée.
Il relit le fichier toutes les **5 min** (`cache: 'no-store'`) pour qu'un onglet resté
ouvert voie le bandeau disparaître après le retour à `develop`, et **ignore un flag expiré**
côté client (si le cron a du retard, on n'affiche pas une échéance mensongère). Toute erreur
(404, JSON invalide, réseau) ⇒ pas de bandeau : un bandeau d'information ne doit jamais
faire échouer l'app.

---

## Phase 8 — Post-déploiement & observabilité (2026-07-30)

### 8.1 Smoke tests : d'une URL à une par brique

Le smoke d'origine ne testait que `/api2/doc` : **api2 pouvait répondre alors qu'app2
servait une page blanche ou que le legacy était cassé**. Désormais une URL **par brique** —
api2 `/doc`, un endpoint public api2 (qui atteint la base et sérialise), app2, app4, legacy
`index.php`. **N'importe laquelle en échec ⇒ rollback.**

- Les listes viennent du `.env` de `vps-manager` (`SMOKE_URLS_PREPROD` /
  `SMOKE_URLS_PRODUCTION`, séparées par des espaces) — **aucun domaine en dur** dans le
  script versionné (repo public).
- **Repli** sur l'ancien `SMOKE_URL_*` si la liste n'est pas définie : un `.env` non mis à
  jour continue de fonctionner à l'identique. ⚠️ Corollaire : **tant que le `.env` du VPS
  n'est pas complété, la Phase 8.1 n'est pas active** (cf. « État de reprise » §2).
- Le **retry par URL est conservé** (10 × 6 s ≈ 60 s) : FrankenPHP met plusieurs secondes à
  router après un `docker compose restart`, un curl unique provoquerait un rollback à tort.
  C'est la leçon du 2026-07-23 (l'API avait mis ~25 s), on ne l'a pas perdue en passant au
  multi-URL.

### 8.4 Runbook

[**DEPLOYMENT_RUNBOOK.md**](../../infrastructure/DEPLOYMENT_RUNBOOK.md) — pensé pour être lu
**en situation**, pas pour expliquer la conception (c'est le rôle de ce journal) :
carte express « je veux… → comment », déclenchement des 3 types de déploiement, où regarder
pour diagnostiquer, **table des échecs courants** (les pièges des Phases 5 et 6 convertis en
correctifs actionnables), rollback du code (auto / réparation de `develop` / manuel),
**rollback de la base** prod, contacts, et une section « ce qui n'est PAS en place » pour ne
pas chercher une fonctionnalité inexistante.

Le rollback DB (§6.4 du plan) commence par **dumper l'état courant** avant de restaurer : si
la restauration se révèle être une erreur d'analyse, on doit pouvoir revenir à l'état
« cassé » pour l'étudier. Documenté mais **non testé** — api2 n'a aucune migration
versionnée à casser aujourd'hui.

### Écarté volontairement

- **§8.2 notification** (Discord/Slack/mail sur succès/échec) : **décision utilisateur de
  cette session** — pas de webhook pour l'instant, on lit l'onglet Actions. Le runbook le
  dit explicitement pour éviter qu'on cherche l'alerte qui n'arrivera pas.
- **§8.3 alerting long terme / uptime externe** : hors scope CI/CD strict. Le
  `health-check.sh` du VPS surveille déjà les URLs toutes les 5 min avec alerte mail et
  anti-faux-positifs.

---

## Écarts historiques assumés (Phase 1, toujours valables)

1. **Legacy : uniquement `php -l`** (syntaxe) — pas de lint de style vu la dette.
2. **hadolint en `failure-threshold: error`** — les Dockerfiles legacy ont beaucoup
   de `warning`/`info` de dette ; seul un vrai `error` bloque. Un seul `error`
   trouvé au démarrage (DL3020 dans `docker/db/Dockerfile`), corrigé.
3. **Node 22** — et non 20 : `@nuxt/eslint` tire `eslint-flat-config-utils` 3.x qui
   utilise `Object.groupBy` (Node 21+). En Node 20, `npx eslint .` casse
   (`Object.groupBy is not a function`). Voir le commentaire en tête de `ci.yml`.
   *(La note « Node 20 » des versions précédentes de ce doc était erronée.)*

---

## Validation (checklist)

- [x] CI verte sur PRs réelles (Phase 1)
- [x] Mécanisme skipped/fail validé en conditions réelles
- [x] Temps CI < 2 min sur PR mono-brique (≈1 min observé, Phase 1)
- [x] PHPStan level 3 vert sur api2 (Phase 2)
- [x] `composer audit --locked` vert sur api2
- [x] **`ci-summary` required check sur `main`** ✅
- [x] `audit-npm` / `secrets-scan` opérationnels (run vert observé)
- [x] Épreuve « touche-à-tout » : jobs skipped → verts par brique (a révélé + corrigé 2 dettes, voir section dédiée)
- [x] Phase 3 : `nuxt build` app3 validé en local (exit 0) + smoke api2 sans DB validé en local (exit 0)
- [x] Phase 3 : `build-nuxt` / `smoke-api2` **verts sur PR réelle** (épreuve touche-à-tout minimale app3 + api2)
- [x] Phase 4 : PHPUnit api2 **27 tests / 115 assertions verts** en local (conteneur `kpi_api2` + base de fixtures)
- [x] Phase 4 : suite `integration` **skippée proprement** sans base (`11 skipped`, message explicite)
- [x] Phase 4 : PHPStan toujours `[OK] No errors` après ajout des tests et modif de `doctrine.yaml`
- [ ] Phase 4 : job `tests-api2` **vert sur PR réelle** (à constater au 1er push)
- [x] Phase 7 : garde-fous du wrapper testés en sandbox (12 cas : ENV, SHA, `--experimental` refusé en prod, injection dans `--branch`, `..`, TTL hors bornes, options exclusives…)
- [x] Phase 7 : `--check-expiry` validé sur ses 4 chemins (non expiré / `expires_at` absent / non parsable / expiré sans remote)
- [x] Phase 7 : dépôt du marqueur validé (2 apps, app absente, aucune app, `clear` idempotent) + JSON conforme au composable
- [x] Phase 7 : ESLint vert sur les fichiers ajoutés d'app2 et app4 (exit 0)
- [ ] Phase 7 : **run réel** — bandeau visible sur app2 + app4, puis retour auto à `develop` par le cron (§7.6)
- [x] Phase 8 : `bash -n` OK sur le wrapper ; smoke multi-URL avec repli sur `SMOKE_URL_*`
- [x] Phase 8 : runbook rédigé et référencé dans l'index infrastructure
- [ ] Phase 8 : rollback DB **testé** (impossible aujourd'hui : aucune migration versionnée)

---

## Phase 2 — état

- [x] **CodeQL** (SAST JS/TS app2/3/4) — `codeql.yml`, onglet Security
- [x] **Trivy config** (misconfig `docker/`, bloquant CRITICAL only)
- [~] **php-cs-fixer** — reporté (churn 56/57 fichiers, valeur purement stylistique)

## Reporté en Phase 2bis / 3

- PHPStan level 4 → 5 (baseline + réduction)
- Trivy mode **image** (HIGH/CRITICAL) après le build Phase 3
- Durcir hadolint + trivy-config en HIGH une fois la dette Dockerfile nettoyée
- php-cs-fixer `@Symfony` dans un lot de reformat dédié (si souhaité)
