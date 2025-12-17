# Guide de Démarrage Rapide - Migration API2

> **Date** : 2025-12-17
> **Objectif** : Tester et migrer progressivement vers API2 + Symfony 7.4 LTS

---

## 🚀 Actions Immédiates à Réaliser

### 1️⃣ Migration Symfony 7.4 LTS (1h)

#### Étape A : Mise à jour des packages
```bash
cd /home/laurent/Documents/dev/kpi

# ✅ Le composer.json a déjà été modifié pour Symfony 7.4.*
# Lancer la mise à jour
make composer_update_api2

# Vérifier les versions installées
make php_bash
cd /var/www/html/api2
composer show symfony/* | grep "^symfony"
```

**Attendu** : Toutes les versions Symfony doivent afficher `v7.4.x`

#### Étape B : Clear cache
```bash
make api2_cache_clear
make api2_cache_warmup
```

#### Étape C : Tests de validation
```bash
# Tester les endpoints publics
curl -k -X GET "https://kpi.localhost/api2/events/std"
curl -k -X GET "https://kpi.localhost/api2/stars"
```

**Succès si** : HTTP 200 + JSON valide

#### Étape D : Commit
```bash
git add sources/api2/composer.json sources/api2/composer.lock
git commit -m "chore(api2): Upgrade to Symfony 7.4 LTS

- Update all Symfony packages 7.3.* → 7.4.*
- Extends support until November 2028
- No breaking changes (minor upgrade)
- All endpoints tested and validated

See: DOC/developer/in-progress/migrations/SYMFONY_7.4_LTS_MIGRATION.md"
```

---

### 2️⃣ Tests des Endpoints Existants (2h)

#### Public Endpoints (30 min)

**Script de test automatique** :
```bash
# Créer ce script dans DOC/developer/scripts/
cat > DOC/developer/scripts/test_api2_public.sh << 'EOF'
#!/bin/bash
set -e

BASE_URL="https://kpi.localhost/api2"
LEGACY_URL="https://kpi.localhost/api"

echo "🧪 Testing Public Endpoints - API2 vs Legacy"
echo "=============================================="

# Events
echo -n "✓ Events (std): "
RESULT=$(curl -k -s "${BASE_URL}/events/std" | jq 'length')
echo "${RESULT} events"

# Stars
echo -n "✓ Stars: "
STARS=$(curl -k -s "${BASE_URL}/stars" | jq -r '.average')
echo "Average: ${STARS}"

# Rating (test POST)
UUID=$(uuidgen)
echo -n "✓ Rating POST: "
RATING=$(curl -k -s -X POST "${BASE_URL}/rating" \
  -H "Content-Type: application/json" \
  -d "{\"uid\":\"${UUID}\",\"stars\":5}" | jq -r '.success')
echo "${RATING}"

echo ""
echo "✅ All public endpoints working!"
EOF

chmod +x DOC/developer/scripts/test_api2_public.sh
./DOC/developer/scripts/test_api2_public.sh
```

#### WSM Endpoints (1h)

**⚠️ Important** : Tester sur un match NON validé

```bash
# Identifier un match de test
MATCH_ID=999  # Remplacer par un vrai ID

# Test 1: Update game parameter
curl -k -v -X PUT "https://kpi.localhost/api2/wsm/gameParam/${MATCH_ID}" \
  -H "Content-Type: application/json" \
  -d '{"param":"ScoreA","value":"3"}'

# Test 2: Start timer
curl -k -v -X PUT "https://kpi.localhost/api2/wsm/gameTimer/${MATCH_ID}" \
  -H "Content-Type: application/json" \
  -d '{
    "params": {
      "action": "run",
      "startTime": 0,
      "runTime": 0,
      "maxTime": 1200
    }
  }'

# Test 3: Add goal event
curl -k -v -X PUT "https://kpi.localhost/api2/wsm/gameEvent/${MATCH_ID}" \
  -H "Content-Type: application/json" \
  -d '{
    "params": {
      "action": "add",
      "period": 1,
      "tpsJeu": "5:30",
      "code": "B",
      "player": "123456",
      "number": 5,
      "team": "A",
      "reason": ""
    }
  }'
```

