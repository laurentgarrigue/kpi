<?php

namespace App\Tests\Integration;

use Doctrine\DBAL\Connection;

/**
 * Phase 1b : rotation du refresh cookie, logout, détection de rejeu, révocation d'appareil
 * ({@see DOC/developer/in-progress/AUTH_HARDENING.md § 3}).
 */
final class AuthRefreshSessionTest extends ApiTestCase
{
    private Connection $connection;
    private const PASSWORD = 'Correct-Horse-99!';

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->beginWrites();
        $this->connection->executeStatement(
            'UPDATE kp_user SET Pwd = ? WHERE Code = ?',
            [password_hash(self::PASSWORD, PASSWORD_BCRYPT), 'TADMIN'],
        );
    }

    // ------------------------------------------------------- AUTH-CK-01 : login pose les deux cookies

    public function testLoginSetsBothCookies(): void
    {
        $this->postLogin('TADMIN', self::PASSWORD, ip: '198.51.100.1');
        $cookies = $this->client->getResponse()->headers->getCookies();
        $names = array_map(fn($c) => $c->getName(), $cookies);

        self::assertContains('kpi_access', $names);
        self::assertContains('kpi_refresh', $names);

        foreach ($cookies as $cookie) {
            if (in_array($cookie->getName(), ['kpi_access', 'kpi_refresh'], true)) {
                self::assertTrue($cookie->isHttpOnly(), $cookie->getName() . ' doit être HttpOnly');
                self::assertTrue($cookie->isSecure(), $cookie->getName() . ' doit être Secure');
                self::assertSame('lax', $cookie->getSameSite());
            }
        }
    }

    // ------------------------------------------------------- AUTH-RT-01 : rotation

    public function testRefreshSessionRotatesRefreshToken(): void
    {
        $this->postLogin('TADMIN', self::PASSWORD, ip: '198.51.100.2');
        $refresh1 = $this->refreshCookieValue();

        $this->sendCookies(['kpi_refresh' => $refresh1]);
        $this->client->request('POST', '/auth/refresh-session');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $refresh2 = $this->refreshCookieValue();
        self::assertNotSame($refresh1, $refresh2, 'un nouveau refresh token doit être émis');

        // Vieux token révoqué, lien replaced_by positionné.
        $oldRow = $this->connection->fetchAssociative(
            'SELECT revoked_at, replaced_by FROM kp_user_refresh_token WHERE token_hash = ?',
            [hash('sha256', $refresh1)],
        );
        self::assertNotNull($oldRow['revoked_at']);
        self::assertNotNull($oldRow['replaced_by']);
    }

    // ------------------------------------------------------- AUTH-RT-03 : rejeu

    public function testReusingRevokedRefreshTokenRevokesAllActiveTokens(): void
    {
        $this->postLogin('TADMIN', self::PASSWORD, ip: '198.51.100.3');
        $firstRefresh = $this->refreshCookieValue();

        // 1re rotation OK.
        $this->sendCookies(['kpi_refresh' => $firstRefresh]);
        $this->client->request('POST', '/auth/refresh-session');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        // Replay du vieux token → 401 ET toute la chaîne active du compte est révoquée.
        $this->sendCookies(['kpi_refresh' => $firstRefresh]);
        $this->client->request('POST', '/auth/refresh-session');
        self::assertSame(401, $this->client->getResponse()->getStatusCode());

        $activeCount = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM kp_user_refresh_token WHERE user_code = 'TADMIN' AND revoked_at IS NULL",
        );
        self::assertSame(0, $activeCount, 'tous les refresh tokens du compte doivent être révoqués en cas de rejeu');
    }

    // ------------------------------------------------------- AUTH-RT-02 : logout

    public function testLogoutRevokesRefreshTokenAndClearsCookies(): void
    {
        $this->postLogin('TADMIN', self::PASSWORD, ip: '198.51.100.4');
        $refresh = $this->refreshCookieValue();

        $this->sendCookies(['kpi_refresh' => $refresh]);
        $this->client->request('POST', '/auth/logout');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $row = $this->connection->fetchAssociative(
            'SELECT revoked_at FROM kp_user_refresh_token WHERE token_hash = ?',
            [hash('sha256', $refresh)],
        );
        self::assertNotNull($row['revoked_at']);

        // Les cookies sont effacés (Set-Cookie avec Max-Age=0).
        $clearedNames = [];
        foreach ($this->client->getResponse()->headers->getCookies() as $cookie) {
            if ($cookie->isCleared()) {
                $clearedNames[] = $cookie->getName();
            }
        }
        self::assertContains('kpi_refresh', $clearedNames);
        self::assertContains('kpi_access', $clearedNames);
    }

    // ------------------------------------------------------- AUTH-DV-01 / AUTH-DV-02 : alerte nouvel appareil

    public function testFirstLoginRegistersDeviceAndSecondFromSameFingerprintDoesNotCreateDuplicate(): void
    {
        $this->postLogin('TADMIN', self::PASSWORD, ip: '198.51.100.5', userAgent: 'Mozilla/5.0 Firefox/125');
        $this->postLogin('TADMIN', self::PASSWORD, ip: '198.51.100.5', userAgent: 'Mozilla/5.0 Firefox/126'); // même famille

        $count = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM kp_user_known_device WHERE user_code = 'TADMIN'",
        );
        self::assertSame(1, $count, 'même IP /24 + même famille UA = même appareil, pas de duplicata');
    }

    public function testDeviceRevokeTokenRevokesDeviceAndAllRefreshTokens(): void
    {
        // Login + capture du revoke_token_hash en base
        $this->postLogin('TADMIN', self::PASSWORD, ip: '198.51.100.6', userAgent: 'Mozilla/5.0 Chrome/120');
        $hash = (string) $this->connection->fetchOne(
            "SELECT revoke_token_hash FROM kp_user_known_device WHERE user_code = 'TADMIN'",
        );
        self::assertNotEmpty($hash);

        // On ne peut pas récupérer le token clair (seul l'email le reçoit), donc on en forge un et on remplace
        // son hash dans la DB — ça teste l'endpoint comme s'il recevait le vrai lien de l'email.
        $forged = 'test-revoke-token-' . bin2hex(random_bytes(16));
        $this->connection->update(
            'kp_user_known_device',
            ['revoke_token_hash' => hash('sha256', $forged)],
            ['user_code' => 'TADMIN'],
        );

        $this->client->request('GET', '/auth/device/revoke/' . $forged);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $device = $this->connection->fetchAssociative(
            "SELECT revoked_at, revoke_token_hash FROM kp_user_known_device WHERE user_code = 'TADMIN'",
        );
        self::assertNotNull($device['revoked_at']);
        self::assertNull($device['revoke_token_hash']);

        $active = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM kp_user_refresh_token WHERE user_code = 'TADMIN' AND revoked_at IS NULL",
        );
        self::assertSame(0, $active, 'toutes les sessions actives doivent être tuées');

        // Rejouer le même token : 404 (déjà utilisé).
        $this->client->request('GET', '/auth/device/revoke/' . $forged);
        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    // ------------------------------------------------------- Helpers

    private function postLogin(string $username, string $password, string $ip, string $userAgent = 'Mozilla/5.0'): void
    {
        $this->client->request(
            'POST',
            '/auth/login',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'REMOTE_ADDR' => $ip,
                'HTTP_USER_AGENT' => $userAgent,
            ],
            content: json_encode(['username' => $username, 'password' => $password]),
        );
    }

    private function refreshCookieValue(): string
    {
        foreach ($this->client->getResponse()->headers->getCookies() as $cookie) {
            if ($cookie->getName() === 'kpi_refresh' && !$cookie->isCleared()) {
                return (string) $cookie->getValue();
            }
        }
        self::fail('aucun cookie kpi_refresh dans la réponse');
    }
}
