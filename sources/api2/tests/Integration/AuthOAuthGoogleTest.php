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

    // ------------------------------------------------------- AUTH-OA-07 : connect exige un JWT

    public function testConnectRequiresAuthentication(): void
    {
        $this->client->request('GET', '/auth/google/connect');
        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    // ------------------------------------------------------- AUTH-OA-08 : lien direct depuis le profil

    public function testCallbackWithValidLinkCookieWritesTheLinkWithoutEmailMatch(): void
    {
        // L'utilisateur authentifié a TADMIN. L'adresse Google (gmail-perso@…) n'est
        // associée à AUCUN kp_user (donc la voie login échouerait en « oauth_no_account »).
        // Avec un cookie link signé, le callback doit lier TADMIN et son sub directement.
        $this->setGoogleHttpWith(sub: 'g-ADMIN-NEW', email: 'gmail-perso@example.test', verified: true);

        $this->sendCookies([
            'kpi_oauth_state' => 'S',
            'kpi_oauth_link' => $this->buildLinkCookieValue('TADMIN', 'S'),
        ]);

        $this->client->request('GET', '/auth/google/callback?code=xyz&state=S');
        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        $location = (string) $this->client->getResponse()->headers->get('Location');
        self::assertStringContainsString('/profile/security?link=ok', $location);

        // Lien écrit avec le sub reçu, pas d'erreur silencieuse.
        $row = $this->connection->fetchAssociative(
            "SELECT user_code, subject, email_at_link FROM kp_user_oauth WHERE provider = 'google' AND user_code = 'TADMIN'",
        );
        self::assertSame('TADMIN', $row['user_code']);
        self::assertSame('g-ADMIN-NEW', $row['subject']);
        self::assertSame('gmail-perso@example.test', $row['email_at_link']);

        // Et pas d'écriture pour un autre user (TEDIT a cette même adresse en fixture ? Non, ici on
        // vérifie qu'on n'a touché qu'une seule ligne).
        $count = (int) $this->connection->fetchOne("SELECT COUNT(*) FROM kp_user_oauth WHERE provider = 'google'");
        self::assertSame(1, $count);
    }

    public function testCallbackLinkModeDoesNotRequireMatchingEmail(): void
    {
        // Même quand plusieurs comptes KPI partagent l'adresse Google reçue, le mode lien ne regarde
        // PAS l'email : il écrit pour le user_code du cookie signé. C'est précisément ce qui débloque
        // le cas « oauth_email_ambiguous ».
        $this->connection->insert('kp_user', [
            'Code' => 'TAMB', 'Pwd' => '', 'Identite' => 'Autre', 'Mail' => 'admin@example.test',
            'Tel' => '', 'Fonction' => '', 'Niveau' => 8,
            'Filtre_competition' => '', 'Filtre_saison' => '', 'Filtre_competition_sql' => '', 'Filtre_journee' => '',
        ]);
        $this->setGoogleHttpWith(sub: 'g-SHARED', email: 'admin@example.test', verified: true);

        $this->sendCookies([
            'kpi_oauth_state' => 'S',
            'kpi_oauth_link' => $this->buildLinkCookieValue('TADMIN', 'S'),
        ]);

        $this->client->request('GET', '/auth/google/callback?code=xyz&state=S');
        self::assertStringContainsString('/profile/security?link=ok', (string) $this->client->getResponse()->headers->get('Location'));

        $owner = $this->connection->fetchOne(
            "SELECT user_code FROM kp_user_oauth WHERE subject = 'g-SHARED'",
        );
        self::assertSame('TADMIN', $owner, 'lie bien TADMIN même si TAMB partage l\'email');
    }

    // ------------------------------------------------------- AUTH-OA-09 : sub déjà pris → refus

    public function testCallbackLinkRefusesWhenSubjectAlreadyLinkedToAnotherUser(): void
    {
        $this->connection->insert('kp_user_oauth', [
            'user_code' => 'TEDIT', 'provider' => 'google', 'subject' => 'g-TAKEN',
            'email_at_link' => 'e@d.test', 'linked_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
        $this->setGoogleHttpWith(sub: 'g-TAKEN', email: 'e@d.test', verified: true);

        $this->sendCookies([
            'kpi_oauth_state' => 'S',
            'kpi_oauth_link' => $this->buildLinkCookieValue('TADMIN', 'S'),
        ]);

        $this->client->request('GET', '/auth/google/callback?code=xyz&state=S');
        self::assertStringContainsString(
            '/profile/security?link=error&code=already_linked',
            (string) $this->client->getResponse()->headers->get('Location'),
        );
        // Aucune ligne ajoutée pour TADMIN.
        $count = (int) $this->connection->fetchOne("SELECT COUNT(*) FROM kp_user_oauth WHERE user_code = 'TADMIN'");
        self::assertSame(0, $count);
    }

    // ------------------------------------------------------- Cookie link falsifié : revient au mode login

    public function testCallbackIgnoresLinkCookieWithWrongSignature(): void
    {
        // Même user_code, même state, mais signature forgée : le readLinkCookie refuse,
        // on retombe sur le flow normal (oauth_no_account ici).
        $forged = base64_encode(json_encode([
            'user_code' => 'TADMIN', 'state' => 'S', 'exp' => time() + 600, 'sig' => 'not-the-real-hmac',
        ]));
        $this->setGoogleHttpWith(sub: 'g-NO', email: 'nope@nowhere.test', verified: true);
        $this->sendCookies(['kpi_oauth_state' => 'S', 'kpi_oauth_link' => $forged]);

        $this->client->request('GET', '/auth/google/callback?code=xyz&state=S');
        self::assertStringContainsString('oauth_error=oauth_no_account', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testCallbackIgnoresLinkCookieWhoseStateDoesNotMatch(): void
    {
        // Cookie correctement signé mais pour un ancien state : attaquant qui tente de rejouer un
        // cookie orphelin sur un nouvel échange OAuth → refusé, on retombe sur le login.
        $this->setGoogleHttpWith(sub: 'g-NO', email: 'nope@nowhere.test', verified: true);
        $this->sendCookies([
            'kpi_oauth_state' => 'S',
            'kpi_oauth_link' => $this->buildLinkCookieValue('TADMIN', 'OTHER'),
        ]);

        $this->client->request('GET', '/auth/google/callback?code=xyz&state=S');
        self::assertStringContainsString('oauth_error=oauth_no_account', (string) $this->client->getResponse()->headers->get('Location'));
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

    /**
     * Construit la même valeur que OAuthController::buildLinkCookieValue — signature HMAC avec
     * kernel.secret. On passe par le vrai paramètre du conteneur pour ne pas dupliquer la
     * constante et garder le test cassant si la logique de signature change.
     */
    private function buildLinkCookieValue(string $userCode, string $state): string
    {
        $secret = (string) static::getContainer()->getParameter('kernel.secret');
        $exp = time() + 600;
        $payload = [
            'user_code' => $userCode,
            'state' => $state,
            'exp' => $exp,
            'sig' => hash_hmac('sha256', $userCode . '|' . $state . '|' . $exp, $secret),
        ];
        return base64_encode(json_encode($payload));
    }
}
