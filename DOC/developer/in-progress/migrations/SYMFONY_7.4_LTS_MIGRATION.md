# Migration Symfony 7.3 → 7.4 LTS (API2)

> **Date de création** : 2025-12-17
> **Statut** : 📋 Plan créé - Migration à effectuer
> **Durée estimée** : 1-2 heures

---

## 🎯 Objectif

Migrer l'API2 de Symfony 7.3 vers Symfony 7.4 LTS (Long Term Support) pour bénéficier :
- ✅ Support étendu jusqu'en novembre 2028
- ✅ Corrections de bugs et sécurité garanties
- ✅ Nouvelles fonctionnalités de Symfony 7.4
- ✅ Base stable pour les 3 prochaines années

---

## 📊 Versions Actuelles vs Cibles

### Symfony Components
| Package | Version Actuelle | Version Cible | Type |
|---------|-----------------|---------------|------|
| `symfony/framework-bundle` | 7.3.* | 7.4.* | Core |
| `symfony/console` | 7.3.* | 7.4.* | Core |
| `symfony/runtime` | 7.3.* | 7.4.* | Core |
| `symfony/security-bundle` | 7.3.* | 7.4.* | Core |
| `symfony/twig-bundle` | 7.3.* | 7.4.* | Core |
| `symfony/yaml` | 7.3.* | 7.4.* | Core |
| `symfony/asset` | 7.3.* | 7.4.* | Core |
| `symfony/dotenv` | 7.3.* | 7.4.* | Core |

### Autres Packages (déjà compatibles)
| Package | Version Actuelle | Compatible 7.4 |
|---------|-----------------|----------------|
| `api-platform/core` | ^4.2 | ✅ Oui |
| `nelmio/api-doc-bundle` | ^5.8 | ✅ Oui |
| `nelmio/cors-bundle` | ^2.6 | ✅ Oui |
| `doctrine/orm` | ^3.5 | ✅ Oui |
| `doctrine/dbal` | ^3 | ✅ Oui |
| `doctrine/doctrine-bundle` | ^2.18 | ✅ Oui |
| `symfony/maker-bundle` | ^1.64 | ✅ Oui |

---

## 🔄 Plan de Migration

### Étape 1 : Backup et Préparation (5 min)

#### 1.1 Créer une branche de migration
```bash
cd /home/laurent/Documents/dev/kpi
git checkout -b feature/symfony-7.4-lts
git status
```

#### 1.2 Backup du composer.json actuel
```bash
cp sources/api2/composer.json sources/api2/composer.json.backup-7.3
```

### Étape 2 : Mise à Jour composer.json (10 min)

#### 2.1 Modifier les versions Symfony
**Fichier** : [sources/api2/composer.json](../../../sources/api2/composer.json)

**Changements à effectuer** :

```diff
{
    "require": {
        "php": ">=8.2",
        ...
-       "symfony/asset": "7.3.*",
+       "symfony/asset": "7.4.*",
-       "symfony/console": "7.3.*",
+       "symfony/console": "7.4.*",
-       "symfony/dotenv": "7.3.*",
+       "symfony/dotenv": "7.4.*",
-       "symfony/framework-bundle": "7.3.*",
+       "symfony/framework-bundle": "7.4.*",
-       "symfony/runtime": "7.3.*",
+       "symfony/runtime": "7.4.*",
-       "symfony/security-bundle": "7.3.*",
+       "symfony/security-bundle": "7.4.*",
-       "symfony/twig-bundle": "7.3.*",
+       "symfony/twig-bundle": "7.4.*",
-       "symfony/yaml": "7.3.*"
+       "symfony/yaml": "7.4.*"
    },
    "extra": {
        "symfony": {
            "allow-contrib": false,
-           "require": "7.3.*"
+           "require": "7.4.*"
        }
    }
}
```

### Étape 3 : Exécuter la Mise à Jour Composer (15-30 min)

#### 3.1 Mise à jour des dépendances
```bash
# Depuis le host (via Makefile)
make composer_update_api2

# OU depuis le conteneur PHP
make php_bash
cd /var/www/html/api2
composer update symfony/*
```

**Sortie attendue** :
```
Loading composer repositories with package information
Updating dependencies
Lock file operations: 0 installs, 8 updates, 0 removals
  - Upgrading symfony/asset (v7.3.x => v7.4.x)
  - Upgrading symfony/console (v7.3.x => v7.4.x)
  - Upgrading symfony/dotenv (v7.3.x => v7.4.x)
  - Upgrading symfony/framework-bundle (v7.3.x => v7.4.x)
  - Upgrading symfony/runtime (v7.3.x => v7.4.x)
  - Upgrading symfony/security-bundle (v7.3.x => v7.4.x)
  - Upgrading symfony/twig-bundle (v7.3.x => v7.4.x)
  - Upgrading symfony/yaml (v7.3.x => v7.4.x)
```

#### 3.2 Vérifier les dépendances
```bash
composer show symfony/* | grep "^symfony"
```

