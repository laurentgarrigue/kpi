<?php

namespace App\Tests\Unit\MatchMedals;

use App\MatchMedals\Model\Referee;
use PHPUnit\Framework\TestCase;

/** FEATURE_MATCH_MEDALS.md § 3.2 to § 3.5. */
final class MedalRulesTest extends TestCase
{
    use MedalAssertions;

    public function testMed16OpenerGoesToTheTeamAndPlayerOfTheFirstGoalOnTheTimeAxis(): void
    {
        // recorded out of order: the goal of B comes first on the clock
        $medals = self::compute(GameBuilder::game()->goal('A', 'M2', '05:00', 7)->goal('B', 'M1', '05:00', 8)->build());

        self::assertContains('opener', self::teamCodes($medals, 'B'));
        self::assertSame(['opener'], self::playerCodes($medals, 8));
    }

    public function testMed17WireToWireRequiresNeverBeingCaughtUp(): void
    {
        self::assertNotContains('wire_to_wire', self::teamCodes(self::compute(GameBuilder::game()->goals('ABAA')->build()), 'A'));
        self::assertContains('wire_to_wire', self::teamCodes(self::compute(GameBuilder::game()->goals('AABA')->build()), 'A'));
    }

    public function testMed18ComebackAfterThreeGoalsBehindAndFightbackForADraw(): void
    {
        self::assertContains('comeback', self::teamCodes(self::compute(GameBuilder::game()->goals('BBBAAAABA')->build()), 'A'));

        $draw = self::compute(GameBuilder::game()->goals('BBBBAAAA')->build());
        self::assertSame(['fightback'], array_values(array_intersect(self::teamCodes($draw, 'A'), ['comeback', 'fightback'])));
    }

    public function testMed19BounceBackIsStrictlyUnderTheWindow(): void
    {
        $quick = self::compute(GameBuilder::game()->goal('B', 'M1', '06:00', 8)->goal('A', 'M1', '05:01', 7)->build());
        self::assertContains('bounce_back', self::teamCodes($quick, 'A'));
        self::assertContains('bounce_back', self::playerCodes($quick, 7));

        $late = self::compute(GameBuilder::game()->goal('B', 'M1', '06:00', 8)->goal('A', 'M1', '05:00', 7)->build());
        self::assertNotContains('bounce_back', self::teamCodes($late, 'A'), '60 s is not under 60 s');
    }

    public function testMed20OneScoringRunPerRun(): void
    {
        self::assertSame(['scoring_run'], array_values(array_intersect(self::teamCodes(self::compute(GameBuilder::game()->goals('AAAA')->build()), 'A'), ['scoring_run'])));

        $twoRuns = self::compute(GameBuilder::game()->goals('AAABAAA')->build());
        self::assertSame(2, count(array_keys(self::teamCodes($twoRuns, 'A'), 'scoring_run')));
    }

    public function testMed21OvertimeMedals(): void
    {
        $medals = self::compute(GameBuilder::game()->knockout()->endedIn('P2')
            ->goal('A', 'M1', '05:00', 7)->goal('B', 'M2', '05:00', 8)->goal('A', 'P2', '03:00', 9)->build());

        self::assertSame(['extra_time', 'marathon'], array_values(array_intersect(self::teamCodes($medals, 'B'), ['extra_time', 'marathon', 'golden_goal'])));
        self::assertContains('golden_goal', self::teamCodes($medals, 'A'));
        self::assertContains('golden_goal', self::playerCodes($medals, 9));
    }

    public function testMed22CleanSheetAndFairPlay(): void
    {
        $medals = self::compute(GameBuilder::game()->score('0', '0')->card('A', 'V', 'M1', '05:00')->build());

        self::assertSame(['clean_sheet'], self::teamCodes($medals, 'A'));
        self::assertSame(['clean_sheet', 'fair_play'], self::teamCodes($medals, 'B'));
    }

    public function testMed23PenaltyKillThroughTwoExclusionsWithoutConceding(): void
    {
        $killed = self::compute(GameBuilder::game()->card('B', 'V', 'M1', '08:00')->card('B', 'J', 'M2', '08:00')->goal('A', 'M2', '02:00', 7)->build());
        self::assertContains('penalty_kill', self::teamCodes($killed, 'B'));

        $conceded = self::compute(GameBuilder::game()->card('B', 'V', 'M1', '08:00')->card('B', 'J', 'M2', '08:00')->goal('A', 'M2', '07:00', 7)->build());
        self::assertNotContains('penalty_kill', self::teamCodes($conceded, 'B'));
    }

