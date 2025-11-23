# Plan d'Action - Consolidation des Endpoints AJAX

## 📋 Vue d'Ensemble

**Objectif** : Consolider ~80 endpoints AJAX autonomes dans les API structurées `/api` et `/api2` pour améliorer la maintenabilité, réduire la duplication, normaliser les routes et optimiser les performances.

**Principes directeurs** :
1. ✅ **Impact minimal** sur les clients existants (applications Vue/Nuxt, JavaScript legacy)
2. 🔄 **Migration progressive** avec période de double support (legacy + new endpoints)
3. 📐 **Routes normalisées** suivant les conventions REST
4. 🔍 **Détection et élimination des doublons**
5. ⚡ **Optimisation** des requêtes SQL et de la logique métier
6. 🔐 **Sécurité renforcée** via authentification centralisée

---

## 📊 Inventaire Complet : 80+ Endpoints AJAX Identifiés

### Répartition par Catégorie Fonctionnelle

| Catégorie | Nombre | Localisation Principale |
|-----------|--------|------------------------|
| **Autocomplete/Search** | 19 | `admin/Autocompl_*.php`, `sources/search*.php` |
| **Match Management** | 15 | `admin/v2/` (get*, set*, save*, evt_match.php) |
| **Status Updates** | 4 | `admin/v2/Statut*.php` |
| **CSV/Export** | 5 | `admin/csv_*.php`, `admin/export_*.php` |
| **Live Broadcasting** | 7 | `live/ajax_*.php` |
| **Data Sync/Connector** | 4 | `connector/` (get_evenement, set_evenement, etc.) |
| **Player/Team Management** | 4 | `admin/ajax_update_team.php`, `admin/v2/delJoueur.php`, etc. |
| **Initialization/Setup** | 3 | `admin/InitTitulaireJQ.php`, `admin/UpdateCellJQ.php`, etc. |
| **API Data Export** | 2 | `sources/api_players.php`, `sources/api_stats.php` |
| **Calendar/Events** | 3 | `sources/json-events.php`, `sources/json-clubs.php`, `sources/upload_ics.php` |
| **Utilities** | 2 | `sources/lang.php`, `connector/ajax_md5.php` |
| **XML Import** | 2 | `admin/xml_icf_import.php`, `admin/xmlparser.php` |

**Total** : ~80 endpoints AJAX autonomes

---

## 🎯 Analyse des Doublons et Optimisations

### 1. Autocomplete - 7 Doublons Identifiés

| Endpoint Actuel | Variantes | Proposition Consolidée |
|----------------|-----------|------------------------|
| `Autocompl_joueur.php` | v1, v2, v3 (3 fichiers) | **`GET /api/autocomplete/players`** |
| `Autocompl_club.php` | v1, v2 (2 fichiers) | **`GET /api/autocomplete/clubs`** |
| `Autocompl_compet.php` | v1, v2 (2 fichiers) | **`GET /api/autocomplete/competitions`** |
| `Autocompl_arb.php` | v1, v3 (2 fichiers) | **`GET /api/autocomplete/referees`** |

**Paramètres normalisés** :
```json
{
  "q": "search query",
  "limit": 10,
  "season": "2024-2025",  // optionnel pour competitions
  "format": "json"        // toujours JSON dans nouvelle API
}
```

**Gain** : 13 fichiers → 4 endpoints consolidés (-69% fichiers)

---

### 2. Chrono/Timer - 2 Doublons Similaires

| Endpoint Actuel | Fonction | Proposition |
|----------------|----------|-------------|
| `setChrono.php` | Création/démarrage chrono complet | **`POST /api/match/{matchId}/timer`** (create) |
| `ajax_updateChrono.php` | Mise à jour rapide (run_time, penalties) | **`PATCH /api/match/{matchId}/timer`** (update) |

**Optimisation** : Endpoint unifié avec distinction POST (création) vs PATCH (update partiel)

---

### 3. Event Import - 2 Fichiers Identiques

| Endpoint Actuel | Différence | Proposition |
|----------------|------------|-------------|
| `set_evenement.php` | Version originale | **`POST /api/connector/events`** |
| `set_evenement2.php` | Alternative (quasi-identique) | ❌ **Supprimer** (rediriger vers v1) |

**Action** : Analyser les différences, consolider la meilleure logique, supprimer le doublon

---

### 4. CSV Exports - Multiples Fichiers Similaires

| Endpoint Actuel | Fonction | Proposition |
|----------------|----------|-------------|
| `csv_stats_export.php` | Export stats événement | **`GET /api/export/stats/{eventId}?format=csv`** |
| `export_stats_csv.php` | Export stats (similaire) | ⚠️ **Vérifier doublon** puis consolider |
| `csv_activite_arbitres.php` | Export activité arbitres | **`GET /api/export/referee-activity?format=csv`** |
| `csv_player_list.php` | Export liste joueurs | **`GET /api/export/players/{teamId}?format=csv`** |

**Optimisation** : Un seul endpoint `/api/export/{resource}` avec paramètre `format` (csv/json/xlsx)

---

## 🏗️ Structure Cible des API

### Architecture Proposée

