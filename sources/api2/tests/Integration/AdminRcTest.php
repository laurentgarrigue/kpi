<?php

namespace App\Tests\Integration;

use Doctrine\DBAL\Connection;

/**
 * Page RC d'app4 après l'absorption de kp_rc dans kp_designation (FEATURE_DESIGNATIONS.md RC-02, RC-04 ; ticket
 * designations/05). Fixtures : RC 9821 (TSTRN 2999) et 9822 (TSTRN 2998), licencié 9511 (C001) ; NDES 2998
 * (journées 9801, 9802), licenciés 9812 (C002) et 9813 (sans club).
 */
final class AdminRcTest extends ApiTestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->beginWrites();
    }

    public function testRc04TheListKeepsItsContract(): void
    {
        $list = $this->requestAs('TADMIN', 'GET', '/admin/rc?season=2999&competitions=TSTRN');

        self::assertSame(1, $list['total']);
        self::assertSame(
            ['id' => 9821, 'competitionCode' => 'TSTRN', 'season' => '2999', 'ordre' => 1, 'matric' => 9511, 'club' => 'C001'],
            array_intersect_key($list['items'][0], array_flip(['id', 'competitionCode', 'season', 'ordre', 'matric', 'club'])),
        );
    }

    public function testRc04ACompetitionRcIsADesignationCopiedToResponsableInsc(): void
    {
        $created = $this->requestAs('TADMIN', 'POST', '/admin/rc', ['season' => '2998', 'competitionCode' => 'NDES', 'matric' => 9812, 'ordre' => 1], 201);

        self::assertSame(['RC', 'COMPETITION', 'NDES', 'C002'], array_values($this->connection->fetchAssociative(
            'SELECT role, perimetre_type, perimetre_id, club FROM kp_designation WHERE id = ?',
            [$created['id']],
        )));
        self::assertSame('DESIGNE Arbitre (9812)', $this->connection->fetchOne('SELECT Responsable_insc FROM kp_journee WHERE Id = 9801'));
    }

    public function testRc04ANationalRcWithoutCompetitionIsDesignatedOnTheNationalLevel(): void
    {
        $created = $this->requestAs('TADMIN', 'POST', '/admin/rc', ['season' => '2998', 'competitionCode' => null, 'matric' => 9813, 'ordre' => 2], 201);

        $list = $this->requestAs('TADMIN', 'GET', '/admin/rc?season=2998');
        $national = array_values(array_filter($list['items'], static fn (array $rc): bool => $rc['id'] === $created['id']))[0];
        self::assertNull($national['competitionCode']);
        self::assertSame('National (sans compétition)', $national['competitionLabel']);
        self::assertSame(['NIVEAU', 'NATIONAL'], array_values($this->connection->fetchAssociative(
            'SELECT perimetre_type, perimetre_id FROM kp_designation WHERE id = ?',
            [$created['id']],
        )));
    }

    public function testRc02TheLegacyViewShowsTheRcDesignations(): void
    {
        $this->requestAs('TADMIN', 'POST', '/admin/rc', ['season' => '2998', 'competitionCode' => null, 'matric' => 9813, 'ordre' => 2], 201);

        self::assertSame(
            [['Code_competition' => 'TSTRN', 'Matric' => 9511], ['Code_competition' => null, 'Matric' => 9813]],
            array_map(
                static fn (array $row): array => ['Code_competition' => $row['Code_competition'], 'Matric' => (int) $row['Matric']],
                $this->connection->fetchAllAssociative("SELECT Code_competition, Matric FROM kp_rc WHERE Code_saison = '2998' ORDER BY Id"),
            ),
        );
        // jointure du legacy sur kp_competition (utf8mb3)
        self::assertSame('Régionale à renommer', $this->connection->fetchOne(
            "SELECT c.Libelle FROM kp_rc rc JOIN kp_competition c ON c.Code = rc.Code_competition AND c.Code_saison = rc.Code_saison WHERE rc.Id = 9822"
        ));
    }

    public function testRc04ErrorsKeepTheirStatus(): void
    {
        $this->requestAs('TADMIN', 'POST', '/admin/rc', ['season' => '2999', 'competitionCode' => 'TSTRN', 'matric' => 9511, 'ordre' => 2], 409);
        $this->requestAs('TADMIN', 'POST', '/admin/rc', ['season' => '2999', 'competitionCode' => 'TSTRN', 'matric' => 1, 'ordre' => 2], 404);
        $this->requestAs('TADMIN', 'POST', '/admin/rc', ['season' => '2999', 'competitionCode' => 'TSTRN', 'matric' => 9512, 'ordre' => 0], 400);
        $this->requestAs('TDIV', 'POST', '/admin/rc', ['season' => '2999', 'competitionCode' => 'TSTRN', 'matric' => 9512, 'ordre' => 2], 403);
    }

    public function testRc04ChangingTheOrderKeepsTheRcAndChangingThePersonReplacesIt(): void
    {
        $this->requestAs('TADMIN', 'PUT', '/admin/rc/9821', ['competitionCode' => 'TSTRN', 'matric' => 9511, 'ordre' => 3]);
        self::assertSame(3, (int) $this->connection->fetchOne('SELECT ordre FROM kp_designation WHERE id = 9821'));

        $replaced = $this->requestAs('TADMIN', 'PUT', '/admin/rc/9821', ['competitionCode' => 'TSTRN', 'matric' => 9512, 'ordre' => 3]);

        self::assertNotSame(9821, $replaced['id']);
        self::assertFalse($this->connection->fetchOne('SELECT 1 FROM kp_designation WHERE id = 9821'));
        self::assertSame(9512, (int) $this->connection->fetchOne('SELECT matric FROM kp_designation WHERE id = ?', [$replaced['id']]));
        $this->requestAs('TADMIN', 'PUT', '/admin/rc/9801', ['matric' => 9512, 'ordre' => 1], 404); // 9801 = délégué, pas un RC
    }

    public function testRc04BulkDeleteOnlyDeletesRc(): void
    {
        $result = $this->requestAs('TADMIN', 'POST', '/admin/rc/bulk-delete', ['ids' => [9821, 9801]]);

        self::assertSame(1, $result['deleted']);
        self::assertTrue((bool) $this->connection->fetchOne('SELECT 1 FROM kp_designation WHERE id = 9801'));
    }

    public function testRc04TheCompetitionListStillFlagsCompetitionsWithAnRc(): void
    {
        $list = $this->requestAs('TADMIN', 'GET', '/admin/competitions?season=2999&limit=500');

        $tstrn = array_values(array_filter($list['items'], static fn (array $c): bool => $c['code'] === 'TSTRN'))[0];
        self::assertTrue($tstrn['hasRc']);
    }

    public function testRc04TheSeasonCopyCopiesTheRcDesignations(): void
    {
        $result = $this->requestAs('TADMIN', 'POST', '/admin/operations/seasons/copy-rc', ['sourceCode' => '2999', 'targetCode' => '2997']);

        self::assertSame(['copied' => 1, 'skipped' => 0], array_intersect_key($result, array_flip(['copied', 'skipped'])));
        self::assertSame('TSTRN', $this->connection->fetchOne("SELECT perimetre_id FROM kp_designation WHERE role = 'RC' AND saison = '2997'"));
        $again = $this->requestAs('TADMIN', 'POST', '/admin/operations/seasons/copy-rc', ['sourceCode' => '2999', 'targetCode' => '2997']);
        self::assertSame(['copied' => 0, 'skipped' => 1], array_intersect_key($again, array_flip(['copied', 'skipped'])));
    }
}
