# Plan d'action CI/CD GitHub Actions

**Date** : 2026-07-17 (mis à jour 2026-07-22)
**Statut** : 🟢 En cours — Phases 0-1 terminées, Phase 2 en cours
**Objectif** : Mettre en place un pipeline CI/CD progressif, sécurisé et adapté aux différentes briques du projet (legacy PHP, api2 Symfony/FrankenPHP, Nuxt app2/app3/app4, WordPress), avec déploiement one-click préprod/prod sur VPS et support de features expérimentales en préprod.

> **📍 Avancement** (voir le journal d'exécution :
> [CI_CD_PHASE1_NOTES.md](./CI_CD_PHASE1_NOTES.md)) :
>
> | Phase | Statut |
> |---|---|
> | **0** Fondations | ✅ Terminée |
> | **1** Lint & format | ✅ Terminée — `.github/workflows/ci.yml`, `ci-summary` required check sur `main` |
> | **2** Sécurité statique | ✅ Quasi complète — PHPStan (api2, level 3), `composer audit`, `npm audit`, Gitleaks, **CodeQL** (JS/TS), **Trivy config** faits ; php-cs-fixer reporté (churn 56/57 fichiers) |
> | **3-8** | ⬜ À faire |
>
> Ce document reste le **plan cible** ; les écarts d'exécution assumés (Node 22 au
> lieu de 20, PHPStan démarré au level 3, etc.) sont tracés dans le journal.

---

## 🎯 Vision d'ensemble

### Contraintes du projet

| Contrainte | Impact sur le design |
|---|---|
| **Multi-briques hétérogènes** | CI par chemin (path filters) — pas de pipeline monolithique |
| **Deux serveurs web coexistants** (Apache + FrankenPHP) | Jobs de déploiement distincts pour legacy vs api2 |
| **Flow existant** `feature → develop → main` (Dependabot inclus) | CI câblée sur `develop` et `main`, PR d'abord |
| **Déploiement Docker + Makefile** | Réutilisation du `make` existant côté VPS, pas de refonte |
| **Deux environnements VPS** (préprod, prod) | Deux GitHub Environments distincts avec approbations différenciées |
| **Features expérimentales en préprod** | Mécanisme `workflow_dispatch` pour push arbitraire vers préprod |
| **Sécurité** | Aucun secret long-lived en clair, aucune clé SSH root, principle of least privilege |

### Principes directeurs

1. **Progressif** : chaque phase apporte de la valeur seule et se valide indépendamment.
2. **Path-based** : chaque job ne se déclenche que si les fichiers concernés changent.
3. **Fail-fast** : lint et checks statiques d'abord, tests plus lourds ensuite.
4. **Idempotent** : chaque déploiement doit pouvoir être rejoué sans casse.
5. **Rollback-first** : chaque déploiement prépare son rollback avant de committer le changement.
6. **Least privilege** : les credentials de préprod ne doivent JAMAIS pouvoir toucher la prod.

### État de départ (au 2026-07-17)

- ✅ Dependabot configuré (`.github/dependabot.yml`) — cible `develop`
- ✅ Makefile complet couvrant tous les environnements
- ✅ Branches : `develop` (intégration) + `main` (prod)
- ❌ Aucun workflow GitHub Actions (`.github/workflows/` absent)
- ❌ Aucun test automatisé exécuté (des dossiers `tests/playwright` existent dans `app4` mais pas industrialisés)
- ❌ Déploiement 100 % manuel via SSH + `make` sur le VPS

> **Depuis** : `.github/workflows/ci.yml` existe (Phases 1-2), `ci-summary` est le
> required check sur `main`. Le déploiement reste manuel (Phases 5-6 à faire).

---

## 🗺️ Vue phasée (résumé)

| Phase | Livrable | Effort estimé | Valeur ajoutée |
|---|---|---|---|
| **0** | Fondations : environments, secrets, branch protection | 0.5 j | Base sécurisée |
| **1** | Lint & format sur PR (par brique) | 0.5 j | Feedback rapide |
| **2** | Sécurité statique : deps, secrets, SAST | 1 j | Détection early |
| **3** | Build & smoke tests (Docker/Nuxt/Symfony) | 1 j | Non-régression build |
| **4** | Tests fonctionnels (par brique, adoption incrémentale) | 2-5 j | Filet de sécurité |
| **5** | Déploiement continu **préprod** sur merge `develop` | 1 j | Zéro clic préprod |
| **6** | Déploiement **prod** manuel avec approbation | 1 j | Prod maîtrisée |
| **7** | Déploiement de **features expérimentales** en préprod | 0.5 j | Tests réels sans polluer `develop` |
| **8** | Post-déploiement : smoke tests, rollback, alerting | 1 j | Robustesse opérationnelle |

