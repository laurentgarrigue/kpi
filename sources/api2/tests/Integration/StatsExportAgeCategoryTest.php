<?php

namespace App\Tests\Integration;

use Doctrine\DBAL\Connection;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Spreadsheet export of the statistics (CATEGORIE_AGE.md story 10): a player without birth date has an empty age
 * category cell, never « null » nor « ? », so that it can be sorted and filtered. api2 has no CSV export: the XLSX one
 * is its tabular export. Fixtures: team 9301 (RCH 2999); player 9511 made of unknown birth date here.
 */
final class StatsExportAgeCategoryTest extends ApiTestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->beginWrites();
        $this->connection->executeStatement('UPDATE kp_licence SET Naissance = NULL WHERE Matric = 9511');
        $this->connection->insert('kp_competition_equipe_joueur', ['Id_equipe' => 9301, 'Matric' => 9511, 'Nom' => 'SCRUT', 'Prenom' => 'Capitaine', 'Sexe' => 'M', 'Categ' => null, 'Numero' => 1, 'Capitaine' => '-']);
    }

    public function testStory10UnknownAgeCategoryIsAnEmptyCellOfThePlayersListExport(): void
    {
        $sheet = $this->exportedPlayersList();

        $categoryColumn = array_search('categorie', $sheet[0], true);
        $matricColumn = array_search('matric', $sheet[0], true);
        $player = array_values(array_filter($sheet, static fn (array $row): bool => (string) $row[$matricColumn] === '9511'));

        self::assertNotFalse($categoryColumn);
        self::assertCount(1, $player);
        self::assertContains($player[0][$categoryColumn], [null, '']);
    }

    /** @return list<list<mixed>> rows of the first sheet, the header first */
    private function exportedPlayersList(): array
    {
        $this->client->request('GET', '/admin/stats/export/xlsx?' . http_build_query([
            'season' => '2999', 'type' => 'ListeJoueurs', 'competitions' => ['RCH'], 'limit' => 500,
            // Column codes as headers, to find the columns whatever the translation
            'labels' => json_encode(['matric' => 'matric', 'categorie' => 'categorie'], JSON_THROW_ON_ERROR),
        ]), server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->tokenFor('TADMIN')]);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $file = tempnam(sys_get_temp_dir(), 'stats') . '.xlsx';
        file_put_contents($file, $this->client->getInternalResponse()->getContent());
        try {
            return IOFactory::load($file)->getActiveSheet()->toArray(null, false, false);
        } finally {
            unlink($file);
        }
    }
}
