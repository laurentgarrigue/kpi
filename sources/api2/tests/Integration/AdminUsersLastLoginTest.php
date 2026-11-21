<?php

namespace App\Tests\Integration;

use Doctrine\DBAL\Connection;

/**
 * Colonne « Dernière connexion » de la liste des utilisateurs, réservée au profil 1 (PAGE_UTILISATEURS.md § 2.1 n° 16).
 * Comptes des fixtures : TSUPER (profil 1), TADMIN (profil 2), TEDIT et TVIEW (profil 8).
 */
final class AdminUsersLastLoginTest extends ApiTestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->beginWrites();
        $this->login('TEDIT', true, '2026-11-01 10:00:00');
        $this->login('TEDIT', true, '2026-11-10 09:30:00');
        $this->login('TEDIT', false, '2026-11-15 08:00:00');
        $this->login('TVIEW', false, '2026-11-12 08:00:00');
    }

    private function login(string $userCode, bool $success, string $attemptedAt): void
    {
        $this->connection->insert('kp_user_login_history', [
            'user_code' => $userCode,
            'success' => $success ? 1 : 0,
            'method' => 'password',
            'failure_reason' => $success ? null : 'bad_password',
            'ip' => '127.0.0.1',
            'user_agent' => 'test',
            'attempted_at' => $attemptedAt,
        ]);
    }

    /** @return array<string, array<string, mixed>> */
    private function usersSeenBy(string $userCode): array
    {
        $items = $this->requestAs($userCode, 'GET', '/admin/users?search=Test&limit=100')['items'];

        return array_column($items, null, 'code');
    }

    public function testSuperAdminSeesTheLastSuccessfulLoginInUtc(): void
    {
        $users = $this->usersSeenBy('TSUPER');

        self::assertSame('2026-11-10T09:30:00+00:00', $users['TEDIT']['lastLoginAt']);
    }

    public function testSuperAdminSeesNullWithoutAnySuccessfulLogin(): void
    {
        $users = $this->usersSeenBy('TSUPER');

        self::assertArrayHasKey('lastLoginAt', $users['TVIEW']);
        self::assertNull($users['TVIEW']['lastLoginAt']);
    }

    public function testOtherProfilesDoNotReceiveTheLastLogin(): void
    {
        $users = $this->usersSeenBy('TADMIN');

        self::assertArrayHasKey('TEDIT', $users);
        self::assertArrayNotHasKey('lastLoginAt', $users['TEDIT']);
    }
}