Total : ~8-12 j-hommes selon niveau de test visé, étalable sur plusieurs semaines/mois.

---

## Phase 0 — Fondations (indispensable avant tout)

**Objectif** : préparer le terrain sécurisé sans encore automatiser quoi que ce soit.

### 0.1 GitHub Environments

Créer dans **Settings → Environments** deux environments distincts :

- `preprod`
  - Aucun reviewer requis (déploiement auto)
  - Branches autorisées : `develop` + pattern `feature/*`, `hotfix/*` (pour la Phase 7)
- `production`
  - **Required reviewers** : au moins toi-même (approbation manuelle obligatoire)
  - **Wait timer** : optionnel, 5 min pour laisser la fenêtre d'annuler
  - Branches autorisées : `main` uniquement

Chaque environment porte ses propres secrets → aucune fuite préprod→prod possible.

### 0.2 Secrets minimaux à provisionner

| Nom | Environment | Contenu | Rotation |
|---|---|---|---|
| `SSH_HOST` | preprod / production | IP ou hostname du VPS | rare |
| `SSH_USER` | preprod / production | user dédié `deploy` (jamais root) | rare |
| `SSH_KEY` | preprod / production | clé Ed25519 privée dédiée à ce workflow | 6 mois |
| `SSH_PORT` | preprod / production | port SSH | rare |
| `DEPLOY_PATH` | preprod / production | chemin absolu du checkout sur VPS | rare |

Point clé : **la clé SSH prod n'est chargée que dans l'environment `production`**. GitHub Actions garantit qu'un job ciblant `preprod` ne peut PAS lire les secrets de `production`.

### 0.3 User `deploy` sur le VPS

Sur le VPS, créer un user `deploy` :
- Membre du groupe `docker` (pour `docker compose`)
- `sudo` **uniquement** pour `systemctl reload nginx` si nécessaire (sinon aucun sudo)
- Répertoire `~/.ssh/authorized_keys` contient **uniquement** la clé publique du secret `SSH_KEY` avec `command="/home/deploy/deploy-wrapper.sh"` (voir Phase 6)
- Chroot au chemin `DEPLOY_PATH`

Aucune connexion interactive, aucun password.

### 0.4 Branch protection rules

Dans **Settings → Branches** :

- `main` :
  - Require PR before merging
  - Require status checks to pass : `ci-summary` (ajouté en Phase 1)
  - Require linear history
  - Restrict pushes (interdire push direct)
  - Require signed commits (optionnel mais recommandé)
- `develop` :
  - Require PR before merging (ou permettre push direct si tu es solo — à toi de voir)
  - Require status checks : `ci-summary`

### 0.5 Validation Phase 0

- [ ] Deux environments visibles dans GitHub UI
- [ ] Secrets présents mais NON encore utilisés
- [ ] SSH `deploy@vps` fonctionne depuis ton poste (test manuel)
- [ ] Branch protection empêche un push direct sur `main`

---

## Phase 1 — Lint & format sur PR

**Objectif** : feedback qualité < 2 minutes sur chaque PR, par brique changée.

### 1.1 Détection des changements (path filters)

Créer un job unique `changes` qui expose des outputs booléens :

```yaml
# .github/workflows/ci.yml (extrait conceptuel)
jobs:
  changes:
    runs-on: ubuntu-latest
    outputs:
      legacy: ${{ steps.filter.outputs.legacy }}
      api2:   ${{ steps.filter.outputs.api2 }}
      app2:   ${{ steps.filter.outputs.app2 }}
      app3:   ${{ steps.filter.outputs.app3 }}
      app4:   ${{ steps.filter.outputs.app4 }}
      docker: ${{ steps.filter.outputs.docker }}
    steps:
      - uses: actions/checkout@v4
      - uses: dorny/paths-filter@v3
        id: filter
        with:
          filters: |
            legacy:
              - 'sources/**'
              - '!sources/api2/**'
              - '!sources/app2/**'
              - '!sources/app3/**'
              - '!sources/app4/**'
            api2:   ['sources/api2/**']
            app2:   ['sources/app2/**']
            app3:   ['sources/app3/**']
            app4:   ['sources/app4/**']
            docker: ['docker/**', 'Makefile']
```

