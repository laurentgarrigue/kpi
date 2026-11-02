# Durcissement authentification — checklist de déploiement par phase

**Créée le** : 1 novembre 2026 — **À compléter à chaque phase**
**Contexte** : [AUTH_HARDENING.md](AUTH_HARDENING.md) · runbook général [DEPLOYMENT_RUNBOOK.md](../infrastructure/DEPLOYMENT_RUNBOOK.md)

> **Convention** (comme le [runbook](../infrastructure/DEPLOYMENT_RUNBOOK.md)) : ⌨️ poste de dev ·
> 🖥 VPS (SSH, en tant que `laurent` sauf mention) · 🌐 interface GitHub · 📦 autre dépôt.

**Règle d'or** : à chaque phase, on vérifie **aussi** qu'app2 (publique) et le site public (app3) ne sont pas impactés.

---

## Phase 1a — Rate-limit, verrouillage, journal (backend uniquement)

Branche : `claude/enhance-user-authentication-33dZW` · Spec : [AUTH_HARDENING.md § 2](AUTH_HARDENING.md).

### 1a.1 ⌨️ Préparation locale

```bash
git fetch origin
git checkout claude/enhance-user-authentication-33dZW
git pull
make docker_dev_up
make api2_composer_install   # installe symfony/rate-limiter ajouté dans composer.json
make api2_migrations_migrate # crée la table kp_user_login_history
make api2_restart            # worker FrankenPHP recharge le kernel
```

| # | Action | Résultat attendu | ☑ |
|---|---|---|---|
| 1 | `make api2_logs lines=50` | Pas d'erreur de boot ; `rate_limiter` chargé | ☐ |
| 2 | `make backend_bash` puis `mysql -u root -proot kpi -e "DESCRIBE kp_user_login_history"` | Table présente avec les colonnes attendues | ☐ |
| 3 | Dans Swagger `https://kpi.localhost/api2/doc` | Endpoint `GET /auth/login-history` visible dans le tag « 20. App4 - Authentication » | ☐ |

### 1a.2 ⌨️ Tests automatisés

```bash
make api2_bash
# dans le conteneur :
API2_TEST_DB=1 vendor/bin/phpunit --testsuite=unit --filter LoginAttemptLogger
API2_TEST_DB=1 vendor/bin/phpunit --testsuite=integration --filter AuthLoginHardening
```

| # | Test | Résultat attendu | ☑ |
|---|---|---|---|
| 1 | `LoginAttemptLoggerTest` | PASS — 4 reasons distinctes correctement journalisées | ☐ |
| 2 | `AuthLoginHardeningTest::testRateLimitByIp` | PASS — 6ᵉ appel retourne 429 avec `Retry-After` | ☐ |
| 3 | `AuthLoginHardeningTest::testAccountLockoutAfterFiveFailures` | PASS — bon mot de passe refusé avec 423 pendant 15 min | ☐ |
| 4 | `AuthLoginHardeningTest::testSuccessResetsFailureCounter` | PASS | ☐ |
| 5 | `AuthLoginHardeningTest::testLoginHistoryWrittenOnEveryAttempt` | PASS — chaque appel insère une ligne | ☐ |
| 6 | `AuthLoginHardeningTest::testMd5FallbackMarkedLegacyHash` | PASS — colonne `legacy_hash=1` sur ancien compte | ☐ |

### 1a.3 ⌨️ Tests manuels

| # | Action | Résultat attendu | ☑ |
|---|---|---|---|
| 1 | `curl -X POST https://kpi.localhost/api2/auth/login -H 'Content-Type: application/json' -d '{"username":"TADMIN","password":"wrong"}'` répété 6 fois | 5ᵉ : 401 ; 6ᵉ : **429** avec en-tête `Retry-After` | ☐ |
| 2 | `curl -X POST https://kpi.localhost/api2/auth/login -H 'Content-Type: application/json' -d '{"username":"TADMIN","password":"wrong"}'` répété 5 fois puis un appel avec le bon mot de passe | 6ᵉ : **423 Locked**, pas d'émission de JWT | ☐ |
| 3 | Attendre 15 min, retenter avec le bon mot de passe | 200 — JWT émis | ☐ |
| 4 | Login OK en profil 1 puis `GET /api2/auth/login-history?code=TADMIN` | Liste des tentatives récentes, 200 | ☐ |
| 5 | Login OK en profil 8, puis `GET /api2/auth/login-history?code=TADMIN` | **403** (pas son propre compte) | ☐ |
| 6 | Login OK en profil 8, puis `GET /api2/auth/login-history?code=<son code>` | 200 — n'a que son propre historique | ☐ |

### 1a.4 ⌨️ Non-régression

