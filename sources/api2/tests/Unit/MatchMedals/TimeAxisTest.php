<?php

namespace App\Tests\Unit\MatchMedals;

use PHPUnit\Framework\TestCase;

/** FEATURE_MATCH_MEDALS.md § 5.2 (time axis) and § 5.3 (exclusions and power play). */
final class TimeAxisTest extends TestCase
{
    use MedalAssertions;

    public function testMed07ElapsedTimeIsTheDefaultLengthMinusTheRemainingTimeStretchedToTheMinute(): void
    {
        $medals = self::compute(GameBuilder::game()->goal('A', 'M2', '09:10', 7)->build());
        self::assertSame(650, $medals->timeline->goals()[0]->seconds);

        $stretched = self::compute(GameBuilder::game()->goal('A', 'M2', '11:30', 7)->build());
        self::assertSame(720, $stretched->timeline->periodLength('M2'));
    }

    public function testMed08AGoalAgainstAnExcludedTeamIsPowerplayAndLiftsTheExclusion(): void
    {
        $medals = self::compute(GameBuilder::game()->card('B', 'J', 'M1', '05:00')
            ->goal('A', 'M1', '04:00', 7)->goal('B', 'M1', '03:30', 8)->build());

        [$first, $second] = $medals->timeline->goals();
        self::assertTrue($first->isPowerplay);
        self::assertFalse($second->isShorthanded, 'the exclusion was lifted by the goal');
        self::assertContains('powerplay', self::playerCodes($medals, 7));
    }

    public function testMed09AnExclusionLastsTwoMinutesOfPlay(): void
    {
        $medals = self::compute(GameBuilder::game()->card('B', 'V', 'M1', '05:00')->goal('A', 'M1', '02:50', 7)->build());

        self::assertFalse($medals->timeline->goals()[0]->isPowerplay, '130 s > 120 s');
    }

    public function testMed10APermanentRedCardIsNeverLiftedAndLastsUntilTheEnd(): void
    {
        $medals = self::compute(GameBuilder::game()->card('B', 'D', 'M1', '08:00')
            ->goal('A', 'M2', '09:00', 7)->goal('A', 'M2', '02:00', 7)->goal('A', 'P1', '04:00', 7)->score('3', '0')->endedIn('P1')->build());

        foreach ($medals->timeline->goals() as $goal) {
            self::assertTrue($goal->isPowerplay);
        }
    }

    public function testMed11AConfiguredBlackCardBehavesLikeAPermanentRedCard(): void
    {
        $game = GameBuilder::game()->card('B', 'N', 'M1', '08:00')->goal('A', 'M2', '09:00', 7)->goal('A', 'M2', '02:00', 7)->build();

        $medals = self::compute($game, ['permanentCards' => ['D', 'N']]);

        self::assertTrue($medals->timeline->goals()[1]->isPowerplay);
    }

    public function testMed12AnExclusionCarriesOverToTheNextPeriod(): void
    {
        $medals = self::compute(GameBuilder::game()->card('B', 'J', 'M1', '00:30')->goal('A', 'M2', '09:20', 7)->build());

        self::assertTrue($medals->timeline->goals()[0]->isPowerplay, '30 s + 40 s < 120 s');
    }

    public function testMed13EqualExclusionsMakeNeitherPowerplayNorShorthanded(): void
    {
        $medals = self::compute(GameBuilder::game()->card('A', 'V', 'M1', '06:00')->card('B', 'V', 'M1', '06:00')->goal('A', 'M1', '05:30', 7)->build());

        $goal = $medals->timeline->goals()[0];
        self::assertFalse($goal->isPowerplay);
        self::assertFalse($goal->isShorthanded);
    }

    public function testMed14ACardAndAGoalAtTheSameSecondCountTheCardFirst(): void
    {
        $medals = self::compute(GameBuilder::game()->goal('A', 'M1', '05:00', 7)->card('A', 'V', 'M1', '05:00')->build());

        self::assertTrue($medals->timeline->goals()[0]->isShorthanded);
        self::assertContains('shorthanded', self::teamCodes($medals, 'A'));
    }

    public function testMed15AtMostTwoExclusionsCountPerTeam(): void
    {
        $medals = self::compute(GameBuilder::game()
            ->card('B', 'V', 'M1', '06:00')->card('B', 'V', 'M1', '06:00')->card('B', 'V', 'M1', '06:00')
            ->card('A', 'V', 'M1', '06:00')->card('A', 'V', 'M1', '06:00')->goal('A', 'M1', '05:30', 7)->build());

        self::assertFalse($medals->timeline->goals()[0]->isPowerplay, '2 against 2, the third exclusion does not count');
    }
}