```
/api/
├── autocomplete/          [Nouveau groupe]
│   ├── GET /players
│   ├── GET /teams
│   ├── GET /clubs
│   ├── GET /competitions
│   ├── GET /referees
│   ├── GET /officials
│   ├── GET /cities
│   └── GET /sessions
│
├── match/                 [Nouveau groupe]
│   ├── GET    /{matchId}
│   ├── PATCH  /{matchId}                    (update fields)
│   ├── GET    /{matchId}/players
│   ├── POST   /{matchId}/players            (add player)
│   ├── DELETE /{matchId}/players/{matricule}
│   ├── PATCH  /{matchId}/players/{matricule} (status, number)
│   ├── GET    /{matchId}/events
│   ├── POST   /{matchId}/events             (add goal/card)
│   ├── PUT    /{matchId}/events/{eventId}   (update)
│   ├── DELETE /{matchId}/events/{eventId}
│   ├── GET    /{matchId}/timer
│   ├── POST   /{matchId}/timer              (start)
│   ├── PATCH  /{matchId}/timer              (update)
│   ├── DELETE /{matchId}/timer              (reset)
│   └── PATCH  /{matchId}/officials          (save refs, timekeepers)
│
├── team/                  [Nouveau groupe]
│   ├── GET    /{teamId}
│   ├── PATCH  /{teamId}                     (colors, logo)
│   ├── GET    /{teamId}/roster              (composition)
│   └── POST   /{teamId}/initialize-starters
│
├── competition/           [Extension du groupe existant]
│   ├── PATCH  /{competId}/status
│   └── PATCH  /{competId}/lock
│
├── journee/               [Nouveau groupe]
│   ├── GET    /{journeeId}/teams
│   ├── PATCH  /{journeeId}/status
│   └── POST   /{journeeId}/events           (link event)
│
├── export/                [Nouveau groupe]
│   ├── GET /stats/{eventId}                 (?format=csv|json|xlsx)
│   ├── GET /players/{teamId}
│   ├── GET /referee-activity
│   └── GET /calendar/{journeeId}.ics
│
├── search/                [Consolidation search]
│   ├── GET /teams
│   └── GET /clubs
│
├── connector/             [Migration depuis /connector/]
│   ├── GET  /events                         (get_evenement.php)
│   ├── POST /events                         (set_evenement.php)
│   └── POST /events/confirm                 (ajax_okevent.php)
│
├── live/                  [Migration depuis /live/]
│   ├── GET   /timer                         (get_sec.php)
│   ├── GET   /stream/{channel}              (ajax_refresh_voie.php)
│   ├── POST  /stream/{channel}              (ajax_change_tv.php)
│   ├── POST  /cache/match                   (ajax_cache_pitch.php)
│   └── GET   /next-scene/{channel}          (ajax_refresh_scene.php)
│
├── import/                [Nouveau groupe]
│   ├── POST /players/csv                    (csv_icf_import.php)
│   └── POST /players/xml                    (xml_icf_import.php)
│
└── utility/               [Utilitaires]
    ├── POST /language                       (lang.php → redirection)
    └── GET  /md5                            (ajax_md5.php)
```

### API2 (Symfony) - Endpoints Prioritaires

Les endpoints à haute valeur ajoutée migreront vers **`/api2`** (Symfony + API Platform) :

```
/api2/api/
├── matches/                               [Resource REST complète]
│   ├── GET    /matches                    (collection)
│   ├── GET    /matches/{id}               (item)
│   ├── PATCH  /matches/{id}
│   ├── GET    /matches/{id}/events
│   ├── POST   /matches/{id}/events
│   └── GET    /matches/{id}/players
│
├── teams/                                 [Resource REST]
│   ├── GET    /teams
│   ├── GET    /teams/{id}
│   └── PATCH  /teams/{id}
│
├── competitions/                          [Resource REST]
│   ├── GET    /competitions
│   ├── GET    /competitions/{id}
│   └── PATCH  /competitions/{id}
│
└── players/                               [Resource REST]
    ├── GET    /players
    ├── GET    /players/{id}
    └── GET    /players/{id}/statistics
```

**Avantages API2** :
- Documentation OpenAPI automatique
- Validation de schéma automatique (JSON Schema)
- Pagination standardisée
- Filtres/tri automatiques
- Gestion d'erreurs normalisée (RFC 7807 Problem Details)

---

## 📋 Mapping Détaillé : Ancien → Nouveau

### Catégorie 1 : AUTOCOMPLETE (19 endpoints → 8 consolidés)

| Fichier Actuel | Route Legacy | Nouvelle Route API | Nouvelle Route API2 | Statut Doublon |
|----------------|--------------|-------------------|-------------------|----------------|
| `Autocompl_joueur.php` | `/admin/Autocompl_joueur.php?q=` | `GET /api/autocomplete/players` | `GET /api2/api/autocomplete/players` | ✅ Consolidé (v1+v2+v3) |
| `Autocompl_joueur2.php` | `/admin/Autocompl_joueur2.php?q=` | ↑ **Idem** | ↑ **Idem** | 🔄 Redirection |
| `Autocompl_joueur3.php` | `/admin/Autocompl_joueur3.php?q=` | ↑ **Idem** | ↑ **Idem** | 🔄 Redirection |
| `Autocompl_equipe.php` | `/admin/Autocompl_equipe.php?q=` | `GET /api/autocomplete/teams` | `GET /api2/api/autocomplete/teams` | ✅ Unique |
| `Autocompl_club.php` | `/admin/Autocompl_club.php?q=` | `GET /api/autocomplete/clubs` | `GET /api2/api/autocomplete/clubs` | ✅ Consolidé (v1+v2) |
| `Autocompl_club2.php` | `/admin/Autocompl_club2.php?q=` | ↑ **Idem** | ↑ **Idem** | 🔄 Redirection |
| `Autocompl_compet.php` | `/admin/Autocompl_compet.php?q=` | `GET /api/autocomplete/competitions` | `GET /api2/api/autocomplete/competitions` | ✅ Consolidé (v1+v2) |
| `Autocompl_compet2.php` | `/admin/Autocompl_compet2.php?q=` | ↑ **Idem** | ↑ **Idem** | 🔄 Redirection |
| `Autocompl_refJournee.php` | `/admin/Autocompl_refJournee.php?q=` | `GET /api/autocomplete/sessions` | `GET /api2/api/autocomplete/sessions` | ✅ Unique |
| `Autocompl_ville.php` | `/admin/Autocompl_ville.php?q=` | `GET /api/autocomplete/cities` | `GET /api2/api/autocomplete/cities` | ✅ Unique |
| `Autocompl_arb.php` | `/admin/Autocompl_arb.php?q=` | `GET /api/autocomplete/referees` | `GET /api2/api/autocomplete/referees` | ✅ Consolidé (v1+v3) |
| `Autocompl_arb3.php` | `/admin/Autocompl_arb3.php?q=` | ↑ **Idem** | ↑ **Idem** | 🔄 Redirection |
| `Autocompl_getCompo.php` | `/admin/Autocompl_getCompo.php?q=` | `GET /api/team/{teamId}/roster` | `GET /api2/api/teams/{id}/roster` | ✅ Unique |
| `Autocompl_session_journee.php` | `/admin/Autocompl_session_journee.php?q=` | `GET /api/autocomplete/sessions` | *(fusion avec refJournee)* | 🔄 Fusion doublon |
| `autocompleteOfficiel.php` | `/admin/v2/autocompleteOfficiel.php?term=` | `GET /api/autocomplete/officials` | `GET /api2/api/autocomplete/officials` | ✅ Unique |
| `searchClubs.php` | `/sources/searchClubs.php?q=` | `GET /api/search/clubs` | `GET /api2/api/search/clubs` | ⚠️ Différent (full search avec coords) |
| `searchEquipes.php` | `/sources/searchEquipes.php?q=` | `GET /api/search/teams` | `GET /api2/api/search/teams` | ⚠️ Différent (full search détails) |
| `ajax_search_equipe.php` | `/sources/ajax_search_equipe.php?q=` | `GET /api/search/teams` | *(fusion avec searchEquipes)* | 🔄 Fusion |

