<?php

namespace App\Tests\Unit\MatchMedals;

use App\MatchMedals\MedalThresholds;
use App\MatchMedals\Model\AwardScope;
use PHPUnit\Framework\TestCase;

/** FEATURE_MATCH_MEDALS.md § 5.1. */
final class EligibilityTest extends TestCase
{
    use MedalAssertions;

    public function testMed01ANotValidatedGameGivesNoMedal(): void
    {
        $medals = self::compute(GameBuilder::game()->goals('AAB', 7)->notValidated()->build());

        self::assertFalse($medals->timeline->isEligible());
        self::assertSame([], $medals->awards);
    }

    public function testMed02AScoreThatDiffersFromTheGoalsKeepsScoreMedalsOnly(): void
    {
        $medals = self::compute(GameBuilder::game()->goals('AAA', 7)->score('4', '0')->build());

        self::assertFalse($medals->timeline->isReliable());
        self::assertSame(['clean_sheet', 'fair_play'], self::teamCodes($medals, 'A'), 'no opener: timeline medal');
        self::assertSame(['hat_trick'], self::playerCodes($medals, 7));
    }

    public function testMed03AGoalWithoutTimeMakesTheTimelineUnreliable(): void
    {
        $medals = self::compute(GameBuilder::game()->goal('A', 'M1', '05:00', 7)->goal('B', 'M2', null, 8)->build());

        self::assertTrue($medals->timeline->isEligible());
        self::assertFalse($medals->timeline->isReliable());
        self::assertNotContains('opener', self::teamCodes($medals, 'A'));
    }

    public function testMed03AnUnknownPeriodMakesTheTimelineUnreliable(): void
    {
        $medals = self::compute(GameBuilder::game()->goal('A', 'X9', '05:00', 7)->build());

        self::assertFalse($medals->timeline->isReliable());
    }

    public function testMed04AForfeitScoreGivesNoMedal(): void
    {
        $medals = self::compute(GameBuilder::game()->score('F', '5')->build());

        self::assertFalse($medals->timeline->isEligible());
        self::assertSame([], $medals->awards);
    }

    public function testMed05ShootOutGoalsCountForNothing(): void
    {
        $medals = self::compute(GameBuilder::game()->goal('A', 'M1', '05:00', 7)->goal('B', 'M2', '05:00', 8)
            ->goal('A', 'TB', null, 7)->goal('A', 'TB', null, 7)->goal('A', 'TB', null, 7)->score('1', '1')->build());

        self::assertTrue($medals->timeline->isReliable(), 'untimed shoot-out goals do not make the timeline unreliable');
        self::assertNotContains('hat_trick', self::playerCodes($medals, 7));
    }

    public function testMed06APlacementGameGivesMedalsLikeAnyOther(): void
    {
        $medals = self::compute(GameBuilder::game()->goals('AB', 7)->build());

        self::assertNotSame([], $medals->of(AwardScope::Team));
    }

    public function testMed29ThresholdsComeFromOneConfigurationAndAllAreRequired(): void
    {
        self::assertSame(30, self::thresholds()->bounceBackSeconds);
        $this->expectException(\InvalidArgumentException::class);
        MedalThresholds::fromArray(['bounceBackSeconds' => 45]);
    }

    public function testThresholdsRoundTripThroughTheSeasonCopy(): void
    {
        $thresholds = self::thresholds(['bounceBackSeconds' => 45]);

        self::assertEquals($thresholds, MedalThresholds::fromArray($thresholds->toArray()));
    }
}
