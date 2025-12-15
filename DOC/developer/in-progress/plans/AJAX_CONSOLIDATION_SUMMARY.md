# Synthèse - Consolidation des Endpoints AJAX

> **Document de référence complet** : [AJAX_ENDPOINTS_CONSOLIDATION_PLAN.md](AJAX_ENDPOINTS_CONSOLIDATION_PLAN.md)

---

## 🎉 MISE À JOUR IMPORTANTE (Nov 2025)

**✅ API2 (Symfony) existe déjà dans `develop` !**
- 16% de la migration est déjà faite (16/82 endpoints)
- Infrastructure complète prête (Symfony 7.3 + API Platform 4.2)
- Gain de 4 semaines sur le planning

**🆕 3 nouveaux endpoints identifiés** :
- `CopyTeamComposition.php` - Copie roster équipe
- `GetTeamCompetitions.php` - Historique compétitions
- `api_worker.php` - Worker événements live (8 actions)

**📋 Voir détails** : [AJAX_CONSOLIDATION_UPDATE_NOV2025.md](AJAX_CONSOLIDATION_UPDATE_NOV2025.md)

---

## 📊 Chiffres Clés (Mis à Jour)

- **82 endpoints AJAX autonomes** identifiés (+2 vs original)
- **16 endpoints déjà migrés** dans API2 (19.5% complété ✨)
- **13 doublons** détectés (autocomplete, chrono, event imports)
- **~70% de réduction** visée (82 fichiers → 25-30 endpoints REST)
- **7 phases de migration** planifiées sur **14 semaines** (-4 vs original)
- **3 niveaux d'impact** : Critique (WSM, App3), Haut (Admin), Moyen/Faible (Exports, Utils)

## 🎯 Objectifs

1. ✅ **Normaliser** les routes selon conventions REST
2. ✅ **Éliminer** les doublons et code redondant
3. ✅ **Optimiser** les performances et requêtes SQL
4. ✅ **Sécuriser** avec authentification centralisée
5. ✅ **Maintenir** la compatibilité avec clients existants

## 📂 Répartition par Catégorie (Mis à Jour)

| Catégorie | Fichiers Actuels | API2 Existants | À Migrer | Endpoints Cibles | % Complété |
|-----------|-----------------|----------------|----------|------------------|------------|
| **Public API** | 7 | 7 | 0 | 7 | ✅ 100% |
| **Autocomplete/Search** | 19 | 0 | 19 | 8 | ⏳ 0% |
| **Match Management** | 15 | 4 | 11 | 7 groupes REST | ⏳ 27% |
| **Team Management** | 6 | 0 | 6 | 4 | ⏳ 0% |
| **Staff/Scrutineering** | 4 | 4 | 0 | 4 | ✅ 100% |
| **Reports** | 1 | 1 | 0 | 1 | ✅ 100% |
| **Status Updates** | 4 | 0 | 4 | 4 | ⏳ 0% |
| **CSV/Export** | 5 | 0 | 5 | 4 | ⏳ 0% |
| **Live Broadcasting** | 8 | 0 | 8 | 5 | ⏳ 0% |
| **Calendar/Events** | 3 | 0 | 3 | 2 | ⏳ 0% |
| **Imports** | 2 | 0 | 2 | 2 | ⏳ 0% |
| **Connector/Sync** | 4 | 0 | 4 | 3 | ⏳ 0% |
| **Utilities** | 4 | 0 | 4 | 2 | ⏳ 0% |
| **TOTAL** | **82** | **16** | **66** | **~30** | **19.5%** ✨ |

## 🏗️ Structure API Cible

### `/api/` (PHP Legacy - Endpoints Rapides)
```
/api/autocomplete/{resource}   → 8 endpoints (players, teams, clubs, etc.)
/api/match/{id}/*               → 12 endpoints (CRUD, events, players, timer)
/api/export/{resource}          → 4 endpoints (stats, players, calendar)
/api/search/{resource}          → 2 endpoints (teams, clubs)
/api/live/*                     → 5 endpoints (stream, cache, timer)
/api/connector/*                → 3 endpoints (events sync)
```

### `/api2/` (Symfony - Endpoints Complexes)
```
/api2/api/matches               → Resource REST complète
/api2/api/teams                 → Resource REST complète
/api2/api/competitions          → Resource REST complète
/api2/api/players               → Resource REST complète
```

## 🔥 Top 7 Doublons à Consolider

1. **Autocompl_joueur.php** (v1, v2, v3) → `GET /api/autocomplete/players`
2. **Autocompl_club.php** (v1, v2) → `GET /api/autocomplete/clubs`
3. **Autocompl_compet.php** (v1, v2) → `GET /api/autocomplete/competitions`
4. **Autocompl_arb.php** (v1, v3) → `GET /api/autocomplete/referees`
5. **setChrono.php + ajax_updateChrono.php** → `POST/PATCH /api/match/{id}/timer`
6. **set_evenement.php + set_evenement2.php** → `POST /api/connector/events`
7. **csv_stats_export.php + export_stats_csv.php** → `GET /api/export/stats/{eventId}`

## 📅 Phases de Migration (Priorités)

### Phase 1-2 : Fondations + Autocomplete (Semaines 1-4) ⚡
- Créer infrastructure API
- Migrer 19 endpoints autocomplete → 8 consolidés
- **Impact** : Haut (admin jQuery)

