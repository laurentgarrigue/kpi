<?php

namespace App\Tests\Unit\Scrutineering;

use App\Scrutineering\TeamNation;
use PHPUnit\Framework\TestCase;

/** Nation d'une équipe pour la navigation « même nation » (PAGE_SCRUTINEERING.md § 1.2, ADM-SCR-16). */
final class TeamNationTest extends TestCase
{
    public function testFrenchClubCodeOfFourCharactersIsFrance(): void
    {
        self::assertSame('FRA', TeamNation::of('4401'));
    }

    public function testInternationalCodeKeepsItsThreeFirstCharacters(): void
    {
        self::assertSame('GER', TeamNation::of('GER'));
        self::assertSame('GER', TeamNation::of('GER-W'));
        self::assertSame('ITA', TeamNation::of('ITA21'));
    }

    public function testEmptyCodeHasNoNation(): void
    {
        self::assertSame('', TeamNation::of(''));
    }
}
