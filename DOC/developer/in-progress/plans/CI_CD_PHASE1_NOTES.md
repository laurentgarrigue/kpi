# Phase 1 CI/CD — Notes d'exécution

**Statut** : 🟢 CI verte sur la PR #216 — reste à merger dans `develop` puis câbler le required check
**Fichier livré** : [`.github/workflows/ci.yml`](../../../../.github/workflows/ci.yml)
**Plan de référence** : [CI_CD_STRATEGY.md](./CI_CD_STRATEGY.md) — Phase 1

Met en place le lint & les checks statiques par brique sur chaque PR vers `develop`
ou `main`, avec un job récapitulatif `ci-summary` destiné à devenir l'unique required
check du ruleset `main`.

**Outillage** : `gh` est installé et configuré (voir
[GIT_WORKFLOW.md](../../guides/GIT_WORKFLOW.md)) — la CI se
suit via `make pr_checks` (= `gh pr checks --watch`) et se merge via
`gh pr merge <n> --squash --delete-branch`.

---

## Ce que fait le workflow

| Job | Déclencheur (path filter) | Ce qu'il vérifie |
|---|---|---|
| `changes` | toujours | Calcule quels dossiers ont changé (dorny/paths-filter) |
| `lint-nuxt` | `sources/app2\|app3\|app4/**` | `npm ci` + `npx eslint .` sur chaque app modifiée (matrice) |
| `lint-api2` | `sources/api2/**` | `composer install` + `lint:yaml config` + `lint:container` |
| `lint-legacy` | `sources/**` (hors api2/app*) | `php -l` sur les fichiers PHP legacy (parallèle -P4) |
| `lint-docker` | `docker/**`, `Makefile` | hadolint + `docker compose config` (dev/preprod/prod) |
| `ci-summary` | toujours (`if: always()`) | Échoue si un job requis a échoué/annulé ; sinon vert |

Une brique non touchée ⇒ son job est **skipped**, et `ci-summary` traite skipped
comme non-bloquant. Donc une PR mono-brique ne lance que le lint concerné.

---

## Écarts assumés par rapport au plan écrit

Le plan (§1.2) supposait des outils qui **n'existent pas encore** dans le repo.
Décisions prises pour ne pas faire de checks fictifs :

1. **api2 : pas de PHPStan ni php-cs-fixer en Phase 1.**
   Aucun des deux n'est dans `sources/api2/composer.json`, et il n'y a pas de
   `phpstan.neon` ni `.php-cs-fixer.dist.php`. Les lancer échouerait. Phase 1 se
   limite donc aux linters Symfony natifs (`lint:yaml`, `lint:container`), qui ne
   demandent aucune install tierce. **PHPStan/php-cs-fixer = à ajouter en Phase 2** :
   `make api2_composer_require_dev package=phpstan/phpstan` puis créer la config, et
   enfin décommenter un job `phpstan-api2` (niveau 1 pour démarrer).

2. **app2 / app3 n'ont pas de script `lint`** (seul app4 en a un). Le workflow
   appelle donc `npx eslint .` directement, uniforme pour les trois.

3. **Legacy : uniquement `php -l`** (syntaxe), conforme au plan qui prévoit
   « rien » comme lint de style sur le legacy vu la dette. 7000+ fichiers PHP →
   le `php -l` reste un filet léger contre les `<?php` cassés.

4. **hadolint en `failure-threshold: error`** : ne bloque que sur les findings de
   niveau `error` (les Dockerfiles legacy ont beaucoup de `warning`/`info` de dette
   qu'on ne veut pas bloquer). Le premier run a révélé **un seul `error`** :
   `docker/db/Dockerfile:3 DL3020 ADD→COPY`, corrigé. À durcir en `warning` une fois
   la dette Dockerfile nettoyée (Phase 2/3).

5. **Node 20** (jamais 22 — cf. contraintes app2/app3 du projet).

---

## Historique d'exécution

- ✅ Fichiers committés sur `feature/worktree_workflow`, PR **#216** ouverte vers `develop`.
- ✅ Premier run rouge → il a fait remonter le seul finding hadolint bloquant
  (DL3020 ADD→COPY dans `docker/db/Dockerfile`). Corrigé.
- ✅ Second run **vert** : `changes` ✓, `lint-legacy` ✓, `lint-docker` ✓, `ci-summary` ✓,
  `lint-api2`/`lint-nuxt` skipped (non touchés). CI < 1 min.

## Étapes restantes

1. **Merger la PR #216 dans `develop`** :
   ```bash
   gh pr merge 216 --squash --delete-branch
   ```
   Cela fait exister la CI sur `develop` → toute future PR vers `develop` la déclenchera.

2. **Câbler `ci-summary` comme required check** (le clic laissé de côté en Phase 0) :
   GitHub → Settings → Rules → ruleset `main_ruleset` → cocher **Require status
   checks to pass** → ajouter **`ci-summary`**. NE le faire qu'après avoir vu la CI
   verte (c'est fait). (Optionnel : idem sur un futur ruleset `develop`.)

---

## Validation Phase 1 (checklist du plan §1.4)

- [ ] PR touchant uniquement `sources/app2/**` → seul le lint app2 tourne *(à vérifier
      sur une vraie PR app2 ; le mécanisme skipped est validé côté api2/nuxt)*
- [ ] PR touchant `sources/api2/**` → seul le lint api2 tourne
- [ ] PR avec une erreur ESLint volontaire → CI rouge, merge bloqué
- [x] Temps CI < 2 min sur PR mono-brique *(≈1 min observé)*
- [ ] `ci-summary` visible et coché comme required check sur `main` *(étape restante n°2)*

---

## TODO reportés en Phase 2

- Ajouter PHPStan (niveau 1 → viser 5) + php-cs-fixer à api2
- `composer audit` / `npm audit` / Trivy / Gitleaks / CodeQL (sécurité statique)
- Durcir hadolint (`failure-threshold: warning`)
