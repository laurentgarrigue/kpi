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
    }

    /** Le schéma de fixtures fait échouer le dernier UPDATE (filtres de mandat) quand le code cible vaut TSTFAIL. */
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
    }
}
