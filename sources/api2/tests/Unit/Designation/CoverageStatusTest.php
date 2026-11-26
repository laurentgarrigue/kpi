<?php

namespace App\Tests\Unit\Designation;

use App\Designation\CoverageHolder;
use App\Designation\CoverageStatus;
use App\Designation\Requirement;
use PHPUnit\Framework\TestCase;

/** États d'une case du suivi de couverture (FEATURE_DESIGNATIONS.md D26, SUI-01, SUI-04). */
final class CoverageStatusTest extends TestCase
{
    public function testSui01ARequiredRoleWithoutHolderIsRed(): void
    {
        self::assertSame(CoverageStatus::Red, CoverageStatus::of(Requirement::Required, []));
    }

    public function testSui01ARoleWithoutMeaningAtThisLevelIsNotApplicable(): void
    {
        self::assertSame(CoverageStatus::NotApplicable, CoverageStatus::of(Requirement::NotApplicable, []));
    }

    public function testAnOptionalRoleWithoutHolderIsEmpty(): void
    {
        self::assertSame(CoverageStatus::Empty, CoverageStatus::of(Requirement::Optional, []));
    }

    public function testAHolderWithAnEmailFromNoTeamClubIsGreen(): void
    {
        self::assertSame(CoverageStatus::Green, CoverageStatus::of(Requirement::Required, [new CoverageHolder(true, false)]));
        self::assertSame(CoverageStatus::Green, CoverageStatus::of(Requirement::Optional, [new CoverageHolder(true, false)]));
    }

    public function testSui04AHolderWithoutEmailIsOrange(): void
    {
        self::assertSame(CoverageStatus::Orange, CoverageStatus::of(Requirement::Required, [new CoverageHolder(false, false)]));
        self::assertSame(CoverageStatus::Orange, CoverageStatus::of(Requirement::Optional, [new CoverageHolder(true, false), new CoverageHolder(false, false)]));
    }

    public function testSui04AHolderFromTheClubOfAnEngagedTeamStaysGreenButIsWarned(): void
    {
        self::assertSame(CoverageStatus::Green, CoverageStatus::of(Requirement::Required, [new CoverageHolder(true, true)]));
        self::assertSame(['same_club'], (new CoverageHolder(true, true))->warnings());
    }

    public function testTheReasonsOfAnOrangeHolderAreNamed(): void
    {
        self::assertSame(['no_email', 'same_club'], (new CoverageHolder(false, true))->warnings());
        self::assertSame([], (new CoverageHolder(true, false))->warnings());
    }
}