**Critères de succès** :
- ✅ HTTP 200
- ✅ `{"success":true}` dans la réponse
- ✅ Données bien enregistrées en BDD

#### Staff Endpoints (30 min)

**⚠️ BLOCAGE** : Token auth non implémenté - tests possibles mais NON sécurisés

```bash
TOKEN="test"  # N'importe quel token fonctionne actuellement (TODO à corriger)
EVENT_ID=123  # Remplacer par un vrai ID

curl -k -X GET "https://kpi.localhost/api2/staff/${TOKEN}/teams/${EVENT_ID}"
```

**Attendu** : Liste des équipes en JSON

**⛔ À FAIRE URGENT** : Implémenter la vérification du token

---

### 3️⃣ Corriger les Sécurités Manquantes (1 journée)

#### Priorité 1 : Token Authentication (4h)

**Créer le service d'authentification** :

**Fichier** : `sources/api2/src/Security/TokenAuthenticator.php`

```php
<?php

namespace App\Security;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class TokenAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private Connection $connection
    ) {
    }

    public function supports(Request $request): ?bool
    {
        // Supporte les routes /staff/* et /report/*
        return str_starts_with($request->getPathInfo(), '/api/staff/')
            || str_starts_with($request->getPathInfo(), '/api/report/');
    }

    public function authenticate(Request $request): Passport
    {
        // Extraire le token de l'URL (e.g., /staff/{token}/...)
        $pathParts = explode('/', trim($request->getPathInfo(), '/'));
        $token = $pathParts[2] ?? null;

        if (!$token) {
            throw new AuthenticationException('No token provided');
        }

        // Vérifier le token dans la BDD
        $sql = "SELECT id, user_email, scope FROM kp_staff_tokens
                WHERE token = ? AND expiry > NOW() AND active = 1";

        $result = $this->connection->fetchAssociative($sql, [$token]);

        if (!$result) {
            throw new AuthenticationException('Invalid or expired token');
        }

        return new SelfValidatingPassport(
            new UserBadge($result['user_email'])
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Allow the request to continue
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'error' => 'Unauthorized',
            'message' => $exception->getMessage()
        ], Response::HTTP_UNAUTHORIZED);
    }
}
```

**Créer la table tokens** (si n'existe pas) :

```sql
CREATE TABLE IF NOT EXISTS kp_staff_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(64) UNIQUE NOT NULL,
    user_email VARCHAR(255) NOT NULL,
    scope VARCHAR(50) NOT NULL COMMENT 'staff, report, admin',
    event_id INT DEFAULT NULL COMMENT 'Restrict to specific event',
    active TINYINT(1) DEFAULT 1,
    expiry DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_active_expiry (active, expiry)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Mettre à jour StaffController** :

```php
// Dans StaffController.php
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/staff', name: 'staff_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]  // Ajouter cette ligne
class StaffController extends AbstractController
{
    // ... rest of code
}
```

**Tester** :
```bash
# Sans token valide → 401
curl -k -v -X GET "https://kpi.localhost/api2/staff/invalid/test"

# Avec token valide → 200
# (Créer un token de test dans la BDD avant)
curl -k -v -X GET "https://kpi.localhost/api2/staff/valid-token-here/test"
```

#### Priorité 2 : Cache WSM (2h)

**Créer le service de cache** :

**Fichier** : `sources/api2/src/Service/CacheMatchService.php`

```php
<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\Filesystem\Filesystem;

class CacheMatchService
{
    private string $cacheDir;

    public function __construct(
        private Connection $connection,
        string $projectDir
    ) {
        $this->cacheDir = $projectDir . '/var/cache/wsm';
        (new Filesystem())->mkdir($this->cacheDir);
    }

    public function createMatchCache(int $matchId): array
    {
        // Récupérer toutes les données du match
        $matchData = $this->getMatchData($matchId);
        $events = $this->getMatchEvents($matchId);
        $players = $this->getMatchPlayers($matchId);
        $timer = $this->getMatchTimer($matchId);

        $cache = [
            'match' => $matchData,
            'events' => $events,
            'players' => $players,
            'timer' => $timer,
            'last_update' => time()
        ];

        // Sauvegarder en JSON
        $fileName = $this->cacheDir . '/match_' . $matchId . '.json';
        file_put_contents($fileName, json_encode($cache, JSON_PRETTY_PRINT));

        return $cache;
    }

