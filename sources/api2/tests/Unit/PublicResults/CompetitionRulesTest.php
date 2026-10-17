<?php

namespace App\Tests\Unit\PublicResults;

use App\PublicResults\CompetitionRules;
use PHPUnit\Framework\TestCase;

final class CompetitionRulesTest extends TestCase
{
    public function testDisplayTitleIsTheLabelWhenTitleIsActiveOtherwiseTheSubtitle(): void
    {
        self::assertSame('Coupe de France', CompetitionRules::displayTitle('O', 'Coupe de France', 'Finale'));
        self::assertSame('Finale', CompetitionRules::displayTitle('N', 'Coupe de France', 'Finale'));
        self::assertSame('Coupe de France', CompetitionRules::displayTitle('N', 'Coupe de France', ''));
    }

    public function testApi12MedalsForRanksOneToThreeOfAFinishedFinalRound(): void
    {
        self::assertSame(1, CompetitionRules::medal('END', true, 1));
        self::assertSame(3, CompetitionRules::medal('END', true, 3));
        self::assertNull(CompetitionRules::medal('END', true, 4));
        self::assertNull(CompetitionRules::medal('ON', true, 1), 'pas terminée');
        self::assertNull(CompetitionRules::medal('END', false, 1), 'pas le tour final');
        self::assertTrue(CompetitionRules::isFinalRound(10));
        self::assertFalse(CompetitionRules::isFinalRound(9));
        self::assertFalse(CompetitionRules::isFinalRound(null));
    }

    public function testRankingIsPublishedForStartedChampionshipsFinishedCompetitionsAndMulti(): void
    {
        self::assertTrue(CompetitionRules::isRankingPublished('CHPT', 'ON'));
        self::assertFalse(CompetitionRules::isRankingPublished('CHPT', 'ATT'));
        self::assertFalse(CompetitionRules::isRankingPublished('CP', 'ON'));
        self::assertTrue(CompetitionRules::isRankingPublished('CP', 'END'));
        self::assertTrue(CompetitionRules::isRankingPublished('MULTI', 'ATT'));
    }

    public function testRankIsTheLevelRankForCups(): void
    {
        self::assertSame(2, CompetitionRules::rank('CP', 5, 2));
        self::assertSame(5, CompetitionRules::rank('CHPT', 5, 2));
    }

    public function testPersonNameDropsTheLicenceNumber(): void
    {
        self::assertSame('DUPONT Jean', CompetitionRules::personName('DUPONT Jean (123456)'));
        self::assertSame('DUPONT Jean', CompetitionRules::personName('DUPONT Jean'));
        self::assertNull(CompetitionRules::personName(''));
        self::assertNull(CompetitionRules::personName(null));
    }

    public function testVisualIsAPathUnderImgOnlyWhenActive(): void
    {
        self::assertSame('logo/cdf.png', CompetitionRules::visual('O', 'cdf.png'));
        self::assertSame('https://cdn.test/cdf.png', CompetitionRules::visual('O', 'https://cdn.test/cdf.png'));
        self::assertNull(CompetitionRules::visual('N', 'cdf.png'));
        self::assertNull(CompetitionRules::visual('O', ''));
    }

    public function testPointsAreStoredTimesOneHundred(): void
    {
        self::assertSame(7, CompetitionRules::points(700));
        self::assertSame(7.5, CompetitionRules::points(750));
    }

    public function testSharedRanksForTies(): void
    {
        self::assertSame([1, 2, 2, 4], CompetitionRules::sharedRanks([9, 5, 5, 3]));
        self::assertSame([], CompetitionRules::sharedRanks([]));
    }
}
