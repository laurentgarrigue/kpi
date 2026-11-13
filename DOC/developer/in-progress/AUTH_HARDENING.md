# Durcissement de l'authentification app4 / api2

**Date** : 1 novembre 2026 — plan initial ; 2 novembre 2026 — phases 1b et 2 livrées ; **compléments post-review 2b (gestion appareils + /profile/security) à livrer**
**Statut** : 🚧 En cours — Phases 1a, 1b et 2 livrées sur `main` ; **compléments 1b-bis** (listing appareils, révocation sans email, page `/profile/security`) en cours ; phase 3 (TOTP ciblé) à venir
**Périmètre** : authentification des utilisateurs d'**app4** uniquement (le legacy admin disparaît au profit d'app4, cf. [ROADMAP_KPI.md](ROADMAP_KPI.md)). Toute la logique est centralisée dans **api2** (`AdminAuthController`, `UserProvider`, nouveau `src/Security/`).
**Hors périmètre** : app2 (publique, sans auth), app3 (publique, sans auth), legacy `admin/` et `Login.php` (voués à disparaître — pas de rétrofit sécurité).

---

## 0. Résumé

| Phase | Contenu | Friction utilisateur | Statut |
|---|---|---|---|
| **1a** | Rate-limit login, verrouillage progressif sur échec, journal `kp_user_login_history`, suppression différée du fallback MD5 | Nulle en nominal | ✅ Implémentée (01/11/2026) |
| **1b** | JWT en cookie `HttpOnly` + refresh token glissant (rotation + détection de rejeu), `/auth/logout`, notification email sur nouvel appareil + lien de révocation | Nulle (invisible) | ✅ Implémentée (02/11/2026) — transition hybride : app4 continue d'utiliser le JWT en body **ET** reçoit les cookies, migration pure cookie en v+1 |
| **1b-bis** | **Gestion utilisateur de ses appareils et sessions** : lister ses appareils, en révoquer un sans dépendre de l'email d'alerte, « déconnecter toutes les autres sessions », page `/profile/security` qui concentre déliement Google et (à venir) TOTP | Positive (autonomie) | 🚧 En cours (02/11/2026) |
| **1b-ter** | **Continuité de session** : jamais déconnecté pendant une gestion sportive ou un match (renouvellement fiable, rotation tolérante, zéro saisie perdue en Scoring) — § 3ter | Positive | 🚧 Lot A en cours (13/11/2026) |
| **2** | OAuth Google en **option** sur la page `/login` (bouton « Continuer avec Google »), lien/déliement depuis `/profile/security` | **Négative** (facilite) | ✅ Implémentée (02/11/2026) |
| **3** | TOTP (Google Authenticator / Authy / 1Password) **obligatoire** pour les profils `≤ 3`, **optionnel** pour les autres ; appareil de confiance 30 jours ; 10 codes de récupération | Faible, ciblée | 📋 Spécifiée, non implémentée |

> **Principe directeur** : la vraie faille aujourd'hui n'est pas l'absence de 2FA — c'est le JWT en `localStorage` (volé par un XSS), l'absence totale de rate-limit (bruteforce libre) et l'absence de signal utilisateur sur les connexions suspectes. On corrige d'abord les fondations invisibles, puis on ajoute du 2FA là où il protège vraiment (profils admin).

---

## 1. État des lieux (état `main` au 01/11/2026)

### 1.1 Flux actuel

- **app4** `pages/login.vue` : saisie `username` + `password`, `POST /auth/login`, JWT stocké en `localStorage` (`kpi_admin_token`), redirection `/select-mandate` ou `/`.
- **api2** `AdminAuthController::login` : récupère l'utilisateur via `UserProvider` (table `kp_user`, match `Code` ou `Mail` si unique), vérifie le mot de passe en **bcrypt avec fallback MD5 auto-upgradé**, charge les mandats, loggue dans `kp_journal` (ligne « Connexion Admin »), renvoie le JWT.
- **UserProvider** : lecture `kp_user` + jointure `kp_licence` ; le login par `Mail` ne marche que si le mail résout à **un seul** compte (comptes partagés possibles).
- **PasswordPolicy** : 10 car. min, 1 maj / 1 min / 1 chiffre / 1 spécial — appliquée au reset et au changement forcé, **pas au login** (ancien compte autorisé à conserver un mot de passe plus faible).
- **Reset** : `POST /auth/reset-password` avec token stocké 48 h dans `kp_user_token`.
- **JWT** : lexik_jwt, extracteur `Authorization: Bearer`, cookie `enabled: false`, TTL via `JWT_TTL`.

### 1.2 Faiblesses identifiées

| Faille | Scénario d'attaque | Gravité | Phase qui corrige |
|---|---|---|---|
| JWT en `localStorage` | Un XSS n'importe où dans app4 → vol du token → session volée pendant toute la durée du TTL (pas de révocation) | ★★★★★ | **1b** |
| Pas de rate-limit sur `/auth/login` | Bruteforce vitesse réseau, par IP ou par compte | ★★★★ | **1a** |
| Pas de verrouillage sur échecs | Un compte à mot de passe faible tombe en quelques heures | ★★★★ | **1a** |
| Pas de notification « nouvelle connexion » | L'utilisateur ne voit jamais qu'un tiers s'est connecté | ★★★ | **1b** |
| MD5 fallback encore actif | Dump de la base ⇒ cassage trivial des hashes MD5 restants | ★★ | **1a** (plan de retrait) |
| Pas de 2FA sur profils admin | Phishing réussi sur un super-admin → prise de contrôle totale | ★★★ | **3** |
| `kp_user.Mail` non unique | Impossible de bâtir OAuth proprement sur le mail seul | ★★ | **2** (lier par `sub` OAuth) |

### 1.3 Ce qui existe déjà et qu'on garde

