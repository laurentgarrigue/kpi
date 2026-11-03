<?php

namespace App\Controller;

use App\Entity\User;
use App\Security\GoogleOAuthClient;
use App\Security\KnownDeviceService;
use App\Security\LoginAttemptLogger;
use App\Security\LoginRateLimiter;
use App\Security\RefreshTokenService;
use App\Security\UserProvider;
use App\Service\NotificationService;
use Doctrine\DBAL\Connection;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * OAuth 2.0 — flow « Continuer avec Google » (AUTH_HARDENING.md § 4).
 *
 * Jamais de création automatique de compte : l'identité Google doit se lier à un `kp_user`
 * existant. Le callback distingue DEUX modes à partir du cookie `kpi_oauth_link` :
 *
 *   A. Mode « lien depuis le profil » (AUTH-OA-07/08/09) — déclenché par /auth/google/connect
 *      avec un JWT : la session prouve la propriété, le callback écrit directement dans
 *      kp_user_oauth sans comparaison d'e-mail. Couvre les cas « e-mail KPI partagé » et
 *      « e-mail Google différent de l'e-mail KPI ».
 *
 *   B. Mode « login » (AUTH-OA-02 à 05) — pas de cookie link. Trois cas :
 *      1. (provider, subject) déjà connu → login direct.
 *      2. E-mail vérifié correspond à exactement un `kp_user` sans lien → redirection vers
 *         app4 /auth/google/link (demande du mot de passe actuel).
 *      3. Rien ne correspond, ou ambigu → retour /login avec un message explicite.
 */
#[Route('/auth/google')]
#[OA\Tag(name: '20. App4 - Authentication')]
class OAuthController extends AbstractController
{
    public function __construct(
        private readonly GoogleOAuthClient $googleClient,
        private readonly Connection $connection,
        private readonly UserProvider $userProvider,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly RefreshTokenService $refreshTokenService,
        private readonly KnownDeviceService $knownDeviceService,
        private readonly LoginAttemptLogger $loginLogger,
        private readonly LoginRateLimiter $loginRateLimiter,
        private readonly NotificationService $notificationService,
        private readonly string $app4Url,
    ) {
    }

    /**
     * Entrée du flow : génère un state anti-CSRF stocké en cookie HttpOnly, redirige vers Google.
     * Si OAuth n'est pas configuré (variables d'env manquantes), renvoie 404 — pas de bouton actif.
     */
    #[Route('', name: 'api_auth_google_start', methods: ['GET'])]
    public function start(): Response
    {
        if (!$this->googleClient->isConfigured()) {
            return new JsonResponse(['message' => 'OAuth Google not configured'], Response::HTTP_NOT_FOUND);
        }

        $state = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
        $redirect = new RedirectResponse($this->googleClient->buildAuthorizationUrl($state));
        $redirect->headers->setCookie(
            Cookie::create('kpi_oauth_state')
                ->withValue($state)
                ->withExpires(time() + 600) // 10 min
                ->withPath('/')
                ->withSecure(true)
                ->withHttpOnly(true)
                ->withSameSite('lax'),
        );
        return $redirect;
    }

