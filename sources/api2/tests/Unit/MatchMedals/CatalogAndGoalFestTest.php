<?php

namespace App\Tests\Unit\MatchMedals;

use App\MatchMedals\Catalog\MedalCatalog;
use App\MatchMedals\Stats\GoalFest;
use PHPUnit\Framework\TestCase;

final class CatalogAndGoalFestTest extends TestCase
{
    use MedalAssertions;

    public function testMed41ExplanationVariablesAreReplacedByTheThresholds(): void
    {
        self::assertSame(
            'Marque moins de 45 secondes après avoir encaissé un but.',
            MedalCatalog::render('Marque moins de {bounceBackSeconds} secondes après avoir encaissé un but.', self::thresholds(['bounceBackSeconds' => 45])),
        );
        self::assertSame('{unknown} reste tel quel', MedalCatalog::render('{unknown} reste tel quel', self::thresholds()));
    }

    public function testGoalFestIsTheTopTenPercentFromTenGames(): void
    {
        $thresholds = self::thresholds();

        self::assertNull(GoalFest::threshold([5, 6, 7], $thresholds), 'fewer than 10 games');
        self::assertSame(14, GoalFest::threshold([3, 4, 5, 6, 7, 8, 9, 10, 12, 14], $thresholds));
        self::assertSame(12, GoalFest::threshold([3, 4, 5, 6, 7, 8, 9, 10, 12, 14, 2, 1, 0, 3, 4, 5, 6, 7, 8, 2], $thresholds));
    }
}