**Sortie attendue** : Toutes les versions doivent afficher `v7.4.x`

### Étape 4 : Vérification des Changements Breaking (10 min)

#### 4.1 Consulter le CHANGELOG Symfony 7.4
**URL** : https://github.com/symfony/symfony/blob/7.4/CHANGELOG-7.4.md

**Principaux changements potentiels** :
- Nouvelles features (non breaking)
- Dépréciations ajoutées (non breaking)
- Améliorations de performance

**Aucun breaking change attendu** entre 7.3 et 7.4 (migrations mineures uniquement)

#### 4.2 Vérifier les dépréciations
```bash
php bin/console debug:container --deprecations
```

**Attendu** : Aucune dépréciation pour les fonctionnalités utilisées

### Étape 5 : Tests et Validation (20 min)

#### 5.1 Clear et warmup du cache
```bash
make api2_cache_clear
make api2_cache_warmup

# OU depuis le conteneur
php bin/console cache:clear
php bin/console cache:warmup
```

#### 5.2 Tester les endpoints manuellement
```bash
# Events
curl -X GET "https://kpi.localhost/api2/api/events/std"

# Games
curl -X GET "https://kpi.localhost/api2/api/games/123"

# Charts
curl -X GET "https://kpi.localhost/api2/api/charts/123"

# Team Stats
curl -X GET "https://kpi.localhost/api2/api/team-stats/456/123"

# Stars
curl -X GET "https://kpi.localhost/api2/api/stars"

# Rating
UUID=$(uuidgen)
curl -X POST "https://kpi.localhost/api2/api/rating" \
  -H "Content-Type: application/json" \
  -d "{\"uid\":\"${UUID}\",\"stars\":4}"
```

**Critères de succès** :
- ✅ HTTP 200 pour tous les endpoints
- ✅ Même structure JSON qu'avant
- ✅ Aucune erreur dans les logs

#### 5.3 Vérifier les logs Symfony
```bash
tail -f var/log/dev.log
# OU
make dev_logs
```

**Rechercher** : Erreurs, warnings, dépréciations

#### 5.4 Tester les endpoints WSM (critiques)
```bash
MATCH_ID=999

# Update game param
curl -X PUT "https://kpi.localhost/api2/api/wsm/gameParam/${MATCH_ID}" \
  -H "Content-Type: application/json" \
  -d '{"param":"ScoreA","value":"5"}'

# Game timer
curl -X PUT "https://kpi.localhost/api2/api/wsm/gameTimer/${MATCH_ID}" \
  -H "Content-Type: application/json" \
  -d '{"params":{"action":"run","startTime":0,"runTime":600,"maxTime":1200}}'
```

### Étape 6 : Tests Automatisés (optionnel - si PHPUnit installé)

```bash
cd /var/www/html/api2
./vendor/bin/phpunit
```

### Étape 7 : Commit et Documentation (10 min)

#### 7.1 Vérifier les fichiers modifiés
```bash
git status
git diff sources/api2/composer.json
git diff sources/api2/composer.lock
```

#### 7.2 Commit des changements
```bash
git add sources/api2/composer.json sources/api2/composer.lock
git commit -m "chore(api2): Migrate Symfony 7.3 → 7.4 LTS

- Update all Symfony packages from 7.3.* to 7.4.*
- Update symfony/framework-bundle
- Update symfony/console, runtime, security, twig, yaml
- Update symfony/asset, dotenv
- No breaking changes - backward compatible
- Tests: All endpoints validated manually
- Support extended until November 2028

See: DOC/developer/in-progress/migrations/SYMFONY_7.4_LTS_MIGRATION.md"
```

#### 7.3 Push et merge
```bash
git push origin feature/symfony-7.4-lts

# Créer une PR ou merger directement selon workflow
git checkout develop  # ou main
git merge feature/symfony-7.4-lts
git push origin develop
```

---

## 🆕 Nouvelles Fonctionnalités Symfony 7.4

### 1. AssetMapper Improvements (si utilisé)
- Meilleure gestion des assets modernes
- Support TypeScript natif

