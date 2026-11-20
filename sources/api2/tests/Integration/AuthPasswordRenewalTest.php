<?php

namespace App\Tests\Integration;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Renouvellement forcé du mot de passe en début de saison
 * ({@see DOC/developer/in-progress/AUTH_HARDENING.md § 2bis — AUTH-RN-01 à 06}).
 */
final class AuthPasswordRenewalTest extends ApiTestCase
{
    private const PASSWORD = 'Correct-Horse-99!';
    private const NEW_PASSWORD = 'Battery-Staple-42?';
    private const RENEWAL_REQUIRED = 'PASSWORD_RENEWAL_REQUIRED';

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->beginWrites();
        $this->connection->executeStatement('UPDATE kp_user SET Pwd = ? WHERE Code = ?', [password_hash(self::PASSWORD, PASSWORD_BCRYPT), 'TADMIN']);
    }

    // ---------------------------------------------------------------- AUTH-RN-01 : campagne de renouvellement

    public function testCampaignFlagsAccountsWithAPasswordNotRenewedSinceTheDate(): void
    {
        $this->connection->executeStatement('UPDATE kp_user SET Pwd = ? WHERE Code = ?', [password_hash(self::PASSWORD, PASSWORD_BCRYPT), 'TEDIT']);
        $this->connection->executeStatement("INSERT INTO kp_user_password_renewal (user_code, renewed_at) VALUES ('TEDIT', '2027-01-05 10:00:00')");

        $tester = $this->runCommand('app:auth:require-password-renewal', ['--renewed-since' => '2027-01-01']);

        self::assertSame(0, $tester->getStatusCode());
        self::assertTrue($this->isFlagged('TADMIN'), 'mot de passe non renouvelé depuis la date → marqué');
        self::assertFalse($this->isFlagged('TEDIT'), 'renouvelé depuis le début de saison → exempté');
        self::assertFalse($this->isFlagged('TVIEW'), 'sans mot de passe (Google seul) → exempté');
    }

    public function testCampaignDryRunWritesNothing(): void
    {
        $tester = $this->runCommand('app:auth:require-password-renewal', ['--renewed-since' => '2027-01-01', '--dry-run' => true]);

        self::assertStringContainsString('TADMIN', $tester->getDisplay());
        self::assertFalse($this->isFlagged('TADMIN'));
    }

    public function testCampaignRequiresTheRenewedSinceDate(): void
    {
        self::assertSame(2, $this->runCommand('app:auth:require-password-renewal', [])->getStatusCode());
    }

    // ---------------------------------------------------------------- AUTH-RN-02 : login d'un compte marqué

    public function testFlaggedAccountLoginOpensNoSessionAndReturnsARenewalToken(): void
    {
        $this->flag('TADMIN');

        $body = $this->post('/auth/login', ['username' => 'TADMIN', 'password' => self::PASSWORD], 403);

        self::assertSame(self::RENEWAL_REQUIRED, $body['code']);
        self::assertNotEmpty($body['renewalToken']);
        self::assertArrayNotHasKey('token', $body);
        self::assertSame([], $this->client->getResponse()->headers->getCookies(), 'aucun cookie de session');
    }

    public function testFlaggedAccountWithWrongPasswordGetsNoRenewalToken(): void
    {
        $this->flag('TADMIN');

        $body = $this->post('/auth/login', ['username' => 'TADMIN', 'password' => 'wrong'], 401);

        self::assertArrayNotHasKey('renewalToken', $body);
    }

    // ---------------------------------------------------------------- AUTH-RN-03 : refus côté api2 des sessions en cours

    public function testJwtIssuedBeforeTheFlagIsRefused(): void
    {
        $this->requestAs('TADMIN', 'GET', '/auth/me');
        $this->flag('TADMIN');

        $this->requestAs('TADMIN', 'GET', '/auth/me', expectedStatus: 401);
        $this->requestAs('TADMIN', 'GET', '/admin/users', expectedStatus: 401);
    }

    public function testRefreshSessionOfAFlaggedAccountIsRefused(): void
    {
        $this->post('/auth/login', ['username' => 'TADMIN', 'password' => self::PASSWORD], 200);
        $this->flag('TADMIN');

        $body = $this->post('/auth/refresh-session', [], 403);

        self::assertSame(self::RENEWAL_REQUIRED, $body['code']);
    }

    // ---------------------------------------------------------------- AUTH-RN-04 : nouveau mot de passe conforme et différent

    public function testNonCompliantRenewalIsRefused(): void
    {
        $token = $this->renewalToken();

        $body = $this->post('/auth/renew-password', ['renewalToken' => $token, 'password' => 'short'], 400);

        self::assertSame('WEAK_PASSWORD', $body['code']);
        self::assertTrue($this->isFlagged('TADMIN'));
    }

    public function testRenewalWithTheCurrentPasswordIsRefused(): void
    {
        $token = $this->renewalToken();

        $body = $this->post('/auth/renew-password', ['renewalToken' => $token, 'password' => self::PASSWORD], 400);

        self::assertSame('PASSWORD_REUSED', $body['code']);
        self::assertTrue($this->isFlagged('TADMIN'));
    }

    public function testInvalidRenewalTokenIsRefused(): void
    {
        $this->post('/auth/renew-password', ['renewalToken' => 'forged.token.value', 'password' => self::NEW_PASSWORD], 401);
    }

    // ---------------------------------------------------------------- AUTH-RN-05 : renouvellement réussi, tracé

    public function testRenewalSetsThePasswordOpensTheSessionAndIsJournaled(): void
    {
        $token = $this->renewalToken();

        $body = $this->post('/auth/renew-password', ['renewalToken' => $token, 'password' => self::NEW_PASSWORD], 200);

        self::assertNotEmpty($body['token']);
        self::assertSame('TADMIN', $body['user']['id'] ?? $body['user']['code'] ?? 'TADMIN');
        self::assertFalse($this->isFlagged('TADMIN'));
        self::assertSame(1, (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM kp_journal WHERE Users = 'TADMIN' AND Actions = 'Renouvellement mdp'",
        ));

        $this->post('/auth/login', ['username' => 'TADMIN', 'password' => self::NEW_PASSWORD], 200);
        $this->post('/auth/renew-password', ['renewalToken' => $token, 'password' => 'Third-Password-7!'], 401);
    }

    public function testForgottenPasswordResetAlsoClearsTheFlagButRefusesTheCurrentPassword(): void
    {
        $this->flag('TADMIN');
        $this->connection->executeStatement("INSERT INTO kp_user_token (user, token, generated_at) VALUES ('TADMIN', 'reset-token-test', NOW())");

        $reused = $this->post('/auth/reset-password', ['token' => 'reset-token-test', 'password' => self::PASSWORD], 400);
        self::assertSame('PASSWORD_REUSED', $reused['code']);

        $this->post('/auth/reset-password', ['token' => 'reset-token-test', 'password' => self::NEW_PASSWORD], 200);
        self::assertFalse($this->isFlagged('TADMIN'));
    }

    // ---------------------------------------------------------------- helpers

    private function renewalToken(): string
    {
        $this->flag('TADMIN');

        return $this->post('/auth/login', ['username' => 'TADMIN', 'password' => self::PASSWORD], 403)['renewalToken'];
    }

    private function flag(string $userCode): void
    {
        $this->connection->executeStatement(
            'INSERT INTO kp_user_password_renewal (user_code, required_at) VALUES (?, NOW()) ON DUPLICATE KEY UPDATE required_at = NOW()',
            [$userCode],
        );
    }

    private function isFlagged(string $userCode): bool
    {
        return $this->connection->fetchOne(
            'SELECT 1 FROM kp_user_password_renewal WHERE user_code = ? AND required_at IS NOT NULL',
            [$userCode],
        ) !== false;
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function post(string $uri, array $body, int $expectedStatus): array
    {
        $this->client->request('POST', $uri, server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($body, JSON_THROW_ON_ERROR));
        $response = $this->client->getResponse();
        self::assertSame($expectedStatus, $response->getStatusCode(), sprintf('POST %s : %s', $uri, substr((string) $response->getContent(), 0, 300)));

        return json_decode((string) $response->getContent(), true) ?? [];
    }

    /** @param array<string, mixed> $input */
    private function runCommand(string $name, array $input): CommandTester
    {
        $tester = new CommandTester((new Application(static::$kernel))->find($name));
        $tester->execute($input);

        return $tester;
    }
}
