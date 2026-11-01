<?php

namespace App\Tests\Integration;

use Doctrine\DBAL\Connection;

/**
 * Durcissement de /auth/login — rate-limit, verrouillage, journal
 * ({@see DOC/developer/in-progress/AUTH_HARDENING.md § 2.2}).
 *
 * Les trois dimensions sont testées isolément :
 *  - rate-limit par IP (AUTH-RL-01) : plusieurs essais depuis la même IP ;
 *  - rate-limit par code utilisateur (AUTH-RL-02) : plusieurs essais sur le même code depuis des IP distinctes ;
 *  - verrouillage du compte (AUTH-LK-01/02) : 5 échecs depuis le dernier succès → 423, même avec le bon mot de passe.
 *
 * On fixe un bcrypt connu sur TADMIN en setUp pour contrôler le mot de passe sans toucher aux fixtures.
 */
final class AuthLoginHardeningTest extends ApiTestCase
{
    private Connection $connection;
    private const PASSWORD = 'Correct-Horse-99!';
    private const WRONG = 'wrong-password';

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->beginWrites();
        $hash = password_hash(self::PASSWORD, PASSWORD_BCRYPT);
        $this->connection->executeStatement('UPDATE kp_user SET Pwd = ? WHERE Code = ?', [$hash, 'TADMIN']);
    }

    // ---------------------------------------------------------------- AUTH-JL-01

    public function testEveryLoginAttemptWritesToHistory(): void
    {
        $this->postLogin('TADMIN', self::WRONG, ip: '198.51.100.1');
        $this->postLogin('TADMIN', self::PASSWORD, ip: '198.51.100.1');

        $rows = $this->connection->fetchAllAssociative(
            'SELECT success, failure_reason, method, legacy_hash FROM kp_user_login_history WHERE user_code = ? ORDER BY id',
            ['TADMIN'],
        );
        self::assertCount(2, $rows);
        self::assertSame(0, (int) $rows[0]['success']);
        self::assertSame('bad_password', $rows[0]['failure_reason']);
        self::assertSame(1, (int) $rows[1]['success']);
        self::assertNull($rows[1]['failure_reason']);
        self::assertSame('password', $rows[1]['method']);
        self::assertSame(0, (int) $rows[1]['legacy_hash'], 'bcrypt natif → pas de legacy_hash');
    }

    public function testUnknownUserAttemptIsLoggedWithNullCodeAndReasonUnknownUser(): void
    {
        $this->postLogin('DOES-NOT-EXIST', self::WRONG, ip: '198.51.100.2');

        $row = $this->connection->fetchAssociative(
            "SELECT user_code, success, failure_reason FROM kp_user_login_history WHERE failure_reason = 'unknown_user' ORDER BY id DESC LIMIT 1",
        );
        self::assertNotFalse($row);
        self::assertNull($row['user_code']);
        self::assertSame(0, (int) $row['success']);
    }

    // ---------------------------------------------------------------- AUTH-RL-01 (par IP)

    public function testFiveFailuresFromSameIpTriggerIpRateLimit(): void
    {
        // Quota IP = 5 / 15 min. Les 5 premiers → 401 (bad_password), le 6ᵉ → 429.
        for ($i = 0; $i < 5; $i++) {
            $this->postLogin('TADMIN', self::WRONG, ip: '203.0.113.5', expectedStatus: 401);
        }
        $this->postLogin('TADMIN', self::WRONG, ip: '203.0.113.5', expectedStatus: 429);
        self::assertNotNull($this->client->getResponse()->headers->get('Retry-After'));
    }

    // ---------------------------------------------------------------- AUTH-RL-02 (par utilisateur, distribué)

    public function testTenFailuresOnSameUserFromDistinctIpsTriggerUserRateLimit(): void
    {
        // Quota user = 10 / 15 min, chaque IP est fraîche donc n'atteint pas le quota IP (5).
        // Après 5 échecs : le compte est verrouillé (423), mais le limiteur USER continue à être consommé
        // à chaque appel ; au 11ᵉ, il déborde → 429 (prime sur 423 : le rate-limit est le premier check).
        for ($i = 1; $i <= 10; $i++) {
            $this->postLogin('TADMIN', self::WRONG, ip: "198.51.100.{$i}");
        }
        $this->postLogin('TADMIN', self::WRONG, ip: '203.0.113.99', expectedStatus: 429);
        self::assertNotNull($this->client->getResponse()->headers->get('Retry-After'));
    }

    // ---------------------------------------------------------------- AUTH-LK-01 (verrouillage)

    public function testFiveFailuresFromDistinctIpsLockTheAccount(): void
    {
        // On distribue sur 5 IP pour ne PAS déclencher le rate-limit IP, et vérifier que
        // c'est bien le verrouillage de compte (423) qui prend la main sur le bon mot de passe.
        for ($i = 1; $i <= 5; $i++) {
            $this->postLogin('TADMIN', self::WRONG, ip: "198.51.100.{$i}", expectedStatus: 401);
        }
        $this->postLogin('TADMIN', self::PASSWORD, ip: '198.51.100.6', expectedStatus: 423);

        $lockedRow = $this->connection->fetchAssociative(
            "SELECT failure_reason FROM kp_user_login_history WHERE user_code = 'TADMIN' ORDER BY id DESC LIMIT 1",
        );
        self::assertSame('account_locked', $lockedRow['failure_reason']);
    }

    // ---------------------------------------------------------------- AUTH-LK-02 (succès remet le compteur à zéro)

    public function testSuccessResetsFailureCounter(): void
    {
        // 4 échecs < seuil, donc pas de lockout.
        for ($i = 1; $i <= 4; $i++) {
            $this->postLogin('TADMIN', self::WRONG, ip: "198.51.100.{$i}", expectedStatus: 401);
        }
        // Succès : la fenêtre de verrouillage repart (les futurs échecs ne comptent plus les 4 précédents).
        $this->postLogin('TADMIN', self::PASSWORD, ip: '198.51.100.5', expectedStatus: 200);

        // Nouvelle salve de 4 échecs : toujours OK.
        for ($i = 6; $i <= 9; $i++) {
            $this->postLogin('TADMIN', self::WRONG, ip: "198.51.100.{$i}", expectedStatus: 401);
        }
        // 5ᵉ échec post-succès → lockout au prochain appel.
        $this->postLogin('TADMIN', self::WRONG, ip: '198.51.100.10', expectedStatus: 401);
        $this->postLogin('TADMIN', self::PASSWORD, ip: '198.51.100.11', expectedStatus: 423);
    }

    // ---------------------------------------------------------------- AUTH-JL-03 (lecture du journal)

    public function testLoginHistoryEndpointIsReservedToSelfAndSuperAdmin(): void
    {
        $this->postLogin('TADMIN', self::PASSWORD, ip: '198.51.100.1', expectedStatus: 200);

        // TADMIN est profil 2 (admin — pas super-admin, qui est profil 1) :
        // il peut voir le sien, pas celui d'un autre utilisateur.
        $this->requestAs('TADMIN', 'GET', '/auth/login-history?code=TADMIN', expectedStatus: 200);
        $this->requestAs('TADMIN', 'GET', '/auth/login-history?code=TEDIT', expectedStatus: 403);

        // TEDIT (profil 8) ne peut voir que son propre historique.
        $this->requestAs('TEDIT', 'GET', '/auth/login-history?code=TEDIT', expectedStatus: 200);
        $this->requestAs('TEDIT', 'GET', '/auth/login-history?code=TADMIN', expectedStatus: 403);
    }

    // ---------------------------------------------------------------- Helpers

    private function postLogin(string $username, string $password, string $ip, ?int $expectedStatus = null): void
    {
        $this->client->request(
            'POST',
            '/auth/login',
            server: ['CONTENT_TYPE' => 'application/json', 'REMOTE_ADDR' => $ip],
            content: json_encode(['username' => $username, 'password' => $password]),
        );
        if ($expectedStatus !== null) {
            self::assertSame(
                $expectedStatus,
                $this->client->getResponse()->getStatusCode(),
                sprintf(
                    'POST /auth/login (ip=%s, user=%s) attendu %d, corps : %s',
                    $ip, $username, $expectedStatus,
                    substr((string) $this->client->getResponse()->getContent(), 0, 200),
                ),
            );
        }
    }
}