    public function testMed24HatTrickAndNaturalHatTrick(): void
    {
        $natural = self::compute(GameBuilder::game()->goals('AAA', 7)->build());
        self::assertSame(['game_winner', 'hat_trick', 'natural_hat_trick', 'opener'], self::playerCodes($natural, 7));

        $split = self::compute(GameBuilder::game()->goal('A', 'M1', '09:00', 7)->goal('B', 'M1', '08:00', 8)->goal('A', 'M1', '07:00', 7)->goal('A', 'M1', '06:00', 7)->build());
        self::assertContains('hat_trick', self::playerCodes($split, 7));
        self::assertNotContains('natural_hat_trick', self::playerCodes($split, 7));
    }

    public function testMed25TheDecisiveGoalIsTheLosersScorePlusOne(): void
    {
        $medals = self::compute(GameBuilder::game()
            ->goal('A', 'M1', '09:00', 1)->goal('B', 'M1', '08:00', 8)->goal('A', 'M1', '07:00', 2)
            ->goal('B', 'M1', '06:00', 8)->goal('A', 'M1', '05:00', 3)->goal('A', 'M1', '04:00', 4)->build());

        self::assertContains('game_winner', self::playerCodes($medals, 3));
        self::assertNotContains('game_winner', self::playerCodes($medals, 4));
    }

    public function testMed26LateWinnerAndLifelineReadTheRemainingTime(): void
    {
        $late = self::compute(GameBuilder::game()->goal('B', 'M1', '05:00', 8)->goal('A', 'M2', '05:00', 6)->goal('A', 'M2', '00:45', 7)->build());
        self::assertContains('late_winner', self::teamCodes($late, 'A'));
        self::assertContains('late_winner', self::playerCodes($late, 7));

        $short = self::compute(GameBuilder::game()->goal('B', 'M1', '05:00', 8)->goal('A', 'M2', '05:00', 6)->goal('A', 'M2', '00:45', 7)->build(), ['regularPeriodSeconds' => 420]);
        self::assertContains('late_winner', self::teamCodes($short, 'A'), 'same result with 7-minute halves');

        $lifeline = self::compute(GameBuilder::game()->knockout()->endedIn('P1')
            ->goal('B', 'M1', '05:00', 8)->goal('A', 'M2', '00:30', 7)->goal('A', 'P1', '03:00', 6)->build());
        self::assertContains('lifeline', self::teamCodes($lifeline, 'A'));
        self::assertContains('lifeline', self::playerCodes($lifeline, 7));
    }

    public function testMed27ACoachOrUnknownScorerGivesTheTeamMedalOnly(): void
    {
        $medals = self::compute(GameBuilder::game()->goal('A', 'M1', '05:00', 5, coach: true)->goal('A', 'M1', '04:00')->build());

        self::assertContains('opener', self::teamCodes($medals, 'A'));
        self::assertSame([], self::playerCodes($medals, 5));
    }

    public function testMed28ThreeChangesOfLeaderMakeAThriller(): void
    {
        self::assertContains('thriller', self::compute(GameBuilder::game()->goals('BAABBAA')->build())->labels());
        self::assertNotContains('thriller', self::compute(GameBuilder::game()->goals('ABBAABA')->build())->labels(), 'only two changes');
    }

    public function testSeesawAndChessMatchLabels(): void
    {
        self::assertContains('seesaw', self::compute(GameBuilder::game()->goals('ABABABAB')->build())->labels());
        self::assertSame(['chess_match'], self::compute(GameBuilder::game()->goals('AB')->build())->labels());
    }

    public function testMed53And56RefereeMedalsGoToEachDesignatedRefereeOnce(): void
    {
        $medals = self::compute(GameBuilder::game()->knockout()->goals('AB')->referee(11)->referee(11, Referee::SECONDARY)->referee(12, Referee::SECONDARY)->build());

        self::assertSame(['ref_knockout', 'ref_smooth_game'], self::refereeCodes($medals, 11));
        self::assertSame(['ref_knockout', 'ref_smooth_game'], self::refereeCodes($medals, 12));
    }

    public function testRefereeMedalsForOvertimeAndThriller(): void
    {
        $medals = self::compute(GameBuilder::game()->endedIn('P1')->referee(11)->card('A', 'V', 'M1', '09:30')
            ->goal('B', 'M1', '09:00')->goal('A', 'M1', '08:00')->goal('A', 'M1', '07:00')->goal('B', 'M1', '06:00')
            ->goal('B', 'M1', '05:00')->goal('A', 'M2', '04:00')->goal('A', 'P1', '03:00')->build());

        self::assertSame(['ref_extra_time', 'ref_golden_goal', 'ref_thriller'], self::refereeCodes($medals, 11));
    }

    public function testRetiredCodesAreNotComputed(): void
    {
        $game = GameBuilder::game()->goals('AB', 7)->build();
        $rules = [new \App\MatchMedals\Rule\Opener(), new \App\MatchMedals\Rule\FairPlay()];

        $medals = (new \App\MatchMedals\MatchMedalCalculator($rules))->compute($game, self::thresholds(), ['opener']);

        self::assertSame(['fair_play'], self::teamCodes($medals, 'A'));
    }
}
