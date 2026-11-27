<?php

namespace App\Tests\Unit\Designation;

use App\Designation\Authority;
use App\Designation\Designation;
use App\Designation\EffectiveDesignations;
use App\Designation\GamedayScope;
use App\Designation\LegacyBackfillPlanner;
use App\Designation\ScopeType;
use PHPUnit\Framework\TestCase;

/** Reprise des officiels saisis en texte avant les Désignations (colonnes legacy de kp_journee → kp_designation). */
final class LegacyBackfillPlannerTest extends TestCase
{
    private const GAMEDAY = 9387;

    public function testTheLicenceInParenthesesIdentifiesTheOfficial(): void
    {
        self::assertSame(57991, LegacyBackfillPlanner::matricOf('LALLIOT Matthieu (57991)'));
        self::assertSame(17225, LegacyBackfillPlanner::matricOf(' Thomas BERTHELEMY (17225) '));
        self::assertNull(LegacyBackfillPlanner::matricOf('Thomas BERTHELEMY'));
        self::assertNull(LegacyBackfillPlanner::matricOf(''));
        self::assertNull(LegacyBackfillPlanner::matricOf(null));
    }

    public function testNonEmptyLegacyColumnsBecomeDesignationsOnTheGameday(): void
    {
        $plan = LegacyBackfillPlanner::forGameday(self::gameday(), ['Delegue' => 'BERTHELEMY Thomas (17225)', 'Responsable_R1' => 'LALLIOT Matthieu (57991)', 'ChefArbitre' => null], self::effective([]));

        self::assertSame([['role' => 'DELEGUE_CNA', 'matric' => 17225, 'order' => 1], ['role' => 'R1', 'matric' => 57991, 'order' => 1]], $plan->create);
        self::assertSame([], $plan->unresolved);
    }

    public function testRep06TheCnaDelegateOfAnInternationalCompetitionBecomesItsChiefOfficial(): void
    {
        $international = new GamedayScope(self::GAMEDAY, '2026', 'ECA', []);

        $plan = LegacyBackfillPlanner::forGameday($international, ['Delegue' => 'BERTHELEMY Thomas (17225)'], EffectiveDesignations::of($international, []));

        self::assertSame([['role' => 'DELEGUE_CNA', 'matric' => 17225, 'order' => 1]], $plan->create);
    }

    public function testNonPlayingRefereesKeepTheirOrder(): void
    {
        $plan = LegacyBackfillPlanner::forGameday(self::gameday(), ['Arb_nj1' => 'A (101)', 'Arb_nj2' => '', 'Arb_nj3' => 'C (103)'], self::effective([]));

        self::assertSame([['role' => 'ARBITRE_NJ', 'matric' => 101, 'order' => 1], ['role' => 'ARBITRE_NJ', 'matric' => 103, 'order' => 2]], $plan->create);
    }

    public function testARoleAlreadyDesignatedIsLeftAlone(): void
    {
        $effective = self::effective([new Designation(1, '2026', 'R1', ScopeType::Competition, 'N1H', 5, null, Authority::Cna)]);

        $plan = LegacyBackfillPlanner::forGameday(self::gameday(), ['Responsable_R1' => 'LALLIOT Matthieu (57991)'], $effective);

        self::assertSame([], $plan->create);
        self::assertSame([], $plan->unresolved);
    }

    public function testATextWithoutLicenceIsReportedAndItsRoleIsNotTouched(): void
    {
        $plan = LegacyBackfillPlanner::forGameday(self::gameday(), ['Arb_nj1' => 'A (101)', 'Arb_nj2' => 'Un arbitre sans licence'], self::effective([]));

        self::assertSame([], $plan->create);
        self::assertSame([['role' => 'ARBITRE_NJ', 'column' => 'Arb_nj2', 'text' => 'Un arbitre sans licence']], $plan->unresolved);
    }

    public function testTheCompetitionManagerIsTheOneTypedOnEveryGamedayOfTheCompetition(): void
    {
        self::assertSame(17221, LegacyBackfillPlanner::competitionManager(['LALLIOT Philippe (17221)', 'LALLIOT Philippe (17221)', null])['matric']);
    }

    public function testNoCompetitionManagerWhenGamedaysDisagreeOrCannotBeRead(): void
    {
        self::assertNull(LegacyBackfillPlanner::competitionManager(['A (1)', 'B (2)'])['matric']);
        self::assertNull(LegacyBackfillPlanner::competitionManager(['A (1)', 'B sans licence'])['matric']);
        self::assertNull(LegacyBackfillPlanner::competitionManager([null, ''])['matric']);
    }

    private static function gameday(): GamedayScope
    {
        return new GamedayScope(self::GAMEDAY, '2026', 'N1H', []);
    }

    /** @param list<Designation> $designations */
    private static function effective(array $designations): EffectiveDesignations
    {
        return EffectiveDesignations::of(self::gameday(), $designations);
    }
}
