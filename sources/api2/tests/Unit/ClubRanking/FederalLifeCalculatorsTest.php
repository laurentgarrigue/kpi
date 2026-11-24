<?php

namespace App\Tests\Unit\ClubRanking;

use App\ClubRanking\Calculator\CalculationResult;
use App\ClubRanking\Calculator\CompetitionManagerCalculator;
use App\ClubRanking\Calculator\GamedayOfficialCalculator;
use App\ClubRanking\Calculator\ManualListCalculator;
use App\ClubRanking\Calculator\RefereeQualificationCalculator;
use App\ClubRanking\Model\Alert;
use App\ClubRanking\Model\Line;
use App\ClubRanking\Model\Rule;
use App\ClubRanking\Model\RuleType;
use App\ClubRanking\NameNormalizer;
use PHPUnit\Framework\TestCase;

/** 7.3 Vie fédérale and the manual rules (PAGE_CLASSEMENT_NATIONAL_CLUBS.md § 4.3, § 7, CNC-15 to CNC-20). */
final class FederalLifeCalculatorsTest extends TestCase
{
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

    private static function officialRule(): Rule
    {
        return new Rule('7.3', 1, 'Délégué CNA ou Juge arbitre', 'Délégué', 50, RuleType::GamedayOfficial, ['fields' => ['delegate', 'chief_referee']]);
    }

    private static function refereeRule(int $rank, int $points, array $qualifications, int $minGames = 0): Rule
    {
        return new Rule('7.3', $rank, 'Arbitre', 'Arb.', $points, RuleType::RefereeQualification, ['qualifications' => $qualifications, 'min_national_games' => $minGames]);
    }

    private static function fixture(): SeasonFixture
    {
        return SeasonFixture::create()
            ->competition('N1H')->competition('N2H')->competition('OPEND')
            ->named(1, '5006', 'DUPONT', 'Jean-Pierre')
            ->named(2, '3512', 'MARTIN', 'Éloïse')
            ->named(3, '1412', 'MARTIN', 'Paul')
            ->named(4, '4421', 'MARTIN', 'Paul');
    }

    public function testNamesAreComparedWithoutCaseAccentsOrPunctuation(): void
    {
        self::assertSame('MARTIN ELOISE', NameNormalizer::normalize(' martin  Éloïse '));
        self::assertSame('DUPONT JEAN PIERRE', NameNormalizer::normalize('Dupont Jean-Pierre'));
        self::assertSame(['DUPONT Jean', 'MARTIN Paul'], NameNormalizer::split('DUPONT Jean / MARTIN Paul'));
        self::assertSame(['A B', 'C D'], NameNormalizer::split('A B et C D'));
    }

    public function testCnc15ADelegateOnFourGamedaysEarnsOnce(): void
    {
        $fixture = self::fixture();
        foreach ([1, 2, 3, 4] as $id) {
            $fixture->gameday($id, 'N1H', '2026-05-0' . $id, 'Caen', '5006', delegate: 'Dupont Jean-Pierre');
        }

        $result = (new GamedayOfficialCalculator())->compute(self::officialRule(), $fixture->context());

        self::assertSame(['5006' => 50], self::pointsByClub($result));
        self::assertSame(1, $result->lines[0]->matric);
        self::assertSame(Line::SOURCE_LICENSEE, $result->lines[0]->sourceType);
    }

    public function testDelegateAndChiefRefereeEarnForTheirClubsInBothNameOrders(): void
    {
        $fixture = self::fixture()->gameday(1, 'N1H', '2026-05-01', 'Caen', '5006', 'ÉLOÏSE MARTIN', 'Jean-Pierre DUPONT');

        self::assertSame(['3512' => 50, '5006' => 50], self::pointsByClub((new GamedayOfficialCalculator())->compute(self::officialRule(), $fixture->context())));
    }

    public function testALicenceNumberWrittenAfterTheNameMatchesTheLicenseeAutomatically(): void
    {
        $fixture = self::fixture()->gameday(1, 'N1H', '2026-05-01', 'Caen', '5006', 'Paul MARTIN (4)', 'Nom Inexact (1)');

        $result = (new GamedayOfficialCalculator())->compute(self::officialRule(), $fixture->context());

        self::assertSame(['4421' => 50, '5006' => 50], self::pointsByClub($result));
        self::assertSame([], $result->alerts);
        self::assertSame(4, NameNormalizer::matric('Paul MARTIN (4)'));
        self::assertSame('Paul MARTIN', NameNormalizer::withoutMatric('Paul MARTIN (4)'));
        self::assertNull(NameNormalizer::matric('Paul MARTIN'));
        self::assertSame("CANOE CLUB D AVRANCHES", NameNormalizer::normalizeLabel('CANOE CLUB D&#039;AVRANCHES'));
    }

    public function testCnc16AnUnmatchedOrAmbiguousNameIsAnAlertUntilItIsMatchedByHand(): void
    {
        $fixture = self::fixture()
            ->gameday(1, 'N1H', '2026-05-01', 'Caen', '5006', 'Inconnu Pierre', 'MARTIN Paul')
            ->gameday(2, 'OPEND', '2026-05-01', 'Caen', '5006', 'Hors Perimetre');

        $before = (new GamedayOfficialCalculator())->compute(self::officialRule(), $fixture->context());
        $after = (new GamedayOfficialCalculator())->compute(self::officialRule(), $fixture->alias('martin paul', 4)->alias('INCONNU Pierre', 1)->context());

        self::assertSame([], $before->lines);
        self::assertSame([Alert::OFFICIAL_UNRESOLVED, Alert::OFFICIAL_UNRESOLVED], array_map(static fn (Alert $alert): string => $alert->kind, $before->alerts));
        self::assertStringContainsString('2 licenciés', $before->alerts[1]->message);
        self::assertSame(['4421' => 50, '5006' => 50], self::pointsByClub($after));
        self::assertSame([], $after->alerts);
    }

