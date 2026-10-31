<?php

namespace App\Tests\Integration;

use App\Service\EventExportImportService;
use Doctrine\DBAL\Connection;

/**
 * Story 27 (CATEGORIE_AGE.md): importing an exported event recomputes the age categories for the season of the
 * imported teams instead of copying the Categ of the file. Fixtures: event 80 (SCA/SCB, season 2997), player 9511 in
 * team 9381, made born in 2980 here (JUN in 2997); 9512 has no birth date.
 */
final class EventImportAgeCategoriesTest extends ApiTestCase
{
    private const EVENT = 80;

    private Connection $connection;
    private EventExportImportService $events;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->beginWrites();
        $this->events = static::getContainer()->get(EventExportImportService::class);
        $this->connection->executeStatement("UPDATE kp_licence SET Naissance = '2980-03-01' WHERE Matric = 9511");
    }

    public function testImportingAnEventRecomputesTheAgeCategoryForTheSeasonOfTheTeamInsteadOfTheFileValue(): void
    {
        $export = $this->exportWithCategories(['9511' => 'V9', '9512' => 'SEN']);

        $this->events->importEvent(self::EVENT, $export);

        self::assertSame('JUN', $this->categoryOf(9381, 9511));
        self::assertNull($this->categoryOf(9381, 9512), 'unknown birth date: no age category, whatever the file says');
    }

    public function testImportedForeignLicenceKeepsItsKnownBirthDateAndGetsItsCategory(): void
    {
        $this->connection->insert('kp_licence', ['Matric' => 2000901, 'Origine' => '2997', 'Nom' => 'FOREIGN', 'Prenom' => 'Known', 'Sexe' => 'M', 'Numero_club' => 'GER', 'Naissance' => '2960-06-01']);
        $export = $this->exportWithCategories([]);
        $export['kp_competition_equipe_joueur'][] = ['Id_equipe' => 9383, 'Matric' => 2000901, 'Nom' => 'FOREIGN', 'Prenom' => 'Known', 'Sexe' => 'M', 'Categ' => 'V9', 'Numero' => 9, 'Capitaine' => '-'];
        $export['kp_licence_foreign'][] = ['Matric' => 2000901, 'Origine' => 'ETRG', 'Nom' => 'FOREIGN', 'Prenom' => 'Known', 'Sexe' => 'M', 'Naissance' => null, 'Numero_club' => 'GER'];

        $this->events->importEvent(self::EVENT, $export);

        self::assertSame('2960-06-01', $this->connection->fetchOne('SELECT Naissance FROM kp_licence WHERE Matric = 2000901'));
        self::assertSame('V1', $this->categoryOf(9383, 2000901));
    }

    /**
     * @param array<string, string> $categoriesByMatric Categ written in the exported file
     * @return array<string, mixed>
     */
    private function exportWithCategories(array $categoriesByMatric): array
    {
        $export = $this->events->exportEvent(self::EVENT);
        foreach ($export['kp_competition_equipe_joueur'] as &$player) {
            $player['Categ'] = $categoriesByMatric[(string) $player['Matric']] ?? $player['Categ'];
        }
        unset($player);

        return $export;
    }

    private function categoryOf(int $teamId, int $matric): ?string
    {
        $categ = $this->connection->fetchOne('SELECT Categ FROM kp_competition_equipe_joueur WHERE Id_equipe = ? AND Matric = ?', [$teamId, $matric]);
        self::assertNotFalse($categ, "player $matric must be in team $teamId after the import");

        return $categ;
    }
}
