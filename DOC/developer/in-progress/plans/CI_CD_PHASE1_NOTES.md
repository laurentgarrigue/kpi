# CI/CD Phases 1 & 2 — Journal d'exécution

**Statut Phase 1** : ✅ **terminée et verrouillée** — CI sur `develop`, `ci-summary`
est le required check sur `main_ruleset`.
**Statut Phase 2** : 🟢 **en cours** — PHPStan (api2), `composer audit`, `npm audit`,
Gitleaks faits ; CodeQL + Trivy + php-cs-fixer à venir.

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
| `lint-nuxt` | `sources/app2\|app3\|app4/**` | `npm ci` + `npx eslint .` sur chaque app modifiée (matrice) |
| `lint-api2` | `sources/api2/**` | `composer install` + `lint:yaml config` + `lint:container` |
| `phpstan-api2` | `sources/api2/**` | **PHPStan level 3** (Phase 2) — analyse statique, sans DB |
| `lint-legacy` | `sources/**` (hors api2/app*) | `php -l` sur les fichiers PHP legacy (parallèle -P4) |
| `lint-docker` | `docker/**`, `Makefile` | hadolint + `docker compose config` (dev/preprod/prod) |
| `audit-composer` | `sources/api2/**` | **`composer audit`** (Phase 2) — CVE des deps PHP |
| `audit-npm` | `sources/app*/**` | **`npm audit --omit=dev --audit-level=high`** (Phase 2) |
| `secrets-scan` | toujours | **Gitleaks** (Phase 2) — secrets commités |
| `trivy-config` | `docker/**`, `Makefile` | **Trivy config** (Phase 2) — misconfig Dockerfiles, CRITICAL only |
| `ci-summary` | toujours (`if: always()`) | Échoue si un job requis a échoué/annulé ; sinon vert |

> **CodeQL** vit dans un workflow **séparé** ([`codeql.yml`](../../../../.github/workflows/codeql.yml)),
> hors `ci-summary` : PR sur `app*` + cron hebdo, résultats dans l'onglet Security.

Une brique non touchée ⇒ son job est **skipped**, et `ci-summary` traite skipped
comme non-bloquant. Donc une PR mono-brique ne lance que les jobs concernés.
`secrets-scan` tourne en revanche sur **toute** PR (un secret peut arriver partout).

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
| **CodeQL** (workflow séparé `codeql.yml`) | `github/codeql-action` | SAST JS/TS des apps Nuxt → onglet **Security**. **Non branché dans `ci-summary`** (plus lent, résultats en code-scanning). PR sur `app*` + cron hebdo. PHP non supporté par CodeQL → couvert par PHPStan/audits |

**php-cs-fixer : volontairement reporté.** Un dry-run `@Symfony` reformaterait
**56 des 57 fichiers** de `src/` — un commit de churn massif, à valeur purement
stylistique et zéro correctness. On ne l'ajoute pas maintenant pour ne pas noyer
l'historique ; à faire dans un lot dédié « reformat @Symfony » si souhaité.

**Reporté en Phase 2bis/3** : Trivy en mode **image** (HIGH/CRITICAL, après build
Phase 3), durcir hadolint/trivy en HIGH.

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