### Phase 3 : Match Management (Semaines 5-8) 🔥 CRITIQUE
- Migrer 15 endpoints match → 7 groupes REST
- **Impact** : Critique (WSM, App3, arbitrage live)
- Tests intensifs requis

### Phase 4-7 : Autres Endpoints (Semaines 9-16)
- Status updates, exports, live broadcasting, imports
- **Impact** : Moyen à Faible

### Phase 8 : Décommissionnement (Semaines 17-18)
- Supprimer anciens fichiers
- Nettoyage et archivage

## 🔒 Sécurité - Points d'Attention

| Vulnérabilité Actuelle | Solution |
|------------------------|----------|
| `UpdateCellJQ.php` - modification générique | Remplacer par endpoints REST spécifiques |
| Authentication incohérente | Middleware centralisé + JWT tokens (API2) |
| SQL Injection potentielle | Doctrine DBAL + requêtes préparées partout |
| CORS non contrôlé | Configuration stricte dans API2 |
| Pas de rate limiting | Implémenter 100 req/min par IP |

## 📊 Impact Clients

| Application | Impact | Stratégie |
|-------------|--------|-----------|
| **Admin Legacy (jQuery)** | 🔥 Haut | Wrappers de compatibilité + migration progressive |
| **App3 (Match Sheet)** | 🔥 Critique | Tests intensifs, rollback plan |
| **WSM (Score Management)** | 🔥 Critique | Migration coordonnée, tests live |
| **App2 (Scrutineering)** | ⚠️ Moyen | Mise à jour composables Nuxt |
| **Live Broadcasting** | ⚠️ Moyen | Tests événements réels |

## 🧪 Stratégie de Test

- **Tests unitaires** (PHPUnit) : Chaque contrôleur
- **Tests d'intégration** : Workflows complets (match, events, timer)
- **Tests E2E** (Cypress) : Interfaces utilisateurs
- **Load testing** : Performances sous charge
- **Security testing** : Injection, XSS, CSRF

## 📈 Métriques de Succès

| KPI | Objectif |
|-----|----------|
| Couverture migration | 100% |
| Temps de réponse (P95) | <100ms |
| Taux d'erreur | <0.1% |
| Réduction fichiers | -70% |
| Zero downtime | 100% |

## 🚀 Actions Immédiates (RÉVISÉES Nov 2025)

### Semaine 1 : Validation API2 Existante ⚡

1. **Tester l'API2** (PRIORITÉ #1)
   - ✅ Tester tous les endpoints PublicController (`/api2/api/events`, `/api2/api/games`, etc.)
   - ✅ Tester WsmController (critical : timer, events, stats)
   - ✅ Tester StaffController (scrutineering)
   - ✅ Vérifier documentation Swagger : `https://kpi.localhost/api2/doc`

2. **Analyser les nouveaux endpoints**
   - 📖 Lire code de `CopyTeamComposition.php` (114 lignes)
   - 📖 Lire code de `GetTeamCompetitions.php` (86 lignes)
   - 📖 Lire code de `api_worker.php` (332 lignes)
   - 📝 Documenter dépendances SQL et logique métier

3. **Setup tests API2**
   - 🧪 Installer PHPUnit pour API2
   - 🧪 Créer tests pour PublicController
   - 🧪 Créer tests pour WsmController (critique)

4. **Mise à jour documentation**
   - 📝 Compléter annotations OpenAPI dans contrôleurs
   - 📝 Générer documentation Swagger complète
   - 📝 Créer guide migration développeurs v1.0

### Semaine 2 : POC Autocomplete

5. **POC Autocomplete** (premier endpoint à migrer)
   - 🧪 Créer `AutocompleteController` dans API2
   - 🧪 Implémenter `GET /api2/api/autocomplete/players`
   - 🧪 Tester avec frontend legacy
   - 🧪 Valider performances (<50ms)

## 📚 Documentation

- **Plan complet** : [AJAX_ENDPOINTS_CONSOLIDATION_PLAN.md](AJAX_ENDPOINTS_CONSOLIDATION_PLAN.md)
- **⭐ Mise à jour Nov 2025** : [AJAX_CONSOLIDATION_UPDATE_NOV2025.md](AJAX_CONSOLIDATION_UPDATE_NOV2025.md)
  - API2 déjà implémentée (16 endpoints)
  - 3 nouveaux endpoints identifiés
  - Plan révisé (14 semaines au lieu de 18)
- **API2 Documentation** :
  - README : `sources/api2/README.md`
  - Endpoints : `sources/api2/API_ENDPOINTS.md`
  - Swagger Setup : `sources/api2/SWAGGER_SETUP.md`
- **Worker Documentation** :
  - Guide complet : `sources/live/EVENT_WORKER_README.md`
  - Quick Start : `sources/live/QUICK_START.md`
- **OpenAPI/Swagger** : `https://kpi.localhost/api2/doc`

## 🔗 Ressources

- **Issues GitHub** : Label `api-consolidation`
- **Branches** : `feature/api-consolidation`
- **Tests** : Environnement dédié pré-production
- **Monitoring** : Dashboards APM (temps réponse, erreurs, usage)

---

**Dernière mise à jour** : 2025-11-22 (Révision Nov 2025)
**Version** : 1.1
**Statut** : ✅ Plan révisé avec découverte API2, Phase 1 à 19.5% (16/82 endpoints déjà migrés)
