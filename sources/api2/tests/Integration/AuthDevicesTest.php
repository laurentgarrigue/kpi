<?php

namespace App\Tests\Integration;

use Doctrine\DBAL\Connection;

/**
 * Phase 1b-bis : gestion utilisateur des appareils ({@see DOC/developer/in-progress/AUTH_HARDENING.md § 3bis}).
 *
 * On vérifie :
 *   - AUTH-DV-03 : GET /auth/devices renvoie les appareils actifs + flag isCurrent correct.
 *   - AUTH-DV-04 : POST /auth/devices/{id}/revoke scope strict user + idempotent + anti-énumération (404).
 *   - AUTH-DV-05 : POST /auth/devices/revoke-others ne touche pas l'appareil courant.
 *   - AUTH-DV-06 : 401 sans JWT.
 *   - AUTH-DV-07 : journal `Révocation appareil` écrit.
 *
 * ⚠️ Les endpoints lisent le JWT via l'en-tête Authorization ; la détection « appareil courant »
 * repose sur l'empreinte (IP /24 + famille UA) de la requête, pas sur le JWT. Les tests fixent
 * donc REMOTE_ADDR / User-Agent pour contrôler quel device est reconnu comme « courant ».
 */
final class AuthDevicesTest extends ApiTestCase
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

    // ------------------------------------------------------- AUTH-DV-06

    public function testEndpointsRequireAuthentication(): void
    {
        $this->client->request('GET', '/auth/devices');
        self::assertSame(401, $this->client->getResponse()->getStatusCode());

        $this->client->request('POST', '/auth/devices/1/revoke');
        self::assertSame(401, $this->client->getResponse()->getStatusCode());

        $this->client->request('POST', '/auth/devices/revoke-others');
        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    // ------------------------------------------------------- AUTH-DV-03

    public function testListReturnsActiveDevicesWithCurrentFlag(): void
    {
        // Trois appareils : deux « étrangers » insérés en direct, un « courant » qui sera créé par le login.
        $otherFp1 = str_repeat('a', 64);
        $otherFp2 = str_repeat('b', 64);
        $this->seedDevice('TADMIN', $otherFp1, 'Chrome / 10.0.0.0', '2026-10-01 10:00:00');
        $this->seedDevice('TADMIN', $otherFp2, 'Firefox / 10.0.0.0', '2026-10-02 10:00:00');

        // Login depuis une IP + UA qui vont produire une empreinte « courante » différente des deux ci-dessus.
        $this->postLogin('TADMIN', self::PASSWORD, ip: '203.0.113.5', userAgent: 'Mozilla/5.0 Chrome/120');

        $body = $this->requestWithServer('GET', '/auth/devices', ip: '203.0.113.5', userAgent: 'Mozilla/5.0 Chrome/120');
        self::assertArrayHasKey('devices', $body);
        self::assertCount(3, $body['devices']);

        $currents = array_values(array_filter($body['devices'], fn($d) => $d['isCurrent']));
        self::assertCount(1, $currents, 'exactement un appareil doit être marqué comme courant');
        // Le LABEL garde l'IP complète (c'est l'EMPREINTE qui est masquée en /24 pour reconnaître
        // l'appareil malgré un changement de dernier octet). Le label est une info utilisateur.
        self::assertSame('Chrome / 203.0.113.5', $currents[0]['label']);
    }

    public function testListExcludesRevokedDevices(): void
    {
        $this->seedDevice('TADMIN', str_repeat('a', 64), 'Chrome / 1.1.1.0', '2026-10-01 10:00:00', revokedAt: '2026-10-02 10:00:00');
        $this->postLogin('TADMIN', self::PASSWORD, ip: '203.0.113.6', userAgent: 'Mozilla/5.0 Chrome/120');

        $body = $this->requestWithServer('GET', '/auth/devices', ip: '203.0.113.6', userAgent: 'Mozilla/5.0 Chrome/120');
        $labels = array_column($body['devices'], 'label');
        self::assertNotContains('Chrome / 1.1.1.0', $labels);
    }

    // ------------------------------------------------------- AUTH-DV-04

    public function testRevokeByIdOnlyKillsItsOwnSessions(): void
    {
        // Deux devices sur TADMIN, chacun avec un refresh token actif rattaché à son empreinte.
        $fpA = str_repeat('a', 64);
        $fpB = str_repeat('b', 64);
        $idA = $this->seedDevice('TADMIN', $fpA, 'Device A', '2026-10-01 10:00:00');
        $idB = $this->seedDevice('TADMIN', $fpB, 'Device B', '2026-10-02 10:00:00');
        $this->seedRefreshToken('TADMIN', $fpA);
        $this->seedRefreshToken('TADMIN', $fpB);

        $this->requestAs('TADMIN', 'POST', '/auth/devices/' . $idA . '/revoke', expectedStatus: 200);

        // Device A révoqué, Device B intact.
        self::assertNotNull($this->connection->fetchOne('SELECT revoked_at FROM kp_user_known_device WHERE id = ?', [$idA]));
        self::assertNull($this->connection->fetchOne('SELECT revoked_at FROM kp_user_known_device WHERE id = ?', [$idB]));

        // Refresh tokens : seuls ceux liés à fpA sont tués.
        $activeA = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM kp_user_refresh_token WHERE user_code = ? AND device_fingerprint = ? AND revoked_at IS NULL',
            ['TADMIN', $fpA],
        );
        $activeB = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM kp_user_refresh_token WHERE user_code = ? AND device_fingerprint = ? AND revoked_at IS NULL',
            ['TADMIN', $fpB],
        );
        self::assertSame(0, $activeA);
        self::assertSame(1, $activeB);
    }

    public function testRevokedDeviceAccessTokenIsRejectedBeforeItsExpiry(): void
    {
        // Le navigateur B se connecte et reçoit un JWT d'accès encore valide (JWT_TTL).
        $this->postLogin('TADMIN', self::PASSWORD, ip: '198.51.100.7', userAgent: 'Mozilla/5.0 Firefox/130');
        $token = json_decode((string) $this->client->getResponse()->getContent(), true)['token'];
        $deviceId = (int) $this->connection->fetchOne(
            'SELECT id FROM kp_user_known_device WHERE user_code = ? AND label LIKE ?',
            ['TADMIN', 'Firefox%'],
        );

        $this->client->request('GET', '/auth/devices', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
        self::assertSame(200, $this->client->getResponse()->getStatusCode(), 'avant révocation, le JWT est accepté');

        // Depuis le navigateur A (autre empreinte), on révoque l'appareil B.
        $this->requestAsWithServer('TADMIN', 'POST', '/auth/devices/' . $deviceId . '/revoke', ip: '203.0.113.9', userAgent: 'Mozilla/5.0 Chrome/120');

        $this->client->request('GET', '/auth/devices', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
        self::assertSame(401, $this->client->getResponse()->getStatusCode(), 'après révocation, le JWT de B est refusé');
    }

    public function testRevokeIdOfAnotherUserReturns404(): void
    {
        // Device appartenant à TEDIT, pas à TADMIN qui va tenter.
        $foreignId = $this->seedDevice('TEDIT', str_repeat('c', 64), 'Device étranger', '2026-10-01 10:00:00');

        $this->requestAs('TADMIN', 'POST', '/auth/devices/' . $foreignId . '/revoke', expectedStatus: 404);

        // L'appareil de TEDIT est intact (pas de divulgation, pas d'écriture).
        self::assertNull($this->connection->fetchOne('SELECT revoked_at FROM kp_user_known_device WHERE id = ?', [$foreignId]));
    }

    public function testRevokeIdIsIdempotent(): void
    {
        $id = $this->seedDevice('TADMIN', str_repeat('a', 64), 'Device A', '2026-10-01 10:00:00');

        $this->requestAs('TADMIN', 'POST', '/auth/devices/' . $id . '/revoke', expectedStatus: 200);
        // Second appel : la ligne est déjà révoquée → toujours 200, pas de 404 (anti-énumération + idempotence).
        $this->requestAs('TADMIN', 'POST', '/auth/devices/' . $id . '/revoke', expectedStatus: 200);
    }

    // ------------------------------------------------------- AUTH-DV-05

    public function testRevokeOthersKeepsCurrentDevice(): void
    {
        $otherFp = str_repeat('a', 64);
        $this->seedDevice('TADMIN', $otherFp, 'Device autre', '2026-10-01 10:00:00');
        $this->seedRefreshToken('TADMIN', $otherFp);

        // Login (crée le device courant côté TADMIN) depuis IP + UA fixés.
        $this->postLogin('TADMIN', self::PASSWORD, ip: '203.0.113.7', userAgent: 'Mozilla/5.0 Chrome/120');

        $body = $this->requestAsWithServer('TADMIN', 'POST', '/auth/devices/revoke-others', ip: '203.0.113.7', userAgent: 'Mozilla/5.0 Chrome/120');
        self::assertSame(1, $body['revoked'], 'un seul autre device devait être révoqué');

        // Le device courant est toujours actif, l'autre est révoqué. Le label du device courant porte
        // l'IP complète de la requête (cf. KnownDeviceService::label — l'empreinte est masquée, pas le label).
        $actives = $this->connection->fetchAllAssociative(
            "SELECT label FROM kp_user_known_device WHERE user_code = 'TADMIN' AND revoked_at IS NULL",
        );
        self::assertCount(1, $actives);
        self::assertStringContainsString('203.0.113.7', $actives[0]['label']);

        // Le refresh token de l'autre appareil est tué.
        $otherActive = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM kp_user_refresh_token WHERE device_fingerprint = ? AND revoked_at IS NULL',
            [$otherFp],
        );
        self::assertSame(0, $otherActive);
    }

    // ------------------------------------------------------- AUTH-DV-07

    public function testRevocationIsJournaled(): void
    {
        $id = $this->seedDevice('TADMIN', str_repeat('a', 64), 'Device A', '2026-10-01 10:00:00');

        $this->requestAs('TADMIN', 'POST', '/auth/devices/' . $id . '/revoke', expectedStatus: 200);

        $row = $this->connection->fetchAssociative(
            "SELECT Users, Actions, Journal FROM kp_journal WHERE Actions = 'Révocation appareil' ORDER BY Id DESC LIMIT 1",
        );
        self::assertNotFalse($row);
        self::assertSame('TADMIN', $row['Users']);
        self::assertStringContainsString('/profile/security', $row['Journal']);
    }

    // ---------------------------------------------------------------- Helpers

    private function seedDevice(string $userCode, string $fingerprint, string $label, string $createdAt, ?string $revokedAt = null): int
    {
        $this->connection->insert('kp_user_known_device', [
            'user_code' => $userCode,
            'fingerprint' => $fingerprint,
            'label' => $label,
            'created_at' => $createdAt,
            'last_seen_at' => $createdAt,
            'revoked_at' => $revokedAt,
        ]);
        return (int) $this->connection->lastInsertId();
    }

    private function seedRefreshToken(string $userCode, string $fingerprint): void
    {
        $this->connection->insert('kp_user_refresh_token', [
            'user_code' => $userCode,
            'token_hash' => hash('sha256', bin2hex(random_bytes(16))),
            'device_fingerprint' => $fingerprint,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'expires_at' => (new \DateTimeImmutable('+8 hours'))->format('Y-m-d H:i:s'),
        ]);
    }

    private function postLogin(string $username, string $password, string $ip, string $userAgent): void
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

    /**
     * requestAs classique mais avec contrôle de l'IP et du User-Agent — nécessaire pour que l'empreinte
     * de la requête matche un appareil précis.
     *
     * @return array<mixed>
     */
    private function requestAsWithServer(string $userCode, string $method, string $uri, string $ip, string $userAgent, int $expectedStatus = 200): array
    {
        $this->client->request($method, $uri, server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->tokenFor($userCode),
            'CONTENT_TYPE' => 'application/json',
            'REMOTE_ADDR' => $ip,
            'HTTP_USER_AGENT' => $userAgent,
        ]);
        self::assertSame(
            $expectedStatus,
            $this->client->getResponse()->getStatusCode(),
            sprintf('%s %s : code HTTP inattendu. Corps : %s', $method, $uri, substr((string) $this->client->getResponse()->getContent(), 0, 300)),
        );
        return json_decode((string) $this->client->getResponse()->getContent(), true) ?? [];
    }

    /** Variante sans JWT (lecture publique après seed), utilisée pour tests GET /auth/devices avec un cookie posé. */
    private function requestWithServer(string $method, string $uri, string $ip, string $userAgent): array
    {
        $this->client->request($method, $uri, server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->tokenFor('TADMIN'),
            'REMOTE_ADDR' => $ip,
            'HTTP_USER_AGENT' => $userAgent,
        ]);
        return json_decode((string) $this->client->getResponse()->getContent(), true) ?? [];
    }
}
