# Mise à Jour du Plan de Consolidation AJAX - Novembre 2025

**Date** : 2025-11-22
**Basé sur** : Analyse branche `develop` (commits jusqu'à 4bcbaef)
**Référence** : [AJAX_ENDPOINTS_CONSOLIDATION_PLAN.md](AJAX_ENDPOINTS_CONSOLIDATION_PLAN.md)

---

## 🎉 Découvertes Majeures

### 1. ✅ API2 (Symfony) Déjà Implémentée !

L'API2 avec Symfony 7.3 + API Platform 4.2 **existe déjà** dans la branche `develop` ! Cela change complètement la donne pour la migration.

#### Contrôleurs Implémentés

| Contrôleur | Lignes | Fonctionnalités |
|------------|--------|-----------------|
| **ChartsController.php** | 229 | Charts et rankings pour événements |
| **EventController.php** | 161 | Gestion des événements |
| **GamesController.php** | 128 | Liste des matchs par événement |
| **PublicController.php** | 189 | Endpoints publics (events, ratings, login) |
| **ReportController.php** | 134 | Rapports de match détaillés |
| **StaffController.php** | 216 | Scrutineering (équipements joueurs) |
| **WsmController.php** | 302 | Web Score Management (timer, events, stats) |

**Total** : ~1300+ lignes de code Symfony/API Platform déjà écrites !

#### Configuration Complète

- ✅ API Platform 4.2 configuré
- ✅ NelmioApiDoc (Swagger/OpenAPI) installé
- ✅ CORS configuré
- ✅ Doctrine ORM + Migrations
- ✅ Documentation automatique disponible

**URLs** :
- API Platform UI : `https://kpi.localhost/api2/api`
- Swagger Documentation : `https://kpi.localhost/api2/doc`

---

### 2. 🆕 Nouveaux Endpoints AJAX Identifiés

**3 nouveaux endpoints** autonomes détectés dans `develop` :

#### 2.1 CopyTeamComposition.php

**Fichier** : `sources/admin/CopyTeamComposition.php` (114 lignes)
**Type** : POST AJAX
**Fonction** : Copie la composition (roster) d'une équipe source vers une équipe cible
**Authentification** : Session (Profile ≤ 7)

**Paramètres** :
```json
{
  "idEquipeSource": 123,
  "idEquipeCible": 456
}
```

**Logique** :
- Vérifie que l'équipe cible n'est pas verrouillée (Verrou)
- Contrôle les droits selon Profile (≤3 peut tout modifier, >3 seulement son club)
- Copie les joueurs de `kp_competition_equipe_joueur` source → cible
- Supprime les anciens joueurs de l'équipe cible
- Retourne JSON avec nombre de joueurs copiés

**Tables** :
- `kp_competition_equipe` (vérification verrou)
- `kp_competition_equipe_joueur` (copie joueurs)
- `kp_competition` (vérification verrou)

**Proposition Migration** :
```
POST /api/team/{teamId}/copy-roster
Body: { "sourceTeamId": 123 }
```

Ou dans API2 :
```
POST /api2/api/teams/{teamId}/copy-roster
Body: { "sourceTeamId": 123 }
```

---

#### 2.2 GetTeamCompetitions.php

**Fichier** : `sources/admin/GetTeamCompetitions.php` (86 lignes)
**Type** : GET AJAX
**Fonction** : Récupère les saisons et compétitions d'une équipe
**Authentification** : Session (Profile ≤ 7)

**Actions multiples** :

##### Action 1 : getSaisons
**URL** : `?action=getSaisons`
**Retour** : Liste des 3 dernières saisons (saison active + 2 précédentes)

```json
[
  { "code": "2024-2025", "libelle": "2024-2025" },
  { "code": "2023-2024", "libelle": "2023-2024" },
  { "code": "2022-2023", "libelle": "2022-2023" }
]
```

##### Action 2 : getCompetitions
**URL** : `?action=getCompetitions&saison=2024-2025&idEquipe=123`
**Retour** : Liste des compétitions où cette équipe a participé pour la saison donnée

```json
[
  {
    "id": 456,
    "code": "N1M",
    "libelle": "Nationale 1 Masculine",
    "saison": "2024-2025"
  }
]
```

**Tables** :
- `kp_saison` (liste saisons)
- `kp_competition_equipe` (équipes par compétition)

**Proposition Migration** :

Option 1 (endpoints séparés) :
```
GET /api/seasons?limit=3
GET /api/team/{teamId}/competitions?season={code}
```

Option 2 (endpoints combinés - RECOMMANDÉ) :
```
GET /api2/api/teams/{teamId}/history
Retourne: { "seasons": [...], "competitions": {...} }
```

---

#### 2.3 api_worker.php (Live Events)

**Fichier** : `sources/live/api_worker.php` (332 lignes)
**Type** : GET/POST REST-like API
**Fonction** : Contrôle du worker automatique de génération de caches pour événements live
**Authentification** : Session (optionnelle selon action)

**Actions** :

##### GET ?action=status
Récupère l'état de tous les événements actifs dans le worker

**Retour** :
```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "id_event": 123,
      "date_event": "2025-11-22",
      "hour_event": "14:00:00",
      "offset_event": 15,
      "pitch_event": 4,
      "delay_event": 10,
      "status": "running",
      "created_at": "2025-11-22 10:00:00",
      "updated_at": "2025-11-22 13:45:00"
    }
  ],
  "count": 1
}
```

##### POST action=start
Démarre ou met à jour un événement dans le worker

**Paramètres** :
```json
{
  "id_event": 123,
  "date_event": "2025-11-22",
  "hour_event": "14:00:00",
  "offset_event": 15,
  "pitch_event": 4,
  "delay_event": 10
}
```

- `offset_event` : Minutes avant l'heure de début pour commencer à générer les caches
- `pitch_event` : Nombre de terrains (channels) pour l'événement
- `delay_event` : Secondes entre chaque génération de cache

**Logique** :
- Vérifie si l'événement existe déjà (UPDATE) ou crée nouveau (INSERT)
- Insère/met à jour dans `kp_event_worker_config`
- Statut : 'running', 'paused', 'stopped', 'error'

##### POST action=stop
Arrête un événement dans le worker

**Paramètres** :
```json
{
  "id_event": 123
}
```

Met le statut à 'stopped'.

##### POST action=pause
Met en pause un événement

##### POST action=resume
Reprend un événement en pause

##### POST action=update_config
Met à jour la configuration d'un événement en cours

**Tables** :
- `kp_event_worker_config` (nouvelle table pour configuration worker)

**Proposition Migration** :

Cette API est déjà bien structurée en REST ! Peut être intégrée telle quelle dans `/api/live/worker` ou migrée vers API2 :

```
GET  /api2/api/live/worker/events           (status)
POST /api2/api/live/worker/events           (start)
POST /api2/api/live/worker/events/{id}/stop
POST /api2/api/live/worker/events/{id}/pause
POST /api2/api/live/worker/events/{id}/resume
PATCH /api2/api/live/worker/events/{id}     (update_config)
```

**Fichier compagnon** : `event_worker.php` (238 lignes) - Script CLI exécuté par cron pour générer les caches automatiquement

---

### 3. 🗑️ Endpoint Supprimé

#### ImportPCE.php

**Fichier** : `sources/admin/ImportPCE.php` (**SUPPRIMÉ** - 200 lignes)
**Raison** : Probablement déprécié ou remplacé par une autre solution

**Action** : ✅ Retirer de la liste des endpoints à migrer

---

## 📊 Inventaire Mis à Jour

### Nouveaux Totaux

| Catégorie | Ancien Total | Modifications | Nouveau Total |
|-----------|--------------|---------------|---------------|
| **Team Management** | 4 | +2 (Copy, GetCompetitions) | **6** |
| **Live Broadcasting** | 7 | +1 (api_worker) | **8** |
| **Import** | 3 | -1 (ImportPCE supprimé) | **2** |
| **TOTAL GÉNÉRAL** | ~80 | +3 -1 = +2 | **~82** |

---

## 🎯 Impact sur le Plan de Migration

### Phase 1-2 : Fondations (MODIFIÉ ✨)

**Avant** : Créer l'infrastructure API2 de zéro
**Maintenant** : ✅ **API2 existe déjà !**

**Nouvelles tâches Phase 1-2** :
1. ✅ ~~Créer contrôleurs Symfony~~ → **Déjà fait**
2. ✅ ~~Configurer API Platform~~ → **Déjà fait**
3. ✅ ~~Setup CORS et authentification~~ → **Déjà fait**
4. 🆕 **Tester les endpoints API2 existants**
5. 🆕 **Documenter les endpoints API2 dans Swagger**
6. 🆕 **Créer des tests unitaires pour API2**
7. 🆕 **Ajouter les 3 nouveaux endpoints** :
   - `CopyTeamComposition` → API2 teams
   - `GetTeamCompetitions` → API2 teams
   - `api_worker` → API2 live/worker

**Gain de temps estimé** : **-4 semaines** (Phase 1 quasiment terminée !)

---

### Endpoints API2 Existants vs Plan Original

#### ✅ Déjà Implémentés dans API2

| Fonctionnalité | Endpoint API2 | Statut Plan Original |
|----------------|---------------|---------------------|
| **Events List** | `GET /api2/api/events/{mode}` | ✅ Phase 1 planifiée |
| **Single Event** | `GET /api2/api/event/{id}` | ✅ Phase 1 planifiée |
| **Games List** | `GET /api2/api/games/{eventId}` | ✅ Phase 3 planifiée |
| **Charts & Rankings** | `GET /api2/api/charts/{eventId}` | ✅ Phase 5 planifiée |
| **Team Stats** | `GET /api2/api/team-stats/{teamId}/{eventId}` | ✅ Phase 5 planifiée |
| **App Ratings** | `GET /api2/api/stars` | ✅ Phase 1 planifiée |
| **Submit Rating** | `POST /api2/api/rating` | ✅ Phase 1 planifiée |
| **Staff Scrutineering** | `GET /api2/api/staff/{token}/teams/{eventId}` | ✅ Phase 4 planifiée |
| **Game Report** | `GET /api2/api/report/{token}/game/{gameId}` | ✅ Phase 5 planifiée |
| **WSM Game Params** | `PUT /api2/api/wsm/gameParam/{matchId}` | ✅ Phase 3 planifiée |
| **WSM Game Events** | `PUT /api2/api/wsm/gameEvent/{matchId}` | ✅ Phase 3 planifiée |
| **WSM Timer** | `PUT /api2/api/wsm/gameTimer/{matchId}` | ✅ Phase 3 planifiée |
| **WSM Stats** | `PUT /api2/api/wsm/stats` | ✅ Phase 5 planifiée |

**Résultat** : ~13 endpoints déjà implémentés en API2 !

---

#### 🆕 À Ajouter à API2

Les endpoints suivants du plan original doivent encore être ajoutés :

##### Haute Priorité (Phase 2-3)

| Endpoint Plan Original | Proposition API2 | Complexité |
|------------------------|------------------|------------|
| `GET /api/autocomplete/players` | `GET /api2/api/autocomplete/players` | 🟢 Faible |
| `GET /api/autocomplete/teams` | `GET /api2/api/autocomplete/teams` | 🟢 Faible |
| `GET /api/autocomplete/clubs` | `GET /api2/api/autocomplete/clubs` | 🟢 Faible |
| `GET /api/autocomplete/competitions` | `GET /api2/api/autocomplete/competitions` | 🟢 Faible |
| `GET /api/autocomplete/referees` | `GET /api2/api/autocomplete/referees` | 🟢 Faible |
| `GET /api/autocomplete/officials` | `GET /api2/api/autocomplete/officials` | 🟢 Faible |
| `POST /api/match/{id}/timer` | `POST /api2/api/matches/{id}/timer` | 🟡 Moyen |
| `PATCH /api/match/{id}/timer` | `PATCH /api2/api/matches/{id}/timer` | 🟡 Moyen |
| `GET /api/match/{id}/players` | `GET /api2/api/matches/{id}/players` | 🟢 Faible |
| `POST /api/match/{id}/players` | `POST /api2/api/matches/{id}/players` | 🟡 Moyen |
| `DELETE /api/match/{id}/players/{matricule}` | `DELETE /api2/api/matches/{id}/players/{matricule}` | 🟢 Faible |
| `POST /api/team/{id}/copy-roster` | `POST /api2/api/teams/{id}/copy-roster` | 🟡 Moyen |
| `GET /api/team/{id}/history` | `GET /api2/api/teams/{id}/history` | 🟢 Faible |

##### Moyenne Priorité (Phase 4-5)

| Endpoint Plan Original | Proposition API2 | Complexité |
|------------------------|------------------|------------|
| `GET /api/export/stats/{eventId}` | `GET /api2/api/export/stats/{eventId}` | 🟡 Moyen |
| `GET /api/export/players/{teamId}` | `GET /api2/api/export/players/{teamId}` | 🟡 Moyen |
| `GET /api/export/referee-activity` | `GET /api2/api/export/referee-activity` | 🟡 Moyen |
| `GET /api/calendar/events` | `GET /api2/api/calendar/events` | 🟢 Faible |
| `GET /api/export/calendar/{journeeId}.ics` | `GET /api2/api/export/calendar/{journeeId}.ics` | 🟡 Moyen |
| `POST /api/import/players` | `POST /api2/api/import/players` | 🔴 Complexe |
| `GET /api/live/worker/events` | `GET /api2/api/live/worker/events` | 🟢 Faible (déjà codé) |
| `POST /api/live/worker/events` | `POST /api2/api/live/worker/events` | 🟢 Faible (déjà codé) |

---

## 🔄 Plan de Migration Révisé

### Phase 1 : Tests & Documentation API2 (Semaine 1) ⚡ PRIORITÉ IMMÉDIATE

**Objectif** : Valider et documenter l'API2 existante

#### Tâches :
1. **Tests des endpoints API2 existants**
   - Tester tous les contrôleurs (Public, Staff, WSM, Report, etc.)
   - Vérifier CORS et authentification
   - Valider les réponses JSON

2. **Documentation Swagger complète**
   - Compléter les annotations OpenAPI dans les contrôleurs
   - Générer la documentation Swagger à jour
   - Créer des exemples de requêtes/réponses

3. **Tests unitaires API2**
   - Créer tests PHPUnit pour chaque contrôleur
   - Tests d'intégration pour workflows complets
   - Tests de sécurité (CORS, auth, injection)

4. **Guide migration développeurs**
   - Exemples concrets de migration d'endpoints legacy → API2
   - Patterns de code réutilisables
   - Checklist de validation

**Livrables** :
- ✅ Tous les endpoints API2 testés et fonctionnels
- ✅ Documentation Swagger complète et à jour
- ✅ Suite de tests passants (coverage >80%)
- ✅ Guide de migration v1.0

---

### Phase 2 : Autocomplete Consolidation (Semaines 2-3) 🔥 IMPACT FORT

**Objectif** : Migrer les 19 endpoints autocomplete vers API2

#### Tâches :
1. **Créer AutocompleteController dans API2**
   ```php
   src/Controller/AutocompleteController.php
   ```
   - Endpoints : players, teams, clubs, competitions, referees, officials, cities, sessions
   - Requêtes SQL optimisées (indexes, LIMIT)
   - Pagination et filtres

2. **Migrer logique des fichiers legacy**
   - Extraire la logique SQL de chaque `Autocompl_*.php`
   - Normaliser les réponses JSON
   - Ajouter validation des paramètres (Symfony Validator)

3. **Wrappers de compatibilité**
   ```php
   // admin/Autocompl_joueur.php (wrapper legacy)
   <?php
   header('Location: /api2/api/autocomplete/players?' . http_build_query($_GET));
   exit;
   ```

4. **Migration côté client**
   - Mettre à jour les appels AJAX dans JavaScript legacy
   - Tests sur toutes les pages admin concernées
   - Validation UX (même comportement qu'avant)

**Impact clients** : ⚠️ **Haut** (admin jQuery)

**Stratégie** :
1. Déployer nouveaux endpoints en parallèle
2. Période de transition 1 mois (legacy + new actifs)
3. Logger les appels aux anciens endpoints
4. Migrer clients progressivement
5. Désactiver anciens endpoints après validation

---

### Phase 3 : Match Management (Semaines 4-7) 🔥 IMPACT CRITIQUE

**Objectif** : Migrer les endpoints de gestion de match vers API2

#### Tâches :
1. **Créer MatchController dans API2**
   ```php
   src/Controller/MatchController.php
   ```
   - CRUD matchs
   - Timer (start/stop/update)
   - Players (add/remove/update)
   - Events (goals, cards, penalties)

2. **Migration endpoints critiques**
   - `setChrono.php` → `POST /api2/api/matches/{id}/timer`
   - `ajax_updateChrono.php` → `PATCH /api2/api/matches/{id}/timer`
   - `evt_match.php` → `POST/PUT/DELETE /api2/api/matches/{id}/events`
   - `save*.php` → `PATCH /api2/api/matches/{id}/{resource}`

3. **Transactions SQL atomiques**
   - Doctrine Transactions pour modifications
   - Rollback automatique en cas d'erreur
   - Audit log des modifications

4. **Cache Redis (optionnel)**
   - Cache des données fréquemment lues
   - Invalidation automatique lors des updates
   - TTL configurables

5. **Tests intensifs**
   - Tests en pré-production
   - Scenarios complets de match
   - Load testing

**Impact clients** : ⚠️ **CRITIQUE** (WSM, App3, arbitrage)

**Stratégie** :
1. Tests exhaustifs en staging
2. Déploiement progressif par type de match (amicaux → championnats)
3. Rollback plan préparé
4. Monitoring temps réel des erreurs

---

### Phase 4 : Team Management (Semaines 8-9)

**Objectif** : Migrer les endpoints de gestion d'équipes

#### Nouveaux Endpoints à Ajouter :

1. **CopyTeamComposition** → `POST /api2/api/teams/{id}/copy-roster`
   ```php
   // src/Controller/TeamController.php
   #[Route('/api/teams/{id}/copy-roster', methods: ['POST'])]
   public function copyRoster(int $id, Request $request): JsonResponse
   ```

2. **GetTeamCompetitions** → `GET /api2/api/teams/{id}/history`
   ```php
   #[Route('/api/teams/{id}/history', methods: ['GET'])]
   public function getHistory(int $id, Request $request): JsonResponse
   ```

3. **UpdateTeam** → `PATCH /api2/api/teams/{id}`
   - Couleurs, logo équipe
   - Validation des données

**Impact** : Moyen (administration uniquement)

---

### Phase 5 : Live Worker Integration (Semaines 10-11) 🆕

**Objectif** : Intégrer `api_worker.php` dans API2

#### Tâches :
1. **Créer WorkerController dans API2**
   ```php
   src/Controller/LiveWorkerController.php
   ```

2. **Migration logique worker**
   - `api_worker.php` → endpoints REST API2
   - Table `kp_event_worker_config` déjà existante
   - Conserver le script CLI `event_worker.php`

3. **Endpoints** :
   ```
   GET  /api2/api/live/worker/events           (status)
   POST /api2/api/live/worker/events           (start)
   POST /api2/api/live/worker/events/{id}/stop
   POST /api2/api/live/worker/events/{id}/pause
   POST /api2/api/live/worker/events/{id}/resume
   PATCH /api2/api/live/worker/events/{id}     (update)
   ```

**Impact** : Moyen (opérations live)

---

### Phase 6 : Exports & Calendar (Semaines 12-13)

(Inchangé par rapport au plan original)

---

### Phase 7 : Imports & Utilities (Semaines 14-15)

**Modification** : Retirer `ImportPCE.php` (déjà supprimé)

---

### Phase 8 : Décommissionnement (Semaines 16-17)

(Inchangé par rapport au plan original)

---

## 📈 Métriques de Succès Mises à Jour

| Métrique | Objectif Original | Objectif Révisé | Statut Actuel |
|----------|------------------|-----------------|---------------|
| **Couverture migration** | 100% (80 endpoints) | 100% (82 endpoints) | ~16% (13/82 via API2 existante) |
| **Endpoints API2 créés** | ~30 endpoints | ~30 endpoints | ✅ 13 déjà créés |
| **Temps de réponse (P95)** | <100ms | <100ms | À mesurer |
| **Taux d'erreur** | <0.1% | <0.1% | À mesurer |
| **Réduction fichiers** | -70% | -70% | -16% actuel (13 endpoints consolidés) |
| **Timeline migration** | 18 semaines | **14 semaines** (-4 grâce à API2 existante) | Semaine 0 |

---

## 🎯 Actions Immédiates (Cette Semaine)

### 1. Valider l'API2 Existante ⚡
- [ ] Tester tous les endpoints API2 dans Postman/curl
- [ ] Vérifier la documentation Swagger : `https://kpi.localhost/api2/doc`
- [ ] Identifier les bugs ou endpoints incomplets
- [ ] Documenter les endpoints manquants

### 2. Analyser les Nouveaux Endpoints 🔍
- [ ] Lire le code de `CopyTeamComposition.php` en détail
- [ ] Lire le code de `GetTeamCompetitions.php` en détail
- [ ] Lire le code de `api_worker.php` en détail
- [ ] Identifier les dépendances et tables SQL utilisées

### 3. Créer des Tests API2 🧪
- [ ] Setup PHPUnit pour API2
- [ ] Créer tests pour PublicController
- [ ] Créer tests pour WsmController (critique)
- [ ] Créer tests pour StaffController

### 4. Mettre à Jour le Plan Principal 📝
- [ ] Modifier `AJAX_ENDPOINTS_CONSOLIDATION_PLAN.md` avec les découvertes
- [ ] Mettre à jour les phases et timelines
- [ ] Réviser les priorités

---

## 🔗 Ressources

- **Plan Original** : [AJAX_ENDPOINTS_CONSOLIDATION_PLAN.md](AJAX_ENDPOINTS_CONSOLIDATION_PLAN.md)
- **Synthèse** : [AJAX_CONSOLIDATION_SUMMARY.md](AJAX_CONSOLIDATION_SUMMARY.md)
- **API2 README** : `sources/api2/README.md`
- **API2 Endpoints** : `sources/api2/API_ENDPOINTS.md`
- **Swagger Setup** : `sources/api2/SWAGGER_SETUP.md`
- **Worker Documentation** : `sources/live/EVENT_WORKER_README.md`
- **Worker Quick Start** : `sources/live/QUICK_START.md`

---

## 📊 Tableau de Bord des Endpoints

### Statut Consolidation

| Catégorie | Total Endpoints | API2 Existants | À Migrer | % Complété |
|-----------|----------------|----------------|----------|------------|
| **Public API** | 7 | 7 | 0 | ✅ 100% |
| **Autocomplete** | 19 | 0 | 19 | ⏳ 0% |
| **Match Management** | 15 | 4 (WSM) | 11 | ⏳ 27% |
| **Team Management** | 6 | 0 | 6 | ⏳ 0% |
| **Staff/Scrutineering** | 4 | 4 | 0 | ✅ 100% |
| **Reports** | 1 | 1 | 0 | ✅ 100% |
| **Exports** | 5 | 0 | 5 | ⏳ 0% |
| **Calendar** | 3 | 0 | 3 | ⏳ 0% |
| **Live Broadcasting** | 8 | 0 | 8 | ⏳ 0% |
| **Imports** | 2 | 0 | 2 | ⏳ 0% |
| **Status Updates** | 4 | 0 | 4 | ⏳ 0% |
| **Utilities** | 4 | 0 | 4 | ⏳ 0% |
| **Connector** | 4 | 0 | 4 | ⏳ 0% |
| **TOTAL** | **82** | **16** | **66** | **19.5%** |

---

## 🎉 Conclusion

L'existence de l'API2 dans `develop` est une **excellente nouvelle** ! Cela signifie que :

1. ✅ **16% de la migration est déjà faite** (13 endpoints publics + staff + WSM de base)
2. ✅ **L'infrastructure est prête** (Symfony, API Platform, CORS, Doctrine)
3. ✅ **Gain de 4 semaines** sur le planning (Phase 1 quasi-terminée)
4. 🆕 **3 nouveaux endpoints identifiés** à ajouter au plan
5. 🗑️ **1 endpoint legacy supprimé** (ImportPCE.php)

**Prochaine étape** : Tester l'API2 existante et commencer la migration des autocomplete (Phase 2).

---

**Dernière mise à jour** : 2025-11-22
**Version** : 1.0
**Auteur** : Claude Code (analyse automatique branche develop)