    private function getMatchData(int $matchId): array
    {
        $sql = "SELECT * FROM kp_match WHERE Id = ?";
        return $this->connection->fetchAssociative($sql, [$matchId]) ?: [];
    }

    private function getMatchEvents(int $matchId): array
    {
        $sql = "SELECT * FROM kp_match_detail WHERE Id_match = ? ORDER BY Periode, Temps";
        return $this->connection->fetchAllAssociative($sql, [$matchId]);
    }

    private function getMatchPlayers(int $matchId): array
    {
        $sql = "SELECT * FROM kp_match_joueur WHERE Id_match = ?";
        return $this->connection->fetchAllAssociative($sql, [$matchId]);
    }

    private function getMatchTimer(int $matchId): ?array
    {
        $sql = "SELECT * FROM kp_chrono WHERE IdMatch = ?";
        return $this->connection->fetchAssociative($sql, [$matchId]) ?: null;
    }
}
```

**Utiliser dans WsmController** :

```php
public function __construct(
    private EntityManagerInterface $entityManager,
    private CacheMatchService $cacheService  // Ajouter
) {
}

public function putGameParam(int $matchId, Request $request): JsonResponse
{
    // ... existing code ...

    try {
        $conn->executeStatement($sql, [$data->value, $matchId]);

        // Créer le cache (remplacer TODO)
        $this->cacheService->createMatchCache($matchId);

        return new JsonResponse(['success' => true]);
    } catch (\Exception $e) {
        return new JsonResponse(['error' => $e->getMessage()], 400);
    }
}
```

---

### 4️⃣ Migration des Clients (2-3 jours)

#### App2 - Scrutineering (1 jour)

**Analyser les appels API actuels** :
```bash
cd sources/app2
grep -r "api/staff" . --include="*.ts" --include="*.vue"
grep -r "api/team-stats" . --include="*.ts" --include="*.vue"
```

**Créer composable API2** :

**Fichier** : `sources/app2/composables/useApi2.ts`

```typescript
export const useApi2 = () => {
  const config = useRuntimeConfig()
  const api2BaseUrl = config.public.api2BaseUrl || 'https://kpi.localhost/api2/api'

  const fetchEvents = async (mode: 'std' | 'champ' | 'all' = 'std') => {
    const data = await $fetch(`${api2BaseUrl}/events/${mode}`)
    return data
  }

  const fetchTeamStats = async (teamId: number, eventId: number) => {
    const data = await $fetch(`${api2BaseUrl}/team-stats/${teamId}/${eventId}`)
    return data
  }

  const fetchStaffTeams = async (token: string, eventId: number) => {
    const data = await $fetch(`${api2BaseUrl}/staff/${token}/teams/${eventId}`)
    return data
  }

  // ... autres endpoints

  return {
    fetchEvents,
    fetchTeamStats,
    fetchStaffTeams,
    // ...
  }
}
```

**Mettre à jour nuxt.config.ts** :

```typescript
export default defineNuxtConfig({
  runtimeConfig: {
    public: {
      apiBaseUrl: process.env.API_BASE_URL || 'https://kpi.localhost/api',
      api2BaseUrl: process.env.API2_BASE_URL || 'https://kpi.localhost/api2/api',
    }
  }
})
```

**Mettre à jour .env.development** :

```env
API_BASE_URL=https://kpi.localhost/api
API2_BASE_URL=https://kpi.localhost/api2/api
```

**Migrer progressivement les composants** :
```vue
<script setup lang="ts">
// Avant
const { fetchTeamStats } = useApi()