**Paramètres normalisés** :
```json
GET /api/autocomplete/{resource}?q={query}&limit=10&season=2024-2025
```

**Migration client** :
```javascript
// Avant
fetch('/admin/Autocompl_joueur.php?q=dupont&format=json')

// Après (avec backward compatibility via query params)
fetch('/api/autocomplete/players?q=dupont&limit=10')
```

---

### Catégorie 2 : MATCH MANAGEMENT (15 endpoints → 7 groupes REST)

#### 2.1 Data Retrieval (GET operations)

| Fichier Actuel | Route Legacy | Nouvelle Route API | Méthode | Notes |
|----------------|--------------|-------------------|---------|-------|
| `getEquipesMatch.php` | `/admin/v2/getEquipesMatch.php?idJournee=` | `GET /api/journee/{journeeId}/teams` | GET | Récupère équipes d'une journée |
| `getChrono.php` | `/admin/v2/getChrono.php?idMatch=` | `GET /api/match/{matchId}/timer` | GET | Récupère état du chronomètre |
| `getNextGame.php` | `/admin/v2/getNextGame.php?idMatch=` | `GET /api/match/{matchId}/next-game` | GET | Prochain match sur terrain |
| `getShortGame.php` | `/admin/v2/getShortGame.php?idMatch=&numTarget=` | `GET /api/match/by-number?journee={id}&number={num}` | GET | Recherche match par numéro |
| `get_sec.php` | `/live/get_sec.php` | `GET /api/live/timer` | GET | Secondes serveur (pour sync) |
| `get_evenement.php` | `/connector/get_evenement.php?lst=` | `GET /api/connector/events?ids={list}` | GET | Export JSON complexe événements |

#### 2.2 Match Updates (SET/POST/PATCH operations)

| Fichier Actuel | Route Legacy | Nouvelle Route API | Méthode | Notes |
|----------------|--------------|-------------------|---------|-------|
| `setChrono.php` | `POST /admin/v2/setChrono.php` | `POST /api/match/{matchId}/timer` | POST | Créer/démarrer chrono |
| `ajax_updateChrono.php` | `POST /admin/v2/ajax_updateChrono.php` | `PATCH /api/match/{matchId}/timer` | PATCH | Mise à jour partielle chrono |
| `setEquipesMatch.php` | `POST /admin/v2/setEquipesMatch.php` | `PATCH /api/match/{matchId}/teams` | PATCH | Assigner équipes au match |
| `setPhaseMatch.php` | `POST /admin/v2/setPhaseMatch.php` | `PATCH /api/match/{matchId}/phase` | PATCH | Définir phase/journée |
| `set_evenement.php` | `POST /connector/set_evenement.php` | `POST /api/connector/events` | POST | Import JSON événement |
| `set_evenement2.php` | `POST /connector/set_evenement2.php` | ↑ **Idem** (fusion) | POST | 🔄 **Doublon à supprimer** |
| `setEvenementJournee.php` | `POST /admin/v2/setEvenementJournee.php` | `POST /api/journee/{journeeId}/events` | POST | Lier événement à journée |

#### 2.3 Match Events (Goals, Cards, Penalties)

| Fichier Actuel | Route Legacy | Nouvelle Route API | Méthode | Notes |
|----------------|--------------|-------------------|---------|-------|
| `evt_match.php` | `POST /admin/v2/evt_match.php` | `POST /api/match/{matchId}/events` | POST | Insérer événement |
| `evt_match.php` | `PUT /admin/v2/evt_match.php` | `PUT /api/match/{matchId}/events/{eventId}` | PUT | Modifier événement |
| `evt_match.php` | `DELETE /admin/v2/evt_match.php` | `DELETE /api/match/{matchId}/events/{eventId}` | DELETE | Supprimer événement |

**Structure JSON unifiée** :
```json
// POST /api/match/{matchId}/events
{
  "period": 1,
  "gameTime": "10:30",
  "type": "goal|yellow_card|red_card|definitive_red|green_card",
  "player": "123456",
  "playerNumber": 5,
  "team": "A",
  "reason": "optional text"
}
```

#### 2.4 Match Details Save Operations