Toute nouvelle brique (app5, api3…) = un filtre à ajouter, rien d'autre à toucher côté CI.

### 1.2 Jobs de lint par brique

| Brique | Lint | Format | Runner |
|---|---|---|---|
| `app2`, `app3`, `app4` | `npx eslint .` | déjà géré par ESLint | ubuntu, **Node 22** (voir note) |
| `api2` | `lint:yaml config` + `lint:container` (php-cs-fixer/PHPStan → Phase 2) | idem | ubuntu, PHP 8.4 |
| `legacy` | Phase 1 = `php -l` (syntaxe) seulement — trop de dette pour un lint de style | | ubuntu, PHP 8.4 |
| `docker` | `hadolint` sur les Dockerfiles, `docker compose config` en dry-run | | ubuntu |

> **Node 22 (pas 20)** : `@nuxt/eslint` tire `eslint-flat-config-utils` 3.x qui
> utilise `Object.groupBy` (Node 21+) ; en Node 20, `npx eslint .` casse
> (`Object.groupBy is not a function`). Voir l'en-tête de `ci.yml`.
>
> **api2 en Phase 1** : seulement les linters natifs Symfony (`lint:yaml`,
> `lint:container`), qui n'installent aucun outil tiers. PHPStan et php-cs-fixer
> sont arrivés en Phase 2.

### 1.3 Job récapitulatif

Un job final `ci-summary` qui `needs:` tous les autres et sert d'unique required check dans les branch protection rules. Avantage : ajouter/retirer des jobs en Phase 2/3 n'oblige jamais à retoucher la protection de branche.

### 1.4 Validation Phase 1

- [ ] Créer une PR touchant uniquement `sources/app2/**` → seul le lint app2 tourne
- [ ] Créer une PR touchant `sources/api2/**` → seul le lint api2 tourne
- [ ] Créer une PR avec une erreur ESLint volontaire → CI rouge, merge bloqué
- [ ] Temps CI < 2 min sur PR mono-brique

---

## Phase 2 — Sécurité statique

**Objectif** : détecter les vulnérabilités et secrets **avant** merge, sans slowdown perceptible.

### 2.1 Dépendances

| Écosystème | Outil | Cible |
|---|---|---|
| npm (app2/3/4) | `npm audit --audit-level=high` + **CodeQL JavaScript** | vulns critiques → CI rouge |
| Composer (api2, legacy) | `composer audit` + `local-php-security-checker` | vulns critiques → CI rouge |
| Docker images | `trivy image` sur les images locales buildées en Phase 3 | HIGH/CRITICAL → CI rouge |

`npm audit` reste bruyant → recommandation : rouge seulement sur `high`+ ; `moderate` = warning.

### 2.2 Secrets

- **Gitleaks** ou **TruffleHog** en pre-push (via hook) + en CI sur chaque PR
- Cible : détecter tokens AWS, clés API, mots de passe en clair, `MERCURE_JWT_SECRET` accidentellement committés
- Zéro faux positif toléré → maintenir un `.gitleaks.toml` d'allowlist

### 2.3 SAST

- **CodeQL** activé sur JS/TS (natif GitHub, gratuit) — bien pour app2/3/4
- **PHPStan** niveau progressif pour api2 : commencer niveau 1, viser 5 à moyen terme
- **Psalm** ou **Rector dry-run** en mode advisory (jamais bloquant) pour repérer la dette legacy

### 2.4 Politique de gestion des findings

- Findings HIGH/CRITICAL bloquent le merge sur `main`.
- Findings MEDIUM affichés en commentaire de PR (via `dependency-review-action`) mais non bloquants.
- Un fichier `SECURITY.md` documente la politique et la procédure de report externe.

### 2.5 Validation Phase 2

- [ ] Créer un fichier avec un faux secret AWS → CI bloque
- [ ] Ajouter une dépendance npm connue vulnérable (ex: vieux `lodash@4.17.0`) → CI bloque
- [ ] Rapport CodeQL disponible dans l'onglet Security de GitHub

---

## Phase 3 — Build & smoke tests

**Objectif** : garantir que `develop` reste toujours buildable, sans encore exiger de tests fonctionnels.

### 3.1 Nuxt : build effectif

Pour chaque `appX` modifiée :