**Impact** : Aucun (API2 n'utilise pas AssetMapper actuellement)

### 2. Serializer Enhancements
- Nouvelles options de normalisation
- Meilleure performance

**Impact** : Potentiel (API Platform utilise le Serializer)

### 3. DependencyInjection Autoconfiguration
- Améliorations autowiring
- Meilleure détection services

**Impact** : Positif (améliore DX)

### 4. Cache Improvements
- Nouvelles stratégies de cache
- Meilleur invalidation

**Impact** : Potentiel (à explorer pour cache WSM)

### 5. Security Enhancements
- Nouveaux authenticators
- Améliorations CSRF

**Impact** : Positif (à utiliser pour token auth à implémenter)

---

## 🔍 Points d'Attention

### Compatibilité PHP
**Requis** : PHP 8.2+
**Actuel** : PHP 8.4 ✅

**Action** : Aucune - déjà compatible

### Compatibilité API Platform 4.2
**Status** : ✅ Compatible avec Symfony 7.4
**Documentation** : https://api-platform.com/docs/core/symfony/

**Action** : Aucune - déjà compatible

### Compatibilité Doctrine
**Packages** :
- `doctrine/orm` : ^3.5 ✅
- `doctrine/dbal` : ^3 ✅
- `doctrine/doctrine-bundle` : ^2.18 ✅

**Action** : Aucune - déjà compatible

---

## 🚨 Rollback Plan

### En cas de problème

#### 1. Restaurer composer.json
```bash
cp sources/api2/composer.json.backup-7.3 sources/api2/composer.json
make composer_install_api2
make api2_cache_clear
```

#### 2. Git revert
```bash
git revert HEAD
git push origin develop --force
```

#### 3. Redémarrer les services
```bash
make dev_restart
# OU
make preprod_restart
```

---

## ✅ Checklist de Migration

### Avant Migration
- [ ] Créer branche `feature/symfony-7.4-lts`
- [ ] Backup `composer.json`
- [ ] Vérifier que environnement dev fonctionne
- [ ] Informer l'équipe de la migration

### Pendant Migration
- [ ] Modifier `composer.json` (versions 7.4.*)
- [ ] Exécuter `composer update symfony/*`
- [ ] Vérifier `composer.lock` updated
- [ ] Clear cache Symfony

### Tests Post-Migration
- [ ] Tester tous endpoints Public (7)
- [ ] Tester endpoints Staff (4)
- [ ] Tester endpoints WSM (6) - CRITIQUE
- [ ] Tester endpoint Report (1)
- [ ] Vérifier logs (aucune erreur)
- [ ] Vérifier performance (< 100ms)

### Finalisation
- [ ] Commit changements
- [ ] Push branche
- [ ] Créer PR (si workflow PR)
- [ ] Merger dans develop/main
- [ ] Déployer en pre-production
- [ ] Tester en pre-production
- [ ] Déployer en production

---

## 📊 Suivi des Problèmes

### Problèmes Identifiés

| Date | Problème | Impact | Résolution | Statut |
|------|----------|--------|------------|--------|
| - | - | - | - | - |

**Note** : Aucun problème connu pour migration 7.3 → 7.4 (migration mineure)

---

## 📚 Ressources

### Documentation Symfony
- **Upgrade Guide** : https://symfony.com/doc/current/setup/upgrade_minor.html
- **Changelog 7.4** : https://github.com/symfony/symfony/blob/7.4/CHANGELOG-7.4.md
- **Release Notes** : https://symfony.com/blog/category/living-on-the-edge/7.4
- **LTS Support** : https://symfony.com/releases/7.4

### Documentation API Platform
- **Symfony Compatibility** : https://api-platform.com/docs/core/symfony/
- **Upgrading** : https://api-platform.com/docs/core/upgrade-guide/

### Documentation Interne
- **API2 README** : [sources/api2/README.md](../../../sources/api2/README.md)
- **API2 Endpoints** : [sources/api2/API_ENDPOINTS.md](../../../sources/api2/API_ENDPOINTS.md)
- **Plan de Test API2** : [API2_MIGRATION_TEST_PLAN.md](../plans/API2_MIGRATION_TEST_PLAN.md)

---

## 🎯 Bénéfices de la Migration LTS

### 1. Support Étendu
- **Corrections de bugs** : Jusqu'en novembre 2028
- **Correctifs de sécurité** : Garantis pendant 3 ans
- **Mises à jour mineures** : Régulières et stables

### 2. Stabilité
- Version LTS = Production-ready
- Moins de changements breaking
- Meilleure compatibilité bibliothèques tierces

### 3. Communauté
- Plus grande adoption (version LTS)
- Plus de ressources et tutoriels
- Meilleur support Stack Overflow

### 4. Future-Proof
- Base solide pour 2025-2028
- Pas besoin de migrer avant Symfony 8 LTS (2027)

---

## 📅 Timeline

### Migration Immédiate (Recommandé)
**Durée** : 1-2 heures
**Risque** : Très faible (migration mineure)
**Bénéfice** : Support LTS immédiat

**Planning suggéré** :
1. **Aujourd'hui** : Migration en dev (1h)
2. **Demain** : Tests complets (2h)
3. **J+2** : Déploiement pre-production
4. **J+3** : Validation pre-production (24h)
5. **J+4** : Déploiement production

### Alternative : Migration Différée
**Si besoin de plus de tests** :
1. **Semaine 1** : Migration + tests en dev
2. **Semaine 2** : Tests en pre-production
3. **Semaine 3** : Déploiement production

---

## 🔗 Issues GitHub Associées

- Label : `symfony-upgrade`
- Milestone : `API2 - Symfony 7.4 LTS`

---

**Dernière mise à jour** : 2025-12-17
**Version** : 1.0
**Statut** : 📋 Plan créé - Prêt pour exécution
**Prochaine action** : Exécuter la migration en dev
