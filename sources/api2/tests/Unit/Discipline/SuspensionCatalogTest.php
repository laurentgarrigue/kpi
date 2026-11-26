<?php

namespace App\Tests\Unit\Discipline;

use App\Discipline\SuspensionCatalog;
use PHPUnit\Framework\TestCase;

/** Catalogue des suspensions de l'art. 50 (FEATURE_CARD_SUSPENSIONS.md § 2, S1, S2, S4). */
final class SuspensionCatalogTest extends TestCase
{
    public function testSus01NoCatalogueBeforeTheFirstSeasonThenVersionOne(): void
    {
        self::assertNull(SuspensionCatalog::forSeason('2026'));
        $catalog = SuspensionCatalog::forSeason('2027');
        self::assertNotNull($catalog);
        self::assertSame(1, $catalog->version);
        self::assertSame(
            ['GREEN_SEASON', 'YELLOW_RED_GAMEDAY', 'YELLOW_RED_SEASON', 'BLACK_GAME', 'BLACK_GAMEDAY', 'BLACK_SEASON'],
            array_map(static fn ($rule): string => $rule->code, $catalog->rules),
        );
        self::assertSame(1, SuspensionCatalog::forSeason('2998')?->version);
    }

    public function testOnlyTheNationalSectionIsCovered(): void
    {
        $catalog = SuspensionCatalog::forSeason('2027');
        self::assertTrue($catalog?->coversSection(2));
        foreach ([1, 3, 4, 5, 100] as $section) {
            self::assertFalse($catalog?->coversSection($section));
        }
    }

    public function testSus02TheSerializedCatalogueGivesEachRule(): void
    {
        $array = SuspensionCatalog::forSeason('2027')?->toArray();
        self::assertSame(1, $array['version']);
        self::assertSame('2027', $array['firstSeason']);
        self::assertSame([2], $array['sections']);
        $black = $array['rules'][3];
        self::assertSame(
            ['code' => 'BLACK_GAME', 'labelFr' => '1 carton noir dans un match', 'labelEn' => '1 black card in a game',
                'cards' => ['D'], 'scope' => 'GAME', 'threshold' => 1, 'repeat' => 'EVERY_CARD_AFTER', 'distinctGames' => false,
                'matches' => 2, 'appealJury' => true],
            $black,
        );
        self::assertSame(['J', 'R'], $array['rules'][1]['cards']);
        self::assertTrue($array['rules'][1]['distinctGames']);
    }
}