```yaml
- run: npm ci --prefix sources/appX
- run: npm --prefix sources/appX run build  # ou generate
```

Un build cassé = CI rouge. Cache `~/.npm` par `hashFiles('sources/appX/package-lock.json')` → build ~30 s.

### 3.2 Symfony api2 : boot smoke test

```yaml
- run: composer install --working-dir=sources/api2 --no-scripts --no-progress
- run: php sources/api2/bin/console cache:clear --env=test
- run: php sources/api2/bin/console lint:container
- run: php sources/api2/bin/console lint:yaml config/
- run: php sources/api2/bin/console doctrine:schema:validate --skip-sync
```

Objectif : détecter tout ce qui empêcherait le container FrankenPHP de démarrer.

### 3.3 Legacy PHP : syntaxe seulement

```yaml
- run: find sources -name '*.php' -not -path 'sources/api2/*' -not -path 'sources/vendor/*' -print0 | xargs -0 -n1 -P4 php -l
```

C'est peu, mais c'est un premier filet.

### 3.4 Docker : build des images

`docker compose -f docker/compose.dev.yaml build` en dry-run côté CI. Utile surtout après changement de Dockerfile.

### 3.5 Validation Phase 3

- [ ] Un typo dans un `.vue` bloque la CI
- [ ] Une erreur de config Symfony (`services.yaml`) bloque la CI
- [ ] Un `<?php` mal terminé dans legacy bloque la CI
- [ ] Un Dockerfile cassé bloque la CI

---

## Phase 4 — Tests fonctionnels (adoption incrémentale)

**Objectif** : filet de sécurité fonctionnel, adopté progressivement brique par brique. **Pas de tests fictifs** : on n'ajoute que des tests qui protègent réellement quelque chose.

### 4.1 Grille d'adoption

Le principe : **on ne rend le job de tests bloquant qu'après qu'une brique ait atteint un seuil minimal de couverture pertinente**. Une brique sans tests = job en `continue-on-error` puis retiré.

| Brique | Framework recommandé | Ce qu'on teste en premier | Bloquant à partir de… |
|---|---|---|---|
| **api2** | PHPUnit + Panther/API Platform | endpoints publics (events, games, ratings) + schéma OpenAPI | 5 endpoints couverts |
| **app4** (admin) | Playwright (déjà présent en germe) | flow login + navigation critique | 1 flow smoke |
| **app2** | Vitest (unit) + Playwright (e2e) | composables de charts + parsing API | 3 composables |
| **app3** (match sheet) | Vitest + Playwright | timer + shot clock + BroadcastChannel | 1 flow match complet |
| **Legacy** | Aucune adoption prévue. Tests d'intégration via Playwright sur les pages Smarty critiques (`kpclassement.php`, `feuillemarque.php`) uniquement si régression réelle. | | jamais bloquant sauf `php -l` |

### 4.2 Base de données de test

- Un service MariaDB éphémère dans le job GitHub Actions (`services:`)
- Dump SQL minimal versionné dans `SQL/fixtures/` (pas la vraie base)
- API2 : configuration `.env.test` séparée, isolation totale

### 4.3 Playwright

- Chromium préinstallé sur les runners GitHub → réutiliser `PLAYWRIGHT_BROWSERS_PATH` uniquement en local
- En CI : `npx playwright install --with-deps chromium` (~30 s de cache)
- Screenshots on-failure uploadés en artifact GitHub → debug rapide

### 4.4 Politique de flakiness

- Un test flaky doit être **quarantained** (skip + issue GitHub auto-créée) sous 48 h ou supprimé
- Zéro tolérance sur les tests "re-run pour passer"

### 4.5 Validation Phase 4

- [ ] Suite api2 : couverture ≥ 30 % sur `src/Controller/`
- [ ] Suite app4 : 1 test Playwright login + dashboard passe en < 30 s
- [ ] Chaque brique testée a un badge de couverture dans son README

---

## Phase 5 — Déploiement continu préprod

**Objectif** : merge sur `develop` = préprod à jour, zéro clic.

### 5.1 Workflow `deploy-preprod.yml`

Déclencheur : `push` sur `develop` **après** succès du workflow CI.

