<?php

namespace App\Tests\Unit\ClubRanking;

use App\ClubRanking\Calculator\CalculationResult;
use App\ClubRanking\Calculator\LicenseeInTeamCalculator;
use App\ClubRanking\Calculator\TeamOfClubCalculator;
use App\ClubRanking\Model\Alert;
use App\ClubRanking\Model\Line;
use App\ClubRanking\Model\Rule;
use App\ClubRanking\Model\RuleType;
use PHPUnit\Framework\TestCase;

/** 7.1 Participation (PAGE_CLASSEMENT_NATIONAL_CLUBS.md § 4.1, CNC-04 to CNC-11). */
final class ParticipationCalculatorsTest extends TestCase
{
    private static function teamRule(array $categories, array $params = []): Rule
    {
        return new Rule('7.1', 1, 'Équipe du club', 'Équipe', 20, RuleType::TeamOfClub, ['categories' => $categories, ...$params]);
    }

    private static function licenseeRule(array $categories, array $params = []): Rule
    {
        return new Rule('7.1', 2, 'Licenciés dans une équipe', 'Licenciés', 15, RuleType::LicenseeInTeam, ['categories' => $categories, ...$params]);
    }

    /** @return array<string, int> club → points */
    private static function pointsByClub(CalculationResult $result): array
    {
        $points = [];
        foreach ($result->lines as $line) {
            $points[$line->clubCode] = ($points[$line->clubCode] ?? 0) + $line->points;
        }
        ksort($points);

        return $points;
    }

    private static function fixture(): SeasonFixture
    {
        return SeasonFixture::create()
            ->category('U15', ['N15*', 'CF15'], 'N15')
            ->category('U18', ['N18*'], 'N18')
            ->category('N1D', ['N1F'], 'NPOF', 'N1F');
    }

    public function testCnc04ATeamEngagedInSeveralCompetitionsOfACategoryCountsOnce(): void
    {
        $fixture = self::fixture()->competition('N15A')->competition('N15B')->competition('N15')
            ->team(10, '5006', 'N15A')->team(10, '5006', 'N15B')->team(10, '5006', 'N15');
        $rule = self::teamRule(['U15']);

        $result = (new TeamOfClubCalculator())->compute($rule, $fixture->context([$rule]));

        self::assertCount(1, $result->lines);
        self::assertSame(['5006' => 20], self::pointsByClub($result));
        self::assertSame(Line::SOURCE_TEAM, $result->lines[0]->sourceType);
    }

    public function testATeamInTwoCategoriesCountsOncePerCategory(): void
    {
        $fixture = self::fixture()->team(10, '5006', 'N15')->team(10, '5006', 'CF15');
        $fixture->category('CF15', ['CF15'], 'CF15');
        $rule = self::teamRule(['U15', 'CF15']);

        $result = (new TeamOfClubCalculator())->compute($rule, $fixture->context([$rule]));

        self::assertCount(2, $result->lines);
    }

    public function testCnc05TwoTeamsOfTheSameClubEarnTwice(): void
    {
        $fixture = self::fixture()->team(10, '5006', 'N18')->team(11, '5006', 'N18A');
        $rule = self::teamRule(['U18']);

        $result = (new TeamOfClubCalculator())->compute($rule, $fixture->context([$rule]));

        self::assertSame(['5006' => 40], self::pointsByClub($result));
    }

    public function testCnc06ASelectionEarnsNothingAsATeamButItsPlayersEarnForTheirClubs(): void
    {
        $fixture = self::fixture()
            ->licensee(1, '5006')->licensee(2, '3512')->licensee(3, '1412')->licensee(4, '1412')
            ->team(20, 'CR04', 'N18', [1 => true, 2 => true, 3 => true, 4 => true]);

        $teams = (new TeamOfClubCalculator())->compute(self::teamRule(['U18']), $fixture->context());
        $licensees = (new LicenseeInTeamCalculator())->compute(self::licenseeRule(['U18']), $fixture->context());

        self::assertSame([], $teams->lines);
        self::assertSame(Alert::SELECTION_TEAM, $teams->alerts[0]->kind);
        self::assertSame(['1412' => 15, '3512' => 15, '5006' => 15], self::pointsByClub($licensees));
    }

