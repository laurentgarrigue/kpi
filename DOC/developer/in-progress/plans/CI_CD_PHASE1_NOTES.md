# Phase 1 CI/CD — Notes d'exécution

**Statut** : 🟡 Brouillon prêt, à exécuter plus tard
**Fichier livré** : [`.github/workflows/ci.yml`](../../../../.github/workflows/ci.yml)
**Plan de référence** : [CI_CD_STRATEGY.md](./CI_CD_STRATEGY.md) — Phase 1

Ce brouillon met en place le lint & les checks statiques par brique sur chaque PR
vers `develop` ou `main`, avec un job récapitulatif `ci-summary` destiné à devenir
l'unique required check du ruleset `main`.

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

4. **hadolint en `failure-threshold: error`** (advisory) pour démarrer : les
   Dockerfiles legacy ont de la dette. À durcir en `warning` une fois nettoyés.

5. **Node 20** (jamais 22 — cf. contraintes app2/app3 du projet).

---

## Étapes d'exécution (à faire plus tard)

1. **Basculer sur `develop`** (jamais committer sur `main` directement) :
   ```bash
   git checkout develop && git pull
   git checkout -b feature/ci-phase1   # ou directement sur develop selon ton habitude
   ```

2. **Committer les deux fichiers** :
   ```bash
   git add .github/workflows/ci.yml DOC/developer/in-progress/plans/CI_CD_PHASE1_NOTES.md
   git commit   # message au choix
   git push
   ```

3. **Ouvrir une PR vers `develop`** et vérifier dans l'onglet Actions que la CI
   tourne. Sur une PR mono-brique, seul le lint concerné doit s'exécuter.

4. **Attendre un premier run vert** (corriger les éventuelles erreurs ESLint
   révélées — c'est le but). NE PAS câbler le required check avant d'avoir vu vert
   au moins une fois, sinon toute PR se retrouve bloquée.

5. **Câbler `ci-summary` comme required check** (le clic laissé de côté en Phase 0) :
   GitHub → Settings → Rules → ruleset `main_ruleset` → cocher **Require status
   checks to pass** → ajouter **`ci-summary`**. (Optionnel : même chose sur un
   futur ruleset `develop` si tu décides de le protéger un jour.)

---

## Validation Phase 1 (checklist du plan §1.4)

- [ ] PR touchant uniquement `sources/app2/**` → seul le lint app2 tourne
- [ ] PR touchant `sources/api2/**` → seul le lint api2 tourne
- [ ] PR avec une erreur ESLint volontaire → CI rouge, merge bloqué
- [ ] Temps CI < 2 min sur PR mono-brique
- [ ] `ci-summary` visible et coché comme required check sur `main`

---

## TODO reportés en Phase 2

- Ajouter PHPStan (niveau 1 → viser 5) + php-cs-fixer à api2
- `composer audit` / `npm audit` / Trivy / Gitleaks / CodeQL (sécurité statique)
- Durcir hadolint (`failure-threshold: warning`)
