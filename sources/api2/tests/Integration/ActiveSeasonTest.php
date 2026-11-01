<?php

namespace App\Tests\Integration;

use App\Service\ActiveSeason;
use Doctrine\DBAL\Connection;

/**
 * Active season (kp_saison.Etat = 'A'), the reference season of the age category plausibility (CATEGORIE_AGE.md
 * story 14), of the referees pool categories and of « engagé cette saison ». Fixtures: 2999 active, 2998 inactive.
 */
final class ActiveSeasonTest extends ApiTestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->beginWrites();
    }

    public function testActiveSeasonIsTheSeasonInStateA(): void
    {
        $this->connection->executeStatement("UPDATE kp_saison SET Etat = IF(Code = '2998', 'A', 'I')");

        self::assertSame('2998', $this->activeSeason()->code());
    }

    public function testWithoutAnActiveSeasonTheMostRecentSeasonIsUsed(): void
    {
        $this->connection->executeStatement("UPDATE kp_saison SET Etat = 'I'");

        self::assertSame('2999', $this->activeSeason()->code());
    }

    private function activeSeason(): ActiveSeason
    {
        return static::getContainer()->get(ActiveSeason::class);
    }
}
