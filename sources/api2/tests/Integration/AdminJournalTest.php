<?php

namespace App\Tests\Integration;

/**
 * Journal d'administration — endpoints /admin/journal*.
 *
 * Fige la préservation de l'historique après suppression d'un compte :
 * supprimer un `kp_user` ne doit pas faire disparaître ses lignes de journal
 * (sinon le compteur « ses actions » et la traçabilité métier perdent le fil).
 */
final class AdminJournalTest extends ApiTestCase
{
    public function testJournalListIncludesEntriesOfDeletedUsers(): void
    {
        $items = $this->requestAs('TADMIN', 'GET', '/admin/journal?limit=50')['items'];
        $byCode = array_column($items, null, 'userCode');

        self::assertArrayHasKey('TADMIN', $byCode, 'ligne du compte existant toujours là');
        self::assertSame('Admin Test', $byCode['TADMIN']['userIdentite']);
        self::assertFalse($byCode['TADMIN']['isDeleted']);

        self::assertArrayHasKey('TGHOST', $byCode, 'ligne du compte supprimé toujours là');
        self::assertNull($byCode['TGHOST']['userIdentite'], 'identité remontée = NULL (compte supprimé)');
        self::assertTrue($byCode['TGHOST']['isDeleted']);
    }

    public function testJournalUsersListsCodesOfDeletedUsersToo(): void
    {
        $users = $this->requestAs('TADMIN', 'GET', '/admin/journal/users');
        $codes = array_column($users, 'code');

        self::assertContains('TADMIN', $codes);
        self::assertContains('TGHOST', $codes, 'le filtre doit proposer les comptes supprimés pour pouvoir chercher leur historique');
        $ghost = array_values(array_filter($users, fn($u) => $u['code'] === 'TGHOST'))[0] ?? null;
        self::assertNotNull($ghost);
        self::assertTrue($ghost['isDeleted']);
        self::assertNull($ghost['identite']);
    }

    public function testJournalFilterByDeletedUserReturnsHisEntries(): void
    {
        $items = $this->requestAs('TADMIN', 'GET', '/admin/journal?user=TGHOST')['items'];
        self::assertCount(1, $items);
        self::assertSame('TGHOST', $items[0]['userCode']);
        self::assertTrue($items[0]['isDeleted']);
    }
}