| Fichier Actuel | Route Legacy | Nouvelle Route API | Méthode | Champ Modifié |
|----------------|--------------|-------------------|---------|---------------|
| `saveComments.php` | `POST /admin/v2/saveComments.php` | `PATCH /api/match/{matchId}` | PATCH | `{"comments": "text"}` |
| `saveOfficiel.php` | `POST /admin/v2/saveOfficiel.php` | `PATCH /api/match/{matchId}/officials` | PATCH | `{"role": "value"}` |
| `saveStatut.php` | `POST /admin/v2/saveStatut.php` | `PATCH /api/match/{matchId}/players/{matricule}` | PATCH | `{"status": "C"}` |
| `saveArbitres.php` | `POST /admin/v2/saveArbitres.php` | `PATCH /api/match/{matchId}/officials` | PATCH | `{"referee_primary": "name"}` |
| `saveNo.php` | `POST /admin/v2/saveNo.php` | `PATCH /api/match/{matchId}/players/{matricule}` | PATCH | `{"number": 5}` |

**Consolidation** : Tous les endpoints `save*.php` deviennent des `PATCH /api/match/{matchId}/{resource}`

---

### Catégorie 3 : STATUS UPDATES (4 endpoints → 4 endpoints normalisés)

| Fichier Actuel | Route Legacy | Nouvelle Route API | Méthode | Paramètres |
|----------------|--------------|-------------------|---------|-----------|
| `StatutCompet.php` | `POST /admin/v2/StatutCompet.php` | `PATCH /api/competition/{competId}/status` | PATCH | `{"field": "status|lock|publication", "value": "..."}` |
| `StatutJournee.php` | `POST /admin/v2/StatutJournee.php` | `PATCH /api/journee/{journeeId}/status` | PATCH | `{"field": "publication|type", "value": "..."}` |
| `StatutPeriode.php` | `POST /admin/v2/StatutPeriode.php` | `PATCH /api/match/{matchId}/status` | PATCH | `{"field": "score|status|period|...", "value": "..."}` |
| `StatutSession.php` | `POST /admin/v2/StatutSession.php` | `POST /api/session/filters` | POST | `{"filter": "type", "value": "..."}` |

---

### Catégorie 4 : PLAYER/TEAM OPERATIONS (4 endpoints)

| Fichier Actuel | Route Legacy | Nouvelle Route API | Méthode | Notes |
|----------------|--------------|-------------------|---------|-------|
| `delJoueur.php` | `POST /admin/v2/delJoueur.php` | `DELETE /api/match/{matchId}/players/{matricule}` | DELETE | Supprimer joueur du match |
| `initPresents.php` | `POST /admin/v2/initPresents.php` | `POST /api/match/{matchId}/players/initialize` | POST | Initialiser présents depuis roster |
| `ajax_update_team.php` | `POST /admin/ajax_update_team.php` | `PATCH /api/team/{teamId}` | PATCH | Couleurs, logo équipe |
| `InitTitulaireJQ.php` | `POST /admin/InitTitulaireJQ.php` | `POST /api/team/{teamId}/initialize-starters` | POST | Initialiser titulaires |

---

### Catégorie 5 : CSV/EXPORT (5 endpoints → 4 consolidés)

| Fichier Actuel | Route Legacy | Nouvelle Route API | Format | Notes |
|----------------|--------------|-------------------|--------|-------|
| `csv_icf_import.php` | `POST /admin/csv_icf_import.php` | `POST /api/import/players?format=csv` | CSV | Import joueurs ICF |
| `csv_stats_export.php` | `GET /admin/csv_stats_export.php?evt=` | `GET /api/export/stats/{eventId}?format=csv` | CSV | Export stats événement |
| `export_stats_csv.php` | `GET /admin/export_stats_csv.php` | ↑ **Idem** (fusion) | CSV | 🔄 **Doublon probable** |
| `csv_activite_arbitres.php` | `GET /admin/csv_activite_arbitres.php` | `GET /api/export/referee-activity?format=csv` | CSV | Activité arbitres |
| `csv_player_list.php` | `GET /admin/csv_player_list.php` | `GET /api/export/players/{teamId}?format=csv` | CSV | Liste joueurs équipe |

**Optimisation** : Format via query param `?format=csv|json|xlsx` pour un seul endpoint multi-format

---

### Catégorie 6 : LIVE BROADCASTING (7 endpoints → 5 consolidés)

| Fichier Actuel | Route Legacy | Nouvelle Route API | Méthode | Notes |
|----------------|--------------|-------------------|---------|-------|
| `ajax_refresh_voie.php` | `GET /live/ajax_refresh_voie.php?voie=` | `GET /api/live/stream/{channel}` | GET | Récupérer URL stream |
| `ajax_change_tv.php` | `POST /live/ajax_change_tv.php` | `POST /api/live/stream/{channel}` | POST | Changer URL stream |
| `ajax_refresh_tv.php` | `GET /live/ajax_refresh_tv.php` | *(fusion avec refresh_voie)* | GET | 🔄 Fusion |
| `ajax_change_voie.php` | `POST /live/ajax_change_voie.php` | *(fusion avec change_tv)* | POST | 🔄 Fusion |
| `ajax_cache_pitch.php` | `POST /live/ajax_cache_pitch.php` | `POST /api/live/cache/match` | POST | Cache données match |
| `ajax_cache_event.php` | `POST /live/ajax_cache_event.php` | `POST /api/live/cache/event` | POST | Cache données événement |
| `ajax_refresh_scene.php` | `GET /live/ajax_refresh_scene.php?voie=` | `GET /api/live/next-scene/{channel}` | GET | Prochaine scène |

---

### Catégorie 7 : DATA EXPORT API (2 endpoints → intégration dans /api/export)

| Fichier Actuel | Route Legacy | Nouvelle Route API | Notes |
|----------------|--------------|-------------------|-------|
| `api_players.php` | `GET /sources/api_players.php?saison=&competitions=` | `GET /api/export/players?season={s}&competitions={c}` | Export joueurs compétition |
| `api_stats.php` | `GET /sources/api_stats.php?saison=&competitions=` | `GET /api/export/statistics?season={s}&competitions={c}` | Export statistiques joueurs |