```yaml
on:
  workflow_run:
    workflows: ["CI"]
    branches: [develop]
    types: [completed]

jobs:
  deploy:
    if: ${{ github.event.workflow_run.conclusion == 'success' }}
    runs-on: ubuntu-latest
    environment: preprod
    steps:
      - uses: actions/checkout@v4
        with:
          ref: ${{ github.event.workflow_run.head_sha }}
      - name: Deploy via SSH
        uses: appleboy/ssh-action@v1
        with:
          host:     ${{ secrets.SSH_HOST }}
          username: ${{ secrets.SSH_USER }}
          key:      ${{ secrets.SSH_KEY }}
          port:     ${{ secrets.SSH_PORT }}
          script: |
            /home/deploy/deploy-wrapper.sh preprod ${{ github.event.workflow_run.head_sha }}
```

### 5.2 Le script wrapper (côté VPS)

**Toute la logique de déploiement vit sur le VPS**, pas dans le workflow. Cela évite d'exposer la topologie du VPS dans le repo public, et rend le déploiement testable manuellement.

`/home/deploy/deploy-wrapper.sh` (exemple, à durcir) :

```bash
#!/bin/bash
set -euo pipefail

ENV="$1"        # preprod | production
SHA="$2"        # commit sha à déployer
[[ "$ENV" =~ ^(preprod|production)$ ]] || exit 1
[[ "$SHA" =~ ^[a-f0-9]{40}$ ]]         || exit 1

BASE=/srv/kpi_$ENV
cd "$BASE"

# 1. Snapshot pour rollback (état actuel avant tout changement)
CURRENT_SHA=$(git rev-parse HEAD)
echo "$CURRENT_SHA" > .last-deploy-sha

# 2. Fetch + checkout
git fetch --tags origin
git checkout "$SHA"

# 3. Rebuild uniquement ce qui a changé
CHANGED=$(git diff --name-only "$CURRENT_SHA" "$SHA")
echo "$CHANGED" | grep -q '^sources/api2/'           && REBUILD_API2=1
echo "$CHANGED" | grep -q '^sources/app'             && REBUILD_APPS=1
echo "$CHANGED" | grep -q '^docker/'                 && REBUILD_DOCKER=1

if [[ -n "${REBUILD_APPS:-}" ]]; then
    make app2_generate_preprod
    make app4_generate_preprod  # etc.
fi

if [[ -n "${REBUILD_API2:-}" ]]; then
    make api2_composer_install
    make api2_migrations_migrate
    make api2_cache_clear
    make api2_restart
fi

if [[ -n "${REBUILD_DOCKER:-}" ]]; then
    make docker_preprod_rebuild
else
    make docker_preprod_restart
fi

# 4. Smoke test
curl -fsS https://preprod.kayak-polo.info/api2/doc > /dev/null || {
    echo "Smoke test failed, rolling back"
    git checkout "$CURRENT_SHA"
    make docker_preprod_restart
    exit 1
}

echo "Deploy OK: $CURRENT_SHA → $SHA"
```

Points clés :
- **Regex sur SHA** : empêche l'injection d'argument
- **Whitelist ENV** : empêche un attaquant de passer `production` via préprod
- **Rollback automatique** si smoke test échoue
- **Rebuild sélectif** : gain de temps massif sur les petits merges

### 5.3 SSH restreint côté VPS

Dans `~/.ssh/authorized_keys` du user `deploy` :

```
command="/home/deploy/deploy-wrapper.sh",no-agent-forwarding,no-port-forwarding,no-X11-forwarding,no-pty ssh-ed25519 AAAA...
```

Avec `command=`, la clé ne peut RIEN faire d'autre que d'exécuter le wrapper. Les arguments passés via SSH arrivent dans `$SSH_ORIGINAL_COMMAND` — le wrapper doit les parser strictement.

### 5.4 Validation Phase 5

- [ ] Merge PR sur `develop` → préprod déployée en < 5 min sans intervention
- [ ] Push d'un commit cassé → smoke test échoue → rollback auto → alerte
- [ ] Le workflow ne peut PAS lire les secrets `production` (test explicite)

---

## Phase 6 — Déploiement production

**Objectif** : déploiement prod en 1 clic + 1 approbation, sans SSH manuel.

### 6.1 Workflow `deploy-prod.yml`

Déclencheur : `workflow_dispatch` (bouton "Run workflow" dans GitHub UI) + `push` de tag `v*`.

