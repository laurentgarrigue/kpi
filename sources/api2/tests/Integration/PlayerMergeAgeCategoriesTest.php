<?php

namespace App\Tests\Integration;

use App\Service\PlayerMergeService;
use Doctrine\DBAL\Connection;

/**
 * Story 35 (CATEGORIE_AGE.md): merging duplicate players treats a conventional birth date (01/01/1901, 0000-00-00) as
 * absent, to choose the record carrying the real date and to copy that date. Two non-federal duplicates are created
 * here (Matric > 2000000, same name and club); the fixtures have none. Team 9381 is in season 2997.
 */
final class PlayerMergeAgeCategoriesTest extends ApiTestCase
{
    private const CONVENTIONAL = 2000801;
    private const REAL = 2000802;
    private const REAL_BIRTH_DATE = '2980-05-01';

    private Connection $connection;
    private PlayerMergeService $merge;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->beginWrites();
        $this->merge = static::getContainer()->get(PlayerMergeService::class);
    }

    public function testAutoMergePrefersTheRecordWithARealBirthDateOverOneBornOn1901(): void
    {
        $this->duplicate(self::CONVENTIONAL, '1901-01-01');
        $this->duplicate(self::REAL, self::REAL_BIRTH_DATE);

        $this->merge->autoMergeNonFederalPlayers();

        self::assertSame([(string) self::REAL], $this->remainingDuplicates());
        self::assertSame(self::REAL_BIRTH_DATE, $this->birthDateOf(self::REAL));
    }

    public function testAutoMergeCopiesTheRealBirthDateOntoATargetBornOn1901AndRecomputesItsCategory(): void
    {
        $this->duplicate(self::CONVENTIONAL, '1901-01-01', icfNumber: '12345');
        $this->duplicate(self::REAL, self::REAL_BIRTH_DATE);
        $this->connection->insert('kp_competition_equipe_joueur', ['Id_equipe' => 9381, 'Matric' => self::REAL, 'Nom' => 'DOUBLON', 'Prenom' => 'Joueur', 'Sexe' => 'M', 'Categ' => 'V9', 'Numero' => 9, 'Capitaine' => '-']);

        $this->merge->autoMergeNonFederalPlayers();

        self::assertSame([(string) self::CONVENTIONAL], $this->remainingDuplicates(), 'the ICF number still wins');
        self::assertSame(self::REAL_BIRTH_DATE, $this->birthDateOf(self::CONVENTIONAL));
        self::assertSame('JUN', $this->connection->fetchOne('SELECT Categ FROM kp_competition_equipe_joueur WHERE Id_equipe = 9381 AND Matric = ?', [self::CONVENTIONAL]));
    }

    private function duplicate(int $matric, string $birthDate, ?string $icfNumber = null): void
    {
        $this->connection->insert('kp_licence', ['Matric' => $matric, 'Origine' => '2997', 'Nom' => 'DOUBLON', 'Prenom' => 'Joueur', 'Sexe' => 'M', 'Numero_club' => 'GER', 'Naissance' => $birthDate, 'Reserve' => $icfNumber]);
    }

    /** @return list<string> */
    private function remainingDuplicates(): array
    {
        return array_map('strval', $this->connection->fetchFirstColumn('SELECT Matric FROM kp_licence WHERE Matric IN (?, ?) ORDER BY Matric', [self::CONVENTIONAL, self::REAL]));
    }

    private function birthDateOf(int $matric): ?string
    {
        return $this->connection->fetchOne('SELECT Naissance FROM kp_licence WHERE Matric = ?', [$matric]);
    }
}
