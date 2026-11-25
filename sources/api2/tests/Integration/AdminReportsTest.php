<?php

namespace App\Tests\Integration;

use App\Tests\Support\FakeGotenbergClient;
use Doctrine\DBAL\Connection;

/**
 * Moteur des rapports officiels (FEATURE_DESIGNATIONS.md RAP-01 à RAP-07, RAP-10 ; ticket designations/09), avec le
 * type INCIDENT. Fixtures « désignations » : saison 2998, journée 9801 (NDES, événement 9801 dont 9811 est délégué
 * CNA, compte « 9811 » de profil 5), match 9801 arbitré par 9813 (sans compte) ; 9812 sans rôle ; 9814 compte de
 * profil 7 sans Désignation ; TCLUB profil 7.
 */
final class AdminReportsTest extends ApiTestCase
{
    private const COMPLETE = ['date' => '2998-03-07', 'description' => 'Propos irrespectueux envers l\'arbitre.'];

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->beginWrites();
    }

    public function testRap01TheFormsComeFromTheCatalogue(): void
    {
        $types = $this->requestAs('9811', 'GET', '/admin/reports/types');

        $incident = $types['INCIDENT']['versions'][(string) $types['INCIDENT']['current']] ?? $types['INCIDENT']['versions'][$types['INCIDENT']['current']];
        self::assertSame(['context', 'facts', 'follow_up'], array_column($incident['sections'], 'key'));
        self::assertSame(['COMPETITION', 'EVENEMENT', 'JOURNEE', 'MATCH'], $incident['scopes']);
    }

    public function testRap03OnlyAnOfficialOfThePerimeterOrAProfileOneOrTwoWrites(): void
    {
        $this->create('TCLUB', 'JOURNEE', '9801', expectedStatus: 403);
        $this->create('9814', 'JOURNEE', '9801', expectedStatus: 403);
        $this->create('9811', 'JOURNEE', '9801'); // délégué CNA hérité de l'événement
        $this->create('9811', 'EVENEMENT', '9801');
        self::assertNull($this->create('TADMIN', 'COMPETITION', 'NDES')['authorMatric']);
        $this->create('9811', 'JOURNEE', '9803', expectedStatus: 403); // régionale, hors de son périmètre
    }

    public function testRap04AnOfficialEntersTheReportOfADesignatedReferee(): void
    {
        $report = $this->create('9811', 'MATCH', '9801', ['authorMatric' => 9813]);

        self::assertSame([9813, '9811'], [$report['authorMatric'], $report['enteredBy']]);
        $error = $this->create('9811', 'MATCH', '9801', ['authorMatric' => 9812], 422);
        self::assertSame('author_not_official', $error['error']);
    }

    public function testRap05ADraftIsReadByItsWriterAndProfilesOneAndTwoOnly(): void
    {
        $id = $this->create('9811', 'JOURNEE', '9801')['id'];

        $this->requestAs('9811', 'GET', "/admin/reports/$id");
        $this->requestAs('TADMIN', 'GET', "/admin/reports/$id");
        $this->requestAs('9814', 'GET', "/admin/reports/$id", expectedStatus: 404);
        self::assertSame([], $this->requestAs('9814', 'GET', '/admin/reports?season=2998')['items']);
    }

    public function testRap06ASubmissionNeedsTheRequiredFieldsThenIsFrozenAndSent(): void
    {
        $id = $this->create('9811', 'JOURNEE', '9801', ['answers' => ['place' => 'Bassin 2']])['id'];

        $error = $this->requestAs('9811', 'POST', "/admin/reports/$id/submit", expectedStatus: 422);
        self::assertSame(['date' => 'required', 'description' => 'required'], $error['fields']);

        $this->requestAs('9811', 'PATCH', "/admin/reports/$id", ['answers' => ['place' => 'Bassin 2'] + self::COMPLETE]);
        $submitted = $this->requestAs('9811', 'POST', "/admin/reports/$id/submit");

        self::assertSame(['SOUMIS', false, false], [$submitted['state'], $submitted['canEdit'], $submitted['late']]);
        self::assertStringContainsString('Propos irrespectueux', (string) $this->gotenberg()->html);
        self::assertSame([9811], json_decode((string) $this->connection->fetchOne('SELECT destinataires FROM kp_report_submission WHERE report_id = ?', [$id]), true));
        $this->requestAs('9811', 'PATCH', "/admin/reports/$id", ['answers' => self::COMPLETE], 409);
    }

    public function testRap07OnlyProfilesOneAndTwoReopenWithAReasonAndEarlierPdfsStay(): void
    {
        $id = $this->create('9811', 'JOURNEE', '9801', ['answers' => self::COMPLETE])['id'];
        $this->requestAs('9811', 'POST', "/admin/reports/$id/submit");

        $this->requestAs('9811', 'POST', "/admin/reports/$id/reopen", ['reason' => 'Correction'], 403);
        $this->requestAs('TADMIN', 'POST', "/admin/reports/$id/reopen", ['reason' => ' '], 422);
        $reopened = $this->requestAs('TADMIN', 'POST', "/admin/reports/$id/reopen", ['reason' => 'Faute de frappe']);
        self::assertSame('BROUILLON', $reopened['state']);
        self::assertSame(1, (int) $this->connection->fetchOne("SELECT COUNT(*) FROM kp_journal WHERE Actions = 'Réouverture rapport'"));

        $this->requestAs('9811', 'PATCH', "/admin/reports/$id", ['answers' => ['description' => 'Version corrigée'] + self::COMPLETE]);
        $resubmitted = $this->requestAs('9811', 'POST', "/admin/reports/$id/submit");
        self::assertSame([1, 2], array_column($resubmitted['submissions'], 'number'));

        $this->client->request('GET', "/admin/reports/$id/pdf?number=1", server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->tokenFor('9811')]);
        self::assertSame('application/pdf', $this->client->getResponse()->headers->get('Content-Type'));
        self::assertStringContainsString('Propos irrespectueux', (string) $this->gotenberg()->html);
        self::assertStringNotContainsString('Version corrigée', (string) $this->gotenberg()->html);
    }

    public function testRap10TheBlankPdfHasTheSectionsOfTheForm(): void
    {
        $this->client->request('GET', '/admin/reports/blank/INCIDENT?season=2998', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->tokenFor('9811')]);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        foreach (['Circonstances', 'Faits', 'Suites', 'Description des faits'] as $text) {
            self::assertStringContainsString($text, (string) $this->gotenberg()->html);
        }
        $this->client->request('GET', '/admin/reports/blank/INCIDENT?season=2998', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->tokenFor('TCLUB')]);
        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    public function testANewReportFindsItsEventCompetitionGamedayOrGameBySearch(): void
    {
        $items = $this->requestAs('9811', 'GET', '/admin/reports/scopes?season=2998&q=' . rawurlencode('Désig'))['items'];

        $found = array_map(static fn (array $item): string => $item['scopeType'] . ':' . $item['scopeId'], $items);
        self::assertContains('EVENEMENT:9801', $found);
        self::assertContains('COMPETITION:NDES', $found);
        self::assertContains('MATCH:9801', $found); // équipes « Désignés Un » et « Désignés Deux »
        $byNumber = $this->requestAs('9811', 'GET', '/admin/reports/scopes?season=2998&types=MATCH&q=1')['items'];
        self::assertContains('MATCH:9801', array_map(static fn (array $item): string => $item['scopeType'] . ':' . $item['scopeId'], $byNumber));
        self::assertSame(['MATCH'], array_values(array_unique(array_column($byNumber, 'scopeType'))));
        self::assertSame([], $this->requestAs('9811', 'GET', '/admin/reports/scopes?season=2998&q=D')['items']);
    }

    public function testThePrefillGivesTheKpiDataOfTheMatch(): void
    {
        $prefill = $this->requestAs('9811', 'GET', '/admin/reports/prefill?scopeType=MATCH&scopeId=9801&season=2998');

        self::assertSame(['Désignés Un', 'Désignés Deux'], $prefill['match']['teams']);
        self::assertSame(['DESIGNE Sansclub (9813)'], $prefill['match']['referees']);
    }

    /**
     * @param array<string, mixed> $extra
     *
     * @return array<mixed>
     */
    private function create(string $user, string $scopeType, string $scopeId, array $extra = [], int $expectedStatus = 201): array
    {
        return $this->requestAs($user, 'POST', '/admin/reports', $extra + [
            'type' => 'INCIDENT', 'scopeType' => $scopeType, 'scopeId' => $scopeId, 'season' => '2998',
        ], $expectedStatus);
    }

    private function gotenberg(): FakeGotenbergClient
    {
        return static::getContainer()->get(FakeGotenbergClient::class);
    }
}
