<?php

namespace App\Controller;

use App\Cms\EditorialAccess;
use App\Entity\User;
use App\Security\KnownDeviceService;
use App\Security\LoginCompletion;
use App\Security\LoginAttemptLogger;
use App\Security\LoginRateLimiter;
use App\Security\PasswordMatch;
use App\Security\PasswordPolicy;
use App\Security\PasswordRenewalService;
use App\Security\PasswordRenewalToken;
use App\Security\PasswordVerifier;
use App\Security\RefreshTokenService;
use App\Security\SecondFactorService;
use App\Security\SessionCookies;
use App\Security\TrustedDevices;
use App\Security\UserMandates;
use App\Security\UserProvider;
use App\Service\NotificationService;
use Doctrine\DBAL\Connection;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

/**
 * JWT Authentication controller for App4 admin interface
 */
#[Route('/auth')]
#[OA\Tag(name: '20. App4 - Authentication')]
class AdminAuthController extends AbstractController
{
    public const PASSWORD_RENEWAL_REQUIRED = LoginCompletion::PASSWORD_RENEWAL_REQUIRED;

    public function __construct(
        private readonly UserProvider $userProvider,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly Connection $connection,
        private readonly NotificationService $notificationService,
        private readonly EditorialAccess $editorialAccess,
        private readonly LoginAttemptLogger $loginLogger,
        private readonly LoginRateLimiter $loginRateLimiter,
        private readonly RefreshTokenService $refreshTokenService,
        private readonly KnownDeviceService $knownDeviceService,
        private readonly PasswordVerifier $passwordVerifier,
        private readonly PasswordRenewalService $passwordRenewals,
        private readonly PasswordRenewalToken $renewalTokens,
        private readonly LoginCompletion $loginCompletion,
        private readonly UserMandates $userMandates,
        private readonly SecondFactorService $secondFactors,
        private readonly TrustedDevices $trustedDevices,
    ) {
    }