- `lexik/jwt-authentication-bundle` : conservé, étendu avec un refresh token custom (phase 1b).
- `UserProvider` : conservé, enrichi au besoin.
- `PasswordPolicy` : conservée, étendue aux profils `≤ 3` (politique plus stricte envisagée en phase 3).
- `kp_journal` : conservé comme journal fonctionnel ; le journal de sécurité va dans une **nouvelle table dédiée** `kp_user_login_history` pour ne pas polluer le journal métier et permettre des index spécifiques (lookup par `code` + fenêtre temporelle).

---

## 2. Phase 1a — Rate-limit, verrouillage, journal (implémentation en cours)

### 2.1 Objectifs

Rendre le bruteforce impraticable et donner à l'équipe technique un journal exploitable pour détecter les attaques, **sans aucun changement visible côté utilisateur en usage nominal**.

### 2.2 Critères d'acceptation

| ID | Critère | Validation |
|---|---|---|
| AUTH-RL-01 | Après **5 échecs** d'authentification depuis la **même IP** en 15 min, les tentatives suivantes retournent `429 Too Many Requests` avec en-tête `Retry-After` jusqu'à la fin de la fenêtre | Test d'intégration : 5 appels invalides → 6ᵉ en 429 |
| AUTH-RL-02 | Après **10 échecs** sur le **même code utilisateur** en 15 min (toutes IP confondues), les tentatives retournent `429` même si l'IP est différente | Test d'intégration : 10 appels invalides depuis 2 IP distinctes → 11ᵉ en 429 |
| AUTH-LK-01 | Après **5 échecs consécutifs** sur un même code utilisateur en 15 min, le compte est **verrouillé** pendant 15 min : les tentatives suivantes, **même avec le bon mot de passe**, retournent `423 Locked` jusqu'à expiration | Test d'intégration : 5 échecs + 1 bon mot de passe → 423 |
| AUTH-LK-02 | Un **login réussi** réinitialise le compteur d'échecs du compte | Test d'intégration : 4 échecs + 1 succès → nouveau cycle de 5 échecs possibles |
| AUTH-JL-01 | Chaque tentative (succès ou échec) écrit une ligne dans `kp_user_login_history` avec `code`, `success`, `method` (`password` \| `password_reset`), `ip`, `user_agent`, `failure_reason` (ou `NULL` si succès), `attempted_at` | Test d'intégration : vérification de la ligne après chaque appel |
| AUTH-JL-02 | Le journal distingue les quatre `failure_reason` : `unknown_user`, `bad_password`, `rate_limited`, `account_locked` | Test unitaire du `LoginAttemptLogger` |
| AUTH-JL-03 | Le journal est accessible via un endpoint `GET /auth/login-history?code=X` réservé au profil 1 (super-admin) et à l'utilisateur lui-même sur son propre compte | Test d'intégration : lecture OK pour `self`, 403 pour un autre utilisateur |
| AUTH-MD-01 | Chaque fallback MD5 (ancienne entrée upgradée) est loggué avec `method: password` et une colonne `legacy_hash: true` → métrique de progression du retrait | Test d'intégration : login d'un compte MD5-only + vérification `legacy_hash = true` |
| AUTH-MD-02 | Le fallback MD5 reste **actif** dans cette phase (zero-downtime pour les utilisateurs qui ne se sont jamais reconnectés) ; son retrait fera l'objet d'une PR dédiée après observation du journal pendant au moins 3 mois (seuil : < 1 % des logins quotidiens en MD5) | Documenté dans la rollout checklist |

### 2.3 Choix techniques

- **Rate-limiter** : `symfony/rate-limiter`, policy `sliding_window`, backend **cache local Symfony** (adapter `cache.app`, basé sur APCu / FrankenPHP worker mémoire). Pas de Redis : KPI n'en a pas et une seule instance api2 tourne par environnement, donc la mémoire du process suffit.
    - `kpi_auth_login_ip` : limite 5 / 15 min / IP
    - `kpi_auth_login_user` : limite 10 / 15 min / `username` normalisé

- **Verrouillage** : pas de nouvelle colonne sur `kp_user` (table partagée avec le legacy — on évite les écritures depuis api2 sur ce schéma). Le verrouillage est **dérivé** du journal : si 5 lignes `success=false` dans les 15 dernières minutes pour un `code`, le compte est verrouillé. Avantage : stateless, auto-expirant, pas de job de déblocage. Le super-admin peut forcer le déverrouillage via la nouvelle route `DELETE /auth/login-history/{code}` qui purge les échecs récents (opération auditée).

- **Journal** : nouvelle table `kp_user_login_history`.

    ```sql
    CREATE TABLE kp_user_login_history (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT,
      user_code VARCHAR(8) DEFAULT NULL,        -- NULL si tentative sur un code inconnu
      success TINYINT(1) NOT NULL,
      method VARCHAR(32) NOT NULL,              -- 'password', 'password_reset', futurs 'totp', 'oauth_google'
      failure_reason VARCHAR(32) DEFAULT NULL,  -- 'unknown_user', 'bad_password', 'rate_limited', 'account_locked'
      legacy_hash TINYINT(1) NOT NULL DEFAULT 0,
      ip VARCHAR(45) NOT NULL,                  -- IPv4 ou IPv6
      user_agent VARCHAR(255) DEFAULT NULL,     -- tronqué à 255
      attempted_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY idx_user_time (user_code, attempted_at),
      KEY idx_ip_time (ip, attempted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ```

- **Rétention** : purge via une commande console `app:auth:prune-login-history --older-than=90d` (à ajouter au cron). Le journal est utile pour l'investigation récente ; au-delà de 90 jours, seules les statistiques agrégées comptent (TODO phase 1b : table `kp_user_login_stats_monthly`).

### 2.4 Fichiers touchés

