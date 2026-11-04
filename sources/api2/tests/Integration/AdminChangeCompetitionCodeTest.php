<?php

namespace App\Tests\Integration;

use Doctrine\DBAL\Connection;

/**
 * « Changer un code compétition » (REALIGNEMENT_STRUCTURES_FFCK.md, seam 2) : toutes les références au code suivent.
 * Fixtures : bloc « ticket RSF-03 » de SQL/fixtures/data.sql (TSTRN en 2999 active et 2998 passée).
 */
final class AdminChangeCompetitionCodeTest extends ApiTestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->beginWrites();
    }

    private function rename(bool $allSeasons, string $targetCode = 'TSTRX', int $expectedStatus = 200): void
    {
        $this->requestAs('TSUPER', 'POST', '/admin/operations/codes/change', [
            'sourceCode' => 'TSTRN',
            'targetCode' => $targetCode,
            'allSeasons' => $allSeasons,
            'targetExists' => false,
        ], $expectedStatus);
    }

    /** @return array<string, string> season => value */
    private function bySeason(string $sql): array
    {
        return $this->connection->fetchAllKeyValue($sql);
    }

    public function testCompetitionManagersFollowTheRenameInAllSeasons(): void
    {
        $this->rename(true);

        self::assertSame(
            ['2998' => 'TSTRX', '2999' => 'TSTRX'],
            $this->bySeason('SELECT Code_saison, Code_competition FROM kp_rc WHERE Id IN (9801, 9802) ORDER BY Code_saison')
        );
    }

    public function testReferenceCodeEqualToTheOldCodeFollowsTheRenameInAllSeasons(): void
    {
        $this->rename(true);

        self::assertSame(
            [['TSTRB', '2998', 'TSTRX'], ['TSTRB', '2999', 'TSTRX'], ['TSTRX', '2998', 'TSTRX'], ['TSTRX', '2999', 'TSTRX']],
            $this->connection->fetchAllNumeric(
                "SELECT Code, Code_saison, Code_ref FROM kp_competition WHERE Code IN ('TSTRN', 'TSTRX', 'TSTRB') ORDER BY Code, Code_saison"
            )
        );
    }

    public function testAggregatedCompetitionsOfMultiCompetitionsFollowTheRenameInAllSeasons(): void
    {
        $this->rename(true);

        self::assertSame(
            ['2998' => '["TSTRX","TSTRB"]', '2999' => '["TSTRX","TSTRB"]'],
            $this->bySeason("SELECT Code_saison, multi_competitions FROM kp_competition WHERE Code = 'TSTMU' ORDER BY Code_saison")
        );
    }

    public function testMandateCompetitionFiltersFollowTheRenameInAllSeasons(): void
    {
        $this->rename(true);

        self::assertSame(
            ['|2998|' => '|TSTRX|TSTRB|', '|2999|' => '|TSTRX|TSTRB|'],
            $this->bySeason('SELECT filtre_saison, filtre_competition FROM kp_user_mandat WHERE id IN (9801, 9802) ORDER BY filtre_saison')
        );
    }

    public function testTheGroupOfTheOldReferenceCodeIsRenamedInAllSeasons(): void
    {
        $this->rename(true);

        self::assertSame(
            [['TSTRX', 'Groupe régional à renommer']],
            $this->connection->fetchAllNumeric("SELECT Groupe, Libelle FROM kp_groupe WHERE Groupe IN ('TSTRN', 'TSTRX')")
        );
    }

    public function testRegistrationFormsFollowTheRenameInAllSeasons(): void
    {
        $this->rename(true);

        self::assertSame(
            ['2998' => 'TSTRX', '2999' => 'TSTRX'],
            $this->bySeason('SELECT competition_season, competition_code FROM kp_form WHERE id IN (9801, 9802) ORDER BY competition_season')
        );
    }

    public function testUserFiltersFollowTheRenameInAllSeasonsEvenWhenOnlyTheSqlListHoldsTheCode(): void
    {
        $this->rename(true);

        self::assertSame(
            [
                ['TRSFA', '|TSTRX|TSTRB|', "'TSTRX','TSTRB'"],
                ['TRSFP', '|TSTRX|TSTRB|', "'TSTRX','TSTRB'"],
                ['TRSFS', '', "'TSTRX'"],
            ],
            $this->userFilters()
        );
    }

    public function testInActiveSeasonModeOnlyTheActiveSeasonReferencesAreRenamed(): void
    {
        $this->rename(false);

        self::assertSame(
            ['2998' => 'TSTRN', '2999' => 'TSTRX'],
            $this->bySeason('SELECT Code_saison, Code_competition FROM kp_rc WHERE Id IN (9801, 9802) ORDER BY Code_saison'),
            'responsables de compétition'
        );
        self::assertSame(
            [['TSTRB', '2998', 'TSTRN'], ['TSTRB', '2999', 'TSTRX'], ['TSTRN', '2998', 'TSTRN'], ['TSTRX', '2999', 'TSTRX']],
            $this->connection->fetchAllNumeric(
                "SELECT Code, Code_saison, Code_ref FROM kp_competition WHERE Code IN ('TSTRN', 'TSTRX', 'TSTRB') ORDER BY Code, Code_saison"
            ),
            'code de référence'
        );
        self::assertSame(
            ['2998' => '["TSTRN","TSTRB"]', '2999' => '["TSTRX","TSTRB"]'],
            $this->bySeason("SELECT Code_saison, multi_competitions FROM kp_competition WHERE Code = 'TSTMU' ORDER BY Code_saison"),
            'compétitions agrégées'
        );
        self::assertSame(
            ['|2998|' => '|TSTRN|TSTRB|', '|2999|' => '|TSTRX|TSTRB|'],
            $this->bySeason('SELECT filtre_saison, filtre_competition FROM kp_user_mandat WHERE id IN (9801, 9802) ORDER BY filtre_saison'),
            'filtres de mandat'
        );
        self::assertSame(
            [['TSTRN', 'Groupe régional à renommer'], ['TSTRX', 'Groupe régional à renommer']],
            $this->connection->fetchAllNumeric("SELECT Groupe, Libelle FROM kp_groupe WHERE Groupe IN ('TSTRN', 'TSTRX') ORDER BY Groupe"),
            'groupe : l\'ancien reste (saisons passées), le nouveau est créé à son image (saison active)'
        );
        self::assertSame(
            ['2998' => 'TSTRN', '2999' => 'TSTRX'],
            $this->bySeason('SELECT competition_season, competition_code FROM kp_form WHERE id IN (9801, 9802) ORDER BY competition_season'),
            'formulaires d\'inscription'
        );
        self::assertSame(
            [
                ['TRSFA', '|TSTRX|TSTRB|', "'TSTRX','TSTRB'"],
                ['TRSFP', '|TSTRN|TSTRB|', "'TSTRN','TSTRB'"],
                ['TRSFS', '', "'TSTRX'"],
            ],
            $this->userFilters(),
            'filtres utilisateur'
        );
    }

    public function testInActiveSeasonModeAnExistingGroupOfTheNewCodeIsKept(): void
    {
        $this->connection->executeStatement(
            "INSERT INTO kp_groupe (section, ordre, Code_niveau, Groupe, Libelle) VALUES (3, 2, 'REG', 'TSTRX', 'Groupe existant')"
        );

        $this->rename(false);

        self::assertSame(
            [['TSTRN', 'Groupe régional à renommer'], ['TSTRX', 'Groupe existant']],
            $this->connection->fetchAllNumeric("SELECT Groupe, Libelle FROM kp_groupe WHERE Groupe IN ('TSTRN', 'TSTRX') ORDER BY Groupe")
        );
    }

    /**
     * Le schéma de fixtures fait échouer le dernier UPDATE (filtres de mandat) quand le code cible vaut TSTFAIL
     * (contrainte chk_fixture_rename_failure, SQL/fixtures/README.md) : toutes les autres références sont déjà écrites.
     */
    public function testAFailedRenameLeavesNoReferenceHalfRenamed(): void
    {
        $this->rename(true, 'TSTFAIL', 400);

        self::assertSame(
            0,
            (int) $this->connection->fetchOne(
                "SELECT (SELECT COUNT(*) FROM kp_competition WHERE Code = 'TSTFAIL' OR Code_ref = 'TSTFAIL' OR multi_competitions LIKE '%TSTFAIL%')
                      + (SELECT COUNT(*) FROM kp_rc WHERE Code_competition = 'TSTFAIL')"
            )
        );
        self::assertSame(2, (int) $this->connection->fetchOne("SELECT COUNT(*) FROM kp_competition WHERE Code = 'TSTRN'"));
        self::assertSame(['TSTRN'], $this->connection->fetchFirstColumn("SELECT Groupe FROM kp_groupe WHERE Groupe IN ('TSTRN', 'TSTFAIL')"));
        self::assertSame(0, (int) $this->connection->fetchOne("SELECT COUNT(*) FROM kp_user WHERE Filtre_competition_sql LIKE '%TSTFAIL%'"));
    }

    /** La connexion du worker FrankenPHP est persistante : un échec ne doit pas la laisser sans contrôle des clés étrangères. */
    public function testAFailedRenameRestoresForeignKeyChecks(): void
    {
        $this->rename(true, 'TSTFAIL', 400);

        self::assertSame(1, (int) $this->connection->fetchOne('SELECT @@FOREIGN_KEY_CHECKS'));
    }

    /** @return list<list<string>> user code, competition filter, SQL competition filter */
    private function userFilters(): array
    {
        return $this->connection->fetchAllNumeric(
            "SELECT Code, Filtre_competition, Filtre_competition_sql FROM kp_user WHERE Code LIKE 'TRSF%' ORDER BY Code"
        );
    }
}
