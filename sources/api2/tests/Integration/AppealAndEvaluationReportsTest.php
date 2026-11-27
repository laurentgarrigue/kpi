<?php

namespace App\Tests\Integration;

use Doctrine\DBAL\Connection;

/**
 * Rapport de jury d'appel et fiche d'évaluation d'arbitrage (FEATURE_DESIGNATIONS.md RAP-08, RAP-09, D27, D28 ;
 * ticket designations/11). Fixtures « désignations » : événement 9801 (journées 9801 et 9802, délégué CNA 9811),
 * match 9801 n° 1 (équipes 9801 et 9802, arbitre principal 9813) ; comptes 9811 (profil 5), 9814 (profil 7),
 * 9815 (profil 2), TDIV (profil 3).
 */
final class AppealAndEvaluationReportsTest extends ApiTestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->beginWrites();
    }

    public function testRap09TheJuryIsPrefilledAndChangedForTheCaseInTheReportOnly(): void
    {
        $this->designate(['role' => 'REP_EQUIPES', 'scopeType' => 'EVENEMENT', 'scopeId' => '9801', 'matric' => 9812]);
        $this->designate(['role' => 'JURY_APPEL', 'scopeType' => 'JOURNEE', 'scopeId' => '9801', 'matric' => 9814, 'quality' => 'Représentant FFCK']);

        $report = $this->create('9811', 'JURY_APPEL', 'EVENEMENT', '9801');

        self::assertSame([9811, 9812, 9814], array_column($report['answers']['jury'], 'matric'));
        self::assertSame('Président (délégué CNA)', $report['answers']['jury'][0]['role']);
        $designations = (int) $this->connection->fetchOne("SELECT COUNT(*) FROM kp_designation WHERE saison = '2998'");
        $jury = $report['answers']['jury'];
        $jury[2] += ['presence' => 'recused', 'replaced_by' => 'DUPONT Jean'];
        $this->requestAs('9811', 'PATCH', "/admin/reports/{$report['id']}", ['answers' => ['jury' => $jury]]);
        self::assertSame($designations, (int) $this->connection->fetchOne("SELECT COUNT(*) FROM kp_designation WHERE saison = '2998'"));
    }

    public function testTheJuryReportGoesToTheSittingMembersAndTheManagersOfTheTeamsInCause(): void
    {
        $this->designate(['role' => 'REP_EQUIPES', 'scopeType' => 'EVENEMENT', 'scopeId' => '9801', 'matric' => 9812]);
        $this->designate(['role' => 'JURY_APPEL', 'scopeType' => 'JOURNEE', 'scopeId' => '9801', 'matric' => 9814]);
        $this->designate(['role' => 'RESP_EQUIPE', 'scopeType' => 'EQUIPE', 'scopeId' => '9802', 'matric' => 9815]);
        $report = $this->create('9811', 'JURY_APPEL', 'EVENEMENT', '9801');
        $jury = $report['answers']['jury'];
        $jury[2] += ['presence' => 'recused'];

        $this->requestAs('9811', 'PATCH', "/admin/reports/{$report['id']}", ['answers' => [
            'jury' => $jury, 'reason' => 'Carton jaune pour contestation', 'facts' => 'Propos irrespectueux.', 'decision' => 'Rappel à l\'ordre.',
            'matches' => [['number' => '1', 'teams' => 'Désignés Un - Désignés Deux']], 'posted_date' => '2998-03-07', 'posted_time' => '17:25',
        ]]);
        $this->requestAs('9811', 'POST', "/admin/reports/{$report['id']}/submit");

        $recipients = json_decode((string) $this->connection->fetchOne('SELECT destinataires FROM kp_report_submission WHERE report_id = ?', [$report['id']]), true);
        self::assertEqualsCanonicalizing([9811, 9812, 9815], $recipients); // 9814 récusé
        $this->create('9814', 'JURY_APPEL', 'JOURNEE', '9803', 403);
    }

    public function testRap08TheEvaluationIsReservedAndItsMarkComputed(): void
    {
        $this->designate(['role' => 'JUGE_ARBITRE', 'scopeType' => 'EVENEMENT', 'scopeId' => '9801', 'matric' => 9814]);
        $report = $this->create('TADMIN', 'EVALUATION_ARBITRAGE', 'MATCH', '9801');
        self::assertSame('DESIGNE Sansclub (9813)', $report['answers']['evaluated']);

        $marks = ['posture' => 3, 'movement' => 4, 'style' => 2, 'verbal' => 1, 'attitude' => 2, 'management' => 3, 'knowledge' => 4];
        $saved = $this->requestAs('TADMIN', 'PATCH', "/admin/reports/{$report['id']}", ['answers' => $marks + [
            'evaluated' => 'DESIGNE Sansclub (9813)', 'outfit' => true, 'comment' => 'Bon placement.', 'total' => 50,
        ]]);
        self::assertSame([10, 5, 4, 19], [$saved['answers']['form_total'], $saved['answers']['substance_total'], $saved['answers']['laws_total'], $saved['answers']['total']]);
        $this->requestAs('TADMIN', 'POST', "/admin/reports/{$report['id']}/submit");

        $this->requestAs('9814', 'GET', "/admin/reports/{$report['id']}"); // juge arbitre du périmètre
        $this->requestAs('9811', 'GET', "/admin/reports/{$report['id']}", expectedStatus: 404); // délégué : pas destinataire
        $this->requestAs('TDIV', 'GET', "/admin/reports/{$report['id']}", expectedStatus: 404);
    }

    /** @return array<mixed> */
    private function create(string $user, string $type, string $scopeType, string $scopeId, int $expectedStatus = 201): array
    {
        return $this->requestAs($user, 'POST', '/admin/reports', ['type' => $type, 'scopeType' => $scopeType, 'scopeId' => $scopeId, 'season' => '2998'], $expectedStatus);
    }

    /** @param array<string, mixed> $body */
    private function designate(array $body): void
    {
        $this->requestAs('TADMIN', 'POST', '/admin/designations', $body + ['season' => '2998'], 201);
    }
}