- **Nouveaux**
  - `sources/api2/src/Security/LoginAttemptLogger.php` — service d'écriture + lecture du journal
  - `sources/api2/src/Security/LoginRateLimiter.php` — façade au-dessus des deux limiters
  - `sources/api2/migrations/VersionYYYYMMDDHHMMSS_create_login_history.php`
  - `sources/api2/config/packages/rate_limiter.yaml`
  - `sources/api2/tests/Unit/Security/LoginAttemptLoggerTest.php`
  - `sources/api2/tests/Integration/AuthLoginHardeningTest.php`
- **Modifiés**
  - `sources/api2/src/Controller/AdminAuthController.php` — ajout du rate-limit, du lockout, du journal, exposition `GET /auth/login-history`
  - `SQL/fixtures/schema.sql` — ajout de la table pour les tests
  - `sources/api2/composer.json` — ajout de `symfony/rate-limiter`

### 2.5 Non-objectifs

- Pas de changement côté app4 (ni UI, ni store) : la Phase 1a est 100 % backend.
- Pas de notification email : c'est en Phase 1b.
- Pas de refresh token : c'est en Phase 1b.

---

## 3. Phase 1b — Cookie HttpOnly, refresh token, notification nouvel appareil

### 3.1 Objectif

Rendre le vol de JWT inutile et notifier l'utilisateur de toute connexion depuis un appareil inconnu. **Toujours invisible pour l'utilisateur en usage nominal.**

### 3.2 Critères d'acceptation

| ID | Critère |
|---|---|
| AUTH-CK-01 | Le login réussi pose deux cookies : `kpi_access` (JWT, TTL 15 min) et `kpi_refresh` (opaque, TTL 8 h glissant), tous deux `HttpOnly; Secure; SameSite=Lax; Path=/api2` |
| AUTH-CK-02 | `localStorage.kpi_admin_token` n'est plus écrit (ni lu). Le `authStore` ne contient plus de `token` ; `isAuthenticated` est dérivé de l'appel `/auth/me` |
| AUTH-RT-01 | Au bout de 15 min, l'app4 reçoit `401` sur un appel métier ; un intercepteur HTTP appelle `POST /auth/refresh` (envoie le cookie refresh), reçoit un nouveau cookie access, et rejoue la requête de façon transparente |
| AUTH-RT-02 | `POST /auth/logout` invalide côté serveur le refresh token courant (table `kp_user_refresh_token`, flag `revoked_at`) et supprime les cookies |
| AUTH-RT-03 | Un refresh token réutilisé après révocation retourne `401` **et** révoque toute la chaîne de refresh de l'utilisateur (détection de replay) |
| AUTH-DV-01 | Au premier login depuis un couple (IP /24 + user-agent famille) inconnu, un cookie signé `kpi_device` (TTL 60 j) est posé **et** un email est envoyé à l'utilisateur : « Nouvelle connexion depuis Chrome / Paris / 1.2.3.4, le [date]. Si ce n'est pas vous, [lien de révocation]. » |
| AUTH-DV-02 | Le lien de révocation (`GET /auth/device/revoke/{token}`) invalide tous les refresh tokens actifs et déconnecte l'utilisateur |

### 3.3 Choix techniques

- `symfony/security-http` extracteur `cookie` activé pour `kpi_access`.
- Refresh token : chaîne random 64 car. base64url, stockée **hashée** (SHA-256) dans `kp_user_refresh_token (id, user_code, token_hash, device_fingerprint, created_at, expires_at, revoked_at, replaced_by)`.
- Appareil connu : `sha256(ip_class_c + user_agent_family)`, stocké dans `kp_user_known_device (id, user_code, fingerprint, label, last_seen_at, revoked_at)`.
- Email via le `NotificationService` existant (déjà injecté dans `AdminAuthController`).

### 3.4 Impact app4

- `composables/useAuth.ts` : `login` et `refresh` n'exposent plus de `token` ; le `fetch` métier s'appuie sur `credentials: 'include'`.
- `stores/authStore.ts` : suppression de `token`, `localStorage.*_token`. Les autres clés (user, mandates…) restent en `localStorage` par confort (cache UI non sensible).
- `plugins/auth-refresh.ts` : nouvel intercepteur qui, sur `401`, retente une fois après `POST /auth/refresh`.
- `nuxt.config.ts` : passer `app.ssrAPIBaseUrl` ↔ `app.clientAPIBaseUrl` sur le même domaine (contrainte cookie).

---

## 3bis. Phase 1b-bis — Gestion utilisateur des appareils et sessions

### 3bis.1 Problème constaté

Après la phase 1b, le seul moyen de révoquer un appareil est de cliquer le lien reçu dans l'email d'alerte « nouvelle connexion ». Trois trous fonctionnels :

1. Si l'utilisateur **perd l'email** (supprimé, filtré en spam, boîte purgée), il n'a plus aucun recours autonome.
2. Si l'appareil était **déjà connu** (ex. retour sur un ordinateur partagé où l'utilisateur ne veut plus apparaître), aucun email n'est émis, donc aucun lien de révocation n'existe.
3. Il n'existe aucun endroit où l'utilisateur peut **voir la liste de ses sessions actives** et vérifier qu'il n'y a pas d'accès indésirable.

Seul workaround actuel : un super-admin édite `kp_user_known_device` et `kp_user_refresh_token` en SQL. Ce n'est pas une solution opérationnelle.

### 3bis.2 Critères d'acceptation

