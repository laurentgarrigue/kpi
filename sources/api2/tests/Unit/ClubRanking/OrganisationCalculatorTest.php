<?php

namespace App\Tests\Unit\ClubRanking;

use App\ClubRanking\Calculator\EventOrganiserCalculator;
use App\ClubRanking\Model\Alert;
use App\ClubRanking\Model\Line;
use App\ClubRanking\Model\Rule;
use App\ClubRanking\Model\RuleType;
use PHPUnit\Framework\TestCase;

/** 7.2 Organisation (PAGE_CLASSEMENT_NATIONAL_CLUBS.md § 4.2, § 5.7, CNC-12 to CNC-14a). */
final class OrganisationCalculatorTest extends TestCase
{
    private static function rule(int $rank, int $points, string $nature): Rule
    {
        return new Rule('7.2', $rank, 'Organisation', 'Orga', $points, RuleType::EventOrganiser, ['nature' => $nature]);
    }

    /** @return array<string, list<string>> rule code → clubs */
    private static function awards(SeasonFixture $fixture): array
    {
        $rules = [
            self::rule(1, 55, 'international'),
            self::rule(2, 45, 'final_multi'),
            self::rule(3, 35, 'final_single'),
            self::rule(4, 20, 'day_multi'),
            self::rule(6, 10, 'day_single'),
        ];
        $context = $fixture->context($rules);
        $awards = [];
        foreach ($rules as $rule) {
            foreach ((new EventOrganiserCalculator())->compute($rule, $context)->lines as $line) {
                $awards[$rule->code()][] = $line->clubCode;
            }
        }

        return $awards;
    }

    private static function fixture(): SeasonFixture
    {
        return SeasonFixture::create()
            ->category('N1H', ['N1H'], null, 'N1H')
            ->category('N1D', ['N1F'], null, 'N1F')
            ->category('PO_H', ['NPOH'], 'NPOH')
            ->category('PO_D', ['NPOF'], 'NPOF')
            ->category('N2', ['N2H*'], null, 'N2H')
            ->competition('N1H')->competition('N1F')->competition('NPOH', 'CP')->competition('NPOF', 'CP')
            ->competition('N2H')->competition('OPEND', level: 'NAT')->competition('ECA', 'CP', level: 'INT');
    }

    public function testCnc12TwoDivisionsTheSameWeekendAtTheSamePlaceAreOneMultipleDay(): void
    {
        $fixture = self::fixture()
            ->gameday(1, 'N1H', '2026-05-09', 'Avranches', '5006', end: '2026-05-10')
            ->gameday(2, 'N1F', '2026-05-10', 'avranches ', '5006');

        self::assertSame(['7.2.4' => ['5006']], self::awards($fixture));
    }

    public function testASingleDivisionDayIsASimpleDay(): void
    {
        $fixture = self::fixture()->gameday(1, 'N2H', '2026-05-09', 'Thury', '1412')->gameday(2, 'N2H', '2026-06-13', 'Thury', '1412');

        self::assertSame(['7.2.6' => ['1412', '1412']], self::awards($fixture));
    }

    public function testCnc13BothPlayOffsTheSameWeekendAreOneMultipleFinal(): void
    {
        $fixture = self::fixture()
            ->gameday(1, 'NPOH', '2026-09-26', 'Acigné', '3512')
            ->gameday(2, 'NPOF', '2026-09-27', 'Acigné', '3512');

        self::assertSame(['7.2.2' => ['3512']], self::awards($fixture));
    }

    public function testOneFinalWithAnotherDivisionIsASingleFinal(): void
    {
        $fixture = self::fixture()
            ->gameday(1, 'NPOH', '2026-09-26', 'Acigné', '3512')
            ->gameday(2, 'N2H', '2026-09-26', 'Acigné', '3512');

        self::assertSame(['7.2.3' => ['3512']], self::awards($fixture));
    }

    public function testCnc14aACompetitionOutsideTheNationalPerimeterEarnsNothing(): void
    {
        $fixture = self::fixture()->gameday(1, 'OPEND', '2026-04-04', 'Nantes', '4421')->gameday(2, 'N2H', '2026-05-04', 'Nantes', '4421');

        self::assertSame(['7.2.6' => ['4421']], self::awards($fixture));
    }

    public function testCnc14AGamedayWithoutAClubOrganiserIsAnAlert(): void
    {
        $fixture = self::fixture()->gameday(1, 'N2H', '2026-04-04', 'Caen', '')->gameday(2, 'N2H', '2026-05-04', 'Rouen', 'CR04');
        $rule = self::rule(6, 10, 'day_single');

        $result = (new EventOrganiserCalculator())->compute($rule, $fixture->context([$rule]));

        self::assertSame([], $result->lines);
        self::assertSame([Alert::ORGANISER_UNKNOWN, Alert::ORGANISER_UNKNOWN], array_map(static fn (Alert $alert): string => $alert->kind, $result->alerts));
    }

    public function testAnInternationalCompetitionOrganisedByAClubIsInternational(): void
    {
        $fixture = self::fixture()->gameday(1, 'ECA', '2026-08-01', 'Avranches', '5006', end: '2026-08-03');

        self::assertSame(['7.2.1' => ['5006']], self::awards($fixture));
    }

    public function testTheLineNamesTheEventAndItsGamedays(): void
    {
        $fixture = self::fixture()->gameday(7, 'N2H', '2026-05-09', 'Thury', '1412');
        $rule = self::rule(6, 10, 'day_single');

        $line = (new EventOrganiserCalculator())->compute($rule, $fixture->context([$rule]))->lines[0];

        self::assertSame(Line::SOURCE_EVENT, $line->sourceType);
        self::assertSame('7', $line->sourceId);
        self::assertStringContainsString('Thury', $line->reason);
        self::assertStringContainsString('N2H', $line->reason);
    }

    public function testAnUnknownNatureIsAConfigurationError(): void
    {
        self::assertNotSame([], (new EventOrganiserCalculator())->validate(self::rule(1, 1, 'party'), self::fixture()->scale()));
        self::assertSame([], (new EventOrganiserCalculator())->validate(self::rule(1, 1, 'day_multi'), self::fixture()->scale()));
    }
}
