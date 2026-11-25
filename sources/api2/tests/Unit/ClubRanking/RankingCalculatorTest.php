<?php

namespace App\Tests\Unit\ClubRanking;

use App\ClubRanking\Calculator\CalculationResult;
use App\ClubRanking\Calculator\RankingCalculator;
use App\ClubRanking\Model\Alert;
use App\ClubRanking\Model\Rule;
use App\ClubRanking\Model\RuleType;
use PHPUnit\Framework\TestCase;

/** 7.4 Résultats (PAGE_CLASSEMENT_NATIONAL_CLUBS.md § 4.4, § 5.6, CNC-21 to CNC-25b). */
final class RankingCalculatorTest extends TestCase
{
    private const CHAMPIONSHIPS = ['U15', 'U18', 'PO_D', 'PO_H'];
    private const CUPS = ['CF15', 'CFD', 'CFH'];
    private const REGULAR = ['N1H', 'N1D', 'N2', 'N3'];

    private static function rule(int $rank, int $points, array $slots, array $params = []): Rule
    {
        return new Rule('7.4', $rank, 'Résultat', 'Résultat', $points, RuleType::Ranking, ['slots' => $slots, ...$params]);
    }

    private static function champion(): Rule
    {
        return self::rule(1, 40, [['categories' => self::CHAMPIONSHIPS, 'ranking' => 'final', 'place' => 1]]);
    }

    private static function regularWinner(): Rule
    {
        return self::rule(2, 35, [['categories' => self::REGULAR, 'ranking' => 'regular', 'place' => 1]]);
    }

