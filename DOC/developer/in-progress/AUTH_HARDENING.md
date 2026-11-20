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
| **1a-bis** | Renouvellement forcé des mots de passe en début de saison 2027, inventaire, retrait définitif du MD5 ([#400](https://github.com/laurentgarrigue/kpi/issues/400)) | Ponctuelle (un renouvellement par compte) | 🚧 Implémentée, campagne et retrait à dater |
| **1b** | JWT en cookie `HttpOnly` + refresh token glissant (rotation + détection de rejeu), `/auth/logout`, notification email sur nouvel appareil + lien de révocation | Nulle (invisible) | ✅ Implémentée (02/11/2026) — transition hybride : app4 continue d'utiliser le JWT en body **ET** reçoit les cookies, migration pure cookie en v+1 |
| **1b-bis** | **Gestion utilisateur de ses appareils et sessions** : lister ses appareils, en révoquer un sans dépendre de l'email d'alerte, « déconnecter toutes les autres sessions », page `/profile/security` qui concentre déliement Google et (à venir) TOTP | Positive (autonomie) | 🚧 En cours (02/11/2026) |
| **1b-ter** | **Continuité de session** : jamais déconnecté pendant une gestion sportive ou un match (renouvellement fiable, rotation tolérante, zéro saisie perdue en Scoring) — § 3ter | Positive | 🚧 Lot A en production (19/11/2026), lots B à D à faire |
| **2** | OAuth Google en **option** sur la page `/login` (bouton « Continuer avec Google »), lien/déliement depuis `/profile/security` | **Négative** (facilite) | ✅ Implémentée (02/11/2026) |
| **3** | Double authentification **obligatoire** jusqu'au profil **4** (profil maximal, mandats compris), au choix TOTP ou code par e-mail ; grâce de 7 jours ; appareil de confiance 30 jours ; 10 codes de récupération ; **optionnelle** pour les autres | Faible, ciblée | 🚧 Implémentée (19/11/2026), entrée en vigueur à dater |

> **Principe directeur** : la vraie faille aujourd'hui n'est pas l'absence de 2FA — c'est le JWT en `localStorage` (volé par un XSS), l'absence totale de rate-limit (bruteforce libre) et l'absence de signal utilisateur sur les connexions suspectes. On corrige d'abord les fondations invisibles, puis on ajoute du 2FA là où il protège vraiment (profils admin).

---

## 1. État des lieux (état `main` au 01/11/2026)

### 1.1 Flux actuel

- **app4** `pages/login.vue` : saisie `username` + `password`, `POST /auth/login`, JWT stocké en `localStorage` (`kpi_admin_token`), redirection `/select-mandate` ou `/`.
- **api2** `AdminAuthController::login` : récupère l'utilisateur via `UserProvider` (table `kp_user`, match `Code` ou `Mail` si unique), vérifie le mot de passe en **bcrypt avec fallback MD5 auto-upgradé**, charge les mandats, loggue dans `kp_journal` (ligne « Connexion Admin »), renvoie le JWT.
- **UserProvider** : lecture `kp_user` + jointure `kp_licence` ; le login par `Mail` ne marche que si le mail résout à **un seul** compte (comptes partagés possibles).
- **PasswordPolicy** : 10 car. min, 1 maj / 1 min / 1 chiffre / 1 spécial — appliquée au reset et au changement forcé, **pas au login** (ancien compte autorisé à conserver un mot de passe plus faible). Depuis § 2bis, le renouvellement de saison impose la politique à tous les comptes.
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
| Pas de 2FA sur profils admin (1 à 4, mandats compris) | Phishing réussi sur un super-admin → prise de contrôle totale | ★★★ | **3** |
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
| AUTH-MD-02 | Le fallback MD5 reste **actif** dans cette phase (zero-downtime pour les utilisateurs qui ne se sont jamais reconnectés) ; son retrait se fait à une date annoncée, par `AUTH_LEGACY_MD5_FALLBACK=0` puis `app:auth:purge-md5-hashes` (§ 2bis, AUTH-RN-07/08) | Documenté dans la rollout checklist |

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

## 2bis. Phase 1a-bis — Renouvellement de saison et retrait du MD5

Issue [#400](https://github.com/laurentgarrigue/kpi/issues/400), annoncée aux Plénières kayak-polo (21-22 novembre 2026).

### 2bis.1 Objectif

Au début de la saison 2027 : (1) **tous les comptes** ont un mot de passe conforme à `PasswordPolicy` ; (2) **plus
aucun hash MD5** dans `kp_user.Pwd`. La mise à niveau MD5 → bcrypt au login ne suffit pas : un compte qui ne se
reconnecte jamais garde son MD5, et la complexité d'un bcrypt existant est invérifiable. Seul un renouvellement la
garantit.

### 2bis.2 Critères d'acceptation

| ID | Critère | Validation |
|---|---|---|
| AUTH-RN-01 | `app:auth:password-audit` affiche le nombre de comptes en MD5, en bcrypt, sans mot de passe, d'un autre format, sans e-mail valide et en attente de renouvellement, puis la liste des comptes MD5 avec leur dernier login (le plus récent de `kp_user_login_history` et de `kp_journal` « Connexion » / « Connexion Admin », legacy compris). `--without-email` liste les comptes sans e-mail valide, à transmettre aux régions | `AuthLegacyMd5RemovalTest::testAuditCountsHashKindsAndListsMd5AccountsWithTheirLastLogin` |
| AUTH-RN-02 | `app:auth:require-password-renewal --renewed-since=AAAA-MM-JJ` marque « à renouveler » chaque compte qui a un mot de passe et ne l'a pas renouvelé depuis la date. Les comptes sans mot de passe (Google seul) sont exemptés. `--dry-run` liste sans écrire. Au login d'un compte marqué avec le bon mot de passe : `403 {code: PASSWORD_RENEWAL_REQUIRED, renewalToken}`, **ni JWT ni cookie**. Mauvais mot de passe : 401 habituel, pas de jeton | `AuthPasswordRenewalTest` (campagne, login) |
| AUTH-RN-03 | Refus côté api2, pas seulement côté app4 : un JWT émis avant le marquage est refusé (401) sur toute route, `POST /auth/refresh-session` répond 403 `PASSWORD_RENEWAL_REQUIRED` et révoque les refresh tokens. app4 renvoie alors vers `/login`, qui exige le renouvellement | `testJwtIssuedBeforeTheFlagIsRefused`, `testRefreshSessionOfAFlaggedAccountIsRefused` |
| AUTH-RN-04 | `POST /auth/renew-password {renewalToken, password}` : 400 `WEAK_PASSWORD` si non conforme à `PasswordPolicy`, 400 `PASSWORD_REUSED` si identique à l'ancien (bcrypt ou MD5), 401 `INVALID_TOKEN` si le jeton est faux, expiré (15 min) ou déjà utilisé. La même règle « différent de l'ancien » s'applique à `/auth/reset-password` | `testNonCompliantRenewalIsRefused`, `testRenewalWithTheCurrentPasswordIsRefused`, `testInvalidRenewalTokenIsRefused` |
| AUTH-RN-05 | Renouvellement réussi : mot de passe enregistré en bcrypt, indicateur levé, ligne `kp_journal` (`Renouvellement mdp`), session ouverte (même réponse et cookies que `/auth/login`). Un « mot de passe oublié » mené à terme vaut renouvellement (même trace, texte « Mot de passe oublié ») | `testRenewalSetsThePasswordOpensTheSessionAndIsJournaled`, `testForgottenPasswordResetAlsoClearsTheFlagButRefusesTheCurrentPassword` |
| AUTH-RN-06 | La connexion Google d'un compte marqué ne contourne pas le renouvellement : retour `/login?oauth_error=password_renewal_required` | Revue de code (`OAuthController::issueSession`) |
| AUTH-RN-07 | Avec `AUTH_LEGACY_MD5_FALLBACK=0`, un login avec un mot de passe MD5 correct échoue (401) | `testCorrectMd5PasswordFailsOnceTheFallbackIsRemoved` |
| AUTH-RN-08 | `app:auth:purge-md5-hashes` vide `Pwd` de chaque compte encore en MD5 (32 caractères hexadécimaux) et trace chaque compte dans `kp_journal` (`Purge hash MD5`). Elle refuse de tourner tant que le repli MD5 est actif. Après la purge, la base ne contient plus aucun hash MD5 ; ces comptes passent par « mot de passe oublié » | `testPurgeLeavesNoMd5HashAndJournalsEachAccount`, `testPurgeRefusesToRunWhileTheFallbackIsActive` |

### 2bis.3 Choix techniques

- **Indicateur hors de `kp_user`** (partagée avec le legacy) : table `kp_user_password_renewal (user_code PK,
  required_at, renewed_at)`. `required_at` non NULL = renouvellement exigé ; `renewed_at` = dernier mot de passe choisi
  par l'utilisateur lui-même, qui l'exempte d'une campagne lancée après cette date.
- **Jeton de renouvellement** (`PasswordRenewalToken`) : `{base64(code)}.{expiration}.{hmac}`, 15 min, signé avec
  `kernel.secret` **et le hash courant** du mot de passe : il devient invalide dès que le mot de passe change (usage
  unique sans stockage). Il ne vit qu'en mémoire côté app4 (`useState`), jamais en `localStorage`.
- **Sessions en cours** : `PasswordRenewalJwtListener` invalide au décodage le JWT d'un compte marqué (même mécanique
  que `DeviceBoundJwtListener`) ; JWT_TTL valant 24 h, sans cela une session ouverte la veille resterait utilisable.
- **Repli MD5** : `PasswordVerifier` centralise bcrypt + MD5 ; `AUTH_LEGACY_MD5_FALLBACK` (défaut `1`) le coupe sans
  redéploiement. Le legacy `admin/Login.php` garde son propre repli (pas de rétrofit, cf. périmètre) : il devient sans
  objet dès que la purge a vidé les hash MD5.
- **Profil d'un mot de passe imposé par un administrateur** (`AdminUsersController`) : inchangé, il ne vaut pas
  renouvellement (l'utilisateur ne l'a pas choisi).

### 2bis.4 Points arbitrés par défaut (modifiables sans code)

| Point | Défaut retenu | Où le changer |
|---|---|---|
| Date du renouvellement forcé | Le jour où l'on lance `app:auth:require-password-renewal` (avant la formation arbitre national du 16-17 janvier 2027 ou avant la 1re journée du 13-14 mars) | Date d'exécution de la commande |
| Comptes Google sans mot de passe | Exemptés (aucun mot de passe à renouveler). Un compte Google **avec** mot de passe est concerné | — |
| Comptes déjà renouvelés depuis le début de saison | Exemptés via `--renewed-since` | Option de la commande |
| Stockage de l'indicateur | Table dédiée `kp_user_password_renewal` | — |
| Date du retrait MD5 | Le jour où l'on passe `AUTH_LEGACY_MD5_FALLBACK=0` puis lance la purge | `.env` api2 |

### 2bis.5 Fichiers touchés

- **Nouveaux** : `src/Security/{PasswordVerifier,PasswordMatch,PasswordRenewalToken,PasswordRenewalService,PasswordAudit}.php`,
  `src/EventListener/PasswordRenewalJwtListener.php`, `src/Command/{PasswordAuditCommand,RequirePasswordRenewalCommand,PurgeMd5HashesCommand}.php`,
  `migrations/Version20261116100000.php`, tests `Unit/Security/{PasswordVerifierTest,PasswordRenewalTokenTest}.php`,
  `Integration/{AuthPasswordRenewalTest,AuthLegacyMd5RemovalTest}.php` ; app4 `pages/renew-password.vue`,
  `composables/usePasswordRenewalToken.ts`.
- **Modifiés** : `AdminAuthController` (login, `/auth/renew-password`, reset, refresh-session), `OAuthController`,
  `security.yaml` (route publique), `services.yaml` et `.env.dist` (`AUTH_LEGACY_MD5_FALLBACK`), `SQL/fixtures/schema.sql` ;
  app4 `useAuth.ts`, `login.vue`, `reset-password.vue`, `middleware/auth.ts`, i18n.

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
  Relevé le 13/11/2026 : **86400 (24 h) en dev, préprod et prod**. Avec 24 h, le renouvellement préventif du lot A part à 19 h 12 de session (20 % restants) ; les déconnexions constatées ne viennent pas d'un TTL court, mais plus probablement des causes 1, 2 et 6 (jeton expiré au bout de 24 h, aucun renouvellement pour les profils ≥ 7, rechargement après expiration) ; en dev, un rechargement de la base depuis un dump de prod ou une régénération des clés JWT donne le même symptôme.

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

## 5. Phase 3 — Double authentification obligatoire jusqu'au profil 4

### 5.1 Objectif

Protéger les comptes qui administrent les compétitions (super-admin, bureau CNAKP, responsables de division et de
compétition) contre le vol ou la réutilisation d'un mot de passe, **sans imposer de second facteur aux bénévoles de
profil 5 à 10**. L'utilisateur choisit sa méthode : application TOTP ou code par e-mail.

### 5.2 Population concernée : le profil maximal

Un compte peut tenir un profil élevé par un **mandat** (`kp_user_mandat.niveau`) alors que son `kp_user.Niveau` est
bas. Le second facteur suit donc le **profil maximal** (à ne pas confondre avec le « niveau effectif » de
[DROITS_PAR_PROFIL.md](../../specs/DROITS_PAR_PROFIL.md), qui est celui du mandat actif) :

```
profil maximal = min(kp_user.Niveau, niveau de chacun des mandats du compte)
```

Second facteur **obligatoire** si le profil maximal est **≤ 4**, **à partir de la date d'entrée en vigueur** (§ 5.2bis). Il est calculé **à chaque connexion**, pas au choix
du mandat : un seul niveau de confiance par session, pas de JWT « partiel ». Les mandats n'ont pas de date de
validité : seul leur retrait fait sortir un compte de la population.

Inventaire (🗄 SQL) :

```sql
SELECT u.Code, u.Niveau, MIN(m.niveau) AS niveau_mandat,
       LEAST(u.Niveau, COALESCE(MIN(m.niveau), u.Niveau)) AS profil_maximal
FROM kp_user u
LEFT JOIN kp_user_mandat m ON m.user_code = u.Code
GROUP BY u.Code, u.Niveau
HAVING profil_maximal <= 4
ORDER BY profil_maximal, u.Code;
```

### 5.2bis Date d'entrée en vigueur : décidée par l'exploitant

Comme la campagne de renouvellement (§ 2bis.4), le forçage ne démarre que sur décision explicite, sans redéploiement :

- Variable `AUTH_2FA_ENFORCED_FROM` dans `sources/api2/.env` (date `AAAA-MM-JJ`), puis `make api2_restart`.
- **Vide (défaut) ou date future** : aucune obligation, aucune grâce ouverte. L'enrôlement **volontaire** est déjà
  possible (AUTH-2FA-13) : on peut livrer le code, laisser les volontaires s'équiper, puis fixer la date.
- **Date atteinte** : chaque compte concerné ouvre sa grâce de 7 jours à sa connexion suivante (AUTH-2FA-02).
- **Retour arrière** : vider la variable + `make api2_restart` suspend l'obligation (les seconds facteurs déjà
  activés restent demandés, les dates `enrollment_required_since` sont conservées).
- `app:auth:password-audit` (ou une commande sœur `app:auth:second-factor-audit`) affiche la date en vigueur et, par
  compte concerné : méthode active, en grâce (échéance), enrôlement exigé.

**Ordre imposé** : le login legacy (`admin/Login.php`) est désactivé en début d'année 2027, une fois la console Scoring
validée comme remplaçante de la feuille de marque en ligne legacy. `AUTH_2FA_ENFORCED_FROM` ne doit pas être
antérieure à cette désactivation (sinon le legacy contourne le second facteur, AUTH-2FA-17).

### 5.3 Méthodes de second facteur

| Méthode | Disponible pour | Remarque |
|---|---|---|
| **TOTP** (Google Authenticator, Authy, 1Password…) | Tous | **Recommandée** : indépendante de la boîte mail, fonctionne hors réseau |
| **Code par e-mail** | Tous, profils 1 à 4 compris | Au choix de l'utilisateur. Exige un `kp_user.Mail` valide |
| **Codes de récupération** (10) | Tout compte qui a activé un second facteur | Secours (téléphone perdu, e-mail injoignable), usage unique |

Un compte a **une seule méthode active** à la fois. L'e-mail n'est **pas** un secours automatique du TOTP : sinon la
protection d'un compte TOTP retomberait au niveau de sa boîte mail.

> **Limite assumée du code par e-mail** : « mot de passe oublié » passe déjà par l'e-mail. Une boîte mail compromise
> donne donc mot de passe **et** code. Le code par e-mail protège contre le vol ou la réutilisation du seul mot de
> passe (cas le plus fréquent), pas contre la compromission de la boîte. L'écran de choix recommande le TOTP.

### 5.4 Critères d'acceptation

| ID | Critère | Validation |
|---|---|---|
| AUTH-2FA-00 | Sans `AUTH_2FA_ENFORCED_FROM`, ou tant que la date n'est pas atteinte, aucun compte n'est tenu d'activer un second facteur ; ceux qui l'ont activé volontairement le voient demandé | Test d'intégration : variable vide → 200 sans `secondFactorEnrollmentDueAt` ; date passée → grâce ouverte |
| AUTH-2FA-01 | Le profil maximal est le minimum de `kp_user.Niveau` et des `niveau` des mandats du compte. Second facteur exigé si ≤ 4 | Test unitaire `SecondFactorPolicy` (sans mandat, mandat plus élevé, mandat moins élevé, plusieurs mandats) |
| AUTH-2FA-02 | **Grâce de 7 jours** : à la première connexion où un compte sans second facteur est concerné, api2 note la date (`enrollment_required_since`). Pendant 7 jours, le login réussit normalement et la réponse porte `secondFactorEnrollmentDueAt` ; app4 affiche une bannière avec un lien vers l'enrôlement. Au-delà : 403 `SECOND_FACTOR_ENROLLMENT_REQUIRED` + jeton d'enrôlement, **ni JWT ni cookie**. Le délai est appliqué par api2, pas seulement par app4 | Test d'intégration : J+6 → 200 + date ; J+8 → 403 |
| AUTH-2FA-03 | Un compte qui **devient** concerné (nouveau mandat ≤ 4, changement de `Niveau`) ouvre sa propre grâce de 7 jours à sa connexion suivante. Un compte qui sort de la population perd l'obligation, pas son second facteur | Test d'intégration |
| AUTH-2FA-04 | Enrôlement TOTP : QR code (SHA-1, 6 chiffres, période 30 s) et clé en texte ; activation seulement après saisie d'un premier code valide | Test d'intégration |
| AUTH-2FA-05 | Enrôlement e-mail : refusé (400 `EMAIL_REQUIRED`) si le compte n'a pas d'e-mail valide ; un code est envoyé, l'activation exige sa saisie (preuve que la boîte est accessible) | Test d'intégration |
| AUTH-2FA-06 | À l'activation, 10 codes de récupération sont affichés **une seule fois**, stockés hachés (bcrypt). Ils peuvent être régénérés depuis `/profile/security` (les anciens sont alors invalidés) | Test d'intégration |
| AUTH-2FA-07 | Login d'un compte qui a un second facteur actif, bon mot de passe, appareil non approuvé : 403 `SECOND_FACTOR_REQUIRED` + `challengeToken` + `method`, **ni JWT ni cookie**. `POST /auth/2fa/verify {challengeToken, code}` ouvre la session (même réponse et cookies que `/auth/login`). Mauvais mot de passe : 401 habituel, pas de jeton | Test d'intégration |
| AUTH-2FA-08 | Méthode e-mail : le code est envoyé au login (puis via `POST /auth/2fa/email/send {challengeToken}`). 6 chiffres, valable 10 min, usage unique, stocké haché ; un nouvel envoi invalide le précédent ; envois limités à 1 par minute et 5 par heure et par compte (429 au-delà) | Test d'intégration |
| AUTH-2FA-09 | TOTP : tolérance d'une période (± 30 s) ; un code déjà accepté est refusé (dernier pas de temps mémorisé) | Test unitaire |
| AUTH-2FA-10 | Échec de second facteur : 401, ligne `kp_user_login_history` (`failure_reason = bad_second_factor`), compté dans le verrouillage AUTH-LK-01 (5 échecs / 15 min → 423) | Test d'intégration |
| AUTH-2FA-11 | Case « Faire confiance à cet appareil 30 jours » : cookie signé `kpi_2fa_trust` lié au compte et à l'appareil, qui saute le second facteur sur cet appareil. Appareils approuvés listés et révocables dans `/profile/security` ; **tous révoqués** au changement de mot de passe, au changement de méthode et à la réinitialisation par un super-admin | Test d'intégration |
| AUTH-2FA-12 | La connexion Google d'un compte qui a un second facteur ne le contourne pas : le callback ne pose pas de session, il renvoie vers app4 `/auth/second-factor` avec le jeton de challenge (même logique qu'AUTH-RN-06) | Test d'intégration |
| AUTH-2FA-13 | Un compte de profil maximal > 4 peut activer un second facteur volontairement ; il lui est ensuite demandé comme à un compte concerné | Test d'intégration |
| AUTH-2FA-14 | Changer de méthode depuis `/profile/security` exige un code valide de la méthode actuelle (ou un code de récupération). **Désactivation interdite** tant que le profil maximal est ≤ 4 | Test d'intégration |
| AUTH-2FA-15 | `POST /auth/2fa/reset/{code}` (super-admin, lui-même doté d'un second facteur actif) supprime le second facteur du compte : journalisé dans `kp_journal` (`Réinitialisation 2FA`), e-mail d'information à l'utilisateur, enrôlement **immédiat** (sans nouvelle grâce) à sa connexion suivante | Test d'intégration |
| AUTH-2FA-16 | Journal : `kp_user_login_history.method` vaut `totp`, `email_otp` ou `recovery_code` pour le second facteur ; activation, changement de méthode et régénération des codes dans `kp_journal` | Test d'intégration |
| AUTH-2FA-17 | Le login legacy (`admin/Login.php`) ne contourne pas le second facteur : il est désactivé (début 2027) avant l'entrée en vigueur ; à défaut, il refuse les comptes de profil maximal ≤ 4 (message renvoyant vers admin2) | Constat de la désactivation du legacy avant de fixer `AUTH_2FA_ENFORCED_FROM` |

### 5.5 Choix techniques

- **Pas de `scheb/2fa-bundle`** : il s'appuie sur une session serveur, api2 est sans état (JWT). On reprend le modèle
  du renouvellement de mot de passe (§ 2bis) : le login répond 403 avec un **jeton** signé `SecondFactorToken`
  (`{but}.{base64(code)}.{expiration}.{hmac}`, but `challenge` ou `enroll`, 10 min, HMAC avec `kernel.secret` et le
  hash courant du mot de passe), gardé en mémoire côté app4 (`useState`), jamais en `localStorage`.
- **Ordre des étapes** (`LoginCompletion`) : mot de passe → second facteur (défi ou enrôlement exigé) → renouvellement
  exigé (§ 2bis) → session. Le second facteur passe **avant** le renouvellement : sinon `/auth/renew-password`
  ouvrirait une session sans lui. Tant que le second facteur n'est pas prouvé, le login n'écrit **aucune** ligne
  `success = 1` dans `kp_user_login_history` (elle remettrait le compteur de verrouillage à zéro entre deux salves de
  codes) : la ligne est écrite à la preuve, avec la méthode `totp`, `email_otp` ou `recovery_code`.
- **`SecondFactorPolicy`** (logique pure) : profil maximal (§ 5.2) et décision `Pass` / `Grace` / `Challenge` /
  `EnrollmentRequired` avec la date d'échéance de la grâce. `SecondFactorService::evaluate()` l'applique au login
  (`AdminAuthController`, `OAuthController::issueSession`) et mémorise le début de la grâce.
- **TOTP** : implémentation interne `Totp` (RFC 6238, HMAC-SHA1, 6 chiffres, 30 s, ± 1 période), testée sur les
  vecteurs de la RFC — pas de dépendance ajoutée. QR code en SVG avec `endroid/qr-code` (déjà présent). Secret chiffré
  (libsodium `secretbox`, `SecondFactorSecretBox`) avec une clé dédiée `AUTH_2FA_ENCRYPTION_KEY`, **pas**
  `kernel.secret`. Sans clé valide, seul le code par e-mail est proposé.
- **Code par e-mail** (`EmailOtpService`) : `NotificationService` existant ; texte FR/EN sans lien cliquable.
- **Codes de récupération** (`RecoveryCodes`) : format `abcde-fghij`, bcrypt, acceptés à la place de tout code.
- **Appareils de confiance** (`TrustedDevices`) : cookie `kpi_2fa_trust` = `{id}.{jeton}`, SHA-256 du jeton en base.
- **Google** : le callback renvoie vers `/auth/second-factor#challenge=…` ou `#enroll=…` ; le **fragment** d'URL
  n'est jamais envoyé à un serveur (ni journaux, ni en-tête `Referer`).
- **Sessions en cours au moment de l'activation de l'obligation** : non coupées ; la grâce s'ouvre à la connexion
  suivante (JWT de 24 h, cf. § 3ter.6).

### 5.6 Nouvelles tables (migration `Version20261120100000`)

`pending_totp_secret_encrypted` garde le secret d'un TOTP en cours d'enrôlement : le secret actif n'est remplacé
qu'à la confirmation du premier code.

```sql
CREATE TABLE kp_user_second_factor (
    user_code VARCHAR(8) NOT NULL,
    method VARCHAR(16) DEFAULT NULL,
    totp_secret_encrypted VARBINARY(255) DEFAULT NULL,
    pending_totp_secret_encrypted VARBINARY(255) DEFAULT NULL,
    totp_last_timestep BIGINT UNSIGNED DEFAULT NULL,
    enrollment_required_since DATETIME DEFAULT NULL,
    activated_at DATETIME DEFAULT NULL,
    PRIMARY KEY (user_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE kp_user_email_otp (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_code VARCHAR(8) NOT NULL,
    purpose VARCHAR(16) NOT NULL,
    code_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_user_time (user_code, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE kp_user_recovery_code (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_code VARCHAR(8) NOT NULL,
    code_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL,
    used_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_user (user_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE kp_user_trusted_device (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_code VARCHAR(8) NOT NULL,
    device_token_hash CHAR(64) NOT NULL,
    label VARCHAR(100) NOT NULL,
    created_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_user (user_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 5.7 Dépendances et configuration

- Aucune dépendance composer ajoutée (`endroid/qr-code` est déjà présent).
- `AUTH_2FA_ENCRYPTION_KEY` (32 octets en base64, `php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'`) dans
  `sources/api2/.env` de chaque environnement. **Ne jamais la changer** une fois des TOTP activés (ils deviendraient
  illisibles : il faudrait réinitialiser chaque compte).
- `AUTH_2FA_ENFORCED_FROM` (date, vide par défaut) dans `sources/api2/.env` (§ 5.2bis).

### 5.7bis Endpoints

| Route | Accès | Rôle |
|---|---|---|
| `POST /auth/2fa/verify` `{challengeToken, code, trustDevice}` | jeton `challenge` | Preuve → session (ou 403 `PASSWORD_RENEWAL_REQUIRED`) |
| `POST /auth/2fa/email/send` `{challengeToken}` | jeton `challenge` | Nouveau code par e-mail à la connexion |
| `POST /auth/2fa/enroll/{totp,email}/{setup,activate}` | jeton `enroll` | Enrôlement exigé ; `activate` ouvre la session et renvoie `recoveryCodes` |
| `GET /auth/2fa` | JWT | État : méthode, obligation, échéance, codes restants, appareils de confiance |
| `POST /auth/2fa/{totp,email}/setup` `{currentCode?}`, `…/activate` `{code}` | JWT | Activation ou changement de méthode |
| `POST /auth/2fa/email/code` | JWT | Code par e-mail prouvant la méthode actuelle |
| `POST /auth/2fa/recovery-codes` `{code}` | JWT | Nouveaux codes de récupération |
| `POST /auth/2fa/disable` `{code}` | JWT | Désactivation (409 `SECOND_FACTOR_MANDATORY` jusqu'au profil 4) |
| `POST /auth/2fa/trusted-devices/{id}/revoke` | JWT | Retirer la confiance à un appareil |
| `POST /auth/2fa/reset/{code}` | JWT, profil 1 avec second facteur | Réinitialisation (téléphone perdu) |

Commande : `php bin/console app:auth:second-factor-audit` (date en vigueur, état de chaque compte concerné).

### 5.7ter Fichiers touchés

- **api2, nouveaux** : `src/Security/{Totp,SecondFactorPolicy,SecondFactorDecision,SecondFactorOutcome,SecondFactorSettings,
  SecondFactorSecretBox,SecondFactorToken,SecondFactorService,EmailOtpService,EmailOtpSendResult,RecoveryCodes,
  TrustedDevices,LoginCompletion,SessionCookies,UserMandates}.php`, `src/Controller/SecondFactorController.php`,
  `src/Command/SecondFactorAuditCommand.php`, `migrations/Version20261120100000.php` ; tests
  `Unit/Security/{TotpTest,SecondFactorPolicyTest,SecondFactorSecretBoxTest,SecondFactorTokenTest}.php`,
  `Integration/AuthSecondFactorTest.php`.
- **api2, modifiés** : `AdminAuthController` (login via `LoginCompletion`, `secondFactor` dans `/auth/me` et
  `/auth/refresh-session`, appareils de confiance révoqués au changement de mot de passe), `OAuthController`,
  `LoginAttemptLogger`, `KnownDeviceService::label()`, `NotificationService`, `security.yaml`, `services.yaml`,
  `.env.dist`, `.env.test`, `SQL/fixtures/schema.sql`, `Integration/AuthOAuthGoogleTest.php`.
- **app4, nouveaux** : `pages/auth/second-factor.vue`, `components/auth/{SecondFactorEnrollment,RecoveryCodes,
  SecondFactorSettings,SecondFactorGraceBanner}.vue`, `composables/useSecondFactor.ts`, `utils/secondFactor.ts`,
  `tests/unit/secondFactor.spec.ts`.
- **app4, modifiés** : `composables/useAuth.ts`, `pages/login.vue`, `pages/profile/security.vue`, `layouts/admin.vue`,
  `middleware/auth.ts`, `stores/authStore.ts`, `types/index.ts`, `i18n/locales/{fr,en}.json`.
- **Doc utilisateur** : [DOC/user/SECOND_FACTOR.md](../../user/SECOND_FACTOR.md).

### 5.8 Points ouverts

- **Comptes de profil ≤ 4 partagés** entre plusieurs personnes (s'il en existe) : le TOTP ne se partage pas
  proprement ; à repérer dans l'inventaire et à scinder en comptes nominatifs.
- **Passkeys (WebAuthn)**, seule méthode résistante à l'hameçonnage en temps réel : v+1, d'abord pour les profils 1 et 2.

---

## 6. Modèle de rôles et politique par profil

Le profil s'entend comme **profil maximal** (§ 5.2) : `kp_user.Niveau` et mandats confondus.

| Profil maximal | Rôle principal | Mot de passe | Second facteur (TOTP ou e-mail) | OAuth Google |
|---|---|---|---|---|
| 1 | Super Admin | obligatoire + politique renforcée (14 car.) | **obligatoire** (phase 3) | optionnel, second facteur exigé quand même |
| 2 | Bureau CNAKP | obligatoire + politique renforcée | **obligatoire** | idem |
| 3 | Responsable Division | obligatoire | **obligatoire** | idem |
| 4 | Resp. poule / compétition | obligatoire | **obligatoire** | idem |
| 5-6 | Délégué fédéral, organisateur de journée | obligatoire | **recommandé** | optionnel |
| 7-9 | Club, consultation, table de marque | obligatoire | optionnel | **recommandé** (facilite) |
| 10 | Contrôle équipement (scrutineering) | obligatoire | optionnel | optionnel |

---

## 7. Compatibilité et migration

- **Aucun breaking change utilisateur** pour la phase 1a : les comptes existants continuent de fonctionner avec leur mot de passe actuel.
- **Phase 1b** (cookies) : nécessite une re-connexion de tous les utilisateurs connectés après déploiement (le `localStorage.kpi_admin_token` n'est plus lu). Prévoir une bannière informative dans app4 avant le déploiement.
- **Phase 2** (OAuth) : aucune bascule, c'est un ajout.
- **Phase 3** (second facteur) : les comptes de profil maximal ≤ 4 (mandats compris, inventaire § 5.2) reçoivent un e-mail avant l'entrée en vigueur ; chacun dispose ensuite de 7 jours de grâce à partir de sa première connexion concernée ; procédure de secours via super-admin (AUTH-2FA-15).

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
- [ ] Phase 1a-bis — renouvellement de saison 2027 et retrait du MD5 (code livré ; campagne et retrait à dater)
- [x] Phase 1b — cookies HttpOnly, refresh, email nouvel appareil (livrée 02/11/2026, hybride cookie+body)
- [x] Phase 1b-bis — gestion appareils + `/profile/security` (02/11/2026)
- [x] Phase 1b-ter — continuité de session (§ 3ter) : lot A ☑, lot B ☐, lot C ☐, lot D ☐
- [x] Phase 2 — OAuth Google en option (livrée 02/11/2026)
- [x] Phase 2-bis — Lier Google depuis le profil (AUTH-OA-07/08/09/10, 03/11/2026)
- [ ] Phase 3 — second facteur (TOTP ou e-mail) obligatoire jusqu'au profil 4, mandats compris (code livré le 19/11/2026 ; entrée en vigueur à dater)

Lorsque les 4 phases seront cochées, déplacer ce document en `DOC/developer/archive/features/AUTH_HARDENING.md` et mettre à jour `DOC/developer/archive/README.md` (cf. CLAUDE.md).
