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