```yaml
on:
  workflow_dispatch:
    inputs:
      ref:
        description: 'Tag ou SHA à déployer (doit être sur main)'
        required: true
        default: 'main'

jobs:
  deploy:
    runs-on: ubuntu-latest
    environment: production   # ← déclenche l'approbation manuelle
    steps:
      - uses: actions/checkout@v4
        with: { ref: ${{ inputs.ref }} }
      - name: Verify ref is on main
        run: git merge-base --is-ancestor ${{ inputs.ref }} origin/main
      - name: Deploy
        uses: appleboy/ssh-action@v1
        with: { host: ..., script: "/home/deploy/deploy-wrapper.sh production ${{ inputs.ref }}" }
```

### 6.2 Convention de release

- Merge `develop` → `main` uniquement via PR taggée `release/vX.Y.Z`
- Un tag `vX.Y.Z` est poussé après merge
- Le déploiement prod se déclenche sur ce tag (ou manuellement via UI en cas d'urgence)

### 6.3 Contrôles avant déploiement prod

- **Vérifier que le ref est sur `main`** : évite qu'un attaquant force un déploiement d'une branche arbitraire
- **Approbation manuelle** via environment `production`
- **Backup base de données automatique** avant migration (déclenché par le wrapper)
- **Migration Doctrine dry-run** systématique avant le vrai run

### 6.4 Rollback prod

Deux niveaux :
- **Rollback code** : `deploy-wrapper.sh production <sha_precedent>` → 1 min
- **Rollback DB** : documentation d'un runbook + backup horaire (à faire côté ops)

### 6.5 Validation Phase 6

- [ ] Bouton "Deploy to production" dans l'onglet Actions
- [ ] Approbation demandée avant exécution
- [ ] Tag `v1.0.0-test` déployable et rollbackable
- [ ] Secrets prod jamais lisibles en préprod

---

## Phase 7 — Features expérimentales en préprod

**Objectif** : déployer une branche `feature/*` en préprod sans passer par `develop`, pour la tester en conditions réelles avant merge.

### 7.1 Contrainte forte

Préprod héberge en permanence **le dernier `develop`**. Y pousser une feature branche = **temporairement écraser** cet état. Il faut donc :
1. Prévenir l'utilisateur clairement dans le UI
2. Après un délai (ou à la fin du sprint), rebasculer automatiquement sur `develop`

### 7.2 Workflow `deploy-preprod-experimental.yml`

```yaml
on:
  workflow_dispatch:
    inputs:
      branch:
        description: 'Nom de la branche à déployer en préprod'
        required: true
      ttl_hours:
        description: 'Durée max avant retour à develop (heures)'
        default: '24'

jobs:
  deploy:
    runs-on: ubuntu-latest
    environment: preprod
    steps:
      - uses: actions/checkout@v4
        with: { ref: ${{ inputs.branch }} }
      - name: Deploy
        uses: appleboy/ssh-action@v1
        with:
          script: |
            /home/deploy/deploy-wrapper.sh preprod ${{ github.sha }} --experimental \
              --ttl ${{ inputs.ttl_hours }} \
              --branch ${{ inputs.branch }}
```

### 7.3 Marqueur visuel en préprod

Le wrapper, en mode `--experimental`, dépose un fichier `sources/EXPERIMENTAL_FLAG.json` :

```json
{
  "branch": "feature/nouveau-scoring",
  "sha": "abcd1234",
  "deployed_at": "2026-07-17T14:00:00Z",
  "expires_at": "2026-07-18T14:00:00Z"
}
```

Chaque app Nuxt affiche un bandeau rouge fluo "🧪 PRÉPROD — Feature expérimentale : nouveau-scoring — expire dans 6 h" tant que le fichier existe. Zéro confusion possible avec un état "stable" de préprod.

### 7.4 Retour automatique à `develop`

Un cron sur le VPS (systemd timer ou `crontab -u deploy`) vérifie toutes les heures si `expires_at` est dépassé → redéploie `develop`. Le cron est déjà côté VPS, pas de GitHub Action nécessaire.

### 7.5 Sécurité

- L'input `branch` est sanitizé côté wrapper : `[[ "$BRANCH" =~ ^[a-zA-Z0-9._/-]+$ ]]`
- Seuls les membres de l'équipe (via GitHub environment `preprod`) peuvent déclencher le workflow
- Impossible de cibler `production` par ce workflow (l'environment est en dur à `preprod`)

### 7.6 Validation Phase 7

- [ ] Déployer `feature/test` sur préprod via bouton
- [ ] Bandeau expérimental visible sur toutes les apps
- [ ] Après TTL, retour auto sur `develop`

---

## Phase 8 — Post-déploiement & observabilité

**Objectif** : savoir en < 1 min si un déploiement a cassé quelque chose.

### 8.1 Smoke tests post-deploy

Étendre le smoke test du wrapper au-delà du simple `curl /api2/doc` :

```bash
smoke_tests=(
    "https://<host>/api2/doc"                    # api2 vivant
    "https://<host>/api2/events"                 # endpoint public répond
    "https://<host>/app.<host>/"                 # app2 servie
    "https://<host>/index.php?page=classement"   # legacy vivant
)
for url in "${smoke_tests[@]}"; do
    curl -fsS --max-time 10 "$url" > /dev/null || rollback
done
```

### 8.2 Notification

Sur succès ou échec de déploiement : notification vers Discord/Slack/mail (via webhook, secret `NOTIFY_WEBHOOK`). Inclure :
- SHA déployé + auteur du commit
- Environnement
- Durée du déploiement
- Résultat smoke tests
- Lien vers logs GitHub Actions

### 8.3 Alerting long terme

Hors scope CI/CD strict, mais à prévoir en parallèle :
- Uptime monitoring externe (UptimeRobot ou équivalent) sur `/api2/doc` et `/index.php`
- Logs FrankenPHP + Apache centralisés
- Métriques `mercure_data/`, latence api2

### 8.4 Runbook

Un `DOC/developer/infrastructure/DEPLOYMENT_RUNBOOK.md` documente :
- Comment déclencher un déploiement (préprod / prod / expérimental)
- Comment rollback manuellement si le rollback auto a échoué
- Qui contacter en cas d'échec
- Comment consulter les logs des jobs GitHub Actions

### 8.5 Validation Phase 8

- [ ] Un déploiement KO déclenche une notification claire en < 1 min
- [ ] Runbook rédigé, testé par un tiers (ou toi le lundi suivant)
- [ ] Rollback documenté et testé au moins une fois

---

## 🔒 Récapitulatif sécurité

| Risque | Mitigation |
|---|---|
| Fuite credentials prod via workflow préprod | GitHub Environments séparés, secrets scopés |
| Injection via input `branch` en Phase 7 | Regex stricte côté wrapper VPS |
| Compromission repo → deploy prod arbitraire | Approbation manuelle + `git merge-base --is-ancestor origin/main` |
| SSH key volée | Clé Ed25519 dédiée, `command=` restreint, rotation 6 mois |
| Secret committé | Gitleaks pre-push + CI |
| Vuln transitive | `npm audit` + `composer audit` + Trivy + Dependabot |
| `docker compose` en root | User `deploy` non-root, membre `docker` uniquement |
| Rollback DB impossible | Backup auto avant chaque migration, runbook explicite |

---

## 🧩 Extensibilité

Ajouter une future brique (ex : `app5`) demande **3 modifications seulement** :

1. **`.github/workflows/ci.yml`** : ajouter un filtre `app5` et un job de lint/build
2. **`.github/dependabot.yml`** : ajouter `/sources/app5` dans les directories npm
3. **`deploy-wrapper.sh` (VPS)** : ajouter la commande `make app5_generate_*` dans la section rebuild

Aucun autre changement, la CI reste modulaire.

---

## 📚 Références utiles

- [GitHub Actions security hardening](https://docs.github.com/en/actions/security-guides/security-hardening-for-github-actions)
- [dorny/paths-filter](https://github.com/dorny/paths-filter) — path-based CI
- [appleboy/ssh-action](https://github.com/appleboy/ssh-action) — déploiement SSH
- [Trivy](https://aquasecurity.github.io/trivy/) — scan images Docker
- [Gitleaks](https://github.com/gitleaks/gitleaks) — détection secrets

---

## ✅ Prochaine étape recommandée

Phases 0-1 terminées, Phase 2 en cours (voir le bandeau d'avancement en tête et le
journal [CI_CD_PHASE1_NOTES.md](./CI_CD_PHASE1_NOTES.md)).

**Reste en Phase 2** : CodeQL (SAST JS/TS), Trivy (images Docker), php-cs-fixer
(style api2), puis durcir hadolint.

**Ensuite — Phase 3 (Build & smoke)** : garantir que `develop` reste buildable
(build Nuxt effectif, boot smoke api2, build Docker). Après quoi **Phase 5**
(déploiement continu préprod) est le premier grand palier de valeur opérationnelle.
