<?php

namespace App\Tests\Integration;

use App\Security\GoogleOAuthClient;
use Doctrine\DBAL\Connection;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Phase 2 : OAuth Google ({@see DOC/developer/in-progress/AUTH_HARDENING.md § 4}).
 *
 * On remplace l'HttpClient par un mock pour simuler les réponses de l'endpoint token de Google.
 * Les claims (`sub`, `email`, `email_verified`) viennent d'un id_token JWT bidon ; l'implémentation
 * ne vérifie pas la signature puisque le token transite par le canal arrière HTTPS.
 */
final class AuthOAuthGoogleTest extends ApiTestCase
{
    private Connection $connection;

    /** @var array{sub: string, email: string, email_verified: bool, name: string} claims du prochain id_token */
    private array $googleClaims;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->beginWrites();
        $this->setGoogleHttpWith(sub: 'g-ADMIN-123', email: 'admin@example.test', verified: true);
        // Installé une seule fois : le conteneur refuse de remplacer un service déjà initialisé,
        // les tests ne changent donc que les claims renvoyés par le mock.
        static::getContainer()->set(GoogleOAuthClient::class, new GoogleOAuthClient(
            $this->mockGoogleHttp(),
            clientId: 'fake-client',
            clientSecret: 'fake-secret',
            redirectUri: 'https://kpi.localhost/api2/auth/google/callback',
        ));
    }

    // ------------------------------------------------------- AUTH-OA-05 : aucun compte

    public function testCallbackWithUnknownEmailRedirectsToLoginWithError(): void
    {
        $this->setGoogleHttpWith(sub: 'g-GHOST', email: 'ghost@nowhere.test', verified: true);
        $this->sendCookies(['kpi_oauth_state' => 'S']);
        $this->client->request('GET', '/auth/google/callback?code=xyz&state=S');
        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        self::assertStringContainsString('oauth_error=oauth_no_account', (string) $this->client->getResponse()->headers->get('Location'));
    }

    // ------------------------------------------------------- AUTH-OA-04 : email match → page de link

    public function testCallbackWithMatchingEmailRedirectsToLinkPage(): void
    {
        // Email de TADMIN (admin@example.test, cf. fixtures) → correspond à exactement un compte.
        $this->sendCookies(['kpi_oauth_state' => 'S']);
        $this->client->request('GET', '/auth/google/callback?code=xyz&state=S');
        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        $location = (string) $this->client->getResponse()->headers->get('Location');
        self::assertStringContainsString('/auth/google/link', $location);
        self::assertStringContainsString('code=TADMIN', $location);

        // Le cookie pending est posé pour que /link puisse écrire sans refaire l'échange.
        $cookieNames = array_map(fn($c) => $c->getName(), $this->client->getResponse()->headers->getCookies());
        self::assertContains('kpi_oauth_pending', $cookieNames);
    }

    // ------------------------------------------------------- Anti-CSRF state

    public function testCallbackWithMismatchedStateIsRejected(): void
    {
        $this->sendCookies(['kpi_oauth_state' => 'REAL']);
        $this->client->request('GET', '/auth/google/callback?code=xyz&state=FAKE');
        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        self::assertStringContainsString('oauth_error=oauth_state_mismatch', (string) $this->client->getResponse()->headers->get('Location'));
    }

    // ------------------------------------------------------- Email non vérifié

    public function testCallbackRejectsUnverifiedEmail(): void
    {
        $this->setGoogleHttpWith(sub: 'g-ADMIN-123', email: 'admin@example.test', verified: false);
        $this->sendCookies(['kpi_oauth_state' => 'S']);
        $this->client->request('GET', '/auth/google/callback?code=xyz&state=S');
        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        self::assertStringContainsString('oauth_error=oauth_email_not_verified', (string) $this->client->getResponse()->headers->get('Location'));
    }

    // ------------------------------------------------------- AUTH-OA-03 : identité déjà liée → login direct

    public function testCallbackWithExistingOauthLinkLogsInDirectly(): void
    {
        // Pré-insère un lien kp_user_oauth → le callback doit cracher les cookies de session, pas rediriger vers /link.
        $this->connection->insert('kp_user_oauth', [
            'user_code' => 'TADMIN',
            'provider' => 'google',
            'subject' => 'g-ADMIN-123',
            'email_at_link' => 'admin@example.test',
            'linked_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        $this->sendCookies(['kpi_oauth_state' => 'S']);
        $this->client->request('GET', '/auth/google/callback?code=xyz&state=S');
        self::assertSame(302, $this->client->getResponse()->getStatusCode());

        $cookieNames = array_map(fn($c) => $c->getName(), $this->client->getResponse()->headers->getCookies());
        self::assertContains('kpi_access', $cookieNames);
        self::assertContains('kpi_refresh', $cookieNames);
        // Une ligne de journal de sécurité `oauth_google` est écrite.
        $method = $this->connection->fetchOne(
            "SELECT method FROM kp_user_login_history WHERE user_code = 'TADMIN' ORDER BY id DESC LIMIT 1",
        );
        self::assertSame('oauth_google', $method);
    }

    // ------------------------------------------------------- AUTH-OA-06 : unlink nécessite un mot de passe actif

    public function testUnlinkRefusesIfUserHasNoPassword(): void
    {
        // TADMIN a Pwd='' dans les fixtures (jamais défini). On insère d'abord un lien pour pouvoir tester le délien.
        $this->connection->insert('kp_user_oauth', [
            'user_code' => 'TADMIN', 'provider' => 'google', 'subject' => 'g-x', 'email_at_link' => 'x@y.z',
            'linked_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        $this->requestAs('TADMIN', 'POST', '/auth/google/unlink', expectedStatus: 409);
    }

    public function testUnlinkSucceedsWhenPasswordIsSet(): void
    {
        $this->connection->executeStatement('UPDATE kp_user SET Pwd = ? WHERE Code = ?', [password_hash('x', PASSWORD_BCRYPT), 'TADMIN']);
        $this->connection->insert('kp_user_oauth', [
            'user_code' => 'TADMIN', 'provider' => 'google', 'subject' => 'g-x', 'email_at_link' => 'x@y.z',
            'linked_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        $this->requestAs('TADMIN', 'POST', '/auth/google/unlink', expectedStatus: 200);
        $count = (int) $this->connection->fetchOne("SELECT COUNT(*) FROM kp_user_oauth WHERE user_code = 'TADMIN'");
        self::assertSame(0, $count);
    }

    // ------------------------------------------------------- Helpers

    private function setGoogleHttpWith(string $sub, string $email, bool $verified): void
    {
        $this->googleClaims = ['sub' => $sub, 'email' => $email, 'email_verified' => $verified, 'name' => 'Test User'];
    }

    private function mockGoogleHttp(): HttpClientInterface
    {
        // Les claims sont lus à chaque appel, pas capturés : setGoogleHttpWith() reste effectif après setUp.
        return new MockHttpClient(function (string $method, string $url): MockResponse {
            $idToken = $this->fakeIdToken($this->googleClaims);
            return new MockResponse(json_encode([
                'access_token' => 'at-xxx',
                'id_token' => $idToken,
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]), ['http_code' => 200]);
        });
    }

    /** JWT bidon (header + payload base64url + signature arbitraire) — pas de vérif de signature côté client. */
    private function fakeIdToken(array $claims): string
    {
        $b64 = fn(string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        return $b64('{"alg":"RS256","typ":"JWT"}') . '.' . $b64(json_encode($claims)) . '.unsigned';
    }
}
