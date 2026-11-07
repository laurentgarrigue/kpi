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

    public function testJrnCat05CategoriesFilterKeepsOnlyTheCheckedCategories(): void
    {
        $items = $this->requestAs('TADMIN', 'GET', '/admin/journal?categories=connexion,pdf&limit=50')['items'];
        $categories = array_unique(array_column($items, 'category'));
        sort($categories);

        self::assertSame(['connexion', 'pdf'], $categories);
        self::assertNotContains('Scoring score', array_column($items, 'action'), 'le scoring décoché est exclu');
    }

    public function testJrnCat05WithoutCategoriesEveryEntryIsListedWithItsCategory(): void
    {
        $items = $this->requestAs('TADMIN', 'GET', '/admin/journal?limit=50')['items'];
        $byAction = array_column($items, 'category', 'action');

        self::assertSame('scoring', $byAction['Scoring score'] ?? null);
        self::assertSame('pdf', $byAction['PDF presence'] ?? null);
        self::assertSame('connexion', $byAction['Connexion Admin'] ?? null);
    }

    public function testJrnCat06ActionsAreGroupedByCategoryWithScoringUncheckedByDefault(): void
    {
        $categories = $this->requestAs('TADMIN', 'GET', '/admin/journal/actions')['categories'];
        $byKey = array_column($categories, null, 'key');

        self::assertSame(
            ['connexion', 'administration', 'operation', 'presence', 'scoring', 'pdf', 'editorial'],
            array_column($categories, 'key'),
        );
        self::assertContains('Scoring score', $byKey['scoring']['actions']);
        self::assertContains('Connexion Admin', $byKey['connexion']['actions']);
        self::assertFalse($byKey['scoring']['defaultSelected']);
        self::assertTrue($byKey['connexion']['defaultSelected']);
    }
}