    /** @return array<string, int> */
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
            ->category('U15', ['N15*'], 'N15')
            ->category('U18', ['N18*'], 'N18')
            ->category('PO_D', ['NPOF'], 'NPOF')
            ->category('PO_H', ['NPOH'], 'NPOH')
            ->category('N1H', ['N1H'], null, 'N1H')
            ->category('N1D', ['N1F'], null, 'N1F')
            ->category('N2', ['N2H*'], null, 'N2H')
            ->category('N3', ['N3*'], null, 'N3')
            ->category('CF15', ['CF15'], 'CF15')
            ->category('CFD', ['CFF'], 'CFF')
            ->category('CFH', ['CFH'], 'CFH')
            ->licensee(1, '5006')->licensee(2, '3512')->licensee(3, '1412')->licensee(4, '1412')->licensee(5, '4421')
            ->competition('N1H')->competition('NPOH', 'CP')->competition('CFH', 'CP')->competition('N18', 'CP');
    }

    public function testCnc21RegularSeasonWinnerAndPlayOffChampionEarnsBothRules(): void
    {
        $fixture = self::fixture()
            ->team(40, '5006', 'N1H', [1 => true], rank: 1)
            ->team(40, '5006', 'NPOH', [1 => true], levelRank: 1)
            ->category('PO_H', ['NPOH'], 'NPOH');
        $context = $fixture->context();

        self::assertSame(['5006' => 40], self::pointsByClub((new RankingCalculator())->compute(self::champion(), $context)));
        self::assertSame(['5006' => 35], self::pointsByClub((new RankingCalculator())->compute(self::regularWinner(), $context)));
    }

    public function testCnc22SecondOfTheCupEarnsTheThirdOfChampionshipLine(): void
    {
        $rule = self::rule(5, 25, [
            ['categories' => self::CHAMPIONSHIPS, 'ranking' => 'final', 'place' => 3],
            ['categories' => self::CUPS, 'ranking' => 'final', 'place' => 2],
        ]);
        $fixture = self::fixture()->team(41, '3512', 'CFH', [2 => true], levelRank: 2);

        self::assertSame(['3512' => 25], self::pointsByClub((new RankingCalculator())->compute($rule, $fixture->context())));
    }

    public function testCnc23AnUnpublishedOrUnlockedRankingBlocksItsCategory(): void
    {
        $fixture = self::fixture()
            ->competition('NPOH', 'CP', published: false)
            ->competition('N15', 'CP', locked: false)
            ->team(40, '5006', 'NPOH', [1 => true], levelRank: 1)
            ->team(50, '3512', 'N15', [2 => true], levelRank: 1)
            ->team(51, '1412', 'N18', [3 => true], levelRank: 1);

        $result = (new RankingCalculator())->compute(self::champion(), $fixture->context());

        self::assertSame(['1412' => 40], self::pointsByClub($result));
        $blocking = array_values(array_filter($result->alerts, static fn (Alert $alert): bool => $alert->blocking));
        self::assertCount(2, $blocking);
        self::assertSame(Alert::RANKING_NOT_PUBLISHED, $blocking[0]->kind);
    }

    public function testAMissingCompetitionIsReportedWithoutBlockingTheOthers(): void
    {
        $fixture = self::fixture()->team(51, '1412', 'N18', [3 => true], levelRank: 1);

        $result = (new RankingCalculator())->compute(self::champion(), $fixture->context());

        self::assertSame(['1412' => 40], self::pointsByClub($result));
        self::assertContains(Alert::COMPETITION_MISSING, array_map(static fn (Alert $alert): string => $alert->kind, $result->alerts));
    }

    public function testCnc24TiedTeamsEachEarnThePointsOfThePlace(): void
    {
        $rule = self::rule(8, 12, [['categories' => self::CHAMPIONSHIPS, 'ranking' => 'final', 'place' => 5]]);
        $fixture = self::fixture()
            ->team(60, '5006', 'N18', [1 => true], levelRank: 5)
            ->team(61, '3512', 'N18', [2 => true], levelRank: 5)
            ->team(62, '1412', 'N18', [3 => true], levelRank: 7);

        self::assertSame(['3512' => 12, '5006' => 12], self::pointsByClub((new RankingCalculator())->compute($rule, $fixture->context())));
    }

    public function testCnc25ASelectionChampionPaysEveryClubOfItsPlayersWhoPlayed(): void
    {
        $fixture = self::fixture()
            ->licensee(6, '4421')
            ->team(70, 'CR04', 'N18A', [1 => true, 2 => true, 3 => true, 4 => true, 5 => true, 6 => false])
            ->team(70, 'CR04', 'N18', [1 => true], levelRank: 1);

        $result = (new RankingCalculator())->compute(self::champion(), $fixture->context());

        self::assertSame(['1412' => 40, '3512' => 40, '4421' => 40, '5006' => 40], self::pointsByClub($result));
        self::assertCount(4, $result->lines);
    }

    public function testCnc25bTheClubOfTheTeamAndTheClubsOfItsPlayersEarnOnceEach(): void
    {
        $fixture = self::fixture()
            ->licensee(7, '5006')
            ->team(40, '5006', 'NPOH', [1 => true, 7 => true, 2 => true, 3 => true, 4 => true], levelRank: 1);

        $result = (new RankingCalculator())->compute(self::champion(), $fixture->context());

        self::assertSame(['1412' => 40, '3512' => 40, '5006' => 40], self::pointsByClub($result));
    }

    public function testTheClubOfTheTeamCanBeTheOnlyBeneficiary(): void
    {
        $fixture = self::fixture()->team(40, '5006', 'NPOH', [2 => true], levelRank: 1)->entente(40, '1412');
        $rule = self::rule(1, 40, [['categories' => ['PO_H'], 'ranking' => 'final', 'place' => 1]], ['beneficiary' => 'team_club']);

        self::assertSame(['1412' => 40, '5006' => 40], self::pointsByClub((new RankingCalculator())->compute($rule, $fixture->context())));
    }

    public function testASlotNeedsACompetitionOfItsRankingAndAPlace(): void
    {
        $scale = self::fixture()->scale();

        self::assertSame([], (new RankingCalculator())->validate(self::champion(), $scale));
        $errors = (new RankingCalculator())->validate(self::rule(1, 40, [
            ['categories' => ['N2'], 'ranking' => 'final', 'place' => 1],
            ['categories' => ['U15'], 'ranking' => 'final', 'place' => 0],
        ]), $scale);
        self::assertCount(2, $errors);
        self::assertNotSame([], (new RankingCalculator())->validate(self::rule(1, 40, []), $scale));
    }
}
