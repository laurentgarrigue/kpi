<?php

namespace App\Tests\Support;

use App\AgeCategory\AgeCategory;
use App\AgeCategory\AgeCategoryTable;

/** The FFCK age categories (kp_categorie), POU 9-10 … V9 75-120, without a database. */
final class FfckAgeCategoryTable implements AgeCategoryTable
{
    private const ROWS = [
        ['POU', 'Poussin', 9, 10], ['BEN', 'Benjamin', 11, 12], ['MIN', 'Minime', 13, 14],
        ['CAD', 'Cadet', 15, 16], ['JUN', 'Junior', 17, 18], ['SEN', 'Senior', 19, 34],
        ['V1', 'Vétéran 1', 35, 39], ['V2', 'Vétéran 2', 40, 44], ['V3', 'Vétéran 3', 45, 49],
        ['V4', 'Vétéran 4', 50, 54], ['V5', 'Vétéran 5', 55, 59], ['V6', 'Vétéran 6', 60, 64],
        ['V7', 'Vétéran 7', 65, 69], ['V8', 'Vétéran 8', 70, 74], ['V9', 'Vétéran 9', 75, 120],
    ];

    public function all(): array
    {
        return array_map(
            static fn (array $row): AgeCategory => new AgeCategory($row[0], $row[1], $row[2], $row[3]),
            self::ROWS
        );
    }
}
