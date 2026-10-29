<?php

namespace App\Tests\Integration;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Import PCE lancé sur un fichier local (REALIGNEMENT_STRUCTURES_FFCK.md, seam 1) : fichier de fixture
 * tests/Fixtures/pce/pce-2999.txt (saison 2999, licenciés 88xx, CR CRP1/CRP2, CD 3300/6400, Clubs 3301/3302/6401).
 */
final class ImportPceTest extends ApiTestCase
{
    private const FIXTURE_FILE = __DIR__ . '/../Fixtures/pce/pce-2999.txt';

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->beginWrites();
    }

    public function testImportsTheLicenseesOfTheLocalFileForItsSeason(): void
    {
        $tester = $this->import(self::FIXTURE_FILE);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertSame(
            ['8801' => '2999', '8802' => '2999', '8803' => '2999', '8804' => '2999'],
            $this->connection->fetchAllKeyValue('SELECT Matric, Origine FROM kp_licence WHERE Matric BETWEEN 8800 AND 8899 ORDER BY Matric'),
        );
        self::assertSame('3301', $this->connection->fetchOne('SELECT Numero_club FROM kp_licence WHERE Matric = 8801'));
    }

    public function testCreatesEveryPceClubMissingFromKpiInTheSameImportEachUnderASingleCd(): void
    {
        $this->import(self::FIXTURE_FILE);

        self::assertSame(
            ['3301' => '3300', '3302' => '3300', '6401' => '6400'],
            $this->connection->fetchAllKeyValue("SELECT Code, Code_comite_dep FROM kp_club WHERE Code IN ('3301', '3302', '6401') ORDER BY Code"),
        );
    }

    public function testUpdatesTheLabelAndTheCdOfAnExistingClub(): void
    {
        $this->connection->insert('kp_club', ['Code' => '3301', 'Libelle' => 'ANCIEN NOM', 'Code_comite_dep' => '6400']);

        $this->import(self::FIXTURE_FILE);

        self::assertSame(
            ['Libelle' => 'CK CLUB PCE ALPHA', 'Code_comite_dep' => '3300'],
            $this->connection->fetchAssociative("SELECT Libelle, Code_comite_dep FROM kp_club WHERE Code = '3301'"),
        );
    }

    public function testNeverChangesTheKpiOwnFieldsOfAClub(): void
    {
        $kpiFields = [
            'Officiel' => 'N',
            'Reserve' => 'interne',
            'Coord' => '44.8,-0.5',
            'Postal' => '1 quai des Chartrons 33000 Bordeaux',
            'Coord2' => '44.9,-0.6',
            'www' => 'https://alpha.example',
            'email' => 'contact@alpha.example',
        ];
        $this->connection->insert('kp_club', ['Code' => '3301', 'Libelle' => 'ANCIEN NOM', 'Code_comite_dep' => '3300'] + $kpiFields);

        $this->import(self::FIXTURE_FILE);

        self::assertSame(
            $kpiFields,
            $this->connection->fetchAssociative("SELECT Officiel, Reserve, Coord, Postal, Coord2, www, email FROM kp_club WHERE Code = '3301'"),
        );
    }

    public function testUpdatesTheLabelAndTheCrOfAnExistingCd(): void
    {
        $this->connection->insert('kp_cd', ['Code' => '3300', 'Libelle' => 'ANCIEN NOM', 'Code_comite_reg' => 'CRP2']);

        $this->import(self::FIXTURE_FILE);

        self::assertSame(
            ['Libelle' => 'CD CK PCE 33', 'Code_comite_reg' => 'CRP1'],
            $this->connection->fetchAssociative("SELECT Libelle, Code_comite_reg FROM kp_cd WHERE Code = '3300'"),
        );
    }

    public function testAMissingFileFailsWithAnExplicitMessageAndWritesNothing(): void
    {
        $tester = $this->import(__DIR__ . '/../Fixtures/pce/absent.txt');

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('introuvable', $tester->getDisplay());
        $this->assertNothingImported();
    }

    public function testAnEmptyFileFailsWithAnExplicitMessageAndWritesNothing(): void
    {
        $emptyFile = (string) tempnam(sys_get_temp_dir(), 'pce-empty-');
        try {
            $tester = $this->import($emptyFile);
        } finally {
            unlink($emptyFile);
        }

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('vide', $tester->getDisplay());
        $this->assertNothingImported();
    }

    private function assertNothingImported(): void
    {
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM kp_licence WHERE Origine = ?', ['2999']));
        self::assertFalse($this->connection->fetchOne("SELECT 1 FROM kp_cr WHERE Code IN ('CRP1', 'CRP2')"));
    }

    private function import(string $file): CommandTester
    {
        $tester = new CommandTester((new Application(static::$kernel))->find('app:import-pce'));
        $tester->execute(['--file' => $file]);

        return $tester;
    }
}