---

### Catégorie 8 : CALENDAR/EVENTS (3 endpoints)

| Fichier Actuel | Route Legacy | Nouvelle Route API | Format | Notes |
|----------------|--------------|-------------------|--------|-------|
| `json-events.php` | `GET /sources/json-events.php?start=&end=` | `GET /api/calendar/events?start={date}&end={date}` | JSON | Événements FullCalendar |
| `json-clubs.php` | `GET /sources/json-clubs.php` | `GET /api/geo/clubs` | JSON | Clubs avec coordonnées |
| `upload_ics.php` | `GET /sources/upload_ics.php?J=` | `GET /api/export/calendar/{journeeId}.ics` | ICS | Calendrier ICS |

---

### Catégorie 9 : UTILITIES (2 endpoints)

| Fichier Actuel | Route Legacy | Nouvelle Route API | Notes |
|----------------|--------------|-------------------|-------|
| `lang.php` | `GET /sources/lang.php?lang=&p=` | `POST /api/user/language` | Changement langue (ou garder côté client) |
| `ajax_md5.php` | `GET /connector/ajax_md5.php?user=&pwd=` | `POST /api/auth/hash` | Helper MD5 (⚠️ déprécié, utiliser bcrypt) |

---

### Catégorie 10 : GENERIC UPDATE (1 endpoint critique)

| Fichier Actuel | Route Legacy | Nouvelle Route API | Notes |
|----------------|--------------|-------------------|-------|
| `UpdateCellJQ.php` | `POST /admin/UpdateCellJQ.php` | ❌ **À remplacer par endpoints spécifiques** | Trop générique, risque sécurité |

**Action** : Ce fichier permet de modifier n'importe quelle cellule via whitelist. Il doit être décomposé en endpoints REST spécifiques pour chaque ressource.

---

### Catégorie 11 : LOCK/VERROU (1 endpoint)

| Fichier Actuel | Route Legacy | Nouvelle Route API | Méthode |
|----------------|--------------|-------------------|---------|
| `VerrouCompetJQ.php` | `POST /admin/VerrouCompetJQ.php?compet=&verrou=` | `PATCH /api/competition/{competId}/lock` | PATCH |

**Payload** :
```json
{
  "locked": true
}
```

---

### Catégorie 12 : XML IMPORT (2 endpoints)

| Fichier Actuel | Route Legacy | Nouvelle Route API | Format |
|----------------|--------------|-------------------|--------|
| `xml_icf_import.php` | `POST /admin/xml_icf_import.php` | `POST /api/import/players?format=xml` | XML |
| `xmlparser.php` | *(helper)* | *(intégré dans endpoint import)* | - |

---

## 📅 Plan de Migration par Phases

### Phase 1 : Fondations (Semaines 1-2) ⚡ PRIORITÉ HAUTE

**Objectif** : Créer l'infrastructure de base dans `/api` et `/api2`

#### Tâches :
1. **Créer la structure des contrôleurs dans `/api`** :
   - `config/autocompleteRouter.php`
   - `controllers/autocompleteControllers.php`
   - `controllers/matchControllers.php`
   - `controllers/exportControllers.php`

2. **Créer les contrôleurs Symfony dans `/api2`** :
   - `src/Controller/AutocompleteController.php`
   - `src/Controller/MatchController.php`
   - `src/Controller/ExportController.php`

3. **Implémenter l'authentification centralisée** :
   - Middleware de validation de session
   - Token authentication pour API2
   - CORS configuration

4. **Tests d'intégration** :
   - Tests unitaires pour chaque contrôleur
   - Tests d'authentification

**Livrables** :
- ✅ Infrastructure API prête
- ✅ Documentation OpenAPI mise à jour
- ✅ Tests passants

---

### Phase 2 : Migration Autocomplete (Semaines 3-4) 🔥 IMPACT FORT

**Objectif** : Consolider les 19 endpoints autocomplete en 8 endpoints normalisés

#### Tâches :
1. **Implémenter les 8 endpoints consolidés** :
   ```
   GET /api/autocomplete/players
   GET /api/autocomplete/teams
   GET /api/autocomplete/clubs
   GET /api/autocomplete/competitions
   GET /api/autocomplete/referees
   GET /api/autocomplete/officials
   GET /api/autocomplete/cities
   GET /api/autocomplete/sessions
   ```

2. **Optimisation SQL** :
   - Utiliser des requêtes préparées avec indexes
   - Limiter les résultats par défaut (10-20)
   - Ajouter pagination si nécessaire

3. **Créer des wrappers de compatibilité** :
   ```php
   // admin/Autocompl_joueur.php (version legacy wrapper)
   <?php
   // Redirection vers nouvelle API avec backward compatibility
   header('Location: /api/autocomplete/players?' . http_build_query($_GET));
   exit;
   ```

4. **Migration côté client** :
   - Créer un guide de migration pour les développeurs frontend
   - Exemples de code avant/après
   - Helpers JavaScript pour transition

**Impact clients** :
- ⚠️ **Haut** : Applications d'administration (Vue.js legacy, JQuery)
- ⚠️ **Moyen** : App2/App3 (Nuxt) si utilisent ces endpoints

**Stratégie de déploiement** :
1. Déployer nouveaux endpoints en parallèle
2. Garder anciens endpoints actifs pendant 1 mois
3. Logger les appels aux anciens endpoints (analytics)
4. Migrer clients progressivement
5. Désactiver anciens endpoints après validation

---

### Phase 3 : Migration Match Management (Semaines 5-8) 🔥 IMPACT CRITIQUE

**Objectif** : Consolider les 15 endpoints de gestion de match en API REST cohérente

#### Tâches :
1. **Groupe Match Core** :
   ```
   GET    /api/match/{matchId}
   PATCH  /api/match/{matchId}
   GET    /api/match/{matchId}/timer
   POST   /api/match/{matchId}/timer
   PATCH  /api/match/{matchId}/timer
   ```

