<?php

namespace App\Tests\Integration;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Inventaire des mots de passe et retrait définitif du MD5
 * ({@see DOC/developer/in-progress/AUTH_HARDENING.md § 2bis — AUTH-RN-01, AUTH-RN-07, AUTH-RN-08}).
 *
 * Le repli MD5 est piloté par AUTH_LEGACY_MD5_FALLBACK, lu au démarrage du kernel : chaque test choisit
 * sa valeur avant de créer le client.
 */
final class AuthLegacyMd5RemovalTest extends ApiTestCase
{
    private const ENV = 'AUTH_LEGACY_MD5_FALLBACK';
    private const PASSWORD = 'legacy-password';

    private Connection $connection;

    protected function tearDown(): void
    {
        parent::tearDown();
        unset($_SERVER[self::ENV], $_ENV[self::ENV]);
    }

    public function testMd5LoginStillWorksAndIsUpgradedWhileTheFallbackIsActive(): void
    {
        $this->bootWithFallback(true);

        $this->login(self::PASSWORD, 200);

        self::assertStringStartsWith('$2y$', (string) $this->connection->fetchOne("SELECT Pwd FROM kp_user WHERE Code = 'TADMIN'"));
    }

    public function testCorrectMd5PasswordFailsOnceTheFallbackIsRemoved(): void
    {
        $this->bootWithFallback(false);

        $this->login(self::PASSWORD, 401);

        self::assertSame(md5(self::PASSWORD), $this->connection->fetchOne("SELECT Pwd FROM kp_user WHERE Code = 'TADMIN'"));
    }

    public function testPurgeRefusesToRunWhileTheFallbackIsActive(): void
    {
        $this->bootWithFallback(true);

        self::assertSame(1, $this->runCommand('app:auth:purge-md5-hashes')->getStatusCode());
        self::assertSame(md5(self::PASSWORD), $this->connection->fetchOne("SELECT Pwd FROM kp_user WHERE Code = 'TADMIN'"));
    }

    public function testPurgeLeavesNoMd5HashAndJournalsEachAccount(): void
    {
        $this->bootWithFallback(false);
        $bcrypt = password_hash('Kept-Password-1!', PASSWORD_BCRYPT);
        $this->connection->executeStatement("UPDATE kp_user SET Pwd = ? WHERE Code = 'TEDIT'", [$bcrypt]);

        $dryRun = $this->runCommand('app:auth:purge-md5-hashes', ['--dry-run' => true]);
        self::assertStringContainsString('TADMIN', $dryRun->getDisplay());
        self::assertSame(1, $this->countMd5Hashes(), 'dry-run : rien écrit');

        self::assertSame(0, $this->runCommand('app:auth:purge-md5-hashes')->getStatusCode());

        self::assertSame(0, $this->countMd5Hashes());
        self::assertSame('', $this->connection->fetchOne("SELECT Pwd FROM kp_user WHERE Code = 'TADMIN'"));
        self::assertSame($bcrypt, $this->connection->fetchOne("SELECT Pwd FROM kp_user WHERE Code = 'TEDIT'"), 'bcrypt intact');
        self::assertSame(1, (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM kp_journal WHERE Users = 'TADMIN' AND Actions = 'Purge hash MD5'",
        ));
    }

    public function testAuditCountsHashKindsAndListsMd5AccountsWithTheirLastLogin(): void
    {
        $this->bootWithFallback(true);
        $this->connection->executeStatement("UPDATE kp_user SET Pwd = ? WHERE Code = 'TEDIT'", [password_hash('x', PASSWORD_BCRYPT)]);
        $this->connection->executeStatement("UPDATE kp_user SET Mail = '' WHERE Code = 'TVIEW'");

        $display = $this->runCommand('app:auth:password-audit', ['--without-email' => true])->getDisplay();

        self::assertMatchesRegularExpression('/Hash MD5\s+1\b/', $display);
        self::assertMatchesRegularExpression('/Hash bcrypt\s+1\b/', $display);
        self::assertStringContainsString('2026-10-01 10:00:00', $display, 'dernier login de TADMIN (kp_journal des fixtures)');
        self::assertMatchesRegularExpression('/Comptes sans e-mail valide \(\d+\)[\s\S]*TVIEW/', $display);
    }

    private function bootWithFallback(bool $enabled): void
    {
        self::ensureKernelShutdown();
        $_SERVER[self::ENV] = $_ENV[self::ENV] = $enabled ? '1' : '0';
        $this->client = static::createClient();
        $this->connection = $this->beginWrites();
        $this->connection->executeStatement("UPDATE kp_user SET Pwd = ? WHERE Code = 'TADMIN'", [md5(self::PASSWORD)]);
    }

    private function login(string $password, int $expectedStatus): void
    {
        $this->client->request('POST', '/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['username' => 'TADMIN', 'password' => $password]));
        self::assertSame($expectedStatus, $this->client->getResponse()->getStatusCode(), (string) $this->client->getResponse()->getContent());
    }

    private function countMd5Hashes(): int
    {
        return (int) $this->connection->fetchOne("SELECT COUNT(*) FROM kp_user WHERE Pwd REGEXP '^[0-9a-fA-F]{32}$'");
    }

    /** @param array<string, mixed> $input */
    private function runCommand(string $name, array $input = []): CommandTester
    {
        $tester = new CommandTester((new Application(static::$kernel))->find($name));
        $tester->execute($input);

        return $tester;
    }
}