    /**
     * Login endpoint - authenticates user and returns JWT token.
     *
     * Durcissement (AUTH_HARDENING.md § 2) :
     *  - rate-limit par IP (5/15 min) et par code utilisateur (10/15 min) → 429 ;
     *  - verrouillage dérivé du journal : 5 échecs dans la fenêtre glissante → 423 ;
     *  - chaque tentative est journalisée dans kp_user_login_history ;
     *  - compte « mot de passe à renouveler » : 403 PASSWORD_RENEWAL_REQUIRED + jeton de renouvellement,
     *    aucune session (AUTH_HARDENING.md § 2bis, AUTH-RN-02).
     */
    #[Route('/login', name: 'api_auth_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json([
                'message' => 'Invalid JSON payload'
            ], Response::HTTP_BAD_REQUEST);
        }

        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($username) || empty($password)) {
            return $this->json([
                'message' => 'Username and password are required'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Rate-limit avant tout accès DB : un attaquant distribué ne doit pas pouvoir sonder
        // le UserProvider à vitesse réseau. L'IP est lue via trustedProxies (framework.yaml).
        $limit = $this->loginRateLimiter->consume($request, $username);
        if (!$limit->isAccepted()) {
            $this->loginLogger->logFailure($username === '' ? null : $username, LoginAttemptLogger::REASON_RATE_LIMITED, $request);
            $retryAfter = max(1, $limit->getRetryAfter()->getTimestamp() - time());
            return $this->json(['message' => 'Too many attempts, please retry later'], Response::HTTP_TOO_MANY_REQUESTS, [
                'Retry-After' => (string) $retryAfter,
            ]);
        }

        try {
            /** @var User $user */
            $user = $this->userProvider->loadUserByIdentifier($username);
        } catch (UserNotFoundException) {
            $this->loginLogger->logFailure(null, LoginAttemptLogger::REASON_UNKNOWN_USER, $request);
            return $this->json([
                'message' => 'Invalid credentials'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Verrouillage : 5 échecs consécutifs depuis le dernier succès dans la fenêtre glissante.
        // Même avec le bon mot de passe, 423 Locked pendant la fenêtre.
        if ($this->loginLogger->isAccountLocked($user->getCode())) {
            $this->loginLogger->logFailure($user->getCode(), LoginAttemptLogger::REASON_ACCOUNT_LOCKED, $request);
            return $this->json([
                'message' => 'Account temporarily locked after too many failed attempts',
                'code' => 'ACCOUNT_LOCKED',
            ], Response::HTTP_LOCKED);
        }

        // bcrypt, puis repli MD5 tant que AUTH_LEGACY_MD5_FALLBACK est actif.
        $usedLegacyHash = false;
        if (!$this->verifyPassword($user, $password, $usedLegacyHash)) {
            $this->loginLogger->logFailure($user->getCode(), LoginAttemptLogger::REASON_BAD_PASSWORD, $request);
            return $this->json([
                'message' => 'Invalid credentials'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Succès : alimente le journal de sécurité (AUTH-JL-01) avant toute suite.
        // Second facteur, puis renouvellement exigé, puis session (AUTH_HARDENING.md § 5).
        return $this->loginCompletion->afterPassword($user, $request, $username, $usedLegacyHash);
    }

    /**
     * Renouvellement forcé du mot de passe (AUTH_HARDENING.md § 2bis, AUTH-RN-02 à 05) : le jeton remis par
     * /auth/login prouve le mot de passe actuel ; le nouveau doit respecter PasswordPolicy et différer de
     * l'ancien. En cas de succès, la session s'ouvre comme après un login.
     */
    #[Route('/renew-password', name: 'api_auth_renew_password', methods: ['POST'])]
    #[OA\Post(
        path: '/auth/renew-password',
        summary: 'Set a new password for an account flagged \'password renewal required\', then open the session',
        tags: ['20. App4 - Authentication']
    )]
    #[OA\Response(response: 200, description: 'Password renewed, same payload and cookies as /auth/login')]
    #[OA\Response(response: 400, description: 'WEAK_PASSWORD or PASSWORD_REUSED')]
    #[OA\Response(response: 401, description: 'INVALID_TOKEN')]
    public function renewPassword(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $token = is_array($data) ? (string) ($data['renewalToken'] ?? '') : '';
        $password = is_array($data) ? (string) ($data['password'] ?? '') : '';
        if ($token === '' || $password === '') {
            return $this->json(['message' => 'renewalToken and password are required'], Response::HTTP_BAD_REQUEST);
        }

        $userCode = $this->renewalTokens->userCode($token);
        $storedHash = $userCode === null ? '' : $this->loginCompletion->storedHash($userCode);
        if ($userCode === null || !$this->renewalTokens->isValid($token, $storedHash)) {
            return $this->json(['message' => 'Invalid or expired token', 'code' => 'INVALID_TOKEN'], Response::HTTP_UNAUTHORIZED);
        }

        $passwordError = $this->newPasswordError($password, $storedHash);
        if ($passwordError !== null) {
            return $passwordError;
        }

        $this->connection->executeStatement('UPDATE kp_user SET Pwd = ? WHERE Code = ?', [password_hash($password, PASSWORD_BCRYPT), $userCode]);
        $this->passwordRenewals->markRenewed($userCode, 'Renouvellement imposé au login');
        $this->trustedDevices->revokeAll($userCode);

        /** @var User $user */
        $user = $this->userProvider->loadUserByIdentifier($userCode);

        // Le jeton de renouvellement n'est remis qu'après le second facteur : la session peut s'ouvrir.
        return $this->loginCompletion->openSession($user, $request);
    }

    /**
     * Get current authenticated user info
     */
    #[Route('/me', name: 'api_auth_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json([
                'message' => 'Not authenticated'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $mandates = $this->userMandates->forUser($user->getCode());
        $userData = $user->toArray();
        // Rights independent of the profile level, e.g. 'editor' (FEATURE_CMS.md § 4).
        $userData['capabilities'] = $this->editorialAccess->capabilities($user->getCode());
        $userData['mandates'] = $mandates;
        $userData['oauthProviders'] = $this->oauthProvidersFor($user->getCode());
        $userData['secondFactor'] = $this->secondFactors->summary($user);
        $this->applyActiveMandateToUserData($userData, $user, $mandates);

        return $this->json([
            'user' => $userData
        ]);
    }

    /**
     * Liste des liens OAuth actifs du compte (ex. `['google' => true]`) — exposée dans /auth/me
     * pour que /profile/security sache si le bouton « Délier Google » doit s'afficher (AUTH-PS-02).
     *
     * @return array<string, bool>
     */
    private function oauthProvidersFor(string $userCode): array
    {
        $rows = $this->connection->fetchFirstColumn(
            'SELECT provider FROM kp_user_oauth WHERE user_code = ?',
            [$userCode],
        );
        $result = [];
        foreach ($rows as $provider) {
            $result[(string) $provider] = true;
        }
        return $result;
    }

    /**
     * Rotate refresh cookie: validate the opaque refresh token read from the kpi_refresh cookie,
     * issue a new access JWT + a new refresh token, set both cookies back.
     *
     * Returns 401 on invalid/revoked token (replay triggers full chain revocation).
     */
    #[Route('/refresh-session', name: 'api_auth_refresh_session', methods: ['POST'])]
    #[OA\Post(
        path: '/auth/refresh-session',
        summary: 'Rotate the refresh cookie and emit a new access JWT. Also returns {token,user,hasMandates} so app4 can bootstrap its store from cookies (used by the OAuth completion page).',
        tags: ['20. App4 - Authentication']
    )]
    #[OA\Response(response: 200, description: 'New cookies set + login payload in body')]
    #[OA\Response(response: 401, description: 'Missing, invalid or revoked refresh token')]
    public function refreshSession(Request $request): JsonResponse
    {
        $presented = (string) $request->cookies->get('kpi_refresh', '');
        if ($presented === '') {
            return $this->json(['message' => 'Missing refresh token'], Response::HTTP_UNAUTHORIZED);
        }

        $result = $this->refreshTokenService->rotate($presented);
        if (!$result) {
            // Même en cas de rejeu détecté (toute la chaîne révoquée), on répond 401 — pas d'indication à l'attaquant.
            $response = $this->json(['message' => 'Invalid refresh token'], Response::HTTP_UNAUTHORIZED);
            $response->headers->clearCookie('kpi_refresh', '/', null, true, true, 'lax');
            $response->headers->clearCookie('kpi_access', '/', null, true, true, 'lax');
            return $response;
        }

        if ($this->passwordRenewals->isRequired($result['userCode'])) {
            // Session ouverte avant le marquage : on la ferme, le login exigera le renouvellement (AUTH-RN-03).
            $this->refreshTokenService->revokeAllFor($result['userCode']);
            $response = $this->json(['message' => 'Password renewal required', 'code' => self::PASSWORD_RENEWAL_REQUIRED], Response::HTTP_FORBIDDEN);
            $response->headers->clearCookie('kpi_refresh', '/', null, true, true, 'lax');
            $response->headers->clearCookie('kpi_access', '/', null, true, true, 'lax');
            return $response;
        }

        /** @var User $user */
        $user = $this->userProvider->loadUserByIdentifier($result['userCode']);
        $accessToken = $this->jwtManager->create($user);
        $mandates = $this->userMandates->forUser($user->getCode());

        // Shape alignée sur /auth/login : app4 peut remplir son authStore à partir du body
        // sans passer par /auth/me puis un endpoint séparé. C'est ce dont /auth/oauth-complete
        // a besoin pour amorcer la session après un login via Google.
        $userData = $user->toArray();
        $userData['capabilities'] = $this->editorialAccess->capabilities($user->getCode());
        $userData['mandates'] = $mandates;
        $userData['oauthProviders'] = $this->oauthProvidersFor($user->getCode());
        $userData['secondFactor'] = $this->secondFactors->summary($user);
        $userData['activeMandate'] = null;
        $userData['effectiveProfile'] = $user->getNiveau();
        $userData['effectiveFilters'] = $userData['filters'];

        $response = $this->json([
            'token' => $accessToken,
            'user' => $userData,
            'hasMandates' => count($mandates) > 0,
            'refreshed' => true, // conservé pour compat avec un éventuel appelant qui n'attend que ça
        ]);
        $response->headers->setCookie(SessionCookies::access($accessToken));
        $response->headers->setCookie(SessionCookies::refresh($result['token']));
        return $response;
    }

    /**
     * Logout: revoke the refresh token and clear both cookies. Idempotent.
     */
    #[Route('/logout', name: 'api_auth_logout', methods: ['POST'])]
    #[OA\Post(path: '/auth/logout', summary: 'Revoke the refresh cookie and clear auth cookies', tags: ['20. App4 - Authentication'])]
    #[OA\Response(response: 200, description: 'Session ended')]
    public function logout(Request $request): JsonResponse
    {
        $presented = (string) $request->cookies->get('kpi_refresh', '');
        if ($presented !== '') {
            $this->refreshTokenService->revoke($presented);
        }

        $response = $this->json(['message' => 'Logged out']);
        $response->headers->clearCookie('kpi_refresh', '/', null, true, true, 'lax');
        $response->headers->clearCookie('kpi_access', '/', null, true, true, 'lax');
        return $response;
    }

    /**
     * Public endpoint hit by the « revoke this device » link in the alert email.
     * Revokes the device fingerprint and every active refresh token of the user. No JWT required
     * — the signed token in the URL IS the proof of ownership (sent to the account's email).
     */
    #[Route('/device/revoke/{token}', name: 'api_auth_device_revoke', methods: ['GET', 'POST'])]
    #[OA\Get(path: '/auth/device/revoke/{token}', summary: 'Revoke a device via the signed link in the alert email', tags: ['20. App4 - Authentication'])]
    #[OA\Response(response: 200, description: 'Device revoked')]
    #[OA\Response(response: 404, description: 'Unknown or already-used token')]
    public function revokeDevice(string $token): JsonResponse
    {
        $userCode = $this->knownDeviceService->revokeByToken($token, $this->refreshTokenService);
        if ($userCode === null) {
            return $this->json(['message' => 'Unknown or already-used revocation token'], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->connection->prepare(
                "INSERT INTO kp_journal (Dates, Users, Actions, Journal) VALUES (NOW(), ?, 'Révocation appareil', ?)"
            )->executeStatement([$userCode, 'Révocation déclenchée depuis l\'email d\'alerte']);
        } catch (\Exception) {
        }

        $response = $this->json(['message' => 'Device revoked, all sessions ended']);
        $response->headers->clearCookie('kpi_refresh', '/', null, true, true, 'lax');
        $response->headers->clearCookie('kpi_access', '/', null, true, true, 'lax');
        return $response;
    }

    /**
     * Return the recent login history (success and failures) for a user.
     *
     * Durcissement (AUTH-JL-03) : accessible au super-admin (profil 1) pour tout utilisateur,
     * et à chaque utilisateur sur son propre compte uniquement.
     */
    #[Route('/login-history', name: 'api_auth_login_history', methods: ['GET'])]
    #[OA\Get(
        path: '/auth/login-history',
        summary: 'List recent login attempts for a user',
        tags: ['20. App4 - Authentication']
    )]
    #[OA\Response(response: 200, description: 'List of recent attempts (max 50)')]
    #[OA\Response(response: 403, description: 'Not allowed to view another user history')]
    public function loginHistory(Request $request): JsonResponse
    {
        /** @var User|null $me */
        $me = $this->getUser();
        if (!$me) {
            return $this->json(['message' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $code = (string) $request->query->get('code', $me->getCode());

        // Soi-même toujours autorisé ; super-admin (profil 1) autorisé sur n'importe quel compte.
        $isSelf = $code === $me->getCode();
        $isSuperAdmin = $me->getNiveau() <= 1;
        if (!$isSelf && !$isSuperAdmin) {
            return $this->json(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, success, method, failure_reason, legacy_hash, ip, user_agent, attempted_at
             FROM kp_user_login_history
             WHERE user_code = ?
             ORDER BY id DESC
             LIMIT 50',
            [$code]
        );

        return $this->json(['code' => $code, 'attempts' => array_map(
            fn(array $row) => [
                'id' => (int) $row['id'],
                'success' => (bool) $row['success'],
                'method' => $row['method'],
                'failure_reason' => $row['failure_reason'],
                'legacy_hash' => (bool) $row['legacy_hash'],
                'ip' => $row['ip'],
                'user_agent' => $row['user_agent'],
                'attempted_at' => $row['attempted_at'],
            ],
            $rows,
        )]);
    }

    /**
     * Refresh token endpoint
     */
    #[Route('/refresh', name: 'api_auth_refresh', methods: ['POST'])]
    public function refresh(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json([
                'message' => 'Not authenticated'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $mandates = $this->userMandates->forUser($user->getCode());

        // Generate new JWT token
        $token = $this->jwtManager->create($user);

        $userData = $user->toArray();
        // Rights independent of the profile level, e.g. 'editor' (FEATURE_CMS.md § 4).
        $userData['capabilities'] = $this->editorialAccess->capabilities($user->getCode());
        $userData['mandates'] = $mandates;
        $this->applyActiveMandateToUserData($userData, $user, $mandates);

        return $this->json([
            'token' => $token,
            'user' => $userData
        ]);
    }

    /**
     * Populate activeMandate/effectiveProfile/effectiveFilters on $userData from whichever
     * mandate is currently active on $user (set by ActiveMandateListener from the
     * X-Active-Mandate header), mirroring AdminAuthMandateController::switchMandate().
     *
     * @param array<string, mixed> $userData
     * @param array<int, array<string, mixed>> $mandates
     */
    private function applyActiveMandateToUserData(array &$userData, User $user, array $mandates): void
    {
        $activeMandateId = $user->getActiveMandateId();
        $mandate = null;
        if ($activeMandateId !== null) {
            foreach ($mandates as $m) {
                if ($m['id'] === $activeMandateId) {
                    $mandate = $m;
                    break;
                }
            }
        }

        if ($mandate) {
            $userData['activeMandate'] = [
                'id' => $mandate['id'],
                'libelle' => $mandate['libelle'],
            ];
            $userData['effectiveProfile'] = $mandate['niveau'];
            $userData['effectiveFilters'] = $mandate['filters'];
        } else {
            $userData['activeMandate'] = null;
            $userData['effectiveProfile'] = $user->getNiveau();
            $userData['effectiveFilters'] = $userData['filters'];
        }
    }

    /**
     * Validate a password reset token and change password
     */
    #[Route('/reset-password', name: 'api_auth_reset_password', methods: ['POST'])]
    #[OA\Post(
        path: '/auth/reset-password',
        summary: 'Reset password using a token',
        tags: ['20. App4 - Authentication']
    )]
    #[OA\Response(response: 200, description: 'Password changed')]
    #[OA\Response(response: 400, description: 'Invalid password')]
    #[OA\Response(response: 401, description: 'Invalid or expired token')]
    public function resetPassword(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['message' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        $token = $data['token'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($token) || empty($password)) {
            return $this->json(['message' => 'Token and password are required'], Response::HTTP_BAD_REQUEST);
        }

        // Find token (valid for 48h)
        $row = $this->connection->fetchAssociative(
            'SELECT user, generated_at FROM kp_user_token WHERE token = ?',
            [$token]
        );

        if (!$row) {
            return $this->json(['message' => 'Invalid or expired token', 'code' => 'INVALID_TOKEN'], Response::HTTP_UNAUTHORIZED);
        }

        $generatedAt = new \DateTimeImmutable($row['generated_at']);
        $expiresAt = $generatedAt->modify('+48 hours');
        if (new \DateTimeImmutable() > $expiresAt) {
            // Clean up expired token
            $this->connection->executeStatement('DELETE FROM kp_user_token WHERE token = ?', [$token]);
            return $this->json(['message' => 'Invalid or expired token', 'code' => 'INVALID_TOKEN'], Response::HTTP_UNAUTHORIZED);
        }

        $userCode = $row['user'];

        $passwordError = $this->newPasswordError($password, $this->loginCompletion->storedHash($userCode));
        if ($passwordError !== null) {
            return $passwordError;
        }

        // Hash with bcrypt and update
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $this->connection->executeStatement('UPDATE kp_user SET Pwd = ? WHERE Code = ?', [$hashedPassword, $userCode]);
        // Un mot de passe choisi par l'utilisateur vaut renouvellement (compte marqué qui a oublié son mot de passe).
        $this->passwordRenewals->markRenewed($userCode, 'Mot de passe oublié');
        $this->trustedDevices->revokeAll($userCode);

        // Delete the used token
        $this->connection->executeStatement('DELETE FROM kp_user_token WHERE user = ?', [$userCode]);

        return $this->json(['message' => 'Password changed successfully']);
    }

    /**
     * Request a password reset link — public, no auth required.
     * Accepts username or email; always returns 200 to avoid enumeration.
     */
    #[Route('/forgot-password', name: 'api_auth_forgot_password', methods: ['POST'])]
    #[OA\Post(
        path: '/auth/forgot-password',
        summary: 'Request a password reset link',
        tags: ['20. App4 - Authentication']
    )]
    #[OA\Response(response: 200, description: 'Email sent if account found')]
    public function forgotPassword(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $identifier = trim((string) ($data['identifier'] ?? ''));

        if ($identifier === '') {
            return $this->json(['message' => 'Identifier is required'], Response::HTTP_BAD_REQUEST);
        }

        // Look up user by login code or email (case-insensitive)
        $row = $this->connection->fetchAssociative(
            'SELECT Code, Mail FROM kp_user WHERE Code = ? OR LOWER(Mail) = LOWER(?)',
            [$identifier, $identifier]
        );

        // Licence number typed with its leading zeros (see UserProvider::stripLicenceZeros)
        if (!$row && ($stripped = UserProvider::stripLicenceZeros($identifier)) !== null) {
            $row = $this->connection->fetchAssociative(
                'SELECT Code, Mail FROM kp_user WHERE Code = ?',
                [$stripped]
            );
        }

        // Always return 200 to avoid user enumeration
        if (!$row || empty($row['Mail'])) {
            return $this->json(['message' => 'If an account exists, a reset link has been sent.']);
        }

        $locale = in_array($data['locale'] ?? '', ['en', 'fr'], true) ? $data['locale'] : 'fr';

        $token = bin2hex(random_bytes(32));
        $this->connection->executeStatement(
            'INSERT INTO kp_user_token (token, user, generated_at) VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE token = VALUES(token), generated_at = NOW()',
            [$token, $row['Code']]
        );

        $this->notificationService->sendPasswordReset($row['Mail'], $token, false, '', $row['Code'], $locale);

        return $this->json(['message' => 'If an account exists, a reset link has been sent.']);
    }

    /**
     * Submit an access request — public, no auth required.
     * Sends an email to the administrator with the applicant's details.
     */
    #[Route('/access-request', name: 'api_auth_access_request', methods: ['POST'])]
    #[OA\Post(
        path: '/auth/access-request',
        summary: 'Submit an access request to the administrator',
        tags: ['20. App4 - Authentication']
    )]
    #[OA\Response(response: 200, description: 'Request submitted')]
    #[OA\Response(response: 400, description: 'Missing required fields')]
    public function accessRequest(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['message' => 'Invalid JSON payload'], Response::HTTP_BAD_REQUEST);
        }

        $required = ['email', 'nom', 'prenom', 'responsabilites', 'message'];
        foreach ($required as $field) {
            if (empty(trim((string) ($data[$field] ?? '')))) {
                return $this->json(['message' => "Field '{$field}' is required"], Response::HTTP_BAD_REQUEST);
            }
        }

        $email         = trim($data['email']);
        $nom           = trim($data['nom']);
        $prenom        = trim($data['prenom']);
        $licence       = trim((string) ($data['licence'] ?? ''));
        $club          = trim((string) ($data['club'] ?? ''));
        $responsabilites = trim($data['responsabilites']);
        $message       = trim($data['message']);

        $body  = "Nouvelle demande d'accès KPI Admin\n";
        $body .= str_repeat('─', 40) . "\n";
        $body .= "Nom       : {$nom}\n";
        $body .= "Prénom    : {$prenom}\n";
        $body .= "Email     : {$email}\n";
        if ($licence !== '') {
            $body .= "Licence   : {$licence}\n";
        }
        if ($club !== '') {
            $body .= "Club      : {$club}\n";
        }
        $body .= "Rôle(s)   : {$responsabilites}\n";
        $body .= "\nMessage :\n{$message}\n";

        $this->notificationService->sendAdminNotification(
            "Demande d'accès KPI — {$prenom} {$nom}",
            $body
        );

        return $this->json(['message' => 'Your request has been submitted.']);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────

    /** Nouveau mot de passe refusé : non conforme à PasswordPolicy ou identique à l'ancien (AUTH-RN-04). */
    private function newPasswordError(string $password, string $storedHash): ?JsonResponse
    {
        $complexityError = PasswordPolicy::validate($password);
        if ($complexityError !== null) {
            return $this->json(['message' => $complexityError, 'code' => 'WEAK_PASSWORD'], Response::HTTP_BAD_REQUEST);
        }
        if ($this->passwordVerifier->isSameAsCurrent($password, $storedHash)) {
            return $this->json(['message' => 'The new password must differ from the current one', 'code' => 'PASSWORD_REUSED'], Response::HTTP_BAD_REQUEST);
        }

        return null;
    }

    /**
     * Vérifie le mot de passe ({@see PasswordVerifier}) ; un ancien hash MD5 accepté est aussitôt
     * remplacé par un bcrypt.
     *
     * $usedLegacyHash est positionné à true quand c'est le repli MD5 qui a validé le login :
     * {@see LoginAttemptLogger} le reporte dans la colonne `legacy_hash` du journal, ce qui
     * permet de suivre le retrait du MD5 (AUTH-MD-01).
     */
    private function verifyPassword(User $user, string $plainPassword, bool &$usedLegacyHash = false): bool
    {
        $match = $this->passwordVerifier->verify($plainPassword, $user->getPassword());
        $usedLegacyHash = $match === PasswordMatch::LegacyMd5;
        if ($usedLegacyHash) {
            $this->connection->executeStatement(
                'UPDATE kp_user SET Pwd = ? WHERE Code = ?',
                [password_hash($plainPassword, PASSWORD_BCRYPT), $user->getUserIdentifier()]
            );
        }

        return $match !== PasswordMatch::None;
    }
}
