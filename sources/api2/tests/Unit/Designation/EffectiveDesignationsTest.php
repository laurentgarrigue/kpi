<?php

namespace App\Tests\Unit\Designation;

use App\Designation\Authority;
use App\Designation\Designation;
use App\Designation\EffectiveDesignations;
use App\Designation\GamedayScope;
use App\Designation\ScopeType;
use PHPUnit\Framework\TestCase;

/** Désignations effectives d'une journée : la plus précise l'emporte (FEATURE_DESIGNATIONS.md D9, DES-08 à DES-10). */
final class EffectiveDesignationsTest extends TestCase
{
    private const SEASON = '2027';

    public function testDes08AnEventDesignationAppliesToEachOfItsGamedays(): void
    {
        $onEvent = self::designation(1, 'DELEGUE_CNA', ScopeType::Evenement, '12', 1001);

        foreach ([501, 502] as $gamedayId) {
            $effective = EffectiveDesignations::of(self::gameday($gamedayId, 'N1H', [12]), [$onEvent]);
            self::assertSame([$onEvent], $effective->holders('DELEGUE_CNA'));
            self::assertSame(ScopeType::Evenement, $effective->origin('DELEGUE_CNA'));
        }
    }

    public function testDes08AGamedayDesignationOverridesTheEventOne(): void
    {
        $onEvent = self::designation(1, 'DELEGUE_CNA', ScopeType::Evenement, '12', 1001);
        $onGameday = self::designation(2, 'DELEGUE_CNA', ScopeType::Journee, '502', 1002);
        $designations = [$onEvent, $onGameday];

        self::assertSame([$onEvent], EffectiveDesignations::of(self::gameday(501, 'N1H', [12]), $designations)->holders('DELEGUE_CNA'));
        $other = EffectiveDesignations::of(self::gameday(502, 'N1H', [12]), $designations);
        self::assertSame([$onGameday], $other->holders('DELEGUE_CNA'));
        self::assertSame(ScopeType::Journee, $other->origin('DELEGUE_CNA'));
    }

    public function testDes09ACompetitionDesignationOverridesTheEventOne(): void
    {
        $onEvent = self::designation(1, 'JUGE_ARBITRE', ScopeType::Evenement, '12', 1001);
        $onCompetition = self::designation(2, 'JUGE_ARBITRE', ScopeType::Competition, 'N1D', 1002);
        $designations = [$onEvent, $onCompetition];

        self::assertSame([$onCompetition], EffectiveDesignations::of(self::gameday(501, 'N1D', [12]), $designations)->holders('JUGE_ARBITRE'));
        self::assertSame([$onEvent], EffectiveDesignations::of(self::gameday(502, 'N1H', [12]), $designations)->holders('JUGE_ARBITRE'));
    }

    public function testDes10ANewPhaseOfACompetitionInheritsWithoutAction(): void
    {
        $onCompetition = self::designation(1, 'R1', ScopeType::Competition, 'CF15', 1001);

        $newPhase = EffectiveDesignations::of(self::gameday(999, 'CF15', []), [$onCompetition]);

        self::assertSame([$onCompetition], $newPhase->holders('R1'));
    }

    public function testEveryHolderOfTheMostPreciseScopeIsKeptInDesignationOrder(): void
    {
        $nj2 = self::designation(2, 'ARBITRE_NJ', ScopeType::Journee, '501', 1002, order: 2);
        $nj1 = self::designation(1, 'ARBITRE_NJ', ScopeType::Journee, '501', 1001, order: 1);
        $onEvent = self::designation(3, 'ARBITRE_NJ', ScopeType::Evenement, '12', 1003);

        $effective = EffectiveDesignations::of(self::gameday(501, 'N2', [12]), [$nj2, $onEvent, $nj1]);

        self::assertSame([$nj1, $nj2], $effective->holders('ARBITRE_NJ'));
    }

    public function testTheNationalLevelAppliesOnlyToNationalCompetitions(): void
    {
        $bureau = self::designation(1, 'BUREAU_CNA', ScopeType::Niveau, Designation::NATIONAL_LEVEL, 1001);

        self::assertSame([$bureau], EffectiveDesignations::of(self::gameday(501, 'N3', []), [$bureau])->holders('BUREAU_CNA'));
        self::assertSame([], EffectiveDesignations::of(self::gameday(502, 'REG07', []), [$bureau])->holders('BUREAU_CNA'));
    }

    public function testDesignationsOfAnotherSeasonOrPerimeterAreIgnored(): void
    {
        $otherSeason = self::designation(1, 'R1', ScopeType::Competition, 'N1H', 1001, season: '2026');
        $otherGameday = self::designation(2, 'R1', ScopeType::Journee, '777', 1002);
        $otherEvent = self::designation(3, 'R1', ScopeType::Evenement, '99', 1003);
        $team = self::designation(4, 'RESP_EQUIPE', ScopeType::Equipe, '42', 1004);

        $effective = EffectiveDesignations::of(self::gameday(501, 'N1H', [12]), [$otherSeason, $otherGameday, $otherEvent, $team]);

        self::assertSame([], $effective->holders('R1'));
        self::assertNull($effective->origin('R1'));
        self::assertSame([], $effective->holders('RESP_EQUIPE'));
    }

    private static function gameday(int $id, string $competition, array $eventIds): GamedayScope
    {
        return new GamedayScope($id, self::SEASON, $competition, $eventIds);
    }

    private static function designation(
        int $id,
        string $role,
        ScopeType $scope,
        string $scopeId,
        int $matric,
        int $order = 1,
        string $season = self::SEASON,
    ): Designation {
        return new Designation($id, $season, $role, $scope, $scopeId, $matric, '1234', Authority::Cna, $order);
    }
}
