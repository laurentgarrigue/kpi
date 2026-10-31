<?php

namespace App\Tests\Integration;

use Doctrine\DBAL\Connection;

/**
 * Catégorie d'âge (CATEGORIE_AGE.md stories 1, 4, 5): every screen derives it from the birth year and the season of
 * the competition (active season when there is none), never from the calendar year nor the licence's season of origin.
 * Fixtures: team 9381 (SCA, season 2997) with players 9511 and 9512; season 2999 made active here.
 */
final class AgeCategoryEndpointsTest extends ApiTestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->beginWrites();
        $this->connection->executeStatement("UPDATE kp_saison SET Etat = IF(Code = '2999', 'O', 'I')");
        $this->connection->executeStatement("UPDATE kp_licence SET Naissance = '2980-03-01', Origine = '2997' WHERE Matric = 9511");
        $this->connection->executeStatement("UPDATE kp_licence SET Naissance = '1901-01-01' WHERE Matric = 9512");
    }

    public function testPresenceSheetGivesTheCategoryOfTheTeamSeasonAndNoneForAnUnknownBirthDate(): void
    {
        $players = $this->requestAs('TADMIN', 'GET', '/admin/teams/9381/players')['players'];
        $categories = array_combine(self::column($players, 'matric'), self::column($players, 'categ'));

        self::assertSame('JUN', $categories[9511], '2997 - 2980 = 17');
        self::assertNull($categories[9512], 'conventional 1901 = unknown');
    }

    public function testAthleteCardGivesTheCategoryOfTheActiveSeasonNotOfTheLicenceOrigin(): void
    {
        $athlete = $this->requestAs('TADMIN', 'GET', '/admin/athletes/9511');

        self::assertSame(['code' => 'SEN', 'libelle' => 'Senior'], $athlete['categorieAge'], '2999 - 2980 = 19, origin 2997 would give JUN');
    }

    public function testPlayerSearchGivesTheCategoryOfTheActiveSeasonNotOfTheCalendarYear(): void
    {
        $players = $this->requestAs('TADMIN', 'GET', '/admin/players/search?q=9511')['players'];

        self::assertSame('SEN', $players[0]['categ']);
    }
}