    public function testCnc07TheClubOfTheTeamEarnsBothRulesAndAnotherClubOnce(): void
    {
        $fixture = self::fixture()
            ->licensee(1, '5006')->licensee(2, '3512')->licensee(3, '3512')
            ->team(30, '5006', 'N1F', [1 => true, 2 => true, 3 => true]);

        $teams = (new TeamOfClubCalculator())->compute(self::teamRule(['N1D']), $fixture->context());
        $licensees = (new LicenseeInTeamCalculator())->compute(self::licenseeRule(['N1D']), $fixture->context());

        self::assertSame(['5006' => 20], self::pointsByClub($teams));
        self::assertSame(['3512' => 15, '5006' => 15], self::pointsByClub($licensees));
    }

    public function testTheClubOfTheTeamCanBeLeftOutOfTheLicenseeRule(): void
    {
        $fixture = self::fixture()->licensee(1, '5006')->licensee(2, '3512')->team(30, '5006', 'N1F', [1 => true, 2 => true]);

        $result = (new LicenseeInTeamCalculator())->compute(self::licenseeRule(['N1D'], ['include_team_club' => false]), $fixture->context());

        self::assertSame(['3512' => 15], self::pointsByClub($result));
    }

    public function testCnc08ACoachAloneOfHisClubBringsNothing(): void
    {
        $fixture = self::fixture()->licensee(1, '5006')->licensee(9, '4421')
            ->team(30, '5006', 'N1F', [1 => true], coaches: [9]);

        $result = (new LicenseeInTeamCalculator())->compute(self::licenseeRule(['N1D']), $fixture->context());

        self::assertSame(['5006' => 15], self::pointsByClub($result));
    }

    public function testCnc09APlayerWhoNeverPlayedBringsNothingUnlessTheRosterIsEnough(): void
    {
        $fixture = self::fixture()->licensee(1, '5006')->licensee(2, '3512')
            ->team(30, '5006', 'N1F', [1 => true, 2 => false]);

        $played = (new LicenseeInTeamCalculator())->compute(self::licenseeRule(['N1D']), $fixture->context());
        $roster = (new LicenseeInTeamCalculator())->compute(self::licenseeRule(['N1D'], ['presence' => 'roster']), $fixture->context());

        self::assertSame(['5006' => 15], self::pointsByClub($played));
        self::assertSame(['3512' => 15, '5006' => 15], self::pointsByClub($roster));
    }

    public function testCnc10EachClubOfADeclaredEntenteEarnsTheTeamPoints(): void
    {
        $fixture = self::fixture()->team(30, '5006', 'N1F')->entente(30, '3512');

        $result = (new TeamOfClubCalculator())->compute(self::teamRule(['N1D']), $fixture->context());

        self::assertSame(['3512' => 20, '5006' => 20], self::pointsByClub($result));
    }

    public function testCnc11AForeignOrUnlicensedPlayerBringsNothing(): void
    {
        $fixture = self::fixture()
            ->licensee(1, '5006')
            ->licensee(2000001, '3512')
            ->licensee(3, '1412', foreign: true)
            ->licensee(4, '4421', '2025')
            ->team(30, '5006', 'N1F', [1 => true, 2000001 => true, 3 => true, 4 => true]);

        $result = (new LicenseeInTeamCalculator())->compute(self::licenseeRule(['N1D']), $fixture->context());

        self::assertSame(['5006' => 15], self::pointsByClub($result));
    }

    public function testATeamWithoutValidatedGameIsNotCountedByDefault(): void
    {
        $fixture = self::fixture()->team(30, '5006', 'N1F', games: 0);

        self::assertSame([], (new TeamOfClubCalculator())->compute(self::teamRule(['N1D']), $fixture->context())->lines);
        self::assertCount(1, (new TeamOfClubCalculator())->compute(self::teamRule(['N1D'], ['require_played' => false]), $fixture->context())->lines);
    }

    public function testCnc03AnUnknownOrEmptyCategoryIsAConfigurationError(): void
    {
        $fixture = self::fixture()->category('NEM', []);
        $errors = (new TeamOfClubCalculator())->validate(self::teamRule(['U15', 'XYZ', 'NEM']), $fixture->scale());

        self::assertCount(2, $errors);
        self::assertStringContainsString('XYZ', $errors[0]);
        self::assertStringContainsString('NEM', $errors[1]);
        self::assertNotSame([], (new TeamOfClubCalculator())->validate(self::teamRule([]), $fixture->scale()));
    }
}
