# Documentation Développeur

Documentation technique pour le développement et la maintenance du projet KPI.
L'index complet, document par document, est dans [DOC/README.md](../README.md).

## 📂 Organisation

| Dossier | Contenu | Question à laquelle il répond |
|---|---|---|
| [📚 reference/](reference/) | Architecture, API, règles métier ; [features/](reference/features/) pour une fonctionnalité précise | *Comment est-ce que ça marche aujourd'hui ?* |
| [📖 guides/](guides/) | Environnement de dev, workflow git, tests, bonnes pratiques | *Comment je fais… ?* |
| [🏗️ infrastructure/](infrastructure/) | Déploiement, médias, Nginx, CORS, cron, Matomo | *Comment ça tourne en préprod/prod ?* |
| [⏳ in-progress/](in-progress/) | Plans, migrations et correctifs **non terminés** | *Qu'est-ce qui est en cours ?* |
| [✅ archive/](archive/) | Sujets **traités**, classés par thème ([index](archive/README.md)) | *Pourquoi / comment a-t-on fait ça ?* |

## 🎯 À lire en premier

1. **[reference/FRANKENPHP_MIGRATION_ANALYSIS.md](reference/FRANKENPHP_MIGRATION_ANALYSIS.md)** - Architecture web actuelle (api2 sur FrankenPHP, legacy sur Apache)
2. **[guides/ENVIRONNEMENT_DEV.md](guides/ENVIRONNEMENT_DEV.md)** - Démarrer l'environnement et lire les logs
3. **[guides/GIT_WORKFLOW.md](guides/GIT_WORKFLOW.md)** - Branches, PR, versions
4. **[infrastructure/DEPLOYMENT_RUNBOOK.md](infrastructure/DEPLOYMENT_RUNBOOK.md)** - Déployer et revenir en arrière
5. **[reference/API2_ENDPOINTS.md](reference/API2_ENDPOINTS.md)** et **[reference/APP4_STRUCTURE.md](reference/APP4_STRUCTURE.md)** - Développer sur api2 / app4
6. **[reference/PROFILE_ROLES.md](reference/PROFILE_ROLES.md)** - Profils, rôles et mandats

## ♻️ Cycle de vie d'un document

1. Un nouveau plan, une migration ou un correctif en plusieurs étapes → `in-progress/`.
2. Sujet clos → `git mv` vers `archive/<thème>/` (créer le thème s'il n'existe pas), mettre à jour
   les liens (`grep -rn NOM_DU_FICHIER` sur tout le dépôt, y compris code, workflows et Makefile)
   et l'index [archive/README.md](archive/README.md).
3. Ce qui reste vrai durablement (architecture, procédure) est extrait dans `reference/` ou `guides/`.

Pas de troisième niveau de dossiers (sauf `reference/features/` et les thèmes d'archive).

---

- [Documentation Utilisateur](../user/) - Guides et fonctionnalités
- [Spécifications app4](../specs/) - Une spec par page
- [Convention de nommage](../NAMING_CONVENTION.md)
- [README principal](../../README.md) · [CLAUDE.md](../../CLAUDE.md) · [Makefile](../../Makefile)