    /**
     * Entrée du flux « lier Google depuis le profil » (AUTH-OA-07) — JWT exigé.
     *
     * Mémorise le code utilisateur courant dans un cookie SIGNÉ, puis redirige vers Google.
     * Au callback, la présence du cookie bascule la logique de résolution par e-mail (login)
     * vers l'écriture directe dans kp_user_oauth (lien), parce que la session JWT prouve déjà
     * la propriété du compte — plus besoin d'e-mail commun entre Google et KPI.
     */
    #[Route('/connect', name: 'api_auth_google_connect', methods: ['GET'])]
    public function connect(): Response
    {
        if (!$this->googleClient->isConfigured()) {
            return new JsonResponse(['message' => 'OAuth Google not configured'], Response::HTTP_NOT_FOUND);
        }

        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            // Normalement couvert par access_control, garde-fou pour qu'aucun code en dessous
            // ne tourne sans utilisateur résolu.
            return new JsonResponse(['message' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $state = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
        $redirect = new RedirectResponse($this->googleClient->buildAuthorizationUrl($state));
        $redirect->headers->setCookie(
            Cookie::create('kpi_oauth_state')
                ->withValue($state)
                ->withExpires(time() + 600)
                ->withPath('/')->withSecure(true)->withHttpOnly(true)->withSameSite('lax'),
        );
        $redirect->headers->setCookie(
            Cookie::create('kpi_oauth_link')
                ->withValue($this->buildLinkCookieValue($user->getCode(), $state))
                ->withExpires(time() + 600)
                ->withPath('/')->withSecure(true)->withHttpOnly(true)->withSameSite('lax'),
        );
        return $redirect;
    }

    /**
     * Callback de Google : récupère le code, vérifie le state, échange, résout le compte.
     */
    #[Route('/callback', name: 'api_auth_google_callback', methods: ['GET'])]
    public function callback(Request $request): Response
    {
        if (!$this->googleClient->isConfigured()) {
            return new JsonResponse(['message' => 'OAuth Google not configured'], Response::HTTP_NOT_FOUND);
        }

        $state = (string) $request->query->get('state', '');
        $cookieState = (string) $request->cookies->get('kpi_oauth_state', '');
        if ($state === '' || !hash_equals($cookieState, $state)) {
            return $this->redirectLogin('oauth_state_mismatch');
        }

        $code = (string) $request->query->get('code', '');
        if ($code === '') {
            return $this->redirectLogin('oauth_missing_code');
        }

        $claims = $this->googleClient->exchangeCode($code);
        if ($claims === null) {
            return $this->redirectLogin('oauth_exchange_failed');
        }
        if (!$claims['emailVerified']) {
            // Google exige email_verified=true : sans ça, un attaquant pourrait réclamer n'importe quel mail.
            return $this->redirectLogin('oauth_email_not_verified');
        }

        // Mode A — Lien depuis le profil (AUTH-OA-08) : cookie signé valide avec le user_code
        // d'une session authentifiée côté app4, et le state du cookie matche celui de Google.
        // Court-circuite toute la logique de résolution par e-mail (AUTH-OA-04/05).
        $link = $this->readLinkCookie($request, $state);
        if ($link !== null) {
            return $this->finaliseAuthenticatedLink($link['userCode'], $claims['sub'], $claims['email']);
        }

        // Mode B — Login OAuth. Cas 1 : identité OAuth déjà liée → login direct.
        $existing = $this->connection->fetchAssociative(
            "SELECT user_code FROM kp_user_oauth WHERE provider = 'google' AND subject = ?",
            [$claims['sub']],
        );
        if ($existing) {
            return $this->issueSession($existing['user_code'], $request, legacyMd5: false, method: 'oauth_google');
        }

        // Cas 2 : email vérifié correspond à exactement UN compte sans lien OAuth → page de confirmation.
        $matches = $this->connection->fetchAllAssociative(
            'SELECT Code FROM kp_user WHERE LOWER(Mail) = LOWER(?)',
            [$claims['email']],
        );
        if (count($matches) === 1) {
            // On redirige app4 vers une page qui demandera le mot de passe pour prouver la propriété
            // du compte avant d'écrire dans kp_user_oauth (POST /auth/google/link).
            $redirect = new RedirectResponse(
                rtrim($this->app4Url, '/') . '/auth/google/link?code=' . urlencode($matches[0]['Code']) . '&sub=' . urlencode($claims['sub'])
            );
            // Un cookie signé transporte temporairement la paire sub+email pour que /link puisse écrire sans
            // refaire un échange avec Google (le code est à usage unique). TTL court.
            $exp = time() + 600;
            $pending = base64_encode(json_encode([
                'provider' => 'google',
                'code' => $matches[0]['Code'],
                'sub' => $claims['sub'],
                'email' => $claims['email'],
                'exp' => $exp,
                'sig' => $this->signPending($matches[0]['Code'], $claims['sub'], $claims['email'], $exp),
            ]));
            $redirect->headers->setCookie(
                Cookie::create('kpi_oauth_pending')
                    ->withValue($pending)
                    ->withExpires(time() + 600)
                    ->withPath('/')
                    ->withSecure(true)
                    ->withHttpOnly(true)
                    ->withSameSite('lax'),
            );
            $redirect->headers->clearCookie('kpi_oauth_state', '/', null, true, true, 'lax');
            return $redirect;
        }

        // Cas 3 : aucun compte (ou plusieurs ambigus) → retour login avec message.
        return $this->redirectLogin(count($matches) > 1 ? 'oauth_email_ambiguous' : 'oauth_no_account');
    }

    /**
     * Finalise un lien proposé par le callback : lit le cookie kpi_oauth_pending, vérifie le
     * mot de passe fourni, écrit la ligne kp_user_oauth et émet la session.
     */
    #[Route('/link', name: 'api_auth_google_link', methods: ['POST'])]
    public function link(Request $request): Response
    {
        if (!$this->googleClient->isConfigured()) {
            return new JsonResponse(['message' => 'OAuth Google not configured'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['message' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        $password = (string) ($data['password'] ?? '');
        $userCode = (string) ($data['code'] ?? '');
        if ($password === '' || $userCode === '') {
            return $this->json(['message' => 'password and code are required'], Response::HTTP_BAD_REQUEST);
        }

        $pending = $this->readPendingCookie($request);
        if ($pending === null) {
            return $this->json(['message' => 'Session OAuth expirée, relancez la connexion Google'], Response::HTTP_BAD_REQUEST);
        }

        // Récupérer le hash du mot de passe en base — on autorise bcrypt uniquement (pas de MD5),
        // puisque le lien OAuth marque le compte comme « sécurisé » et qu'on refuse à partir de là
        // d'ouvrir une nouvelle voie à un hash faible.
        // Le cookie est lié au compte trouvé par e-mail : on refuse tout autre code (sinon le mot de
        // passe d'un compte quelconque suffirait à y rattacher cette identité Google).
        if (!hash_equals($pending['code'], $userCode)) {
            return $this->json(['message' => 'Invalid credentials'], Response::HTTP_UNAUTHORIZED);
        }

        // Mêmes protections que le login par mot de passe : rate-limit, verrouillage, journal.
        $limit = $this->loginRateLimiter->consume($request, $userCode);
        if (!$limit->isAccepted()) {
            $this->loginLogger->logFailure($userCode, LoginAttemptLogger::REASON_RATE_LIMITED, $request, LoginAttemptLogger::METHOD_PASSWORD);
            $retryAfter = max(1, $limit->getRetryAfter()->getTimestamp() - time());
            return $this->json(['message' => 'Too many attempts, please retry later'], Response::HTTP_TOO_MANY_REQUESTS, [
                'Retry-After' => (string) $retryAfter,
            ]);
        }
        if ($this->loginLogger->isAccountLocked($userCode)) {
            $this->loginLogger->logFailure($userCode, LoginAttemptLogger::REASON_ACCOUNT_LOCKED, $request);
            return $this->json([
                'message' => 'Account temporarily locked after too many failed attempts',
                'code' => 'ACCOUNT_LOCKED',
            ], Response::HTTP_LOCKED);
        }

        $row = $this->connection->fetchAssociative('SELECT Pwd FROM kp_user WHERE Code = ?', [$userCode]);
        if (!$row || !password_verify($password, $row['Pwd'])) {
            $this->loginLogger->logFailure($userCode, LoginAttemptLogger::REASON_BAD_PASSWORD, $request);
            return $this->json(['message' => 'Invalid credentials'], Response::HTTP_UNAUTHORIZED);
        }
        $this->loginRateLimiter->resetUser($userCode);

        // Lien effectif. INSERT IGNORE pour tolérer un double-clic (unicité par provider+sub).
        $this->connection->executeStatement(
            "INSERT IGNORE INTO kp_user_oauth (user_code, provider, subject, email_at_link, linked_at)
             VALUES (?, 'google', ?, ?, NOW())",
            [$userCode, $pending['sub'], $pending['email']],
        );

        $response = $this->issueSession($userCode, $request, legacyMd5: false, method: 'oauth_google');
        $response->headers->clearCookie('kpi_oauth_pending', '/', null, true, true, 'lax');
        return $response;
    }

    /** Supprime le lien pour l'utilisateur connecté. */
    #[Route('/unlink', name: 'api_auth_google_unlink', methods: ['POST'])]
    public function unlink(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['message' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        // Le compte doit garder au moins une méthode d'authentification active : si l'utilisateur
        // n'a pas de mot de passe (Pwd vide), on refuse (il devrait d'abord en définir un).
        $pwd = (string) $this->connection->fetchOne('SELECT Pwd FROM kp_user WHERE Code = ?', [$user->getCode()]);
        if ($pwd === '') {
            return $this->json([
                'message' => 'Définissez d\'abord un mot de passe avant de délier votre compte Google',
                'code' => 'PASSWORD_REQUIRED',
            ], Response::HTTP_CONFLICT);
        }

        $this->connection->executeStatement(
            "DELETE FROM kp_user_oauth WHERE user_code = ? AND provider = 'google'",
            [$user->getCode()],
        );

        return $this->json(['message' => 'Google account unlinked']);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────

    private function redirectLogin(string $errorCode): RedirectResponse
    {
        $redirect = new RedirectResponse(rtrim($this->app4Url, '/') . '/login?oauth_error=' . $errorCode);
        $redirect->headers->clearCookie('kpi_oauth_state', '/', null, true, true, 'lax');
        return $redirect;
    }

    private function redirectSecurity(string $query): RedirectResponse
    {
        $redirect = new RedirectResponse(rtrim($this->app4Url, '/') . '/profile/security?' . $query);
        $redirect->headers->clearCookie('kpi_oauth_state', '/', null, true, true, 'lax');
        $redirect->headers->clearCookie('kpi_oauth_link', '/', null, true, true, 'lax');
        return $redirect;
    }

    /**
     * Écrit le lien kp_user_oauth pour le user_code mémorisé dans le cookie signé.
     * Refuse (AUTH-OA-09) si le sub Google est déjà pris par un autre compte KPI.
     */
    private function finaliseAuthenticatedLink(string $userCode, string $sub, string $email): Response
    {
        // Vérifie que ce sub Google n'est pas déjà attribué à un AUTRE compte KPI.
        $owner = $this->connection->fetchOne(
            "SELECT user_code FROM kp_user_oauth WHERE provider = 'google' AND subject = ?",
            [$sub],
        );
        if (is_string($owner) && $owner !== '' && $owner !== $userCode) {
            return $this->redirectSecurity('link=error&code=already_linked');
        }

        // INSERT IGNORE : couvre le double-clic et le cas où (user_code, 'google') existe déjà
        // (uq_user_provider) avec le MÊME sub — relier le même Google deux fois n'est pas une erreur.
        $this->connection->executeStatement(
            "INSERT IGNORE INTO kp_user_oauth (user_code, provider, subject, email_at_link, linked_at)
             VALUES (?, 'google', ?, ?, NOW())",
            [$userCode, $sub, $email],
        );

        try {
            $this->connection->prepare(
                "INSERT INTO kp_journal (Dates, Users, Actions, Journal) VALUES (NOW(), ?, 'Lien Google ajouté', ?)"
            )->executeStatement([$userCode, 'Lien ajouté depuis /profile/security (email Google : ' . $email . ')']);
        } catch (\Exception) {
        }

        return $this->redirectSecurity('link=ok');
    }

    /**
     * Cookie signé HMAC : {user_code, state, exp}. La signature empêche un attaquant de poser
     * un cookie qui ferait lier son Google à un autre compte ; le state est vérifié en plus
     * pour qu'un cookie volé d'une autre tentative ne puisse être rejoué avec un code Google
     * frais.
     */
    private function buildLinkCookieValue(string $userCode, string $state): string
    {
        $payload = ['user_code' => $userCode, 'state' => $state, 'exp' => time() + 600];
        $payload['sig'] = hash_hmac('sha256', $payload['user_code'] . '|' . $payload['state'] . '|' . $payload['exp'], $this->getParameter('kernel.secret'));
        return base64_encode(json_encode($payload));
    }

    /**
     * Lit et valide le cookie `kpi_oauth_link`. Retourne le user_code ou null si absent / falsifié /
     * expiré / state qui ne matche pas celui de la requête courante.
     *
     * @return array{userCode: string}|null
     */
    private function readLinkCookie(Request $request, string $googleState): ?array
    {
        $raw = (string) $request->cookies->get('kpi_oauth_link', '');
        if ($raw === '') {
            return null;
        }
        $decoded = base64_decode($raw, true);
        if ($decoded === false) {
            return null;
        }
        $data = json_decode($decoded, true);
        if (!is_array($data)) {
            return null;
        }
        if (!is_int($data['exp'] ?? null) || $data['exp'] < time()) {
            return null;
        }
        if (!is_string($data['user_code'] ?? null) || !is_string($data['state'] ?? null) || !is_string($data['sig'] ?? null)) {
            return null;
        }
        $expected = hash_hmac('sha256', $data['user_code'] . '|' . $data['state'] . '|' . $data['exp'], $this->getParameter('kernel.secret'));
        if (!hash_equals($expected, $data['sig'])) {
            return null;
        }
        // Le state du cookie DOIT matcher celui renvoyé par Google : empêche de rejouer un cookie
        // vieux (10 min) sur un nouvel échange OAuth non prévu par le propriétaire du compte.
        if (!hash_equals($data['state'], $googleState)) {
            return null;
        }
        return ['userCode' => $data['user_code']];
    }

    /**
     * @return array{provider: string, code: string, sub: string, email: string}|null
     */
    private function readPendingCookie(Request $request): ?array
    {
        $raw = (string) $request->cookies->get('kpi_oauth_pending', '');
        if ($raw === '') {
            return null;
        }
        $decoded = base64_decode($raw, true);
        if ($decoded === false) {
            return null;
        }
        $data = json_decode($decoded, true);
        if (!is_array($data)) {
            return null;
        }
        if (($data['exp'] ?? 0) < time()) {
            return null;
        }
        foreach (['code', 'sub', 'email', 'sig'] as $key) {
            if (!is_string($data[$key] ?? null)) {
                return null;
            }
        }
        $expectedSig = $this->signPending($data['code'], $data['sub'], $data['email'], (int) $data['exp']);
        if (!hash_equals($expectedSig, $data['sig'])) {
            return null;
        }
        return ['provider' => $data['provider'], 'code' => $data['code'], 'sub' => $data['sub'], 'email' => $data['email']];
    }

    private function signPending(string $code, string $sub, string $email, int $exp): string
    {
        return hash_hmac('sha256', $code . '|' . $sub . '|' . $email . '|' . $exp, $this->getParameter('kernel.secret'));
    }

    /**
     * Crée la session JWT + refresh cookie pour $userCode. Mutualise la logique de AdminAuthController::login
     * (journal, device, cookies) sans dupliquer le body — on renvoie une redirection vers app4.
     */
    private function issueSession(string $userCode, Request $request, bool $legacyMd5, string $method): Response
    {
        /** @var User $user */
        $user = $this->userProvider->loadUserByIdentifier($userCode);
        $this->loginLogger->logSuccess($userCode, $method, $legacyMd5, $request);

        $email = (string) $this->connection->fetchOne('SELECT Mail FROM kp_user WHERE Code = ?', [$userCode]);
        $this->knownDeviceService->recognizeAndNotify($user, $email, $request);

        $accessToken = $this->jwtManager->create($user);
        // Fingerprint attaché pour que la révocation ciblée d'un appareil (AUTH-DV-04) coupe ses sessions.
        $refreshToken = $this->refreshTokenService->issue($user, $this->knownDeviceService->currentFingerprint($request));

        // Journal fonctionnel historique (comme pour un login classique).
        try {
            $identite = trim(($user->getPrenom() ?? '') . ' ' . ($user->getNom() ?? ''));
            $this->connection->prepare(
                "INSERT INTO kp_journal (Dates, Users, Actions, Journal) VALUES (NOW(), ?, 'Connexion Admin', ?)"
            )->executeStatement([$userCode, $identite ?: $userCode]);
        } catch (\Exception) {
        }

        $redirect = new RedirectResponse(rtrim($this->app4Url, '/') . '/?oauth=ok');
        $redirect->headers->setCookie($this->buildAccessCookie($accessToken));
        $redirect->headers->setCookie($this->buildRefreshCookie($refreshToken));
        $redirect->headers->clearCookie('kpi_oauth_state', '/', null, true, true, 'lax');
        return $redirect;
    }

    private function buildAccessCookie(string $jwt): Cookie
    {
        $ttl = (int) ($_ENV['JWT_TTL'] ?? getenv('JWT_TTL') ?: 900);
        return Cookie::create('kpi_access')->withValue($jwt)->withExpires(time() + $ttl)
            ->withPath('/')->withSecure(true)->withHttpOnly(true)->withSameSite('lax');
    }

    private function buildRefreshCookie(string $token): Cookie
    {
        return Cookie::create('kpi_refresh')->withValue($token)
            ->withExpires(time() + RefreshTokenService::TTL_SECONDS)
            ->withPath('/')->withSecure(true)->withHttpOnly(true)->withSameSite('lax');
    }
}
