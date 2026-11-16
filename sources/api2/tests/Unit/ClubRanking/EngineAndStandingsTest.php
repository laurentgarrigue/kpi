<?php

namespace App\Tests\Unit\ClubRanking;

use App\ClubRanking\Calculator\CompetitionManagerCalculator;
use App\ClubRanking\Calculator\EventOrganiserCalculator;
use App\ClubRanking\Calculator\GamedayOfficialCalculator;
use App\ClubRanking\Calculator\LicenseeInTeamCalculator;
use App\ClubRanking\Calculator\ManualListCalculator;
use App\ClubRanking\Calculator\RankingCalculator;
use App\ClubRanking\Calculator\RefereeQualificationCalculator;
use App\ClubRanking\Calculator\TeamOfClubCalculator;
use App\ClubRanking\ClubRankingEngine;
use App\ClubRanking\InvalidScale;
use App\ClubRanking\Model\Adjustment;
use App\ClubRanking\Model\Alert;
use App\ClubRanking\Model\Line;
use App\ClubRanking\Model\Rule;
use App\ClubRanking\Model\RuleType;
use App\ClubRanking\Standings;
use PHPUnit\Framework\TestCase;

/** The whole calculation and the ranking (PAGE_CLASSEMENT_NATIONAL_CLUBS.md § 5.8, § 5.9, § 8). */
final class EngineAndStandingsTest extends TestCase
{
    private static function engine(): ClubRankingEngine
    {
        return new ClubRankingEngine([
            new TeamOfClubCalculator(), new LicenseeInTeamCalculator(), new RankingCalculator(), new EventOrganiserCalculator(),
            new GamedayOfficialCalculator(), new CompetitionManagerCalculator(), new RefereeQualificationCalculator(), new ManualListCalculator(),
        ]);
    }

    private static function inSeason(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-11-16');
    }

    private static function fixture(): SeasonFixture
    {
        return SeasonFixture::create()
            ->category('N1D', ['N1F'], null, 'N1F')
            ->competition('N1F')
            ->licensee(1, '5006')->licensee(2, '3512')
            ->team(30, '5006', 'N1F', [1 => true, 2 => true], rank: 1);
    }

    /** @return list<Rule> */
    private static function rules(int $licenseePoints = 15): array
    {
        return [
            new Rule('7.3', 4, 'Autre membre de la CNA', 'CNA', 20, RuleType::ManualList, ['unit' => 'licensee', 'exclude_rules' => ['7.3.2']]),
            new Rule('7.1', 1, 'Équipe du club', 'Équipe', 20, RuleType::TeamOfClub, ['categories' => ['N1D']]),
            new Rule('7.1', 2, 'Licenciés', 'Licenciés', $licenseePoints, RuleType::LicenseeInTeam, ['categories' => ['N1D']]),
            new Rule('7.3', 2, 'RC', 'RC', 40, RuleType::CompetitionManager),
            new Rule('7.4', 2, 'Vainqueur saison régulière', 'SR 1', 35, RuleType::Ranking, ['slots' => [['categories' => ['N1D'], 'ranking' => 'regular', 'place' => 1]]]),
        ];
    }

    public function testCnc03AnInvalidScaleIsRefusedWithEveryError(): void
    {
        $rules = [new Rule('7.1', 1, 'Équipe', 'Éq', 20, RuleType::TeamOfClub, ['categories' => ['NEM']]), new Rule('7.2', 1, 'Orga', 'Orga', 10, RuleType::EventOrganiser)];

        try {
            self::engine()->compute(self::fixture()->scale($rules), self::fixture()->data(), self::inSeason());
            self::fail('expected InvalidScale');
        } catch (InvalidScale $exception) {
            self::assertCount(2, $exception->errors);
            self::assertStringContainsString('NEM', $exception->errors[0]);
        }
    }

    public function testRulesAccumulateAndManualListsSeeTheComputedLines(): void
    {
        $fixture = self::fixture()->manager(2, 'N1F')->manual('7.3.4', 2)->manual('7.3.4', 1);

        $result = self::engine()->compute($fixture->scale(self::rules()), $fixture->data(), self::inSeason());

        $byRule = [];
        foreach ($result->lines as $line) {
            $byRule[$line->ruleCode][] = $line->clubCode;
        }
        ksort($byRule);
        self::assertSame(['7.1.1' => ['5006'], '7.1.2' => ['3512', '5006'], '7.3.2' => ['3512'], '7.3.4' => ['5006'], '7.4.2' => ['3512', '5006']], $byRule);
        self::assertContains(Alert::EXCLUDED_ENTRY, array_map(static fn (Alert $alert): string => $alert->kind, $result->alerts));
    }

    public function testCnc02ThePointsComeFromTheScale(): void
    {
        $result = self::engine()->compute(self::fixture()->scale(self::rules(12)), self::fixture()->data(), self::inSeason());

        $points = array_map(static fn (Line $line): int => $line->points, array_filter($result->lines, static fn (Line $line): bool => $line->ruleCode === '7.1.2'));
        self::assertSame([12, 12], array_values($points));
    }

    public function testACalculationAfterTheSeasonWarnsAboutTheClubsReadToday(): void
    {
        $result = self::engine()->compute(self::fixture()->scale(self::rules()), self::fixture()->data(), new \DateTimeImmutable('2027-01-10'));

        self::assertSame(Alert::AFTER_SEASON, $result->alerts[0]->kind);
    }

    public function testCnc29TiedClubsShareTheirRankAndTheNextRankIsSkipped(): void
    {
        $lines = [
            new Line('7.1.1', 'A', 96, '', Line::SOURCE_TEAM, '1'),
            new Line('7.1.1', 'B', 96, '', Line::SOURCE_TEAM, '2'),
            new Line('7.1.1', 'C', 120, '', Line::SOURCE_TEAM, '3'),
            new Line('7.1.1', 'D', 50, '', Line::SOURCE_TEAM, '4'),
        ];

        $rows = Standings::build($lines, [], static fn (string $club): string => 'Club ' . $club);

        self::assertSame(['C' => 1, 'A' => 2, 'B' => 2, 'D' => 4], array_column(array_map(static fn ($row): array => ['club' => $row->clubCode, 'rank' => $row->rank], $rows), 'rank', 'club'));
    }

    public function testCnc26Cnc31TheTotalIsTheSumOfTheLinesAndAdjustments(): void
    {
        $lines = [new Line('7.1.1', 'A', 20, '', Line::SOURCE_TEAM, '1'), new Line('7.3.5', 'A', 15, '', Line::SOURCE_LICENSEE, '9', 9)];
        $adjustments = [new Adjustment('7.3.5', 'A', -10, 'Arbitre compté deux fois'), new Adjustment('7.2.5', 'B', 15, 'Formation de mai')];

        $rows = Standings::build($lines, $adjustments, static fn (string $club): string => $club);

        self::assertSame('A', $rows[0]->clubCode);
        self::assertSame(25, $rows[0]->total);
        self::assertSame(['7.1.1' => 20, '7.3.5' => 5], $rows[0]->byRule);
        self::assertSame(15, $rows[1]->total);
    }

    public function testTheSameAwardIsNeverPaidTwice(): void
    {
        $rules = [new Rule('7.1', 1, 'Équipe', 'Éq', 20, RuleType::TeamOfClub, ['categories' => ['N1D', 'N1D']])];

        $result = self::engine()->compute(self::fixture()->scale($rules), self::fixture()->data(), self::inSeason());

        self::assertCount(1, $result->lines);
    }
}
