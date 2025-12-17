# Plan de Test et Migration Progressive - API Legacy vers API2

> **Date de création** : 2025-12-17
> **Statut** : 🚧 En cours - Phase de test des endpoints existants

---

## 📊 État Actuel

### Endpoints API2 Déjà Migrés (16/82 - 19.5%)

#### ✅ Public Endpoints (7/7 - 100%)
| Legacy | API2 | Statut | Sécurité |
|--------|------|--------|----------|
| `/api/events/{mode}` | `/api2/events/{mode}` | ✅ Migré | ✅ Public |
| `/api/event/{id}` | `/api2/event/{id}` | ✅ Migré | ✅ Public |
| `/api/games/{eventId}` | `/api2/games/{eventId}` | ✅ Migré | ✅ Public |
| `/api/charts/{eventId}` | `/api2/charts/{eventId}` | ✅ Migré | ✅ Public |
| `/api/team-stats/{teamId}/{eventId}` | `/api2/team-stats/{teamId}/{eventId}` | ✅ Migré | ✅ Public |
| `/api/stars` | `/api2/stars` | ✅ Migré | ✅ Public |
| `/api/rating` (POST) | `/api2/rating` | ✅ Migré | ⚠️ Validation à renforcer |

#### ⚠️ Staff Endpoints (4/4 - 100% migrés, sécurité à renforcer)
| Legacy | API2 | Statut | Sécurité |
|--------|------|--------|----------|
| `/api/staff/{token}/test` | `/api2/staff/{token}/test` | ✅ Migré | ⛔ TODO: Token auth |
| `/api/staff/{token}/teams/{eventId}` | `/api2/staff/{token}/teams/{eventId}` | ✅ Migré | ⛔ TODO: Token auth |
| `/api/staff/{token}/players/{teamId}` | `/api2/staff/{token}/players/{teamId}` | ✅ Migré | ⛔ TODO: Token auth |
| `/api/staff/{token}/player/{playerId}/...` | `/api2/staff/{token}/player/{playerId}/...` | ✅ Migré | ⛔ TODO: Token auth |

#### 🔥 WSM Endpoints (4/15 - 27% migrés, CRITIQUES pour App3/WSM)
| Legacy | API2 | Statut | Sécurité |
|--------|------|--------|----------|
| `/api/wsm/eventNetwork/{eventId}` | `/api2/wsm/eventNetwork/{eventId}` | ✅ Migré | ⚠️ À sécuriser |
| `/api/wsm/gameParam/{matchId}` | `/api2/wsm/gameParam/{matchId}` | ✅ Migré | ⚠️ Validation OK + Lock check |
| `/api/wsm/gameEvent/{matchId}` | `/api2/wsm/gameEvent/{matchId}` | ✅ Migré | ⚠️ Lock check OK |
| `/api/wsm/playerStatus/{matchId}` | `/api2/wsm/playerStatus/{matchId}` | ✅ Migré | ⚠️ Lock check OK |
| `/api/wsm/gameTimer/{matchId}` | `/api2/wsm/gameTimer/{matchId}` | ✅ Migré | ⚠️ Lock check OK |
| `/api/wsm/stats` | `/api2/wsm/stats` | ✅ Migré | ⚠️ Lock check OK |

#### ✅ Report Endpoints (1/1 - 100%)
| Legacy | API2 | Statut | Sécurité |
|--------|------|--------|----------|
| `/api/report/{token}/game/{gameId}` | `/api2/report/{token}/game/{gameId}` | ✅ Migré | ⛔ TODO: Token auth |

---

## 🔒 Analyse de Sécurité des Endpoints API2

### Sécurités PRÉSENTES ✅

#### 1. WSM - Protection contre matches verrouillés
**Fichier** : [WsmController.php](../../sources/api2/src/Controller/WsmController.php)

```php
// Vérifie que le match n'est pas validé avant toute modification
$sql = "SELECT COUNT(Id) FROM kp_match WHERE Id = ? AND Validation != 'O'";
$count = $conn->fetchOne($sql, [$matchId]);
if ($count != 1) {
    return new JsonResponse(['error' => 'Game locked'], 400);
}
```

