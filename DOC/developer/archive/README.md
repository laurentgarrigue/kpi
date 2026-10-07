# Archives - Sujets traités

Documents conservés pour l'**historique** : migrations terminées, correctifs appliqués, audits
dont les conclusions ont été intégrées, plans exécutés. Ils décrivent l'état du projet **au moment
où ils ont été écrits** : chemins de fichiers, versions et statuts (« ⏳ en cours », « à tester »)
peuvent être périmés. Pour l'état actuel, voir [reference/](../reference/) et [guides/](../guides/).

Classement **par thème**, pas par type de document.

## [php8/](php8/) - Migration PHP 8.4 (✅ oct.-nov. 2025)

- [PHP8_MIGRATION_COMPLETE.md](php8/PHP8_MIGRATION_COMPLETE.md) - **Rapport final** (point d'entrée)
- [PHP8_MIGRATION_SUMMARY.md](php8/PHP8_MIGRATION_SUMMARY.md) - Synthèse technique
- [PHP8_TESTING_CHECKLIST.md](php8/PHP8_TESTING_CHECKLIST.md) - Checklist de tests
- [PHP8_DOCKER_SWITCH.md](php8/PHP8_DOCKER_SWITCH.md) - Bascule des conteneurs
- [MIGRATION_SMARTY_V4.md](php8/MIGRATION_SMARTY_V4.md) - Smarty v2 → v4
- Correctifs : [PHP84_DEPRECATED_FIXES](php8/PHP84_DEPRECATED_FIXES.md),
  [PHP8_GESTIONDOC_FIXES](php8/PHP8_GESTIONDOC_FIXES.md),
  [SMARTY_PHP8_FIXES](php8/SMARTY_PHP8_FIXES.md),
  [WORDPRESS_PHP8_FIXES](php8/WORDPRESS_PHP8_FIXES.md),
  [WORDPRESS_PHP84_MIGRATION](php8/WORDPRESS_PHP84_MIGRATION.md),
  [FIX_FEUILLE_MATCH_MULTI](php8/FIX_FEUILLE_MATCH_MULTI.md),
  [BUG_SQL_COMPET_ASTERISK](php8/BUG_SQL_COMPET_ASTERISK.md)

## [pdf-exports/](pdf-exports/) - PDF et exports tableurs (✅ oct.-nov. 2025)

- [MIGRATION_FPDF_TO_MPDF.md](pdf-exports/MIGRATION_FPDF_TO_MPDF.md) - Plan FPDF → mPDF
- [MIGRATION_FPDF_MYPDF_SUCCESS.md](pdf-exports/MIGRATION_FPDF_MYPDF_SUCCESS.md) - Bilan mPDF / wrapper MyPDF
  (le pattern encore utile est extrait dans [guides/PATTERN_8_IMAGES_ARRIERE_PLAN.md](../guides/PATTERN_8_IMAGES_ARRIERE_PLAN.md))
- [MIGRATION_PDFMATCHMULTI_NOTES.md](pdf-exports/MIGRATION_PDFMATCHMULTI_NOTES.md) - Notes PdfMatchMulti
- [MIGRATION_OPENTBS_TO_OPENSPOUT.md](pdf-exports/MIGRATION_OPENTBS_TO_OPENSPOUT.md) - OpenTBS → OpenSpout
- [QRCODE_MIGRATION.md](pdf-exports/QRCODE_MIGRATION.md) - Bibliothèque QR code
- Correctifs : [FIX_CSV_EXPORT_OPENSPOUT](pdf-exports/FIX_CSV_EXPORT_OPENSPOUT.md),
  [FIX_MYPDF_OPEN_METHOD](pdf-exports/FIX_MYPDF_OPEN_METHOD.md)

## [legacy-frontend/](legacy-frontend/) - Modernisation JS/CSS du legacy PHP (oct.-nov. 2025)

Terminé (✅) : Bootstrap 5.3.8 (phases 1-3), Flatpickr, autocomplete vanilla, Axios → fetch(),
masked input, nettoyage JS phase 1, consolidation des traductions.
Suspendu (⏸️) : élimination complète de jQuery, tooltips des templates `kppage*.tpl`, phase 5
(sélecteurs jQuery) - l'interface legacy est désormais remplacée par app4 (admin) et le sera
par app3 (site public, cf. [PUBLIC_SITE_REDESIGN_STRATEGY.md](../in-progress/PUBLIC_SITE_REDESIGN_STRATEGY.md)).

- Vue d'ensemble : [MIGRATIONS_SUMMARY.md](legacy-frontend/MIGRATIONS_SUMMARY.md)
- Audits : [JS_LIBRARIES_AUDIT](legacy-frontend/JS_LIBRARIES_AUDIT.md),
  [JS_LIBRARIES_USAGE_ANALYSIS](legacy-frontend/JS_LIBRARIES_USAGE_ANALYSIS.md),
  [JS_LIBRARIES_CLEANUP_PLAN](legacy-frontend/JS_LIBRARIES_CLEANUP_PLAN.md),
  [JS_CLEANUP_PHASE1_COMPLETE](legacy-frontend/JS_CLEANUP_PHASE1_COMPLETE.md)
- Bootstrap : [PLAN_MIGRATION_BOOTSTRAP](legacy-frontend/PLAN_MIGRATION_BOOTSTRAP.md),
  [BOOTSTRAP_MIGRATION_STATUS](legacy-frontend/BOOTSTRAP_MIGRATION_STATUS.md),
  [PHASE1](legacy-frontend/BOOTSTRAP_PHASE1_COMPLETE.md),
  [PHASE2](legacy-frontend/BOOTSTRAP_PHASE2_COMPLETE.md),
  [PHASE3](legacy-frontend/BOOTSTRAP_PHASE3_COMPLETE.md),
  [PHASE3_INVENTORY](legacy-frontend/BOOTSTRAP_PHASE3_INVENTORY.md)
- Flatpickr : [FLATPICKR_MIGRATION_GUIDE](legacy-frontend/FLATPICKR_MIGRATION_GUIDE.md),
  [FLATPICKR_MIGRATION_STATUS](legacy-frontend/FLATPICKR_MIGRATION_STATUS.md)
- Autocomplete : [AUTOCOMPLETE_MIGRATION_GUIDE](legacy-frontend/AUTOCOMPLETE_MIGRATION_GUIDE.md),
  [AUTOCOMPLETE_MIGRATION_SUMMARY](legacy-frontend/AUTOCOMPLETE_MIGRATION_SUMMARY.md),
  [NEXT_STEPS_AUTOCOMPLETE](legacy-frontend/NEXT_STEPS_AUTOCOMPLETE.md),
  exemple [GestionEquipe.js.EXAMPLE_MIGRATED](legacy-frontend/GestionEquipe.js.EXAMPLE_MIGRATED)
- Axios → fetch() : [AXIOS_TO_FETCH_MIGRATION](legacy-frontend/AXIOS_TO_FETCH_MIGRATION.md),
  [MIGRATION_AXIOS_FETCH_GUIDE](legacy-frontend/MIGRATION_AXIOS_FETCH_GUIDE.md),
  [AXIOS_MIGRATION_TEMPLATES_UPDATE](legacy-frontend/AXIOS_MIGRATION_TEMPLATES_UPDATE.md)
- Masked input : [MASKED_INPUT_MIGRATION_STATUS](legacy-frontend/MASKED_INPUT_MIGRATION_STATUS.md)
- ⏸️ jQuery / tooltips : [JQUERY_ELIMINATION_STRATEGY](legacy-frontend/JQUERY_ELIMINATION_STRATEGY.md),
  [PHASE5_JQUERY_SELECTORS_ANALYSIS](legacy-frontend/PHASE5_JQUERY_SELECTORS_ANALYSIS.md),
  [TOOLTIP_MIGRATION_STATUS](legacy-frontend/TOOLTIP_MIGRATION_STATUS.md),
  [TOOLTIP_TESTING_GUIDE](legacy-frontend/TOOLTIP_TESTING_GUIDE.md)
- Traductions : [CONSOLIDATION_TRADUCTIONS](legacy-frontend/CONSOLIDATION_TRADUCTIONS.md),
  [GUIDE_RAPIDE](legacy-frontend/CONSOLIDATION_TRADUCTIONS_GUIDE_RAPIDE.md),
  [SCRIPTS](legacy-frontend/CONSOLIDATION_TRADUCTIONS_SCRIPTS.md)

## [ci-cd/](ci-cd/) - CI/CD GitHub Actions (✅ juil.-sept. 2026)

- [CI_CD_STRATEGY.md](ci-cd/CI_CD_STRATEGY.md) - Plan d'action (phases 0 à 8 livrées), cité par les workflows
- [CI_CD_EXECUTION_NOTES.md](ci-cd/CI_CD_EXECUTION_NOTES.md) - Journal d'exécution
- [GIT_WORKFLOW_SIMPLIFICATION.md](ci-cd/GIT_WORKFLOW_SIMPLIFICATION.md) - Simplification du workflow (lots 1-3)

Pour l'usage courant : [guides/GIT_WORKFLOW.md](../guides/GIT_WORKFLOW.md) et
[infrastructure/DEPLOYMENT_RUNBOOK.md](../infrastructure/DEPLOYMENT_RUNBOOK.md).

## [infrastructure/](infrastructure/) - Docker, serveurs, médias

- [DOCKER_PROD_FIXES.md](infrastructure/DOCKER_PROD_FIXES.md) - Correctifs Docker prod (2025-10)
- [DOCKERFILE_OPTIMIZATIONS.md](infrastructure/DOCKERFILE_OPTIMIZATIONS.md) - Optimisations Dockerfiles (2025-10)
- [MAKEFILE_COMPOSER_UPDATES.md](infrastructure/MAKEFILE_COMPOSER_UPDATES.md) - Makefile & Composer (2025-10)
- [WORDPRESS_MIGRATION_OLD_PROD_TO_VPS.md](infrastructure/WORDPRESS_MIGRATION_OLD_PROD_TO_VPS.md) - WordPress vers le VPS (2025-11)
- [MERGE_CHECKLIST_APP3_MEDIA.md](infrastructure/MERGE_CHECKLIST_APP3_MEDIA.md) - Suppression de l'ancien app3 + médias hors Git (clos le 06/10/2026)

## [features/](features/) - Fonctionnalités et correctifs applicatifs

- [ADMIN_BACKEND_MIGRATION_PLAN.md](features/ADMIN_BACKEND_MIGRATION_PLAN.md) - Création d'app4 + JWT api2 (phases pilote terminées)
- [AUDIT_TESTEUR_PROFIL_2.md](features/AUDIT_TESTEUR_PROFIL_2.md) - Retours de recette profil 2 (tous résolus)
- [MANDATE_NIVEAU_BRUT_AUDIT.md](features/MANDATE_NIVEAU_BRUT_AUDIT.md) - Profil brut vs mandat actif (corrigé 2026-09)
- [2026-02-09_RC_NULL_MIGRATION.md](features/2026-02-09_RC_NULL_MIGRATION.md) - RC « - CNA - » → NULL
- [COMPETITION_TYPE_MULTI.md](features/COMPETITION_TYPE_MULTI.md) - Première doc MULTI, remplacée par
  [reference/features/COMPETITION_TYPE_MULTI_TECHNICAL.md](../reference/features/COMPETITION_TYPE_MULTI_TECHNICAL.md)
- [PR_DESCRIPTION.md](features/PR_DESCRIPTION.md) - Description de PR (GestionOperations + upload d'images)

## [audits/](audits/) - Audits et revues

- [README_MIGRATION.md](audits/README_MIGRATION.md) - Point d'entrée de l'audit de démarrage (oct. 2025)
- [AUDIT_PHASE_0.md](audits/AUDIT_PHASE_0.md) / [AUDIT_SUMMARY.md](audits/AUDIT_SUMMARY.md) - Audit initial
- [MIGRATION.md](audits/MIGRATION.md) - Plan de modernisation initial
- [CLEANUP_QUICK_WINS.md](audits/CLEANUP_QUICK_WINS.md) - Nettoyage pré-migration
- [LIVE_MATCH_REFACTORING_REVIEW.md](audits/LIVE_MATCH_REFACTORING_REVIEW.md) - Revue critique du scoring live
  (raisonnement intégré au plan [in-progress/LIVE_MATCH_SCORING_REFACTORING_PROPOSALS.md](../in-progress/LIVE_MATCH_SCORING_REFACTORING_PROPOSALS.md))
