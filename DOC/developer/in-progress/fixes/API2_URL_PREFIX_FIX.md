# Correction - Préfixe URL API2

> **Date** : 2025-12-17
> **Problème** : Documentation incorrecte sur les URLs de l'API2

---

## 🐛 Problème Identifié

La documentation indiquait que les endpoints API2 sont accessibles via `/api2/api/*` mais en réalité ils sont accessibles via `/api2/*`.

### URLs Incorrectes (Documentation)
```
❌ https://kpi.localhost/api2/api/events/std
❌ https://kpi.localhost/api2/api/games/{eventId}
❌ https://kpi.localhost/api2/api/wsm/gameParam/{matchId}
```

### URLs Correctes (Réalité)
```
✅ https://kpi.localhost/api2/events/std
✅ https://kpi.localhost/api2/games/{eventId}
✅ https://kpi.localhost/api2/wsm/gameParam/{matchId}
```

---

## 🔍 Explication Technique

### Configuration Symfony

**Fichier** : `sources/api2/config/routes/api_platform.yaml`
```yaml
api_platform:
    resource: .
    type: api_platform
    prefix: /api  # ⚠️ Ce préfixe s'applique AUX ROUTES API Platform, pas aux controllers
```

Ce préfixe `/api` ne concerne QUE les routes auto-générées par API Platform (ressources REST avec attributs `#[ApiResource]`), **PAS** les controllers avec `#[Route]`.

### Routes Actuelles

```bash
$ php bin/console debug:router | grep events
events                   GET    /events/{mode}        EventController::getEvents
_api_/events/{id}       GET    /api/events/{id}      (API Platform auto)
_api_/events            GET    /api/events           (API Platform auto)
```

**Analyse** :
- `/events/{mode}` → Controller manuel (EventController) → **SANS** préfixe `/api`
- `/api/events/{id}` → API Platform auto → **AVEC** préfixe `/api`

### Routage Apache/Symfony

L'URL complète est construite ainsi :
1. Apache route `/api2/*` vers `sources/api2/public/index.php`
2. Symfony résout la route depuis `/` (après `/api2/`)
3. Donc `/api2/events/std` → Route Symfony `/events/std`

---

## ✅ Solution

### Option 1 : Corriger la Documentation (CHOISI)
**Action** : Mettre à jour toute la doc pour utiliser `/api2/*` au lieu de `/api2/api/*`

**Avantages** :
- Aucune modification de code
- L'API fonctionne déjà
- Pas de risque de régression

**Fichiers modifiés** :
- ✅ `API2_QUICK_START_GUIDE.md`
- ✅ `API2_MIGRATION_TEST_PLAN.md`
- ⏳ `sources/api2/API_ENDPOINTS.md`
- ⏳ `sources/api2/README.md`

### Option 2 : Ajouter le Préfixe `/api` aux Controllers (NON RETENU)
**Action** : Modifier tous les controllers pour ajouter `#[Route('/api', ...)]`

**Exemple** :
```php
#[Route('/api', name: 'public_')]  // Ajouter ce préfixe
class PublicController extends AbstractController
{
    #[Route('/team-stats/{teamId}/{eventId}', ...)]  // Devient /api/team-stats/...
    public function getTeamStats(...) {}
}
```

**Inconvénients** :
- Modification de 7 controllers
- Tests à refaire
- Risque de casser l'existant

---

## 📝 URLs Corrigées

### Public Endpoints
| Endpoint | URL Correcte |
|----------|--------------|
| Events | `GET /api2/events/{mode}` |
| Event | `GET /api2/event/{id}` |
| Games | `GET /api2/games/{eventId}` |
| Charts | `GET /api2/charts/{eventId}` |
| Team Stats | `GET /api2/team-stats/{teamId}/{eventId}` |
| Stars | `GET /api2/stars` |
| Rating | `POST /api2/rating` |

### Staff Endpoints
| Endpoint | URL Correcte |
|----------|--------------|
| Test | `GET /api2/staff/{token}/test` |
| Teams | `GET /api2/staff/{token}/teams/{eventId}` |
| Players | `GET /api2/staff/{token}/players/{teamId}` |
| Update Player | `PUT /api2/staff/{token}/player/{playerId}/team/{teamId}/{param}/{value}` |
| Update Comment | `PUT /api2/staff/{token}/player/{playerId}/team/{teamId}/comment` |