| # | Action | Résultat attendu | ☑ |
|---|---|---|---|
| 1 | Login app4 standard depuis un navigateur frais | OK, flux `/select-mandate` ou `/` inchangé | ☐ |
| 2 | Reset de mot de passe (POST `/auth/reset-password`) depuis un email valide | OK, politique de complexité toujours appliquée | ☐ |
| 3 | app2 `https://app.kpi.localhost` | Chargement OK (pas d'auth, pas d'impact) | ☐ |
| 4 | Scrutineering `/staff/{id}` (profil 10) | Login OK, pages accessibles | ☐ |
| 5 | `kp_journal` : vérifier qu'une ligne « Connexion Admin » est toujours écrite à chaque login réussi (conservation du journal métier legacy) | OK | ☐ |

### 1a.5 🌐 Pre-production (merge sur `main` → auto-deploy preprod)

| # | Action | Résultat attendu | ☑ |
|---|---|---|---|
| 1 | Merge PR | CI verte, auto-deploy preprod | ☐ |
| 2 | 🖥 `make api2_migrations_migrate` sur preprod | Migration appliquée, pas d'erreur | ☐ |
| 3 | 🖥 `make api2_restart` sur preprod | Worker reparti | ☐ |
| 4 | ⌨️ Smoke-test login preprod | OK | ☐ |
| 5 | 🖥 `make api2_logs lines=100` | Pas d'erreur après 10 min de trafic | ☐ |
| 6 | 🖥 `mysql kpi_preprod -e "SELECT COUNT(*), success FROM kp_user_login_history GROUP BY success"` après quelques heures | Mix succès / échecs cohérent avec le trafic | ☐ |

### 1a.6 🖥 Production (déploiement manuel après validation preprod)

| # | Action | Résultat attendu | ☑ |
|---|---|---|---|
| 1 | 🖥 `cd ~/kpi && git pull && make api2_composer_install` | Composer OK, `symfony/rate-limiter` installé | ☐ |
| 2 | 🖥 `make api2_migrations_migrate` | Table créée, 1 migration appliquée | ☐ |
| 3 | 🖥 `make api2_restart` | Worker reparti, pas d'erreur | ☐ |
| 4 | 🖥 `make api2_logs lines=50` | Boot sans erreur | ☐ |
| 5 | ⌨️ Login prod sur app4 avec un compte réel | OK | ☐ |
| 6 | 🖥 Monitoring sur 48 h : `SELECT DATE(attempted_at), success, COUNT(*) FROM kp_user_login_history GROUP BY 1,2 ORDER BY 1 DESC LIMIT 20` | Volume compatible avec le trafic nominal ; pas de pic d'échecs anormal | ☐ |
| 7 | 🖥 Programmer la purge : ajouter au cron `0 3 * * * /path/to/app:auth:prune-login-history --older-than=90d` (après livraison de la commande) | Cron OK | ☐ |

### 1a.7 Rollback

Si un incident survient (faux positif rate-limit massif, erreur 500 sur `/auth/login`) :

1. 🖥 `cd ~/kpi && git revert <sha> && git push` (ou `git checkout <sha-précédent> -- sources/api2/`).
2. 🖥 `make api2_restart`.
3. **La migration ne se rejoue pas en arrière** : la table `kp_user_login_history` reste. C'est intentionnel (pas de perte de données). Si on redéploie plus tard, aucun travail à refaire.

### 1a.8 Post-déploiement — suivi MD5

À J+30, J+60, J+90 après la mise en prod :

```sql
SELECT
  DATE(attempted_at) AS jour,
  SUM(CASE WHEN success=1 AND legacy_hash=1 THEN 1 ELSE 0 END) AS md5_legacy,
  SUM(CASE WHEN success=1 THEN 1 ELSE 0 END) AS total_success,
  ROUND(100 * SUM(CASE WHEN success=1 AND legacy_hash=1 THEN 1 ELSE 0 END) / NULLIF(SUM(CASE WHEN success=1 THEN 1 ELSE 0 END), 0), 2) AS pct
FROM kp_user_login_history
WHERE attempted_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(attempted_at)
ORDER BY 1 DESC;
```

- Si **< 1 %** des logins quotidiens utilisent encore le hash MD5 pendant 2 semaines consécutives → ouvrir PR « Retrait du fallback MD5 » (phase 1a-bis).
- Envoyer un email aux comptes concernés 2 semaines avant pour les forcer à se reconnecter.

---

## Phase 1b — Cookies HttpOnly, refresh token, email nouvel appareil

Branche : `claude/enhance-user-authentication-33dZW` · Spec : [AUTH_HARDENING.md § 3](AUTH_HARDENING.md).

**Approche hybride** retenue : app4 continue de recevoir le JWT dans le JSON du login (compatibilité) **et** reçoit les cookies `kpi_access` + `kpi_refresh`. L'extracteur `cookie` de lexik_jwt est activé en parallèle du header `Authorization: Bearer`. Les navigateurs ne subissent aucune reconnexion forcée.

