<?php

namespace App\Security;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client minimal du flow OAuth 2.0 autorization code de Google — AUTH_HARDENING.md § 4.
 *
 * Pas de dépendance SDK : symfony/http-client suffit, et on reste maître de ce qui est validé.
 * Le `id_token` reçu par le canal arrière (HTTPS vers token endpoint) est considéré authentique
 * sans vérification de signature — contrairement à l'implicit flow où le token transite par le
 * navigateur. On lit juste les claims (`sub`, `email`, `email_verified`) pour résoudre le compte.
 */
final class GoogleOAuthClient
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $redirectUri,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->clientId !== '' && $this->clientSecret !== '' && $this->redirectUri !== '';
    }

    public function buildAuthorizationUrl(string $state): string
    {
        return self::AUTH_URL . '?' . http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'access_type' => 'online',
            'prompt' => 'select_account',
        ]);
    }

    /**
     * Échange le code autorisation contre les tokens, retourne les claims utiles.
     *
     * @return array{sub: string, email: string, emailVerified: bool, name: ?string}|null null si l'échange échoue.
     */
    public function exchangeCode(string $code): ?array
    {
        try {
            $response = $this->httpClient->request('POST', self::TOKEN_URL, [
                'body' => [
                    'code' => $code,
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'redirect_uri' => $this->redirectUri,
                    'grant_type' => 'authorization_code',
                ],
                'timeout' => 10,
            ]);
            if ($response->getStatusCode() !== 200) {
                return null;
            }
            $payload = $response->toArray(false);
        } catch (\Throwable) {
            return null;
        }

        $idToken = (string) ($payload['id_token'] ?? '');
        if ($idToken === '') {
            return null;
        }

        $claims = $this->decodeIdToken($idToken);
        if ($claims === null) {
            return null;
        }

        $sub = (string) ($claims['sub'] ?? '');
        $email = (string) ($claims['email'] ?? '');
        if ($sub === '' || $email === '') {
            return null;
        }

        return [
            'sub' => $sub,
            'email' => $email,
            'emailVerified' => (bool) ($claims['email_verified'] ?? false),
            'name' => isset($claims['name']) ? (string) $claims['name'] : null,
        ];
    }

    /** @return array<string, mixed>|null */
    private function decodeIdToken(string $idToken): ?array
    {
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            return null;
        }
        $json = base64_decode(strtr($parts[1], '-_', '+/'), true);
        if ($json === false) {
            return null;
        }
        $claims = json_decode($json, true);
        return is_array($claims) ? $claims : null;
    }
}