**Endpoints protégés** :
- ✅ `PUT /api2/wsm/gameParam/{matchId}`
- ✅ `PUT /api2/wsm/gameEvent/{matchId}`
- ✅ `PUT /api2/wsm/playerStatus/{matchId}`
- ✅ `PUT /api2/wsm/gameTimer/{matchId}`
- ✅ `PUT /api2/wsm/stats`

#### 2. WSM - Validation des paramètres autorisés
```php
// gameParam - Only whitelisted params allowed
if (!in_array($data->param ?? '', ['Statut', 'Periode', 'ScoreA', 'ScoreB', 'ScoreDetailA', 'ScoreDetailB', 'Heure_fin'])) {
    return new JsonResponse(['error' => 'Invalid parameter'], 401);
}

// stats - Only whitelisted actions allowed
if (!in_array($data->action ?? '', ['pass', 'possession', 'kickoff', 'kickoff-ko', 'shot-in', 'shot-out', 'shot-stop'])) {
    return new JsonResponse(['error' => 'Invalid action'], 401);
}
```

#### 3. Staff - Validation des champs scrutineering
```php
if (!in_array($parameter, ['kayak_status', 'vest_status', 'helmet_status', 'paddle_count'])) {
    return new JsonResponse(['error' => 'Invalid parameter'], 405);
}
```

#### 4. Public - Validation des données entrantes
```php
// Rating validation
if (!$data || strlen($data->uid ?? '') !== 36 || ($data->stars ?? -1) < 0 || ($data->stars ?? 6) > 5) {
    return new JsonResponse(['error' => 'Invalid data'], 405);
}
```

### Sécurités MANQUANTES ⛔

#### 1. **Authentification Token (CRITIQUE)**
**Fichiers concernés** :
- [StaffController.php](../../sources/api2/src/Controller/StaffController.php)
- [ReportController.php](../../sources/api2/src/Controller/ReportController.php)

**Problème** :
```php
public function test(string $token): JsonResponse
{
    // TODO: Implement token authentication
    return new JsonResponse(['result' => 'OK']);
}
```

**Impact** : ⚠️ Haut - Tous les endpoints staff/report sont ouverts sans vérification

**Solution requise** :
```php
// À implémenter dans un AuthService
private function validateToken(string $token): bool {
    $conn = $this->entityManager->getConnection();
    $sql = "SELECT COUNT(*) FROM kp_staff_tokens
            WHERE token = ? AND expiry > NOW() AND active = 1";
    return $conn->fetchOne($sql, [$token]) > 0;
}
```

#### 2. **Rate Limiting** (à implémenter)
**Impact** : Moyen - Risque d'abus sur les endpoints publics

**Solution** : Implémenter un rate limiter Symfony
```yaml
# config/packages/rate_limiter.yaml
framework:
    rate_limiter:
        api_public:
            policy: 'sliding_window'
            limit: 100
            interval: '1 minute'
```

#### 3. **Cache pour WSM** (Performance + Sécurité)
**Fichiers concernés** : Tous les endpoints WSM

**Problème** :
```php
// TODO: Create cache here
return new JsonResponse(['success' => true]);
```

**Impact** : Moyen - Les changements ne sont pas propagés en temps réel

**Solution** : Implémenter CacheMatchService (équivalent legacy `CacheMatch.php`)

