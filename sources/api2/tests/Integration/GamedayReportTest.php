<?php

namespace App\Tests\Integration;

use Doctrine\DBAL\Connection;

/**
 * Bilan de journée (FEATURE_DESIGNATIONS.md BIL-01 à BIL-05, BIL-07 ; ticket designations/10). Fixtures
 * « désignations » : événement 9801 (journées 9801 et 9802 de NDES, délégué CNA 9811, compte « 9811 » de profil 5),
 * journée 9803 hors événement (REGDES), match 9801 (journée 9801, arbitre 9813), comptes 9814 (profil 7) et
 * 9815 (profil 2).
 */
final class GamedayReportTest extends ApiTestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->beginWrites();
    }

    public function testBil01OneReportPerEventAndAGamedayOfAnEventHasNoReportOfItsOwn(): void
    {
        $this->create('9811', 'EVENEMENT', '9801');

        self::assertSame('already_exists', $this->create('9811', 'EVENEMENT', '9801', 409)['error']);
        self::assertSame('gameday_in_event', $this->create('TADMIN', 'JOURNEE', '9801', 422)['error']);
        $this->create('TADMIN', 'JOURNEE', '9803');
    }

    public function testBil02OnlyTheEffectiveDelegateOrAProfileOneOrTwoWritesIt(): void
    {
        $this->designate(['role' => 'R1', 'scopeType' => 'EVENEMENT', 'scopeId' => '9801', 'matric' => 9814]);

        $this->create('9814', 'EVENEMENT', '9801', 403);
        $this->requestAs('9814', 'POST', '/admin/reports', ['type' => 'INCIDENT', 'scopeType' => 'EVENEMENT', 'scopeId' => '9801', 'season' => '2998'], 201);
        $this->create('9811', 'EVENEMENT', '9801');
    }

    public function testBil03TheReportIsPrefilledWithTheKpiData(): void
    {
        $this->connection->executeStatement(
            "INSERT INTO kp_match_detail (Id_match, Periode, Id_evt_match, Equipe_A_B) VALUES (9801, 'M1', 'V', 'A'), (9801, 'M2', 'J', 'B')"
        );

        $report = $this->create('9811', 'EVENEMENT', '9801');

        self::assertContains(['role' => 'Représentant CNA', 'name' => 'DESIGNE Délégué', 'matric' => 9811], $report['answers']['officials']);
        self::assertSame([['name' => 'DESIGNE Sansclub', 'matric' => 9813, 'games' => 1]], $report['answers']['referees']);
        self::assertSame([1, 0], [$report['answers']['matches_planned'], $report['answers']['matches_played']]);
        $kpi = $this->requestAs('9811', 'GET', '/admin/reports/prefill?scopeType=EVENEMENT&scopeId=9801&season=2998');
        self::assertSame(['green' => 1, 'yellow' => 1, 'red' => 0, 'black' => 0], $kpi['cards']['NDES']);
    }

    public function testBil04ASubmissionMoreThanThreeDaysAfterTheEndIsAcceptedAndMarkedLate(): void
    {
        $this->connection->executeStatement("UPDATE kp_journee SET Date_debut = '2020-03-07', Date_fin = '2020-03-08' WHERE Id IN (9801, 9802)");
        $id = $this->create('9811', 'EVENEMENT', '9801')['id'];

        $submitted = $this->requestAs('9811', 'POST', "/admin/reports/$id/submit");

        self::assertSame(['SOUMIS', true], [$submitted['state'], $submitted['late']]);
    }

    public function testBil05TheBureauTheCompetitionManagersAndTheWriterReceiveIt(): void
    {
        $this->designate(['role' => 'BUREAU_CNA', 'scopeType' => 'NIVEAU', 'scopeId' => 'NATIONAL', 'matric' => 9815]);
        $this->designate(['role' => 'RC', 'scopeType' => 'COMPETITION', 'scopeId' => 'NDES', 'matric' => 9814]);
        $id = $this->create('9811', 'EVENEMENT', '9801')['id'];

        $this->requestAs('9811', 'POST', "/admin/reports/$id/submit");

        $recipients = json_decode((string) $this->connection->fetchOne('SELECT destinataires FROM kp_report_submission WHERE report_id = ?', [$id]), true);
        self::assertEqualsCanonicalizing([9815, 9814, 9811], $recipients);
        $this->requestAs('9814', 'GET', "/admin/reports/$id"); // un destinataire lit le bilan soumis
    }

    public function testBil07TheReportsOfThePerimeterAreLinked(): void
    {
        $incident = $this->requestAs('9811', 'POST', '/admin/reports', ['type' => 'INCIDENT', 'scopeType' => 'MATCH', 'scopeId' => '9801', 'season' => '2998'], 201);
        $bilan = $this->create('9811', 'EVENEMENT', '9801');

        $kpi = $this->requestAs('9811', 'GET', '/admin/reports/prefill?scopeType=EVENEMENT&scopeId=9801&season=2998');

        self::assertContains($incident['id'], array_column($kpi['linked'], 'id'));
        self::assertContains($bilan['id'], array_column($kpi['linked'], 'id'));
    }

    public function testOfficialsRowsAreValidated(): void
    {
        $id = $this->create('9811', 'EVENEMENT', '9801')['id'];

        $error = $this->requestAs('9811', 'PATCH', "/admin/reports/$id", ['answers' => ['officials' => [['role' => 'R1', 'presence' => 'peut-être']]]], 422);
        self::assertSame('invalid', $error['fields']['officials']);
        $saved = $this->requestAs('9811', 'PATCH', "/admin/reports/$id", ['answers' => ['officials' => [['role' => 'Représentant CNA', 'matric' => 9811, 'presence' => 'present']], 'site_water' => 3, 'site_pitches' => 4]]);
        self::assertSame(7, $saved['answers']['site_total']);
    }

    /** @return array<mixed> */
    private function create(string $user, string $scopeType, string $scopeId, int $expectedStatus = 201): array
    {
        return $this->requestAs($user, 'POST', '/admin/reports', ['type' => 'BILAN_JOURNEE', 'scopeType' => $scopeType, 'scopeId' => $scopeId, 'season' => '2998'], $expectedStatus);
    }

    /** @param array<string, mixed> $body */
    private function designate(array $body): void
    {
        $this->requestAs('TADMIN', 'POST', '/admin/designations', $body + ['season' => '2998'], 201);
    }
}
