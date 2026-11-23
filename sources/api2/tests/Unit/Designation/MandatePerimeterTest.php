<?php

namespace App\Tests\Unit\Designation;

use App\Designation\MandatePerimeter;
use PHPUnit\Framework\TestCase;

/**
 * Filtres d'un mandat généré (FEATURE_DESIGNATIONS.md D6, D10, MAN-04, MAN-05, MAN-09). Un filtre vide de
 * kp_user_mandat veut dire « sans restriction » : un mandat généré a donc toujours des compétitions.
 */
final class MandatePerimeterTest extends TestCase
{
    public function testMan05ACompetitionDesignationRestrictsToTheCompetitionWithoutGamedayFilter(): void
    {
        $perimeter = new MandatePerimeter('2998', competitions: ['NDES']);

        self::assertSame(
            ['filtre_saison' => '|2998|', 'filtre_competition' => '|NDES|', 'filtre_journee' => '', 'limitation_equipe_club' => null, 'id_evenement' => ''],
            $perimeter->columns(),
        );
    }

    public function testMan04GamedaysOfSeveralEventsAreUnitedAndTheirCompetitionsAllowed(): void
    {
        $perimeter = new MandatePerimeter('2998', gamedays: [9802 => 'NDES', 9801 => 'NDES', 9803 => 'REGDES']);

        self::assertSame('|NDES|REGDES|', $perimeter->columns()['filtre_competition']);
        self::assertSame('9801,9802,9803', $perimeter->columns()['filtre_journee']);
    }

    public function testAWholeCompetitionMixedWithGamedaysKeepsEveryGamedayOfThatCompetition(): void
    {
        $perimeter = new MandatePerimeter('2998', competitions: ['N1H'], gamedays: [9803 => 'REGDES'], gamedaysOfCompetitions: [501 => 'N1H', 502 => 'N1H']);

        self::assertSame('|N1H|REGDES|', $perimeter->columns()['filtre_competition']);
        self::assertSame('501,502,9803', $perimeter->columns()['filtre_journee']);
    }

    public function testMan09ATeamManagerIsLimitedToTheClubsAndCompetitionsOfHisTeams(): void
    {
        $perimeter = new MandatePerimeter('2998', competitions: ['NDES', 'N2'], clubs: ['C002', 'C001', 'C001']);

        self::assertSame('|N2|NDES|', $perimeter->columns()['filtre_competition']);
        self::assertSame('C001,C002', $perimeter->columns()['limitation_equipe_club']);
    }

    public function testAPerimeterWithoutCompetitionIsEmpty(): void
    {
        self::assertTrue((new MandatePerimeter('2998'))->isEmpty());
        self::assertFalse((new MandatePerimeter('2998', competitions: ['NDES']))->isEmpty());
    }
}
