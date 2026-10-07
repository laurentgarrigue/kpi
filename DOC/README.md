# Documentation KPI - Index Principal

## 🗂️ Organisation

```
DOC/
├── user/                    Guides utilisateurs (fonctionnels, concis)
│   └── archive/             Synthèses passées
├── specs/                   Spécifications des pages app4 (admin2)
└── developer/
    ├── reference/           Ce qui EXISTE : architecture, API, règles métier
    │   └── features/        Documentation technique d'une fonctionnalité
    ├── guides/              Comment FAIRE : workflow git, env. de dev, tests, bonnes pratiques
    ├── infrastructure/      Comment ça TOURNE : déploiement, médias, Nginx, CORS, cron
    ├── in-progress/         Travaux EN COURS (plans, migrations non terminées)
    └── archive/             Sujets TRAITÉS, classés par thème (lecture historique)
```

### Où ranger un document ?

| Le document… | Dossier |
|---|---|
| explique une fonctionnalité à un utilisateur | `user/` |
| spécifie une page de l'admin app4 | `specs/` |
| décrit l'état actuel du système (architecture, API, règle métier) | `developer/reference/` |
| décrit techniquement une fonctionnalité précise | `developer/reference/features/` |
| explique comment travailler au quotidien | `developer/guides/` |
| concerne le déploiement ou l'exploitation des serveurs | `developer/infrastructure/` |
| est un plan, une migration ou un correctif **pas encore terminé** | `developer/in-progress/` |
| relate un travail **terminé** (migration, correctif, audit, plan exécuté) | `developer/archive/<thème>/` |

**Cycle de vie** : un document naît dans `in-progress/`. Quand le sujet est clos, il part dans
`archive/<thème>/` (avec `git mv`, puis mise à jour des liens) ; si une partie reste vraie
durablement, elle est extraite dans `reference/` ou `guides/`. Pas de sous-dossier de premier
niveau supplémentaire : le visualiseur de documentation ([DocViewer](../sources/admin/DocViewer.php))
ne lit que `user/` et `developer/`, et groupe par premier sous-dossier.

Convention de nommage des fichiers : [NAMING_CONVENTION.md](NAMING_CONVENTION.md).

---

## 📘 [Documentation Utilisateur](user/)

- **[NOUVEAUTES.md](user/NOUVEAUTES.md)** - Dernières nouveautés
- **[DOCVIEWER_GUIDE.md](user/DOCVIEWER_GUIDE.md)** - Visualiseur de documentation
- **[APP2_APPLICATION_WEB.md](user/APP2_APPLICATION_WEB.md)** - Application web (app2)
- **[ADMIN_STATISTICS.md](user/ADMIN_STATISTICS.md)** - Statistiques - nouvelle interface (app4)
- **[EVENT_CACHE_MANAGER.md](user/EVENT_CACHE_MANAGER.md)** - Worker de cache pour incrustations vidéo
- **[IMAGE_UPLOAD_MANAGEMENT.md](user/IMAGE_UPLOAD_MANAGEMENT.md)** - Upload et gestion d'images
- **[TEAM_COMPOSITION_COPY.md](user/TEAM_COMPOSITION_COPY.md)** - Copie de composition d'équipe
- **[MATCH_DAY_BULK_OPERATIONS.md](user/MATCH_DAY_BULK_OPERATIONS.md)** - Opérations de masse sur les matchs
- **[BULK_COMPETITION_COPY.md](user/BULK_COMPETITION_COPY.md)** - Copie en masse de compétitions entre saisons
- **[MATCH_CONSISTENCY_STATS.md](user/MATCH_CONSISTENCY_STATS.md)** - Cohérence des plannings de matchs
- **[CONSOLIDATION_PHASES_CLASSEMENT.md](user/CONSOLIDATION_PHASES_CLASSEMENT.md)** - Consolidation des phases de classement
- **[MULTI_COMPETITION_TYPE.md](user/MULTI_COMPETITION_TYPE.md)** - Type de compétition MULTI
- Archive : [SYNTHESE_TRAVAUX_OCT_DEC_2025.md](user/archive/SYNTHESE_TRAVAUX_OCT_DEC_2025.md)

## 📐 [Spécifications app4](specs/)

