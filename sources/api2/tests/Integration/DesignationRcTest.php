<?php

namespace App\Tests\Integration;

use Doctrine\DBAL\Connection;

/**
 * RC après l'absorption de kp_rc dans kp_designation (FEATURE_DESIGNATIONS.md RC-02, RC-04 ; tickets designations/05
 * et 08 : la page RC a été retirée, les RC se désignent comme les autres rôles). Fixtures : RC 9821 (TSTRN 2999) et 9822 (TSTRN 2998), licencié 9511 (C001) ; NDES 2998
 * (journées 9801, 9802), licenciés 9812 (C002) et 9813 (sans club).
 */
final class DesignationRcTest extends ApiTestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->beginWrites();
    }

    public function testRc02TheLegacyViewShowsTheRcDesignations(): void
    {
        $this->requestAs('TADMIN', 'POST', '/admin/designations', ['season' => '2998', 'role' => 'RC', 'scopeType' => 'NIVEAU', 'scopeId' => 'NATIONAL', 'matric' => 9813, 'order' => 2], 201);

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
