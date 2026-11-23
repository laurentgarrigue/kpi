<?php

namespace App\Tests\Integration;

use Doctrine\DBAL\Connection;

/**
 * Relevé des faits (FEATURE_DESIGNATIONS.md REL-01 à REL-05, BIL-06 ; ticket designations/12). Fixtures
 * « désignations » : NDES 2998 (journées 9801 et 9802 de l'événement 9801, délégué CNA 9811 du club C001),
 * match 9801 de la journée 9801 (équipes 9801 du club C001 et 9802 du club C002).
 */
final class DesignationFactsTest extends ApiTestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->beginWrites();
    }

    public function testRel01AndRel04QuantitiesOnlyWithTheirSources(): void
    {
        $this->connection->executeStatement("UPDATE kp_journee SET Code_organisateur = 'C002' WHERE Id IN (9801, 9802)");
        $this->connection->executeStatement("UPDATE kp_match SET Terrain = '1' WHERE Id = 9801");

        $items = $this->facts();

        $hosted = $this->row($items, 'C002', 'gameday_hosted', 'NDES');
        self::assertSame(2, $hosted['quantity']);
        self::assertSame(['JOURNEE', 'JOURNEE'], array_column($hosted['details'], 'sourceType'));
        self::assertSame(1, $this->row($items, 'C002', 'gameday_pitches', 'NDES')['quantity']);
        foreach ($items as $item) {
            self::assertSame(['club', 'clubLabel', 'fact', 'qualifier', 'quantity', 'details'], array_keys($item));
        }
    }

    public function testRel03ADelegateOnTwoGamedaysCountsTwiceForTheFrozenClub(): void
    {
        $this->connection->executeStatement("UPDATE kp_licence SET Numero_club = 'C009' WHERE Matric = 9811");

        self::assertSame(2, $this->row($this->facts(), 'C001', 'official_provided', 'DELEGUE_CNA')['quantity']);
    }

    public function testRel02ANonPlayingRefereeCountsForHisClubOnlyWhenTheOrganiserDesignatedHim(): void
    {
        $this->designate(['role' => 'ARBITRE_NJ', 'scopeType' => 'JOURNEE', 'scopeId' => '9801', 'matric' => 9812, 'authority' => 'ORGANISATEUR']);
        $this->designate(['role' => 'ARBITRE_NJ', 'scopeType' => 'JOURNEE', 'scopeId' => '9801', 'matric' => 9814]);

        $items = $this->facts();

        self::assertSame(1, $this->row($items, 'C002', 'official_provided', 'ARBITRE_NJ')['quantity']);
        self::assertNull($this->row($items, 'C003', 'official_provided', 'ARBITRE_NJ'));
    }

    public function testBil06AnOfficialAbsentInTheSubmittedReportIsNotCounted(): void
    {
        $bilan = $this->requestAs('9811', 'POST', '/admin/reports', ['type' => 'BILAN_JOURNEE', 'scopeType' => 'EVENEMENT', 'scopeId' => '9801', 'season' => '2998'], 201);
        $officials = array_map(static fn (array $row): array => $row + ['presence' => $row['matric'] === 9811 ? 'absent' : 'present'], $bilan['answers']['officials']);
        $this->requestAs('9811', 'PATCH', "/admin/reports/{$bilan['id']}", ['answers' => ['officials' => $officials, 'podium' => true]]);
        $this->requestAs('9811', 'POST', "/admin/reports/{$bilan['id']}/submit");

        self::assertNull($this->row($this->facts(), 'C001', 'official_provided', 'DELEGUE_CNA'));
    }

    public function testCardsOfValidatedGamesGoToTheClubOfThePlayersTeam(): void
    {
        $this->connection->executeStatement(
            "INSERT INTO kp_match_detail (Id_match, Periode, Id_evt_match, Equipe_A_B) VALUES (9801, 'M1', 'J', 'A'), (9801, 'M2', 'R', 'B'), (9801, 'M2', 'J', 'B')"
        );
        self::assertNull($this->row($this->facts(), 'C001', 'card', 'yellow')); // match non validé

        $this->connection->executeStatement("UPDATE kp_match SET Validation = 'O' WHERE Id = 9801");
        $items = $this->facts();

        self::assertSame([1, 1, 1], [$this->row($items, 'C001', 'card', 'yellow')['quantity'], $this->row($items, 'C002', 'card', 'yellow')['quantity'], $this->row($items, 'C002', 'card', 'red')['quantity']]);
    }

    public function testRel05TheStatementIsExportedInCsvAndExcelForProfilesOneAndTwo(): void
    {
        $this->client->request('GET', '/admin/designations/facts?season=2998&format=csv', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->tokenFor('TADMIN')]);
        $response = $this->client->getResponse();
        self::assertStringStartsWith('text/csv', (string) $response->headers->get('Content-Type'));
        // le client de test a déjà déroulé la réponse en flux : son contenu est dans la réponse interne
        self::assertStringContainsString('Club;"Libellé du club";Fait;Précision;Quantité;Sources', $this->client->getInternalResponse()->getContent());

        $this->client->request('GET', '/admin/designations/facts?season=2998&format=xlsx', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->tokenFor('TADMIN')]);
        self::assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $this->client->getResponse()->headers->get('Content-Type'));
        $this->requestAs('TDIV', 'GET', '/admin/designations/facts?season=2998', expectedStatus: 403);
    }

    /** @return list<array<string, mixed>> */
    private function facts(): array
    {
        return $this->requestAs('TADMIN', 'GET', '/admin/designations/facts?season=2998')['items'];
    }

    /**
     * @param list<array<string, mixed>> $items
     *
     * @return array<string, mixed>|null
     */
    private function row(array $items, ?string $club, string $fact, string $qualifier): ?array
    {
        foreach ($items as $item) {
            if ($item['club'] === $club && $item['fact'] === $fact && $item['qualifier'] === $qualifier) {
                return $item;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $body */
    private function designate(array $body): void
    {
        $this->requestAs('TADMIN', 'POST', '/admin/designations', $body + ['season' => '2998'], 201);
    }
}
