<?php

namespace App\Tests\Unit\Designation;

use App\Designation\Authority;
use App\Designation\Designation;
use App\Designation\EffectiveDesignations;
use App\Designation\GamedayScope;
use App\Designation\LegacyGamedayColumns;
use App\Designation\ScopeType;
use PHPUnit\Framework\TestCase;

/** Colonnes legacy de kp_journee recopiées depuis les Désignations effectives (FEATURE_DESIGNATIONS.md DES-11). */
final class LegacyGamedayColumnsTest extends TestCase
{
    private const NAMES = [1001 => 'DELEGUE Jean (1001)', 1002 => 'ARBITRE Une (1002)', 1003 => 'ARBITRE Deux (1003)', 1004 => 'RESP Comp (1004)'];

    public function testDes11EachRoleFillsItsColumnWithTheLegacyNameFormat(): void
    {
        $effective = self::effective([
            self::designation(1, 'DELEGUE_CNA', ScopeType::Evenement, '12', 1001),
            self::designation(2, 'RC', ScopeType::Competition, 'N1H', 1004),
        ]);

        $columns = LegacyGamedayColumns::compute($effective, ['DELEGUE_CNA', 'RC'], self::NAMES);

        self::assertSame(['Delegue' => 'DELEGUE Jean (1001)', 'Responsable_insc' => 'RESP Comp (1004)'], $columns);
    }

    public function testNonPlayingRefereesFillTheirColumnsInOrderAndTheRestIsEmptied(): void
    {
        $effective = self::effective([
            self::designation(1, 'ARBITRE_NJ', ScopeType::Journee, '501', 1003, order: 2),
            self::designation(2, 'ARBITRE_NJ', ScopeType::Journee, '501', 1002, order: 1),
        ]);

        $columns = LegacyGamedayColumns::compute($effective, ['ARBITRE_NJ'], self::NAMES);

        self::assertSame([
            'Arb_nj1' => 'ARBITRE Une (1002)', 'Arb_nj2' => 'ARBITRE Deux (1003)', 'Arb_nj3' => null, 'Arb_nj4' => null, 'Arb_nj5' => null,
        ], $columns);
    }

    public function testARoleWithoutDesignationAnyMoreEmptiesItsColumn(): void
    {
        self::assertSame(['Responsable_R1' => null], LegacyGamedayColumns::compute(self::effective([]), ['R1'], self::NAMES));
    }

    public function testOnlyTheRequestedRolesAreWrittenAndRolesWithoutColumnAreIgnored(): void
    {
        $effective = self::effective([self::designation(1, 'R1', ScopeType::Journee, '501', 1002)]);

        self::assertSame([], LegacyGamedayColumns::compute($effective, ['JURY_APPEL', 'BUREAU_CNA'], self::NAMES));
    }

    public function testTheValueFitsTheLegacyColumn(): void
    {
        $long = str_repeat('X', 100) . ' (1001)';
        $effective = self::effective([self::designation(1, 'R1', ScopeType::Journee, '501', 1001)]);

        $columns = LegacyGamedayColumns::compute($effective, ['R1'], [1001 => $long]);

        self::assertSame(LegacyGamedayColumns::MAX_LENGTH, mb_strlen((string) $columns['Responsable_R1']));
    }

    public function testTheLegacyNameIsLastNameFirstNameAndLicence(): void
    {
        self::assertSame('DUPONT Jean (1001)', LegacyGamedayColumns::personLabel(1001, 'DUPONT', 'Jean'));
        self::assertSame('(1001)', LegacyGamedayColumns::personLabel(1001, null, null));
    }

    /** @param list<Designation> $designations */
    private static function effective(array $designations): EffectiveDesignations
    {
        return EffectiveDesignations::of(new GamedayScope(501, '2027', 'N1H', [12]), $designations);
    }

    private static function designation(int $id, string $role, ScopeType $scope, string $scopeId, int $matric, int $order = 1): Designation
    {
        return new Designation($id, '2027', $role, $scope, $scopeId, $matric, null, Authority::Cna, $order);
    }
}