2. **Groupe Match Events** :
   ```
   GET    /api/match/{matchId}/events
   POST   /api/match/{matchId}/events
   PUT    /api/match/{matchId}/events/{eventId}
   DELETE /api/match/{matchId}/events/{eventId}
   ```

3. **Groupe Match Players** :
   ```
   GET    /api/match/{matchId}/players
   POST   /api/match/{matchId}/players
   DELETE /api/match/{matchId}/players/{matricule}
   PATCH  /api/match/{matchId}/players/{matricule}
   ```

4. **Optimisations** :
   - Transactions SQL pour modifications atomiques
   - Validation des données (Symfony Validator)
   - Cache Redis pour données fréquemment lues
   - WebSocket pour updates temps réel (optionnel)

5. **Migration des endpoints `save*.php`** :
   - Tous deviennent `PATCH /api/match/{matchId}/{resource}`
   - Validation centralisée des permissions
   - Audit log des modifications

**Impact clients** :
- ⚠️ **CRITIQUE** : Interface d'arbitrage en live
- ⚠️ **CRITIQUE** : WSM (Web Score Management)
- ⚠️ **HAUT** : Interface d'administration matches

**Stratégie** :
1. Tests intensifs en pré-production
2. Déploiement progressif par type de match (amicaux → championnats)
3. Rollback plan préparé
4. Monitoring temps réel des erreurs

---

### Phase 4 : Migration Status Updates & Team Management (Semaines 9-10)

**Objectif** : Normaliser les updates de statut et gestion d'équipes

#### Endpoints :
```
PATCH /api/competition/{competId}/status
PATCH /api/journee/{journeeId}/status
PATCH /api/match/{matchId}/status
PATCH /api/team/{teamId}
POST  /api/team/{teamId}/initialize-starters
```

**Impact** : Moyen (administration uniquement)

---

### Phase 5 : Migration Exports & Calendar (Semaines 11-12)

**Objectif** : Consolider les exports CSV/JSON/ICS

#### Endpoints :
```
GET /api/export/stats/{eventId}?format=csv|json|xlsx
GET /api/export/players/{teamId}?format=csv|json
GET /api/export/referee-activity?format=csv
GET /api/export/calendar/{journeeId}.ics
GET /api/calendar/events?start={date}&end={date}
```

**Optimisations** :
- Génération asynchrone pour gros exports
- Cache des exports fréquents
- Compression gzip automatique

**Impact** : Faible (exports ponctuels)

---

### Phase 6 : Migration Live Broadcasting (Semaines 13-14)

**Objectif** : Moderniser les endpoints de diffusion live

#### Endpoints :
```
GET  /api/live/stream/{channel}
POST /api/live/stream/{channel}
POST /api/live/cache/match
GET  /api/live/next-scene/{channel}
```

**Impact** : Moyen (diffusion événements)

---

### Phase 7 : Migration Imports & Utilities (Semaines 15-16)

**Objectif** : Finaliser les endpoints restants

#### Endpoints :
```
POST /api/import/players?format=csv|xml
POST /api/connector/events
GET  /api/connector/events?ids={list}
GET  /api/geo/clubs
```

**Impact** : Faible (opérations ponctuelles)

---

### Phase 8 : Décommissionnement & Nettoyage (Semaines 17-18)

**Objectif** : Supprimer les anciens endpoints après migration complète

#### Tâches :
1. **Vérification** :
   - Analytics : vérifier que les anciens endpoints ne sont plus appelés
   - Tests E2E : valider toutes les fonctionnalités avec nouveaux endpoints

2. **Décommissionnement progressif** :
   - Mettre warnings HTTP `Deprecated` sur anciens endpoints
   - Rediriger automatiquement vers nouveaux endpoints (302)
   - Après 1 mois, retourner erreur 410 Gone

3. **Nettoyage du code** :
   - Supprimer les fichiers PHP autonomes
   - Nettoyer les routes inutilisées
   - Mise à jour documentation

4. **Archivage** :
   - Créer branche Git `archive/ajax-endpoints-legacy`
   - Documenter l'historique de migration

---

## 🔒 Considérations de Sécurité

### Vulnérabilités Actuelles à Corriger

1. **UpdateCellJQ.php - Modification générique dangereuse** :
   - ⚠️ Permet modification de n'importe quelle table/colonne via whitelist
   - ✅ **Solution** : Remplacer par endpoints REST spécifiques avec validation forte

2. **Authentication incohérente** :
   - ⚠️ Mix de session checks, AJAX headers, password auth
   - ✅ **Solution** : Middleware d'authentification centralisé (JWT tokens pour API2)

3. **SQL Injection potentielle** :
   - ⚠️ Certains anciens endpoints utilisent concaténation SQL
   - ✅ **Solution** : Doctrine DBAL avec requêtes préparées partout

4. **CORS non contrôlé** :
   - ⚠️ Certains endpoints n'ont pas de validation d'origine
   - ✅ **Solution** : Configuration CORS stricte dans API2

5. **Pas de rate limiting** :
   - ⚠️ Endpoints autocomplete exploitables pour DoS
   - ✅ **Solution** : Implémenter rate limiting (ex: 100 req/min par IP)

### Checklist Sécurité par Endpoint

Pour chaque nouvel endpoint, vérifier :

- [ ] Authentification requise (sauf endpoints publics)
- [ ] Autorisation basée sur le rôle/profil utilisateur
- [ ] Validation des inputs (Symfony Validator ou custom)
- [ ] Échappement des outputs (prévention XSS)
- [ ] Requêtes SQL préparées uniquement
- [ ] CORS configuré explicitement
- [ ] Rate limiting actif
- [ ] Logging des actions sensibles
- [ ] Tests de sécurité (injection, XSS, CSRF)

---

## 📊 Impact sur les Clients

### Applications Affectées