// Après
const { fetchTeamStats } = useApi2()  // Changer juste ça !
</script>
```

#### App3 - Match Sheet (2 jours - CRITIQUE)

**⚠️ Tests intensifs requis** - WSM endpoints critiques

**Stratégie** :
1. Créer environnement de test isolé
2. Migrer un par un :
   - Timer
   - Events
   - Scores
   - Players
3. Tests sur match réel (non validé)
4. Plan de rollback si problème

**Créer composable WSM** :

**Fichier** : `sources/app3/composables/useWsm2.ts`

```typescript
export const useWsm2 = () => {
  const api2BaseUrl = 'https://kpi.localhost/api2/wsm'

  const updateGameParam = async (matchId: number, param: string, value: string) => {
    const data = await $fetch(`${api2BaseUrl}/gameParam/${matchId}`, {
      method: 'PUT',
      body: { param, value }
    })
    return data
  }

  const addGameEvent = async (matchId: number, params: any) => {
    const data = await $fetch(`${api2BaseUrl}/gameEvent/${matchId}`, {
      method: 'PUT',
      body: { params: { ...params, action: 'add' } }
    })
    return data
  }

  const updateGameTimer = async (matchId: number, params: any) => {
    const data = await $fetch(`${api2BaseUrl}/gameTimer/${matchId}`, {
      method: 'PUT',
      body: { params }
    })
    return data
  }

  // ... autres endpoints WSM

  return {
    updateGameParam,
    addGameEvent,
    updateGameTimer,
    // ...
  }
}
```

---

## 📋 Checklist Globale

### Phase 1 : Infrastructure (Aujourd'hui)
- [x] ✅ Mettre à jour composer.json pour Symfony 7.4
- [ ] ⏳ Exécuter `make composer_update_api2`
- [ ] ⏳ Tester endpoints publics
- [ ] ⏳ Commit migration Symfony 7.4

### Phase 2 : Sécurité (Demain)
- [ ] Créer table `kp_staff_tokens`
- [ ] Implémenter `TokenAuthenticator`
- [ ] Créer `CacheMatchService`
- [ ] Intégrer cache dans WsmController
- [ ] Tester auth + cache

### Phase 3 : Tests (J+2)
- [ ] Créer script de test automatique
- [ ] Tester tous endpoints Public
- [ ] Tester tous endpoints WSM
- [ ] Tester endpoints Staff (avec auth)
- [ ] Comparer legacy vs API2

### Phase 4 : Migration Clients (J+3 à J+5)
- [ ] Créer `useApi2()` pour app2
- [ ] Migrer app2/scrutineering
- [ ] Créer `useWsm2()` pour app3
- [ ] Migrer app3/match-sheet
- [ ] Tests E2E complets

### Phase 5 : Production (J+7)
- [ ] Déployer en pre-production
- [ ] Tests 24h en pre-production
- [ ] Déployer en production
- [ ] Monitoring actif

---

## 📊 Résumé des Fichiers Créés

### Documentation
1. ✅ [API2_MIGRATION_TEST_PLAN.md](API2_MIGRATION_TEST_PLAN.md) - Plan complet de test et migration
2. ✅ [SYMFONY_7.4_LTS_MIGRATION.md](../migrations/SYMFONY_7.4_LTS_MIGRATION.md) - Guide migration Symfony 7.4
3. ✅ [API2_QUICK_START_GUIDE.md](API2_QUICK_START_GUIDE.md) - Ce guide (actions immédiates)

### Code Modifié
1. ✅ `sources/api2/composer.json` - Mis à jour pour Symfony 7.4.*

### Code À Créer
1. ⏳ `sources/api2/src/Security/TokenAuthenticator.php`
2. ⏳ `sources/api2/src/Service/CacheMatchService.php`
3. ⏳ `sources/app2/composables/useApi2.ts`
4. ⏳ `sources/app3/composables/useWsm2.ts`
5. ⏳ `DOC/developer/scripts/test_api2_public.sh`

### BDD
1. ⏳ Table `kp_staff_tokens` (à créer)

---

## 🎯 Commandes Rapides

```bash
# Migration Symfony 7.4
make composer_update_api2
make api2_cache_clear

# Tests
curl -k "https://kpi.localhost/api2/events/std"
curl -k "https://kpi.localhost/api2/stars"

# Accès PHP
make php_bash
cd /var/www/html/api2

# Logs
make dev_logs
tail -f sources/api2/var/log/dev.log
```

---

**Prochaine action** : Exécuter `make composer_update_api2` pour migrer vers Symfony 7.4 LTS !
