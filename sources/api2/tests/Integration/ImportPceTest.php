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

    public function testUpdatesTheLabelOfAnExistingCr(): void
    {
        $this->connection->insert('kp_cr', ['Code' => 'CRP1', 'Libelle' => 'ANCIEN NOM']);

        $this->import(self::FIXTURE_FILE);

        self::assertSame('CR CK PCE NORD', $this->connection->fetchOne("SELECT Libelle FROM kp_cr WHERE Code = 'CRP1'"));
    }

    public function testConvertsTheOverseasCdAndClubCodes(): void
    {
        $this->importLicensees(
            $this->licenseeLine(8811, '971001', 'CD971'),
            $this->licenseeLine(8812, '974001', 'CD974'),
            $this->licenseeLine(8813, '975001', 'CD975'),
            $this->licenseeLine(8814, '976001', 'CD976'),
            $this->licenseeLine(8815, '986001', 'CD986'),
            $this->licenseeLine(8816, '988001', 'CD988'),
        );

        self::assertSame(
            ['9A01' => '9A00', '9D01' => '9D00', '9E01' => '9E00', '9F01' => '9F00', '9G01' => '9G00', '9I01' => '9I00'],
            $this->connection->fetchAllKeyValue("SELECT Code, Code_comite_dep FROM kp_club WHERE Libelle LIKE 'CLUB OUTRE-MER %' ORDER BY Code"),
        );
        self::assertSame(
            ['9A00', '9D00', '9E00', '9F00', '9G00', '9I00'],
            $this->connection->fetchFirstColumn("SELECT Code FROM kp_cd WHERE Libelle LIKE 'CD OUTRE-MER %' ORDER BY Code"),
        );
    }

    public function testIgnoresTheNoClubAndInternationalLicenseesAndThoseOfOtherSeasons(): void
    {
        $this->seedLicensee(8821, '2999', '0000', '0000', 'CRP1');
        $this->seedLicensee(8822, '2999', '0', '0000', 'CRP1');
        $this->seedLicensee(8823, '2998', '3399', '3300', 'CRP1');

        $this->importLicensees($this->licenseeLine(8824, '098001', 'CD098', 'CRP9', '98'));

        self::assertSame([], $this->connection->fetchFirstColumn("SELECT Code FROM kp_club WHERE Code IN ('0', '0000', '3399', '9801')"));
        self::assertSame([], $this->connection->fetchFirstColumn("SELECT Code FROM kp_cd WHERE Code IN ('0000', '9800')"));
        self::assertSame([], $this->connection->fetchFirstColumn("SELECT Code FROM kp_cr WHERE Code IN ('CRP9', '98')"));
    }

    public function testLeavesTheStructuresAbsentFromThePceUntouched(): void
    {
        $this->connection->insert('kp_cr', ['Code' => 'CRP8', 'Libelle' => 'CR HORS PCE']);
        $this->connection->insert('kp_cd', ['Code' => '3500', 'Libelle' => 'CD HORS PCE', 'Code_comite_reg' => 'CRP1']);
        $this->connection->insert('kp_club', ['Code' => '3501', 'Libelle' => 'CLUB HORS PCE', 'Code_comite_dep' => '3300']);

        $this->import(self::FIXTURE_FILE);

        self::assertSame('CR HORS PCE', $this->connection->fetchOne("SELECT Libelle FROM kp_cr WHERE Code = 'CRP8'"));
        self::assertSame(['CD HORS PCE', 'CRP1'], array_values((array) $this->connection->fetchAssociative("SELECT Libelle, Code_comite_reg FROM kp_cd WHERE Code = '3500'")));
        self::assertSame(['CLUB HORS PCE', '3300'], array_values((array) $this->connection->fetchAssociative("SELECT Libelle, Code_comite_dep FROM kp_club WHERE Code = '3501'")));
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

    private function licenseeLine(int $matric, string $clubNumber, string $cdNumber, string $crLabel = 'OUTRE-MER', string $crNumber = 'CRP3'): string
    {
        $department = substr($cdNumber, 2);

        return "$matric;OUTREMER;Test;D;2000-01-01;CLUB OUTRE-MER $department;$clubNumber;COMITE DEPARTEMENTAL OUTRE-MER $department;$cdNumber;"
            . "COMITE REGIONAL $crLabel;$crNumber;ACTIF;;;;OUI;OUI;Canoë Plus";
    }

    /** Licensee already in kp_licence, with its structure labels still filled in. */
    private function seedLicensee(int $matric, string $season, string $clubNumber, string $cdNumber, string $crNumber): void
    {
        $this->connection->insert('kp_licence', [
            'Matric' => $matric, 'Origine' => $season, 'Nom' => 'SEED', 'Prenom' => 'Test',
            'Club' => 'CLUB SEED', 'Numero_club' => $clubNumber,
            'Comite_dept' => 'CD SEED', 'Numero_comite_dept' => $cdNumber,
            'Comite_reg' => 'CR SEED', 'Numero_comite_reg' => $crNumber,
        ]);
    }

    private function importLicensees(string ...$lines): CommandTester
    {
        $file = (string) tempnam(sys_get_temp_dir(), 'pce-');
        file_put_contents($file, "[date_valeur]\n2999-01-15\n[licencies]\n" . implode("\n", $lines) . "\n");
        try {
            return $this->import($file);
        } finally {
            unlink($file);
        }
    }

    private function import(string $file): CommandTester
    {
        $tester = new CommandTester((new Application(static::$kernel))->find('app:import-pce'));
        $tester->execute(['--file' => $file]);

        return $tester;
    }
}