| Application | Endpoints Utilisés | Impact Estimé | Stratégie |
|-------------|-------------------|--------------|-----------|
| **Admin Legacy (jQuery)** | Autocomplete, Match Management, Status Updates | 🔥 **HAUT** | Migration progressive avec wrappers |
| **App2 (Nuxt - Scrutineering)** | API /api/staff/*, autocomplete | ⚠️ **MOYEN** | Mise à jour des composables Nuxt |
| **App3 (Nuxt - Match Sheet)** | Match events, timer, live | 🔥 **CRITIQUE** | Tests intensifs, rollback plan |
| **WSM (Web Score Management)** | /api/wsm/*, match management | 🔥 **CRITIQUE** | Migration coordonnée avec tests live |
| **Live Broadcasting** | /live/* endpoints | ⚠️ **MOYEN** | Tests avec événements réels |
| **WordPress Integration** | Calendar, events | ✅ **FAIBLE** | Mise à jour shortcodes |

### Guide de Migration pour Développeurs Frontend

#### Exemple 1 : Autocomplete Player

**Avant** (Legacy) :
```javascript
// admin/script.js
$('#player-input').autocomplete({
  source: function(request, response) {
    $.ajax({
      url: '/admin/Autocompl_joueur.php',
      data: { q: request.term, format: 'json' },
      success: function(data) {
        response($.map(data.split('\n'), function(item) {
          return { label: item, value: item };
        }));
      }
    });
  }
});
```

**Après** (Nouvelle API) :
```javascript
// admin/script.js
$('#player-input').autocomplete({
  source: function(request, response) {
    fetch(`/api/autocomplete/players?q=${encodeURIComponent(request.term)}&limit=10`)
      .then(res => res.json())
      .then(data => {
        response(data.map(player => ({
          label: `${player.name} (${player.license})`,
          value: player.license,
          data: player
        })));
      });
  }
});
```

**Format JSON Réponse** :
```json
[
  {
    "license": "123456",
    "name": "DUPONT Jean",
    "club": "Paris Kayak Polo",
    "clubId": "75001",
    "birthYear": 1990,
    "category": "Senior"
  }
]
```

#### Exemple 2 : Match Event (Goal) - App3 (Nuxt)

**Avant** (Legacy) :
```typescript
// app3/composables/useMatchEvents.ts
async function addGoal(matchId: number, eventData: any) {
  const response = await fetch('/admin/v2/evt_match.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest'
    },
    body: new URLSearchParams({
      idMatch: matchId.toString(),
      action: 'insert',
      ligne: JSON.stringify(eventData)
    })
  });
  return response.json();
}
```

**Après** (Nouvelle API) :
```typescript
// app3/composables/useMatchEvents.ts
async function addGoal(matchId: number, eventData: GoalEvent) {
  const response = await fetch(`/api/match/${matchId}/events`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${getAuthToken()}`
    },
    body: JSON.stringify({
      period: eventData.period,
      gameTime: eventData.gameTime,
      type: 'goal',
      player: eventData.player,
      playerNumber: eventData.playerNumber,
      team: eventData.team
    })
  });

  if (!response.ok) {
    throw new Error(`HTTP ${response.status}: ${await response.text()}`);
  }

  return response.json();
}
```

**Format JSON Réponse** :
```json
{
  "success": true,
  "eventId": 4567,
  "message": "Goal added successfully"
}
```

#### Exemple 3 : Timer Control (Chrono)

**Avant** (Legacy) :
```javascript
// wsm/timer.js
function startTimer(matchId, startTime, runTime, maxTime) {
  $.post('/admin/v2/setChrono.php', {
    idMatch: matchId,
    action: 'run',
    start_time: startTime,
    run_time: runTime,
    max_time: maxTime,
    shotclock: 0,
    penalties: 0
  });
}
```

**Après** (Nouvelle API - REST) :
```javascript
// wsm/timer.js
async function startTimer(matchId, config) {
  const response = await fetch(`/api/match/${matchId}/timer`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      action: 'start',
      startTime: config.startTime,
      runTime: config.runTime,
      maxTime: config.maxTime,
      shotclock: config.shotclock || 0,
      penalties: config.penalties || 0
    })
  });

  return response.json();
}
```

---

## 🧪 Stratégie de Test

### Tests Unitaires (PHPUnit)

**Pour chaque contrôleur** :
```php
// tests/Controller/AutocompleteControllerTest.php
class AutocompleteControllerTest extends WebTestCase
{
    public function testPlayersAutocomplete(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/autocomplete/players?q=dupont&limit=5');

        $this->assertResponseIsSuccessful();
        $this->assertJson($client->getResponse()->getContent());

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(5, $data);
        $this->assertArrayHasKey('license', $data[0]);
    }

    public function testPlayersAutocompleteRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/autocomplete/players?q=test');

        // Si auth requise
        $this->assertResponseStatusCodeSame(401);
    }
}
```

### Tests d'Intégration

**Scénario complet match** :
```php
// tests/Integration/MatchWorkflowTest.php
class MatchWorkflowTest extends WebTestCase
{
    public function testCompleteMatchWorkflow(): void
    {
        $client = static::createClient();

        // 1. Créer un match
        $matchId = 123;

        // 2. Assigner équipes
        $client->request('PATCH', "/api/match/{$matchId}/teams", [
            'teamA' => 101,
            'teamB' => 102
        ]);
        $this->assertResponseIsSuccessful();

        // 3. Démarrer chrono
        $client->request('POST', "/api/match/{$matchId}/timer", [
            'action' => 'start',
            'maxTime' => 1200
        ]);
        $this->assertResponseIsSuccessful();

        // 4. Ajouter un but
        $client->request('POST', "/api/match/{$matchId}/events", [
            'type' => 'goal',
            'team' => 'A',
            'player' => '123456',
            'period' => 1,
            'gameTime' => '05:30'
        ]);
        $this->assertResponseIsSuccessful();

        // 5. Vérifier le score
        $client->request('GET', "/api/match/{$matchId}");
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals(1, $data['scoreA']);
    }
}
```

### Tests E2E (Cypress ou Playwright)

```javascript
// cypress/e2e/match-management.cy.js
describe('Match Management', () => {
  it('should create and manage a match', () => {
    cy.visit('/admin/match/123');

    // Autocomplete player
    cy.get('#player-search').type('dupont');
    cy.get('.autocomplete-results').should('be.visible');
    cy.get('.autocomplete-results li').first().click();

    // Add goal
    cy.get('#add-goal-btn').click();
    cy.get('#score-team-a').should('contain', '1');

    // Verify API call
    cy.wait('@addGoalRequest').its('response.statusCode').should('eq', 200);
  });
});
```

---

## 📈 Métriques de Succès

### KPI de Migration

| Métrique | Objectif | Mesure |
|----------|----------|--------|
| **Couverture de migration** | 100% des endpoints | Nombre endpoints migrés / Total |
| **Temps de réponse** | <100ms (P95) | Monitoring APM (New Relic, Datadog) |
| **Taux d'erreur** | <0.1% | Logs d'erreur / Total requêtes |
| **Réduction fichiers** | -70% fichiers AJAX | 80 fichiers → ~25 endpoints |
| **Satisfaction développeurs** | >8/10 | Sondage post-migration |
| **Zero downtime** | 100% | Incidents pendant migration |

### Monitoring

**Dashboards à créer** :
1. **Performance API** : Temps de réponse par endpoint
2. **Usage Legacy vs New** : Graphique de transition
3. **Erreurs** : Taux d'erreur par endpoint
4. **Authentification** : Tentatives échouées
5. **Rate Limiting** : Requêtes bloquées

**Alertes** :
- Temps de réponse >500ms (P95)
- Taux d'erreur >1%
- Pics de requêtes (DoS potentiel)

---

## 📚 Documentation

### Documentation API (OpenAPI/Swagger)

**Exemple de spec OpenAPI pour autocomplete** :
```yaml
/api/autocomplete/players:
  get:
    summary: Player autocomplete search
    description: Search players by name or license number
    tags:
      - Autocomplete
    parameters:
      - name: q
        in: query
        required: true
        schema:
          type: string
        description: Search query (min 2 characters)
      - name: limit
        in: query
        schema:
          type: integer
          default: 10
          maximum: 50
        description: Maximum results to return
      - name: season
        in: query
        schema:
          type: string
          example: "2024-2025"
        description: Filter by season (optional)
    responses:
      '200':
        description: Successful response
        content:
          application/json:
            schema:
              type: array
              items:
                type: object
                properties:
                  license:
                    type: string
                    example: "123456"
                  name:
                    type: string
                    example: "DUPONT Jean"
                  club:
                    type: string
                    example: "Paris Kayak Polo"
                  clubId:
                    type: string
                    example: "75001"
                  birthYear:
                    type: integer
                    example: 1990
                  category:
                    type: string
                    example: "Senior"
      '400':
        description: Invalid parameters
      '401':
        description: Unauthorized (if auth required)
```

### Guide de Migration (Markdown)

**Structure du guide** :
```markdown
# Migration Guide: AJAX Endpoints → REST API

## Overview
- Why we're migrating
- Benefits
- Timeline

## Breaking Changes
- Endpoint URL changes
- Parameter name changes
- Response format changes

## Migration by Feature
### Autocomplete
- Before/After code examples
- New response format
- Error handling

### Match Management
...

## Backward Compatibility
- Transition period (1 month)
- Deprecation warnings
- Automatic redirects

## FAQ
- What if I find a bug?
- How to rollback?
- Where to get support?
```

---

## ⚠️ Risques et Mitigation

| Risque | Probabilité | Impact | Mitigation |
|--------|------------|--------|------------|
| **Régression fonctionnelle** | Moyenne | Haut | Tests E2E complets, beta testing avec utilisateurs |
| **Downtime pendant migration** | Faible | Critique | Déploiement progressif, rollback automatisé |
| **Performance dégradée** | Faible | Moyen | Load testing avant prod, monitoring temps réel |
| **Incompatibilité clients** | Moyenne | Haut | Wrappers de compatibilité, documentation détaillée |
| **Authentification cassée** | Faible | Critique | Tests d'auth exhaustifs, session backup |
| **Perte de données** | Très faible | Critique | Transactions SQL, backups avant modifications |

---

## 🚀 Prochaines Étapes Immédiates

### Actions à Lancer Maintenant

1. **Validation du plan** ✅
   - Review avec l'équipe technique
   - Approbation stakeholders
   - Ajustements priorités

2. **Setup infrastructure** (Semaine 1)
   - Créer branches Git : `feature/api-consolidation`
   - Setup environnement de test dédié
   - Configurer CI/CD pour tests automatisés

3. **Proof of Concept** (Semaine 1-2)
   - Implémenter 2 endpoints pilotes :
     - `GET /api/autocomplete/players` (simple)
     - `POST /api/match/{matchId}/events` (complexe)
   - Valider l'approche technique
   - Mesurer performances

4. **Communication** (Semaine 1)
   - Annoncer le projet aux développeurs
   - Créer canal Slack/Discord dédié
   - Planifier sessions de formation

---

## 📞 Support et Questions

- **Documentation** : `/WORKFLOW_AI/AJAX_ENDPOINTS_CONSOLIDATION_PLAN.md` (ce fichier)
- **Issues GitHub** : Utiliser label `api-consolidation`
- **Slack** : Canal `#api-migration` (à créer)
- **Responsable technique** : À définir

---

## 📝 Changelog du Plan

| Date | Version | Modifications |
|------|---------|--------------|
| 2025-11-22 | 1.0 | Création initiale du plan après analyse exhaustive |

---

**Fin du Plan d'Action - Consolidation des Endpoints AJAX**
