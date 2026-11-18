<?php

namespace App\Tests\Integration;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Session continuity, lot B ({@see DOC/developer/in-progress/AUTH_HARDENING.md § 3ter.4}): tolerant rotation of the
 * refresh token (AUTH-SC-10 to 12) and a refresh cookie that outlives the JWT (AUTH-SC-13, 14).
 */
final class AuthSessionContinuityTest extends ApiTestCase
{
    private const PASSWORD = 'Correct-Horse-99!';
    private const DEFAULT_TTL = 259200; // 72 h, REFRESH_TOKEN_TTL default
    private const TOLERANCE = 60;

    /** The scoring tablet. */
    private const TABLET = ['ip' => '198.51.100.30', 'ua' => 'Mozilla/5.0 (X11; Linux x86_64) Firefox/125.0'];
    /** Another pitch using the same account. */
    private const OTHER_PITCH = ['ip' => '203.0.113.40', 'ua' => 'Mozilla/5.0 (X11; Linux x86_64) Firefox/125.0'];
    /** Somebody else on another network. */
    private const ELSEWHERE = ['ip' => '192.0.2.50', 'ua' => 'Mozilla/5.0 (X11; Linux x86_64) Firefox/125.0'];

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->beginWrites();
        foreach (['TADMIN', 'TSCORE'] as $code) {
            $this->connection->executeStatement(
                'UPDATE kp_user SET Pwd = ? WHERE Code = ?',
                [password_hash(self::PASSWORD, PASSWORD_BCRYPT), $code],
            );
        }
    }

    // ------------------------------------------------------- AUTH-SC-10 : grace

    public function testDuplicateFromTheSameDeviceWithinGraceGetsAnotherSuccessor(): void
    {
        $first = $this->login('TADMIN', self::TABLET);
        $second = $this->refreshSession($first, self::TABLET, 200);

        // Two tabs, or the response of the first call lost on the venue wifi: the same token comes back.
        $third = $this->refreshSession($first, self::TABLET, 200);

        self::assertNotSame($second, $third);
        self::assertTrue($this->isActive($second), 'the successor issued first stays valid (the other tab holds it)');
        self::assertTrue($this->isActive($third));
    }

    // ------------------------------------------------------- AUTH-SC-11 : no resurrection

    public function testGraceDoesNotBringBackALoggedOutSession(): void
    {
        $first = $this->login('TADMIN', self::TABLET);
        $otherPitch = $this->login('TADMIN', self::OTHER_PITCH);
        $second = $this->refreshSession($first, self::TABLET, 200);

        $this->sendCookies(['kpi_refresh' => $second]);
        $this->client->request('POST', '/auth/logout');

        $this->refreshSession($first, self::TABLET, 401);
        self::assertTrue($this->isActive($otherPitch), 'a closed session is refused without revoking anything else');
    }

    // ------------------------------------------------------- AUTH-SC-12 : replay scoped to the device

    public function testReplayFromAnotherDeviceRevokesOnlyTheSessionsOfTheChainsDevice(): void
    {
        $first = $this->login('TADMIN', self::TABLET);
        $otherPitch = $this->login('TADMIN', self::OTHER_PITCH);
        $second = $this->refreshSession($first, self::TABLET, 200);

        $this->refreshSession($first, self::ELSEWHERE, 401);

        self::assertFalse($this->isActive($second), 'the stolen chain is cut');
        self::assertTrue($this->isActive($otherPitch), 'the other pitch using the same account stays connected');
    }

    public function testReplayAfterTheGraceWindowIsAReplay(): void
    {
        $first = $this->login('TADMIN', self::TABLET);
        $second = $this->refreshSession($first, self::TABLET, 200);
        $this->connection->executeStatement(
            'UPDATE kp_user_refresh_token SET revoked_at = ? WHERE token_hash = ?',
            [(new \DateTimeImmutable('-2 minutes'))->format('Y-m-d H:i:s'), hash('sha256', $first)],
        );

        $this->refreshSession($first, self::TABLET, 401);

        self::assertFalse($this->isActive($second));
    }

    // ------------------------------------------------------- AUTH-SC-13 : /auth/refresh slides the cookie

    public function testRefreshExtendsTheRefreshCookieWithoutRotatingIt(): void
    {
        $refresh = $this->login('TADMIN', self::TABLET);
        $this->connection->executeStatement(
            'UPDATE kp_user_refresh_token SET expires_at = ? WHERE token_hash = ?',
            [(new \DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:s'), hash('sha256', $refresh)],
        );

        $this->refreshWithJwt('TADMIN', ['kpi_refresh' => $refresh]);

        $cookie = $this->responseCookie('kpi_refresh');
        self::assertNotNull($cookie, 'the refresh cookie is sent back');
        self::assertSame($refresh, $cookie->getValue(), 'same value: no rotation, hence no replay risk');
        self::assertEqualsWithDelta(time() + self::DEFAULT_TTL, $cookie->getExpiresTime(), self::TOLERANCE);
        self::assertEqualsWithDelta(time() + self::DEFAULT_TTL, $this->expiresAt($refresh), self::TOLERANCE);
        self::assertTrue($this->isActive($refresh));
    }

    public function testRefreshIgnoresTheRefreshCookieOfAnotherAccount(): void
    {
        $scorer = $this->login('TSCORE', self::TABLET);
        $before = $this->expiresAt($scorer);

        $this->refreshWithJwt('TADMIN', ['kpi_refresh' => $scorer]);

        self::assertNull($this->responseCookie('kpi_refresh'));
        self::assertSame($before, $this->expiresAt($scorer));
    }

    public function testRefreshWithoutRefreshCookieStillRenewsTheJwt(): void
    {
        $this->refreshWithJwt('TADMIN', []);

        self::assertNull($this->responseCookie('kpi_refresh'));
    }

    // ------------------------------------------------------- AUTH-SC-14 : one duration everywhere

    public function testLoginAndRefreshSessionUseTheRefreshTokenTtl(): void
    {
        $refresh = $this->login('TADMIN', self::TABLET);
        self::assertEqualsWithDelta(time() + self::DEFAULT_TTL, $this->responseCookie('kpi_refresh')->getExpiresTime(), self::TOLERANCE);
        self::assertEqualsWithDelta(time() + self::DEFAULT_TTL, $this->expiresAt($refresh), self::TOLERANCE);

        $successor = $this->refreshSession($refresh, self::TABLET, 200);
        self::assertEqualsWithDelta(time() + self::DEFAULT_TTL, $this->responseCookie('kpi_refresh')->getExpiresTime(), self::TOLERANCE);
        self::assertEqualsWithDelta(time() + self::DEFAULT_TTL, $this->expiresAt($successor), self::TOLERANCE);
    }

    // ------------------------------------------------------- Helpers

    /** @param array{ip: string, ua: string} $device */
    private function login(string $userCode, array $device): string
    {
        $this->client->getCookieJar()->clear();
        $this->client->request(
            'POST',
            '/auth/login',
            server: $this->server($device) + ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['username' => $userCode, 'password' => self::PASSWORD]),
        );
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        return (string) $this->responseCookie('kpi_refresh')?->getValue();
    }

    /** @param array{ip: string, ua: string} $device */
    private function refreshSession(string $refreshToken, array $device, int $expectedStatus): string
    {
        $this->sendCookies(['kpi_refresh' => $refreshToken]);
        $this->client->request('POST', '/auth/refresh-session', server: $this->server($device));
        self::assertSame($expectedStatus, $this->client->getResponse()->getStatusCode());

        return (string) $this->responseCookie('kpi_refresh')?->getValue();
    }

    /** @param array<string, string> $cookies */
    private function refreshWithJwt(string $userCode, array $cookies): void
    {
        $this->sendCookies($cookies);
        $this->client->request('POST', '/auth/refresh', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->tokenFor($userCode),
        ] + $this->server(self::TABLET));
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @param array{ip: string, ua: string} $device
     * @return array<string, string>
     */
    private function server(array $device): array
    {
        return ['REMOTE_ADDR' => $device['ip'], 'HTTP_USER_AGENT' => $device['ua']];
    }

    private function responseCookie(string $name): ?Cookie
    {
        foreach ($this->client->getResponse()->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $name && !$cookie->isCleared()) {
                return $cookie;
            }
        }

        return null;
    }

    private function isActive(string $refreshToken): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT 1 FROM kp_user_refresh_token WHERE token_hash = ? AND revoked_at IS NULL AND expires_at > NOW()',
            [hash('sha256', $refreshToken)],
        );
    }

    private function expiresAt(string $refreshToken): int
    {
        $value = $this->connection->fetchOne(
            'SELECT expires_at FROM kp_user_refresh_token WHERE token_hash = ?',
            [hash('sha256', $refreshToken)],
        );

        return (new \DateTimeImmutable((string) $value))->getTimestamp();
    }
}