Une spec par page de l'admin app4 (`PAGE_*.md`) + specs transverses :
[COMMON_ADMIN_SPECS.md](specs/COMMON_ADMIN_SPECS.md), [DROITS_PAR_PROFIL.md](specs/DROITS_PAR_PROFIL.md),
[MENU_REORGANIZATION.md](specs/MENU_REORGANIZATION.md), [DARK_MODE.md](specs/DARK_MODE.md),
[PWA_AUTO_UPDATE.md](specs/PWA_AUTO_UPDATE.md), [TUTORIEL_ADMIN2.md](specs/TUTORIEL_ADMIN2.md).

## 💻 [Documentation Développeur](developer/)

### [Référence](developer/reference/) — l'existant
- **[FRANKENPHP_MIGRATION_ANALYSIS.md](developer/reference/FRANKENPHP_MIGRATION_ANALYSIS.md)** - ⭐ Architecture web actuelle : `/api2` sur FrankenPHP (worker + Mercure), Apache pour le legacy
- **[API2_ENDPOINTS.md](developer/reference/API2_ENDPOINTS.md)** - Référence complète des endpoints API2
- **[APP4_STRUCTURE.md](developer/reference/APP4_STRUCTURE.md)** - Architecture app4 (stores, composants, patterns)
- **[APP2_TECHNICAL_ARCHITECTURE.md](developer/reference/APP2_TECHNICAL_ARCHITECTURE.md)** - Architecture app2 (PWA, erreurs, API)
- **[PROFILE_ROLES.md](developer/reference/PROFILE_ROLES.md)** - Profils ↔ rôles Symfony, mandats, piège `#[IsGranted]`
- **[PLAYER_ELIGIBILITY_RULES.md](developer/reference/PLAYER_ELIGIBILITY_RULES.md)** - Règles « joueur en règle »
- **[LIVE_MATCH_WEBSOCKET_ARCHITECTURE.md](developer/reference/LIVE_MATCH_WEBSOCKET_ARCHITECTURE.md)** - Chaîne temps réel d'un match (existant)
- **[KPI_FUNCTIONALITY_INVENTORY.md](developer/reference/KPI_FUNCTIONALITY_INVENTORY.md)** - Inventaire des fonctionnalités
- **[EXTRACTION_STRUCTURES_FFCK.md](developer/reference/EXTRACTION_STRUCTURES_FFCK.md)** - Extraction des structures FFCK
- **[features/](developer/reference/features/)** - Fonctionnalités :
  [COMPETITION_TYPE_MULTI_TECHNICAL](developer/reference/features/COMPETITION_TYPE_MULTI_TECHNICAL.md),
  [CONSOLIDATION_PHASES_CLASSEMENT](developer/reference/features/CONSOLIDATION_PHASES_CLASSEMENT.md),
  [RANKING_STATUS_RESTRICTIONS](developer/reference/features/RANKING_STATUS_RESTRICTIONS.md),
  [STAT_LICENCIES_CATEGORIE](developer/reference/features/STAT_LICENCIES_CATEGORIE.md)

### [Guides](developer/guides/) — le quotidien
- **[ENVIRONNEMENT_DEV.md](developer/guides/ENVIRONNEMENT_DEV.md)** - ⭐ `make dev`, où lire les logs de chaque service
- **[GIT_WORKFLOW.md](developer/guides/GIT_WORKFLOW.md)** - ⭐ Branches, PR, worktrees, versions
- **[MAKEFILE_MULTI_ENVIRONMENT.md](developer/guides/MAKEFILE_MULTI_ENVIRONMENT.md)** - Plusieurs instances sur un même serveur
- **[NPM_BACKEND_PRODUCTION_GUIDE.md](developer/guides/NPM_BACKEND_PRODUCTION_GUIDE.md)** - Bibliothèques JS du backend PHP
- **[QA_APP4_PROFILES.md](developer/guides/QA_APP4_PROFILES.md)** - Checklist de test app4 par profil
- **[BEST_PRACTICES_JAVASCRIPT_SMARTY.md](developer/guides/BEST_PRACTICES_JAVASCRIPT_SMARTY.md)** - Bonnes pratiques JS & Smarty
- **[JS_TRANSLATIONS_GUIDE.md](developer/guides/JS_TRANSLATIONS_GUIDE.md)** - Traductions côté JS legacy
- **[PATTERN_8_IMAGES_ARRIERE_PLAN.md](developer/guides/PATTERN_8_IMAGES_ARRIERE_PLAN.md)** - mPDF : images décoratives en arrière-plan
- **[GUIDE_EXTRACTION_STRUCTURES.md](developer/guides/GUIDE_EXTRACTION_STRUCTURES.md)** - Lancer l'extraction des structures
- **[PROMPTS.md](developer/guides/PROMPTS.md)** - Exemples de prompts pour Claude Code