| ID | Critère | Validation |
|---|---|---|
| AUTH-DV-03 | `GET /auth/devices` retourne la liste des appareils actifs du compte authentifié (`id`, `label`, `created_at`, `last_seen_at`, `isCurrent: boolean`), triée par `last_seen_at` DESC. Les appareils déjà révoqués ne sont pas inclus. | Test : liste contient tous les enregistrés, pas les révoqués, et `isCurrent=true` pour celui qui correspond à l'empreinte de la requête. |
| AUTH-DV-04 | `POST /auth/devices/{id}/revoke` révoque l'appareil `{id}` **s'il appartient à l'utilisateur courant** et invalide **tous les refresh tokens associés à cet appareil** (via `device_fingerprint`). Les autres appareils du même compte restent intacts. 404 si l'`id` n'appartient pas à l'utilisateur, 200 si déjà révoqué (idempotent). | Test : révocation d'un autre device du MÊME user tue ses refresh tokens, pas ceux du device courant ; 404 pour un device d'un autre user ; 2ᵉ appel = 200 sans effet. |
| AUTH-DV-05 | `POST /auth/devices/revoke-others` révoque tous les appareils du compte **sauf celui qui porte la requête** ; invalide tous les refresh tokens associés à ces appareils ; conserve la session courante active. | Test : après l'appel, un appel suivant à `GET /auth/devices` ne renvoie plus que le device courant ; le refresh token courant reste valide pour un `POST /auth/refresh-session`. |
| AUTH-DV-06 | Les trois endpoints exigent un JWT valide (401 sans). Un utilisateur ne peut jamais voir ni toucher les devices d'un autre utilisateur. | Test : appel sans token → 401 ; `POST /auth/devices/{id}/revoke` avec l'id d'un autre compte → 404 (pas 403 pour éviter l'énumération). |
| AUTH-DV-07 | La révocation d'un appareil est journalisée dans `kp_journal` (action `Révocation appareil`, texte « Révocation depuis /profile/security » ou « Révocation depuis email d'alerte » selon la source), au même titre que la révocation par lien email. | Vérification sur `kp_journal`. |
| AUTH-PS-01 | app4 expose la page `/profile/security` (JWT requis) qui affiche : la liste des appareils actifs (avec « Cet appareil » marqué), un bouton « Révoquer » par ligne (sauf la ligne courante où le bouton est désactivé), un bouton « Déconnecter toutes les autres sessions », et — si l'utilisateur a un lien Google — un bouton « Délier mon compte Google ». | Smoke-test manuel. |
| AUTH-PS-02 | Si l'utilisateur délie Google alors qu'il n'a pas de mot de passe actif (`Pwd = ''`), le bouton est désactivé et un message indique de définir d'abord un mot de passe (cf. AUTH-OA-06 déjà implémenté côté api2). | Smoke-test manuel + réponse `409 PASSWORD_REQUIRED` déjà couverte par `AuthOAuthGoogleTest`. |

### 3bis.3 Choix techniques

**Service `KnownDeviceService`** — nouvelles méthodes publiques :
- `listActiveFor(string $userCode): array` — retourne les lignes `kp_user_known_device` non révoquées, enrichies au besoin.
- `currentFingerprint(Request $request): string` — expose l'empreinte calculée pour la requête courante (déjà interne, à rendre accessible).
- `revokeById(int $deviceId, string $userCode, RefreshTokenService $refreshTokens, string $journalText): bool` — révoque un appareil appartenant à `$userCode`, retourne `true` si ligne touchée, `false` si inexistant / étranger / déjà révoqué.
- `revokeAllOthersFor(string $userCode, string $currentFingerprint, RefreshTokenService $refreshTokens): int` — révoque tous sauf celui qui correspond à `$currentFingerprint`.

**Service `RefreshTokenService`** — nouvelle méthode :
- `revokeByDeviceFingerprint(string $userCode, string $fingerprint): void` — toutes les sessions d'un device.