    public function testCnc17AManagerOfThreeNationalCompetitionsEarnsOnce(): void
    {
        $fixture = self::fixture()->manager(2, 'N1H')->manager(2, 'N2H')->manager(2, 'N3')->manager(3, 'OPEND')->manager(4, null);
        $rule = new Rule('7.3', 2, 'Responsable de compétition', 'RC', 40, RuleType::CompetitionManager);

        $result = (new CompetitionManagerCalculator())->compute($rule, $fixture->context());

        self::assertSame(['3512' => 40], self::pointsByClub($result));
    }

    public function testCnc18AnInternationalRefereeMustHaveRefereedANationalGame(): void
    {
        $fixture = self::fixture()->referee(1, 'Int', '', 0)->referee(2, 'Int', '', 3);
        $rule = self::refereeRule(3, 35, [['qualification' => 'Int', 'level' => '*']], 1);

        self::assertSame(['3512' => 35], self::pointsByClub((new RefereeQualificationCalculator())->compute($rule, $fixture->context())));
    }

    public function testCnc19ARefereeWhoseLicenceIsNotRenewedEarnsNothing(): void
    {
        $fixture = self::fixture()->licensee(10, '5006', '2025')->referee(10, 'Nat', 'A', 12);

        self::assertSame([], (new RefereeQualificationCalculator())->compute(self::refereeRule(5, 15, [['qualification' => 'Nat', 'level' => 'A']]), $fixture->context())->lines);
    }

    public function testCnc19bQualificationAndLevelAreEnoughWithoutAnyGame(): void
    {
        $fixture = self::fixture()->referee(1, 'Nat', 'B')->referee(2, 'OTM')->referee(3, 'JO')->referee(4, 'Reg', 'C');
        $bc = self::refereeRule(6, 10, [['qualification' => 'Nat', 'level' => 'B'], ['qualification' => 'Nat', 'level' => 'C']]);
        $low = self::refereeRule(7, 3, [['qualification' => 'Nat', 'level' => 'S'], ['qualification' => 'Reg', 'level' => '*'], ['qualification' => 'OTM', 'level' => '*'], ['qualification' => 'JO', 'level' => '*']]);

        self::assertSame(['5006' => 10], self::pointsByClub((new RefereeQualificationCalculator())->compute($bc, $fixture->context())));
        self::assertSame(['1412' => 3, '3512' => 3, '4421' => 3], self::pointsByClub((new RefereeQualificationCalculator())->compute($low, $fixture->context())));
    }

    public function testCnc19cAnInternationalRefereeIsNotANationalA(): void
    {
        $fixture = self::fixture()->referee(1, 'Int', 'A', 2);

        self::assertSame([], (new RefereeQualificationCalculator())->compute(self::refereeRule(5, 15, [['qualification' => 'Nat', 'level' => 'A']]), $fixture->context())->lines);
    }

    public function testARefereeRuleNeedsQualifications(): void
    {
        self::assertNotSame([], (new RefereeQualificationCalculator())->validate(self::refereeRule(5, 15, []), self::fixture()->scale()));
    }

    public function testAManualListPaysOncePerClubOrOncePerLicensee(): void
    {
        $fixture = self::fixture()->licensee(5, '5006')->manual('7.1.4', 1)->manual('7.1.4', 5)->manual('7.1.4', null, '3512')
            ->manual('7.3.4', 1)->manual('7.3.4', 5);
        $perClub = new Rule('7.1', 4, 'Équipe de France', 'EdF', 5, RuleType::ManualList, ['unit' => 'club']);
        $perLicensee = new Rule('7.3', 4, 'Autre membre de la CNA', 'CNA', 20, RuleType::ManualList, ['unit' => 'licensee']);

        self::assertSame(['3512' => 5, '5006' => 5], self::pointsByClub((new ManualListCalculator())->compute($perClub, $fixture->context())));
        self::assertSame(['5006' => 40], self::pointsByClub((new ManualListCalculator())->compute($perLicensee, $fixture->context())));
    }

    public function testCnc20AnEntryExcludedByAnotherRuleIsLeftOutWithAnAlert(): void
    {
        $fixture = self::fixture()->manual('7.3.4', 2)->manual('7.3.4', 3);
        $rule = new Rule('7.3', 4, 'Autre membre de la CNA', 'CNA', 20, RuleType::ManualList, ['unit' => 'licensee', 'exclude_rules' => ['7.3.1', '7.3.2']]);
        $rcLine = new Line('7.3.2', '3512', 40, 'RC', Line::SOURCE_LICENSEE, '2', 2);

        $result = (new ManualListCalculator())->compute($rule, $fixture->context(lines: [$rcLine]));

        self::assertSame(['1412' => 20], self::pointsByClub($result));
        self::assertSame(Alert::EXCLUDED_ENTRY, $result->alerts[0]->kind);
        self::assertSame([2], ManualListCalculator::excludedMatrics($rule, [$rcLine]));
    }
}
