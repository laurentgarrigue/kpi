<?php

namespace App\Tests\Unit\Service;

use App\Service\TeamLastUpdateService;
use PHPUnit\Framework\TestCase;

/** Nom affiché dans « Dernière modification … par Prénom NOM » (PAGE_SCRUTINEERING.md § 5.1, ADM-SCR-16). */
final class TeamLastUpdateServiceTest extends TestCase
{
    public function testTheLicenceGivesFirstNameAndUppercaseLastName(): void
    {
        self::assertSame('Laurent GARRIGUE', TeamLastUpdateService::displayName('42054', 'Laurent', 'Garrigue', 'Laurent Garrigue'));
    }

    public function testWithoutLicenceTheAccountIdentityIsUsed(): void
    {
        self::assertSame('Contrôleur Test', TeamLastUpdateService::displayName('TSCRUT', null, null, 'Contrôleur Test'));
    }

    public function testWithoutAnyNameTheAccountCodeIsUsed(): void
    {
        self::assertSame('TSCRUT', TeamLastUpdateService::displayName('TSCRUT', null, null, ' '));
    }
}