### 1b.1 ⌨️ Préparation locale

```bash
git fetch origin && git checkout claude/enhance-user-authentication-33dZW && git pull
make docker_dev_up
make api2_migrations_migrate   # crée kp_user_refresh_token + kp_user_known_device
make api2_restart
```

### 1b.2 ⌨️ Tests automatisés

```bash
make api2_bash
API2_TEST_DB=1 vendor/bin/phpunit --testsuite=integration --filter AuthRefreshSession
```

| # | Test | Résultat attendu | ☑ |
|---|---|---|---|
| 1 | `testLoginSetsBothCookies` | kpi_access + kpi_refresh HttpOnly, Secure, SameSite=Lax | ☐ |
| 2 | `testRefreshSessionRotatesRefreshToken` | nouveau token émis, ancien révoqué, `replaced_by` positionné | ☐ |
| 3 | `testReusingRevokedRefreshTokenRevokesAllActiveTokens` | 401 + révocation chaîne complète (détection rejeu) | ☐ |
| 4 | `testLogoutRevokesRefreshTokenAndClearsCookies` | revoked_at non nul + cookies effacés | ☐ |
| 5 | `testFirstLoginRegistersDeviceAndSecondFromSameFingerprintDoesNotCreateDuplicate` | pas de duplicata (IP /24 + UA famille) | ☐ |
| 6 | `testDeviceRevokeTokenRevokesDeviceAndAllRefreshTokens` | appareil + sessions révoqués ; 2ᵉ appel = 404 | ☐ |

### 1b.3 ⌨️ Tests manuels

| # | Action | Résultat attendu | ☑ |
|---|---|---|---|
| 1 | Login app4 depuis un navigateur frais | OK, DevTools → onglet Application → Cookies : `kpi_access` et `kpi_refresh` visibles (HttpOnly ✓) | ☐ |
| 2 | Effacer `localStorage.kpi_admin_token` manuellement, recharger | App4 reste connectée (le cookie `kpi_access` suffit à api2) | ☐ |
| 3 | Attendre > JWT_TTL, naviguer, puis déclencher un refresh (via refreshToken() de useAuth) | Nouveau JWT en cookie, pas de 401 utilisateur | ☐ |
| 4 | Click « Déconnexion » | `/auth/logout` → cookies effacés, redirection login | ☐ |
| 5 | Login depuis un nouveau navigateur / appareil | Email d'alerte reçu avec lien de révocation | ☐ |
| 6 | Cliquer le lien de révocation de l'email | Appareil révoqué, toutes sessions actives terminées (le navigateur d'origine est déconnecté au prochain refresh) | ☐ |

### 1b.4 CORS et domaines (préprod/prod)

- `CORS_ALLOW_ORIGIN` dans `sources/api2/.env` doit couvrir l'origine exacte d'app4 (jamais `*` — bloqué par `allow_credentials: true`). Vérifier qu'en préprod/prod les deux sites (api2 et app4) partagent le même domaine racine (`kayak-polo.info`) pour que SameSite=Lax envoie le cookie en navigation.
- `make api2_logs` après le premier login : chercher `Set-Cookie` dans la réponse, pas d'erreur CORS côté app4 (DevTools Console).

### 1b.5 Rollback

Si un incident survient (email d'alerte en boucle, cookie non envoyé, 401 massif) :

1. `git revert <sha>` ciblé sur `sources/api2/src/Controller/AdminAuthController.php` et `sources/api2/config/packages/lexik_jwt_authentication.yaml` (retour à l'extraction Authorization-only).
2. `make api2_restart`.
3. Les tables `kp_user_refresh_token` et `kp_user_known_device` restent — pas de perte de données, pas de replay dangereux (plus personne ne lit la table).

---

## Phase 2 — OAuth Google (à venir)

Statut : 📋 spécifiée. Pré-requis :

1. Projet Google Cloud Console créé, OAuth consent screen validé (interne ou externe selon public visé).
2. `GOOGLE_OAUTH_CLIENT_ID` et `GOOGLE_OAUTH_CLIENT_SECRET` provisionnés dans Symfony Secrets.
3. URL de callback whitelistée dans Google Cloud : `https://kayak-polo.info/api2/auth/google/callback` (+ preprod).

---

## Phase 3 — TOTP profils ≤ 3 (à venir)

Statut : 📋 spécifiée. Pré-requis :

1. Phases 1a, 1b et 2 en production.
2. Liste des utilisateurs profil ≤ 3 identifiée (environ 20-30 personnes).
3. Email d'information rédigé et envoyé 2 semaines avant la date d'activation forcée.
4. Procédure « téléphone perdu » documentée côté support (contact super-admin pour reset).