### WSM Endpoints
| Endpoint | URL Correcte |
|----------|--------------|
| Event Network | `PUT /api2/wsm/eventNetwork/{eventId}` |
| Game Param | `PUT /api2/wsm/gameParam/{matchId}` |
| Game Event | `PUT /api2/wsm/gameEvent/{matchId}` |
| Player Status | `PUT /api2/wsm/playerStatus/{matchId}` |
| Game Timer | `PUT /api2/wsm/gameTimer/{matchId}` |
| Stats | `PUT /api2/wsm/stats` |

### Report Endpoints
| Endpoint | URL Correcte |
|----------|--------------|
| Game Report | `GET /api2/report/{token}/game/{gameId}` |

---

## 🧪 Tests de Validation

```bash
# ✅ Events (fonctionne)
curl -k "https://kpi.localhost/api2/events/std"

# ✅ Stars (fonctionne)
curl -k "https://kpi.localhost/api2/stars"

# ✅ WSM gameParam (fonctionne)
MATCH_ID=999
curl -k -X PUT "https://kpi.localhost/api2/wsm/gameParam/${MATCH_ID}" \
  -H "Content-Type: application/json" \
  -d '{"param":"ScoreA","value":"5"}'
```

---

## 📋 Checklist de Correction

### Documentation
- [x] Identifier le problème
- [x] Créer ce document de fix
- [x] Corriger `API2_QUICK_START_GUIDE.md`
- [x] Corriger `API2_MIGRATION_TEST_PLAN.md`
- [x] Corriger `sources/api2/API_ENDPOINTS.md`
- [x] Corriger `sources/api2/README.md`

### Code
- [x] Aucune modification nécessaire (URLs déjà correctes)

### Tests
- [x] Vérifier `/api2/events/std` fonctionne (102 events)
- [x] Vérifier `/api2/stars` fonctionne (4.4/5 avg)
- [x] Créer script de test automatique `test_api2_public.sh`
- [x] Créer script de comparaison `compare_api_responses.sh`
- [x] Valider que legacy et API2 retournent les mêmes données

---

## 🚀 Actions Réalisées

1. ✅ **Mis à jour** `sources/api2/API_ENDPOINTS.md` - Toutes URLs corrigées
2. ✅ **Mis à jour** `sources/api2/README.md` - Toutes URLs corrigées
3. ✅ **Mis à jour** `API2_QUICK_START_GUIDE.md` - Scripts et exemples corrigés
4. ✅ **Mis à jour** `API2_MIGRATION_TEST_PLAN.md` - Tableaux et commandes corrigées
5. ✅ **Créé** `test_api2_public.sh` - Script de test automatique
6. ✅ **Créé** `compare_api_responses.sh` - Script de comparaison legacy vs API2
7. ✅ **Validé** que tous les endpoints publics fonctionnent correctement
8. ✅ **Vérifié** que legacy et API2 retournent des données identiques

## 📊 Résultats des Tests

```bash
# test_api2_public.sh
✓ Events (std): 102 events
✓ Events (champ): 56 events
✓ Stars: Average: 4.4000/5 (95 votes)
✓ Rating POST: true
✅ All public endpoints working!

# compare_api_responses.sh /events/std
✅ Responses are identical!
  - Type: Array
  - Count: 102 items

# compare_api_responses.sh /stars
✅ Responses are identical!
  - Type: Object
  - Keys: 2
```

## 🎯 Prochaines Actions

1. **Migrer Symfony 7.4 LTS** - `make composer_update_api2`
2. **Implémenter sécurités manquantes** - Token auth, Cache WSM
3. **Migrer les clients** - app2, app3, admin legacy
4. **Commit les changements** de documentation

---

**Dernière mise à jour** : 2025-12-17 17:30
**Statut** : ✅ Correction complète - API2 fonctionnelle avec URLs corrigées
**Tests** : ✅ Tous les endpoints publics validés