**Contrôleur** — nouveau `DeviceController` dédié (sépare la préoccupation « gestion appareils » de l'auth brute, et aligne avec `OAuthController`). Routes :
- `GET /auth/devices` (JWT)
- `POST /auth/devices/{id}/revoke` (JWT)
- `POST /auth/devices/revoke-others` (JWT)

**Pourquoi ne pas étendre `GET /auth/device/revoke/{token}` ?** Deux surfaces d'autorisation différentes :
- Lien email : **pas de JWT**, preuve = token signé, protège contre un attaquant sans session.
- Endpoints utilisateur : **JWT exigé**, protège l'énumération et l'IDOR.

Les mélanger brouillerait la lecture et la revue sécurité.

**Détection de l'appareil courant** : l'empreinte est recalculée à partir de la requête ; on compare l'id de la ligne retournée par la query de l'utilisateur. Pas de nouvelle colonne.

### 3bis.4 Fichiers touchés

- **Nouveaux**
  - `sources/api2/src/Controller/DeviceController.php`
  - `sources/api2/tests/Integration/AuthDevicesTest.php`
  - `sources/app4/pages/profile/security.vue`
- **Modifiés**
  - `sources/api2/src/Security/KnownDeviceService.php` — 4 nouvelles méthodes publiques
  - `sources/api2/src/Security/RefreshTokenService.php` — `revokeByDeviceFingerprint`
  - `sources/api2/config/packages/security.yaml` — ouvrir `^/auth/devices` à `ROLE_USER`
  - `sources/app4/i18n/locales/{fr,en}.json` — section `profile.security.*`
  - app4 : lien vers `/profile/security` dans le menu utilisateur (layout admin)

### 3bis.5 Non-objectifs (déjà prévus plus tard)

- Pas de « renommer un appareil » : le label est auto (famille UA + IP) et n'ajoute rien de métier. V+1 si demandé.
- Pas de « notifier par email la révocation depuis /profile/security » : l'utilisateur est l'auteur, l'alerte serait du bruit.
- Pas de limite anti-brute sur ces endpoints : JWT valide requis, et une révocation d'appareil n'ouvre aucun accès (elle en ferme).

---

## 3ter. Continuité de session — jamais déconnecté pendant une gestion sportive

### 3ter.1 Problème constaté (12/11/2026)

Des utilisateurs d'app4 ont été renvoyés sur `/login` en pleine gestion sportive, y compris pendant un match
sur la console Scoring. L'audit du code a trouvé plusieurs causes, qui existent dans tous les environnements :

| # | Cause | Effet |
|---|---|---|
| 1 | `refreshToken()` n'utilisait le cookie `kpi_refresh` (`/auth/refresh-session`) que si **aucun** JWT n'était stocké ; sinon il appelait `/auth/refresh`, qui exige un JWT **valide** | JWT expiré → 401 → renouvellement en 401 → déconnexion, alors que le cookie aurait suffi |
| 2 | Renouvellement préventif réservé aux profils **< 7** | Table de marque (9) et consultation (8) jamais renouvelées à l'avance |
| 3 | app4 supposait un JWT de 24 h (`tokenTtlSeconds = 86400`), quelle que soit la valeur réelle de `JWT_TTL` | Avec un `JWT_TTL` court, le serveur rejette un jeton qu'app4 croit valide |
| 4 | Rotation du refresh token sans délai de grâce : un ancien cookie présenté de nouveau révoque **toutes** les sessions du compte | Deux onglets qui renouvellent en même temps, une réponse perdue sur un wifi de gymnase, un compte de table de marque partagé entre plusieurs terrains |
| 5 | Cookie refresh de 8 h **absolues** s'il n'est jamais présenté | Une journée de tournoi dépasse 8 h |
| 6 | Au rechargement, `initAuth()` vidait la session dès que l'expiration locale était passée, sans essayer le cookie | Un F5 ou une mise en veille renvoyait au login |
| 7 | `upload()` et `getBlob()` déconnectaient sur 401 sans essayer de renouveler | Un export PDF déconnectait |
| 8 | Sur la console Scoring, un 401 annule le fait de jeu saisi puis quitte la page | Saisie perdue en plein match |

### 3ter.2 Comportement attendu (règles)

1. **Aucune expiration pendant l'usage actif** : tant que l'application est ouverte, la session se prolonge d'elle-même, quel que soit le profil.
2. **Un 401 n'est jamais une déconnexion immédiate** : on tente d'abord un renouvellement, puis on rejoue la requête une fois.
3. **On ne déconnecte que si le serveur refuse le renouvellement.** Un renouvellement *injoignable* (réseau, délai dépassé, 5xx) laisse la session en place et affiche une erreur réseau.
4. **Aucune saisie perdue** (lot C) : une action refusée pour une raison d'authentification est conservée et rejouée après reconnexion.
5. **La déconnexion forcée est réservée aux actes volontaires** : déconnexion manuelle, révocation d'appareil, compte désactivé.

### 3ter.3 Lot A — app4 : renouvellement fiable (critères d'acceptation)

| ID | Critère | Test |
|---|---|---|
| AUTH-SC-01 | L'expiration de la session est lue dans le JWT (claims `iat` et `exp`), jamais supposée. Un jeton illisible est traité comme expiré | `tests/unit/session.spec.ts` |
| AUTH-SC-02 | Renouvellement préventif pour **tous les profils** dès qu'il reste moins de `max(60 s, 20 % de la durée de vie)` ; vérifié chaque minute, au retour de l'onglet au premier plan, au retour du réseau et au retour du focus | `session.spec.ts` (seuil) + recette manuelle |
| AUTH-SC-03 | Choix de la route : JWT encore valide (marge de 30 s) → `POST /auth/refresh` (sans rotation du cookie, donc sans risque de rejeu) ; JWT expiré, absent ou rejeté → `POST /auth/refresh-session` (cookie). Un `401` de `/auth/refresh` bascule sur `/auth/refresh-session` | `session.spec.ts` |
| AUTH-SC-04 | Un seul renouvellement à la fois par navigateur (verrou Web Locks `kpi-auth-refresh`, file d'attente locale à défaut). Une fois le verrou obtenu, si un autre onglet a déjà stocké un jeton plus récent, on l'adopte sans appel réseau | `session.spec.ts` |
| AUTH-SC-05 | La réponse de `/auth/refresh-session` est appliquée (jeton, mandats). Le **mandat actif est conservé** s'il figure toujours dans la liste des mandats (le JWT ne porte pas le mandat : il passe par l'en-tête `X-Active-Mandate`) | `session.spec.ts` |
| AUTH-SC-06 | Au rechargement, si le JWT stocké est expiré mais qu'un utilisateur est connu, app4 restaure la session par le cookie **avant** toute redirection vers `/login`. Refus du serveur → données locales effacées, `/login`. Serveur injoignable → données locales conservées (un rechargement ultérieur réessaiera), `/login` | `session.spec.ts` + recette |
| AUTH-SC-07 | Un `401` sur **toute** requête api2 (`get`/`post`/`put`/`patch`/`del`/`upload`/`getBlob`) déclenche un renouvellement puis **un seul** rejeu. Déconnexion seulement si le renouvellement est refusé ou si le rejeu reçoit encore un `401` | recette |
| AUTH-SC-08 | Renouvellement injoignable → toast réseau, **pas** de déconnexion. Un échec du renouvellement préventif ne déconnecte jamais (la requête métier suivante tranchera) | `session.spec.ts` (classement des réponses) + recette |

**Choix techniques**

- Logique pure dans `sources/app4/utils/session.ts` (lecture du JWT, seuils, choix de route, classement des réponses, conservation du mandat), testée sans Nuxt.
- `composables/useAuth.ts` orchestre : verrou, appel réseau, application du résultat. `refreshToken()` renvoie désormais `'refreshed' | 'denied' | 'unreachable'`.
- `stores/authStore.ts` : plus de `tokenExpiresAt` ni de clé `kpi_admin_token_expires_at` (dérivés du JWT). `initAuth()` renvoie `'authenticated' | 'restorable' | 'none'`.
- `plugins/token-refresh.client.ts` : vérification chaque minute + événements `visibilitychange`, `online`, `focus`. Les minuteries des onglets en arrière-plan sont ralenties par les navigateurs : les événements rattrapent le retard au réveil.
- Le lot A privilégie `/auth/refresh` tant que le JWT est valide : aucune rotation, donc le risque de rejeu (cause 4) n'augmente pas. Le cookie refresh ne sert qu'au rattrapage (JWT expiré, rechargement après une mise en veille).

**Limite connue du lot A** : `/auth/refresh` ne prolonge pas le cookie refresh (8 h absolues). Tant que l'onglet est ouvert, le JWT est renouvelé indéfiniment et le cookie n'est pas nécessaire ; après une mise en veille plus longue que la durée du JWT **et** plus de 8 h après la connexion, une reconnexion reste nécessaire. Corrigé au lot B.

### 3ter.4 Lot B — api2 : rotation tolérante (à spécifier en détail avant code)

| ID | Critère |
|---|---|
| AUTH-SC-10 | Délai de grâce de 60 s : un refresh token remplacé depuis moins de 60 s, présenté **par le même appareil** (`device_fingerprint`), émet un nouveau successeur au lieu d'être traité comme un vol |
| AUTH-SC-11 | Rejeu avéré (hors délai ou autre appareil) : révocation des sessions **de cet appareil**, pas de tout le compte (un compte de table de marque partagé entre terrains ne doit pas tomber d'un coup) |
| AUTH-SC-12 | `/auth/refresh` prolonge aussi le cookie refresh (8 h glissantes), avec un plafond absolu à décider (16 à 24 h) |

### 3ter.5 Lot C — console Scoring : zéro saisie perdue

| ID | Critère |
|---|---|
| AUTH-SC-20 | **Au chargement d'un match** dans la console (ouverture de la page ou « charger un match » dans le même onglet), app4 s'assure que la session reste valable au moins **2 h** et la renouvelle sinon. Un match très long dure environ 1 h (2×10 min + 2×3 min de pause + prolongations + pauses) ; 2 h couvrent ce match et le retard au coup d'envoi. Un échec est signalé **avant** le coup d'envoi (invitation à se reconnecter), pas en plein jeu |
| AUTH-SC-21 | Un `401` définitif sur la console n'en sort pas : une fenêtre de reconnexion s'affiche par-dessus, la console (chrono, période) reste en place |
| AUTH-SC-22 | Les faits de jeu refusés pour une raison d'authentification sont conservés localement (`localStorage`, par match) et rejoués après reconnexion, dédupliqués par `uid` |
| AUTH-SC-23 | Un indicateur discret dans l'en-tête de la console : session valide / renouvellement en cours / à reconnecter |

### 3ter.6 Lot D — exploitation

- Jamais de régénération des clés JWT (`make api2_jwt_generate_keys`, `JWT_PASSPHRASE`) pendant une compétition : tous les JWT en cours deviendraient invalides (à ajouter au [DEPLOYMENT_RUNBOOK](../infrastructure/DEPLOYMENT_RUNBOOK.md)).
- `JWT_TTL` vient de `sources/api2/.env` (Dotenv), pas de l'environnement du conteneur : `docker exec <app>_api2 printenv JWT_TTL` ne l'affiche pas. Le vérifier avec `docker exec <app>_api2 php bin/console debug:dotenv JWT_TTL`.

---

## 4. Phase 2 — OAuth Google (option utilisateur)

### 4.1 Objectif

Permettre à tout utilisateur de se connecter via son compte Google **sans supprimer la voie mot de passe**. C'est une facilitation, pas une contrainte : aucun compte n'est créé automatiquement, le lien se fait sur un `kp_user` existant.

### 4.2 Critères d'acceptation

| ID | Critère |
|---|---|
| AUTH-OA-01 | `GET /auth/google` redirige vers Google avec un `state` signé et un `nonce` |
| AUTH-OA-02 | `GET /auth/google/callback` vérifie `state`, échange le code, lit `sub` et `email` de l'id_token |
| AUTH-OA-03 | Si `(oauth_provider='google', oauth_subject=<sub>)` est connu dans `kp_user_oauth` → connexion directe. Le callback pose les cookies `kpi_access` et `kpi_refresh`, puis redirige vers `/auth/oauth-complete` (app4). Cette page appelle `POST /auth/refresh-session` (qui renvoie `{token, user, hasMandates}` à partir du cookie) pour amorcer `authStore`, puis route vers `/select-mandate` ou `/`. **Sans cette étape, app4 — qui lit encore `localStorage.kpi_admin_token` (transition hybride AUTH-CK-02)  — verrait `isAuthenticated = false` et renverrait l'utilisateur sur `/login` sans message, alors que la session serveur existe.** |
| AUTH-OA-04 | Si `email` correspond à **un seul** `kp_user` avec `email_verified=true` **sans** lien OAuth, la page `/auth/google/link` demande le mot de passe actuel ; sur succès, insertion dans `kp_user_oauth` et connexion |
| AUTH-OA-05 | Si aucun `kp_user` ne correspond, retour `/login` avec message : « Aucun compte KPI ne correspond à cette adresse Google. Contactez votre administrateur. » **Aucune création automatique.** |
| AUTH-OA-06 | Depuis `/profile/security`, l'utilisateur peut délier son compte Google (POST `/auth/google/unlink`) ; si c'est sa seule méthode d'authentification, il doit d'abord définir un mot de passe |
| AUTH-OA-07 | Un utilisateur **déjà authentifié** peut lancer le lien Google depuis `/profile/security` : `GET /auth/google/connect` (JWT exigé) pose un cookie signé `kpi_oauth_link` qui mémorise son `user_code`, puis redirige vers Google. **Cette route couvre explicitement le cas « plusieurs comptes KPI partagent cette adresse Google » (AUTH-OA-04 ambigu) ainsi que le cas « email Google ≠ email KPI »**, tous deux impossibles à résoudre côté callback non authentifié. |
| AUTH-OA-08 | Au `/auth/google/callback`, si le cookie `kpi_oauth_link` est présent et signé valide : pas de résolution par email ni demande de mot de passe. Le lien est écrit **directement** dans `kp_user_oauth` pour le `user_code` du cookie. La session JWT prouve déjà la propriété du compte. Retour `/profile/security?link=ok`. |
| AUTH-OA-09 | Si le `sub` Google issu du callback est déjà lié à **un autre** `kp_user` (`uq_provider_subject`), le flux « lier depuis le profil » retourne `/profile/security?link=error&code=already_linked`. Aucune écriture, aucune erreur silencieuse — l'utilisateur reçoit un message clair qui lui dit de délier d'abord l'autre compte ou de choisir un autre compte Google. |
| AUTH-OA-10 | Côté `/login`, le message « Plusieurs comptes KPI partagent cette adresse. » redirige explicitement l'utilisateur vers la voie AUTH-OA-07 (« Connectez-vous avec votre identifiant puis liez Google depuis votre profil ») — plus de promesse sans implémentation. |

### 4.3 Nouvelle table

```sql
CREATE TABLE kp_user_oauth (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_code VARCHAR(8) NOT NULL,
  provider VARCHAR(20) NOT NULL,       -- 'google', futurs 'microsoft'
  subject VARCHAR(255) NOT NULL,       -- id stable renvoyé par le provider
  email_at_link VARCHAR(100) NOT NULL,
  linked_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_provider_subject (provider, subject),
  UNIQUE KEY uq_user_provider (user_code, provider),
  KEY fk_user (user_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 4.4 Dépendances

- `knpuniversity/oauth2-client-bundle` + `league/oauth2-google` (composer).
- Variables d'environnement `GOOGLE_OAUTH_CLIENT_ID` / `GOOGLE_OAUTH_CLIENT_SECRET` (secrets Symfony) — à créer dans Google Cloud Console.

### 4.5 Phase 2-bis — Flux « lier Google depuis le profil » (post-revue)

**Problème constaté.** Le callback OAuth non authentifié ne peut pas rattacher une identité Google à un compte KPI dans deux cas pourtant courants :

- **E-mail KPI partagé** par plusieurs comptes (`kp_user.Mail` n'est pas unique — on renvoie `oauth_email_ambiguous` et on promet « liez Google depuis votre profil »).
- **E-mail Google ≠ e-mail KPI** (l'utilisateur a un Gmail personnel mais une adresse pro dans `kp_user`) — la résolution par e-mail échoue et renvoie `oauth_no_account`.

Dans les deux cas, la session authentifiée est la **meilleure preuve** de la propriété du compte. On s'en sert pour lier sans ressaisie de mot de passe et sans comparaison d'e-mail.

**Nouvelle route.** `GET /auth/google/connect` (JWT exigé) :

1. Génère un `state` anti-CSRF (comme `/auth/google`).
2. Pose un cookie **signé HMAC** `kpi_oauth_link` contenant `{user_code, state, exp = now + 10 min}`. Le `user_code` est lu depuis la session JWT en cours. La signature empêche un attaquant de forger un cookie qui ferait lier son Google à un autre compte.
3. Redirige vers Google avec le même `state` que le cookie standard `kpi_oauth_state`.

**Nouveau branchement du callback.** Avant la logique « résolution par e-mail » existante, le callback :

1. Si `kpi_oauth_link` présent et signé valide (match `state` et `user_code` courant) → branche « lien authentifié » :
    - Vérifie que le `sub` Google **n'est pas déjà lié à un autre `kp_user`** → sinon `/profile/security?link=error&code=already_linked`, pas d'écriture.
    - `INSERT IGNORE INTO kp_user_oauth` pour `(user_code_du_cookie, 'google', sub, email, NOW())`.
    - Journal `kp_journal` action `Lien Google ajouté`.
    - Redirection `/profile/security?link=ok`.
    - Les cookies `kpi_oauth_link` et `kpi_oauth_state` sont effacés.
2. Sinon → comportement actuel (3 cas : login existant, confirmation par mot de passe, erreur).

**Sécurité du cookie.** `HttpOnly; Secure; SameSite=Lax; Path=/`; TTL 10 min. HMAC avec `kernel.secret`. Jamais lu par JS. Le `state` sert de double preuve : un attaquant qui aurait forgé un cookie ne connaîtrait pas le `state` renvoyé par Google.

**Fichiers touchés :**
- `sources/api2/src/Controller/OAuthController.php` — route `connect`, branche `link` dans `callback`, helpers `buildLinkCookie` / `readLinkCookie`.
- `sources/api2/config/packages/security.yaml` — ouvrir `^/auth/google/connect` à `ROLE_USER`, l'exclure du pattern `login`.
- `sources/app4/pages/profile/security.vue` — bouton « Lier mon compte Google » quand `!googleLinked`, bannière de succès/erreur selon `?link=ok|error`.
- `sources/app4/i18n/locales/{fr,en}.json` — libellés.
- `sources/api2/tests/Integration/AuthOAuthGoogleTest.php` — trois nouveaux cas : lien OK, refus si sub déjà pris, JWT requis sur `connect`.

---

## 5. Phase 3 — TOTP ciblé profils ≤ 3

### 5.1 Objectif

Protéger les comptes critiques (super-admin, bureau CNAKP, responsables division) contre le phishing — **sans imposer de 2FA aux centaines de bénévoles de profil 7-10**.

### 5.2 Critères d'acceptation

| ID | Critère |
|---|---|
| AUTH-2FA-01 | Un utilisateur de profil `≤ 3` sans TOTP activé est redirigé vers `/auth/enroll` dès son prochain login (soft-fail : il peut continuer, mais une bannière lui rappelle l'obligation ; au bout de 7 jours, redirection obligatoire) |
| AUTH-2FA-02 | L'enrôlement affiche un QR code TOTP (algorithme SHA-1, 6 chiffres, période 30 s) et exige la saisie d'un premier code valide pour activer le secret |
| AUTH-2FA-03 | À l'activation, 10 codes de récupération à usage unique sont affichés **une seule fois** et stockés hashés (bcrypt) dans `kp_user_recovery_code` |
| AUTH-2FA-04 | Le login des profils `≤ 3` demande un code TOTP (ou un code de récupération) après le mot de passe |
| AUTH-2FA-05 | Case « Faire confiance à cet appareil 30 jours » : cookie signé HMAC qui saute le prompt TOTP sur ce même appareil (révocable depuis `/profile/security`) |
| AUTH-2FA-06 | Un utilisateur de profil `> 3` peut activer le TOTP volontairement depuis `/profile/security` |
| AUTH-2FA-07 | Un super-admin peut forcer un réenrôlement (téléphone perdu) via `POST /auth/2fa/reset/{code}` ; l'utilisateur se reconnecte, un nouveau QR lui est présenté |
| AUTH-2FA-08 | Désactivation du TOTP par l'utilisateur **interdite** si profil `≤ 3` (seul le super-admin peut la demander, et uniquement si le profil change) |

### 5.3 Dépendances

- `scheb/2fa-bundle` + `scheb/2fa-google-authenticator` (composer).
- `endroid/qr-code` (**déjà présent** dans le lock api2) pour générer le QR.

### 5.4 Nouvelles tables

```sql
CREATE TABLE kp_user_totp (
  user_code VARCHAR(8) NOT NULL,
  secret_encrypted VARBINARY(255) NOT NULL,
  activated_at DATETIME DEFAULT NULL,
  PRIMARY KEY (user_code)
) ENGINE=InnoDB;

CREATE TABLE kp_user_recovery_code (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_code VARCHAR(8) NOT NULL,
  code_hash VARCHAR(255) NOT NULL,
  used_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY fk_user (user_code)
) ENGINE=InnoDB;

CREATE TABLE kp_user_trusted_device (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_code VARCHAR(8) NOT NULL,
  device_token_hash VARCHAR(255) NOT NULL,
  label VARCHAR(100) NOT NULL,
  created_at DATETIME NOT NULL,
  expires_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY fk_user (user_code)
) ENGINE=InnoDB;
```

---

## 6. Modèle de rôles et politique par profil

| Profil `Niveau` | Rôle principal | Mot de passe | TOTP | OAuth Google |
|---|---|---|---|---|
| 1 | Super Admin | obligatoire + politique renforcée (14 car.) | **obligatoire** (phase 3) | optionnel |
| 2 | Bureau CNAKP | obligatoire + politique renforcée | **obligatoire** | optionnel |
| 3 | Responsable Division | obligatoire | **obligatoire** | optionnel |
| 4-6 | Responsables compétition / délégué / organisateur | obligatoire | **recommandé** | optionnel |
| 7-9 | Club, consultation, table de marque | obligatoire | optionnel | **recommandé** (facilite) |
| 10 | Contrôle équipement (scrutineering) | obligatoire | optionnel | optionnel |

---

## 7. Compatibilité et migration

- **Aucun breaking change utilisateur** pour la phase 1a : les comptes existants continuent de fonctionner avec leur mot de passe actuel.
- **Phase 1b** (cookies) : nécessite une re-connexion de tous les utilisateurs connectés après déploiement (le `localStorage.kpi_admin_token` n'est plus lu). Prévoir une bannière informative dans app4 avant le déploiement.
- **Phase 2** (OAuth) : aucune bascule, c'est un ajout.
- **Phase 3** (TOTP) : les 20-30 utilisateurs de profil `≤ 3` reçoivent un email avant la fenêtre de 7 jours ; procédure de secours via super-admin documentée.

---

## 8. Références

- [DOC/specs/DROITS_PAR_PROFIL.md](../../specs/DROITS_PAR_PROFIL.md) — hiérarchie profils et droits
- [DOC/developer/reference/FRANKENPHP_MIGRATION_ANALYSIS.md](../reference/FRANKENPHP_MIGRATION_ANALYSIS.md) — pour l'impact cookies/HttpOnly (api2 derrière Caddy, et prefix `/api2`)
- [AUTH_HARDENING_ROLLOUT_CHECKLIST.md](AUTH_HARDENING_ROLLOUT_CHECKLIST.md) — checklist de déploiement
- Symfony : [Rate Limiter](https://symfony.com/doc/current/rate_limiter.html), [Security Cookie](https://symfony.com/doc/current/security.html#configuring-the-token-storage)
- RFC 6238 (TOTP), RFC 6819 (OAuth 2.0 threat model), OWASP ASVS v4.0 (contrôles V2, V3)

---

## 9. Suivi des phases

Quand une phase est livrée en production, cocher ici et déplacer les éléments livrés en § 1.3 (« ce qui existe déjà »).

- [x] Phase 1a — rate-limit, verrouillage, journal (livrée 01/11/2026)
- [x] Phase 1b — cookies HttpOnly, refresh, email nouvel appareil (livrée 02/11/2026, hybride cookie+body)
- [ ] Phase 1b-bis — gestion appareils + `/profile/security` (02/11/2026)
- [ ] Phase 1b-ter — continuité de session (§ 3ter) : lot A ☐, lot B ☐, lot C ☐, lot D ☐
- [x] Phase 2 — OAuth Google en option (livrée 02/11/2026)
- [ ] Phase 2-bis — Lier Google depuis le profil (AUTH-OA-07/08/09/10, 03/11/2026)
- [ ] Phase 3 — TOTP pour profils ≤ 3

Lorsque les 4 phases seront cochées, déplacer ce document en `DOC/developer/archive/features/AUTH_HARDENING.md` et mettre à jour `DOC/developer/archive/README.md` (cf. CLAUDE.md).