### [Infrastructure](developer/infrastructure/) — l'exploitation
- **[DEPLOYMENT_RUNBOOK.md](developer/infrastructure/DEPLOYMENT_RUNBOOK.md)** - ⭐ Déployer, diagnostiquer, rollback
- **[MEDIA_STORAGE.md](developer/infrastructure/MEDIA_STORAGE.md)** - Médias hors Git, montages, sauvegarde restic
- **[NGINX_STATIC_APP_DEPLOYMENT.md](developer/infrastructure/NGINX_STATIC_APP_DEPLOYMENT.md)** - app2/app4 servies par Nginx (SSG)
- **[CORS_CONFIGURATION.md](developer/infrastructure/CORS_CONFIGURATION.md)** - CORS du legacy (auto-prepend)
- **[CACHE_BUSTING_STRATEGY.md](developer/infrastructure/CACHE_BUSTING_STRATEGY.md)** - Cache busting app2
- **[CRON_DOCUMENTATION.md](developer/infrastructure/CRON_DOCUMENTATION.md)** - Tâches planifiées
- **[MATOMO_CONFIG.md](developer/infrastructure/MATOMO_CONFIG.md)** - Configuration Matomo
- **[WORDPRESS_DOCKER_DECISION.md](developer/infrastructure/WORDPRESS_DOCKER_DECISION.md)** - WordPress dans le conteneur PHP (décision)
- Index détaillé : [infrastructure/README.md](developer/infrastructure/README.md)

### [Travaux en cours](developer/in-progress/)
- **[PUBLIC_SITE_REDESIGN_STRATEGY.md](developer/in-progress/PUBLIC_SITE_REDESIGN_STRATEGY.md)** - Refonte du site public (WordPress + kp*.php → app3)
- **[LIVE_MATCH_SCORING_REFACTORING_PROPOSALS.md](developer/in-progress/LIVE_MATCH_SCORING_REFACTORING_PROPOSALS.md)** - Plan de refonte du scoring live
- **[DOCUMENTS_MIGRATION.md](developer/in-progress/DOCUMENTS_MIGRATION.md)** - Migration des documents PDF vers app4
- **[LEGACY_PDF_STANDALONE_ACCESS.md](developer/in-progress/LEGACY_PDF_STANDALONE_ACCESS.md)** - PDF legacy accessibles depuis admin2
- **[FIX_RANKING_CONSOLIDATED_PHASES.md](developer/in-progress/FIX_RANKING_CONSOLIDATED_PHASES.md)** - ⚠️ Correctif classement : remédiations manuelles restantes
- **[ROADMAP_KPI.md](developer/in-progress/ROADMAP_KPI.md)** - Feuille de route

### [Archives](developer/archive/) — sujets traités
Index détaillé : [archive/README.md](developer/archive/README.md)
- **[php8/](developer/archive/php8/)** - Migration PHP 8.4, Smarty v4, correctifs associés
- **[pdf-exports/](developer/archive/pdf-exports/)** - FPDF → mPDF, OpenTBS → OpenSpout, QR codes
- **[legacy-frontend/](developer/archive/legacy-frontend/)** - Bootstrap, Flatpickr, autocomplete, Axios, jQuery, tooltips, traductions
- **[ci-cd/](developer/archive/ci-cd/)** - Stratégie CI/CD et simplification du workflow git
- **[infrastructure/](developer/archive/infrastructure/)** - Correctifs Docker, migration VPS WordPress, médias hors Git
- **[features/](developer/archive/features/)** - Plan de création d'app4, audits de recette, correctifs fonctionnels
- **[audits/](developer/archive/audits/)** - Audit initial (phase 0), revue du scoring live

---

## 🔗 Liens Rapides

- [README.md principal](../README.md) - Documentation générale du projet
- [CLAUDE.md](../CLAUDE.md) - Guide pour Claude Code
- [GEMINI.md](../GEMINI.md) - Guide pour Gemini
- [Makefile](../Makefile) - Commandes de développement

**Dernière réorganisation** : 2026-10-07