#### 4. **XSS Protection sur commentaires Staff**
**Fichier** : [StaffController.php:186](../../sources/api2/src/Controller/StaffController.php#L186)

**Actuel** :
```php
$comment = isset($input['comment']) ? htmlspecialchars(substr($input['comment'], 0, 255), ENT_QUOTES, 'UTF-8') : '';
```

**Status** : ✅ Correct - Protection XSS présente

#### 5. **SQL Injection Protection**
**Status** : ✅ Correct - Requêtes préparées utilisées partout avec `executeQuery($params)`

#### 6. **CORS Configuration**
**Status** : ✅ Configuré pour localhost et domaines .local (voir [README.md](../../sources/api2/README.md#L265-L270))

---

## 🧪 Plan de Test Progressif

### Phase 1 : Tests Manuels des Endpoints Existants (Semaine 1)

#### Jour 1-2 : Public Endpoints
**Objectif** : Vérifier que les endpoints publics retournent les mêmes données que legacy

##### Test 1.1 : Events API
```bash
# Legacy
curl -k -X GET "https://kpi.localhost/api/events/std"

# API2
curl -k -X GET "https://kpi.localhost/api2/events/std"

# Comparer les JSON - Doivent être identiques
diff <(curl -k -s "https://kpi.localhost/api/events/std" | jq -S .) \
     <(curl -k -s "https://kpi.localhost/api2/events/std" | jq -S .)
```

**Critères de succès** :
- ✅ HTTP 200
- ✅ Même structure JSON
- ✅ Mêmes données
- ✅ Temps de réponse < 100ms

##### Test 1.2 : Games API
```bash
# Remplacer {eventId} par un ID réel de votre BDD
EVENT_ID=123

curl -k -X GET "https://kpi.localhost/api/games/${EVENT_ID}"
curl -k -X GET "https://kpi.localhost/api2/games/${EVENT_ID}"
```

##### Test 1.3 : Charts API
```bash
curl -k -X GET "https://kpi.localhost/api/charts/${EVENT_ID}"
curl -k -X GET "https://kpi.localhost/api2/charts/${EVENT_ID}"
```

##### Test 1.4 : Team Stats
```bash
TEAM_ID=456
curl -k -X GET "https://kpi.localhost/api/team-stats/${TEAM_ID}/${EVENT_ID}"
curl -k -X GET "https://kpi.localhost/api2/team-stats/${TEAM_ID}/${EVENT_ID}"
```

##### Test 1.5 : App Rating
```bash
# GET stars
curl -k -X GET "https://kpi.localhost/api/stars"
curl -k -X GET "https://kpi.localhost/api2/stars"

# POST rating
UUID=$(uuidgen)
curl -k -X POST "https://kpi.localhost/api2/rating" \
  -H "Content-Type: application/json" \
  -d "{\"uid\":\"${UUID}\",\"stars\":4}"
```

#### Jour 3 : Staff Endpoints (⚠️ Sécurité à valider)
**Note** : Token auth non implémenté - tests possibles mais NON sécurisés

```bash
TOKEN="test-token-placeholder"
EVENT_ID=123
TEAM_ID=456

# Test endpoint
curl -k -X GET "https://kpi.localhost/api2/staff/${TOKEN}/test"

# Get teams
curl -k -X GET "https://kpi.localhost/api2/staff/${TOKEN}/teams/${EVENT_ID}"

# Get players
curl -k -X GET "https://kpi.localhost/api2/staff/${TOKEN}/players/${TEAM_ID}"

# Update player scrutineering
PLAYER_ID=789
curl -k -X PUT "https://kpi.localhost/api2/staff/${TOKEN}/player/${PLAYER_ID}/team/${TEAM_ID}/kayak_status/1"

# Update player comment
curl -k -X PUT "https://kpi.localhost/api2/staff/${TOKEN}/player/${PLAYER_ID}/team/${TEAM_ID}/comment" \
  -H "Content-Type: application/json" \
  -d '{"comment":"Equipment OK"}'
```

**⛔ BLOCAGE** : NE PAS utiliser ces endpoints en production tant que l'auth n'est pas implémenté

#### Jour 4-5 : WSM Endpoints (🔥 CRITIQUES pour App3)
**Note** : Lock check implémenté ✅ - Tests à faire sur un match NON validé

```bash
MATCH_ID=999

# Update game parameter
curl -k -X PUT "https://kpi.localhost/api2/wsm/gameParam/${MATCH_ID}" \
  -H "Content-Type: application/json" \
  -d '{"param":"ScoreA","value":"5"}'

# Add goal event
curl -k -X PUT "https://kpi.localhost/api2/wsm/gameEvent/${MATCH_ID}" \
  -H "Content-Type: application/json" \
  -d '{
    "params": {
      "action": "add",
      "period": 1,
      "tpsJeu": "10:30",
      "code": "B",
      "player": "123456",
      "number": 5,
      "team": "A",
      "reason": ""
    }
  }'

# Update player status (captain)
curl -k -X PUT "https://kpi.localhost/api2/wsm/playerStatus/${MATCH_ID}" \
  -H "Content-Type: application/json" \
  -d '{"params":{"team":"A","player":"123456","status":"C"}}'

# Start timer
curl -k -X PUT "https://kpi.localhost/api2/wsm/gameTimer/${MATCH_ID}" \
  -H "Content-Type: application/json" \
  -d '{
    "params": {
      "action": "run",
      "startTime": 0,
      "runTime": 600,
      "maxTime": 1200
    }
  }'

# Add statistics
curl -k -X PUT "https://kpi.localhost/api2/wsm/stats" \
  -H "Content-Type: application/json" \
  -d '{
    "user": "user123",
    "game": 999,
    "team": "A",
    "player": "123456",
    "action": "pass",
    "period": 1,
    "timer": "10:30"
  }'
```

**Tests de sécurité** :
```bash
# Test 1 : Modifier un match verrouillé (Validation = 'O')
# Attendu : HTTP 400 "Game locked"

# Test 2 : Paramètre invalide
curl -k -X PUT "https://kpi.localhost/api2/wsm/gameParam/${MATCH_ID}" \
  -H "Content-Type: application/json" \
  -d '{"param":"INVALID","value":"test"}'
# Attendu : HTTP 401 "Invalid parameter"

# Test 3 : Action invalide
curl -k -X PUT "https://kpi.localhost/api2/wsm/stats" \
  -H "Content-Type: application/json" \
  -d '{"action":"INVALID","game":999}'
# Attendu : HTTP 401 "Invalid action"
```

### Phase 2 : Tests Automatisés (Semaine 2)

#### 2.1 Installation PHPUnit pour API2
```bash
make composer_require_dev_api2 package=symfony/test-pack
make composer_require_dev_api2 package=symfony/browser-kit
make composer_require_dev_api2 package=symfony/http-client
```

#### 2.2 Créer les fichiers de tests
**Structure** :
```
sources/api2/tests/
├── Controller/
│   ├── PublicControllerTest.php
│   ├── StaffControllerTest.php
│   ├── WsmControllerTest.php
│   └── ReportControllerTest.php
├── Security/
│   └── TokenAuthTest.php (à créer après implémentation)
└── bootstrap.php
```

**Exemple de test** : `PublicControllerTest.php`
```php
<?php
namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PublicControllerTest extends WebTestCase
{
    public function testEventsEndpoint(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/events/std');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('id', $data[0] ?? []);
    }

    public function testTeamStats(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/team-stats/456/123');

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
    }

    public function testRatingValidation(): void
    {
        $client = static::createClient();

        // Test invalid UUID
        $client->request('POST', '/api/rating', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['uid' => 'invalid', 'stars' => 4])
        );
        $this->assertResponseStatusCodeSame(405);

        // Test invalid stars
        $client->request('POST', '/api/rating', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['uid' => '550e8400-e29b-41d4-a716-446655440000', 'stars' => 10])
        );
        $this->assertResponseStatusCodeSame(405);
    }
}
```

**Lancer les tests** :
```bash
make php_bash
cd /var/www/html/api2
./vendor/bin/phpunit
```

### Phase 3 : Corrections de Sécurité (Semaine 3)

#### 3.1 Implémenter l'authentification par token
**Fichier à créer** : `sources/api2/src/Security/TokenAuthenticator.php`

**Tâches** :
1. Créer table `kp_staff_tokens` si inexistante
2. Créer service `TokenAuthenticator`
3. Implémenter middleware de vérification
4. Appliquer aux endpoints Staff et Report

#### 3.2 Implémenter le cache WSM
**Fichier à créer** : `sources/api2/src/Service/CacheMatchService.php`

**Équivalent legacy** : `sources/commun/CacheMatch.php`

**Tâches** :
1. Créer service de cache JSON
2. Intégrer dans tous les endpoints WSM
3. Tester propagation temps réel

#### 3.3 Rate Limiting
**Configuration** : `sources/api2/config/packages/rate_limiter.yaml`

**Tâches** :
1. Configurer rate limiter Symfony
2. Appliquer aux endpoints publics (100 req/min)
3. Tester blocage après limite

### Phase 4 : Migration des Clients (Semaines 4-6)

#### 4.1 App2 (Nuxt - Scrutineering)
**Fichiers à modifier** :
- `sources/app2/composables/useApi.ts` (si existe)
- `sources/app2/nuxt.config.ts` (API_BASE_URL)

**Stratégie** :
1. Créer composable `useApi2()` parallèle à l'existant
2. Migrer progressivement les appels
3. Tester en dev avec `.env.development`
4. Déployer en production

#### 4.2 App3 (Nuxt - Match Sheet) - 🔥 CRITIQUE
**Fichiers à analyser** :
- `sources/app3/composables/useWsm.ts` (probablement)
- Tous les composants match/timer/events

**Stratégie** :
1. Créer environnement de test isolé
2. Migrer endpoints WSM un par un
3. Tests intensifs sur match réel
4. Plan de rollback si problème

#### 4.3 Admin Legacy (jQuery)
**Fichiers** : À identifier (probablement `sources/*.php`)

**Stratégie** :
1. Identifier tous les appels AJAX vers `/api/`
2. Créer wrappers de compatibilité
3. Migration progressive avec feature flags

### Phase 5 : Décommissionnement Legacy (Semaines 7-8)

#### 5.1 Vérification avant suppression
**Checklist** :
- [ ] Tous les clients migrés vers API2
- [ ] Tests E2E passent à 100%
- [ ] Monitoring production OK pendant 1 semaine
- [ ] Aucune erreur 404 dans les logs

#### 5.2 Archivage
```bash
# Créer backup des anciens endpoints
mkdir -p sources/api_legacy_backup_$(date +%Y%m%d)
cp -r sources/api/* sources/api_legacy_backup_$(date +%Y%m%d)/

# Git commit avant suppression
git add sources/api_legacy_backup_$(date +%Y%m%d)
git commit -m "Archive legacy API before removal"
```

#### 5.3 Suppression progressive
**Ordre de suppression** :
1. Public endpoints (faible risque)
2. Staff endpoints (après vérif app2)
3. WSM endpoints (DERNIER - après vérif app3)

---

## 📋 Checklist de Migration par Endpoint

### Template de vérification

Pour chaque endpoint :

```markdown
#### Endpoint : `PUT /api/wsm/gameParam/{matchId}`

**Legacy file** : `sources/api/wsm/gameParam.php`
**API2 controller** : `WsmController::putGameParam()`

**Tests** :
- [ ] Test manuel OK (curl)
- [ ] Test automatisé créé
- [ ] Comparaison legacy vs API2 identique
- [ ] Performance < 100ms
- [ ] Sécurité validée (lock check, validation)
- [ ] Cache implémenté
- [ ] Documentation OpenAPI OK

**Clients identifiés** :
- [ ] App3 : `sources/app3/composables/useWsm.ts`
- [ ] WSM Admin : `sources/wsm/...` (à identifier)

**Migration clients** :
- [ ] App3 migré et testé
- [ ] WSM Admin migré
- [ ] Tests E2E passent

**Décommissionnement** :
- [ ] Legacy file supprimé
- [ ] Aucune erreur 404 pendant 7 jours
```

---

## 🚨 Points de Blocage Identifiés

### 🔴 BLOQUANT : Authentification Token
**Impact** : Staff + Report endpoints inutilisables en production

**TODO URGENT** :
1. Analyser le système de tokens legacy
2. Créer table `kp_staff_tokens` si nécessaire
3. Implémenter `TokenAuthenticator` Symfony
4. Tester avec app2/scrutineering

**Estimation** : 2-3 jours

### 🟠 IMPORTANT : Cache WSM
**Impact** : Performances + Temps réel dégradés

**TODO** :
1. Porter `CacheMatch.php` en Symfony service
2. Intégrer dans tous endpoints WSM
3. Tester propagation BroadcastChannel

**Estimation** : 1-2 jours

### 🟡 SOUHAITABLE : Rate Limiting
**Impact** : Risque d'abus faible (app interne)

**TODO** :
1. Configurer rate limiter Symfony
2. Appliquer aux endpoints publics

**Estimation** : 0.5 jour

---

## 📊 Métriques de Succès

### Critères de validation pour passer en production

| Métrique | Objectif | Actuel |
|----------|----------|--------|
| Couverture tests | 100% endpoints | 0% |
| Temps réponse P95 | < 100ms | À mesurer |
| Erreurs 5xx | < 0.1% | À mesurer |
| Compatibilité legacy | 100% | À valider |
| Sécurité (auth) | 100% implémenté | 0% (TODOs présents) |
| Cache WSM | 100% fonctionnel | 0% (TODOs présents) |

---

## 📅 Timeline Révisée

| Semaine | Phase | Livrables | Bloquants |
|---------|-------|-----------|-----------|
| **S1** | Tests manuels | Validation 16 endpoints existants | Aucun |
| **S2** | Tests auto | Suite PHPUnit complète | Aucun |
| **S3** | Sécurité | Token auth + Cache WSM + Rate limit | 🔴 Auth requis |
| **S4** | Migration App2 | Scrutineering sur API2 | 🔴 Auth requis |
| **S5-S6** | Migration App3 | Match Sheet sur API2 (WSM) | 🟠 Cache requis |
| **S7** | Validation | Tests E2E + Monitoring | Aucun |
| **S8** | Décommissionnement | Suppression legacy public/staff | Aucun |

---

## 🔧 Outils et Scripts Utiles

### Script de comparaison automatique legacy vs API2
**Fichier à créer** : `DOC/developer/scripts/compare_api_responses.sh`

```bash
#!/bin/bash
# Compare legacy API vs API2 responses

ENDPOINT=$1
LEGACY_URL="https://kpi.localhost/api${ENDPOINT}"
API2_URL="https://kpi.localhost/api2${ENDPOINT}"

echo "Comparing: ${ENDPOINT}"
echo "Legacy: ${LEGACY_URL}"
echo "API2: ${API2_URL}"

diff <(curl -k -s "${LEGACY_URL}" | jq -S .) \
     <(curl -k -s "${API2_URL}" | jq -S .)

if [ $? -eq 0 ]; then
    echo "✅ Responses are identical"
else
    echo "❌ Responses differ"
fi
```

**Usage** :
```bash
./compare_api_responses.sh "/events/std"
./compare_api_responses.sh "/games/123"
```

---

## 📚 Ressources et Documentation

### Documentation API2
- **README** : [sources/api2/README.md](../../sources/api2/README.md)
- **Endpoints** : [sources/api2/API_ENDPOINTS.md](../../sources/api2/API_ENDPOINTS.md)
- **Swagger UI** : `https://kpi.localhost/api2/doc` (après installation NelmioApiDocBundle)

### Documentation Legacy
- **Plan consolidation** : [AJAX_ENDPOINTS_CONSOLIDATION_PLAN.md](AJAX_ENDPOINTS_CONSOLIDATION_PLAN.md)
- **Mise à jour Nov 2025** : [AJAX_CONSOLIDATION_UPDATE_NOV2025.md](AJAX_CONSOLIDATION_UPDATE_NOV2025.md)

### Guides Symfony
- **Testing** : https://symfony.com/doc/current/testing.html
- **Security** : https://symfony.com/doc/current/security.html
- **Rate Limiting** : https://symfony.com/doc/current/rate_limiter.html

---

## 🎯 Prochaines Actions Immédiates

### Aujourd'hui (J1)
1. ✅ Créer ce plan de test
2. ⏳ Tester manuellement les 7 endpoints Public
3. ⏳ Documenter les différences trouvées

### Cette semaine (J2-J5)
4. Tester les endpoints Staff (identifier système de tokens)
5. Tester les endpoints WSM (créer match de test)
6. Créer la suite de tests PHPUnit de base

### Semaine prochaine
7. Implémenter l'authentification par token
8. Implémenter le cache WSM
9. Migrer app2 vers API2

---

**Dernière mise à jour** : 2025-12-17
**Responsable** : Laurent
**Statut** : 📋 Plan validé - Tests manuels en cours
