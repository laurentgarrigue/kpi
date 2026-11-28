<?php

namespace App\Tests\Integration;

/**
 * Tirs et arrêts saisis à la console (PAGE_SCORING.md §0.19) : statistiques non officielles, gardées dans
 * scoring_live_stat. Elles ne doivent jamais atteindre la feuille de match (kp_match_detail, consolidée à la
 * clôture) ni la vue v_match_detail lue par les médailles, les suspensions et les désignations (T8, SHOT-13).
 *
 * Données : SQL/fixtures/ — match 9404 (en cours).
 */
final class ScoringLiveStatTest extends ApiTestCase
{
    private const ADMIN = 'TADMIN';
    private const MATCH = 9404;

    protected function setUp(): void
    {
        parent::setUp();
        $connection = $this->beginWrites();
        $connection->executeStatement('DELETE FROM scoring_live_stat WHERE id_match = ?', [self::MATCH]);
        $connection->executeStatement("UPDATE kp_match SET Validation = '' WHERE Id = ?", [self::MATCH]);
    }

    private function put(array $body, int $status = 200): array
    {
        return $this->requestAs(self::ADMIN, 'PUT', '/admin/scoring/shots/' . self::MATCH, $body, $status);
    }

    private function shots(): array
    {
        return $this->requestAs(self::ADMIN, 'GET', '/admin/scoring/shots/' . self::MATCH)['shots'];
    }

    public function testAShotAndASaveAreRecordedAndListedNewestFirst(): void
    {
        $this->put(['action' => 'add', 'uid' => 'shot1', 'kind' => 'SHOT', 'period' => 'M1', 'tpsJeu' => '06:42', 'team' => 'A', 'number' => 7, 'player' => '101']);
        $this->put(['action' => 'add', 'uid' => 'save1', 'kind' => 'SAVE', 'period' => 'M1', 'tpsJeu' => '06:10', 'team' => 'B']);

        $shots = $this->shots();

        self::assertSame(['save1', 'shot1'], array_column($shots, 'uid'));
        self::assertSame('SHOT', $shots[1]['kind']);
        self::assertSame('A', $shots[1]['team']);
        self::assertSame('06:42', $shots[1]['tpsJeu']);
        self::assertSame('7', (string) $shots[1]['number']);
    }

    public function testAStatWithoutTeamIsKeptWhenThePossessionIsUnknown(): void
    {
        $this->put(['action' => 'add', 'uid' => 'shot2', 'kind' => 'SHOT', 'period' => 'M1', 'tpsJeu' => '09:00', 'team' => '']);

        self::assertSame('', $this->shots()[0]['team']);
    }

    public function testTheNumberCanBeAddedAfterwardsAndTheStatRemoved(): void
    {
        $this->put(['action' => 'add', 'uid' => 'save2', 'kind' => 'SAVE', 'period' => 'M2', 'tpsJeu' => '03:00', 'team' => 'B']);
        $this->put(['action' => 'update', 'uid' => 'save2', 'kind' => 'SAVE', 'period' => 'M2', 'tpsJeu' => '03:00', 'team' => 'B', 'number' => 1, 'player' => '202']);
        self::assertSame('202', $this->shots()[0]['player']);

        $this->put(['action' => 'remove', 'uid' => 'save2']);
        self::assertSame([], $this->shots());
    }

    public function testAnUnknownKindOrTeamIsRefused(): void
    {
        $this->put(['action' => 'add', 'uid' => 'x1', 'kind' => 'PASS', 'team' => 'A'], 400);
        $this->put(['action' => 'add', 'uid' => 'x2', 'kind' => 'SHOT', 'team' => 'C'], 400);
    }

    public function testShotsNeverReachTheGameSheet(): void
    {
        $this->put(['action' => 'add', 'uid' => 'shot3', 'kind' => 'SHOT', 'period' => 'M1', 'tpsJeu' => '01:00', 'team' => 'A']);
        $this->requestAs(self::ADMIN, 'PUT', '/admin/scoring/gameParam/' . self::MATCH, ['param' => 'Statut', 'value' => 'END']);

        // v_match_detail reads scoring_live_event and kp_match_detail only (it is not in the test schema).
        self::assertSame(0, (int) $this->beginWrites()->fetchOne("SELECT COUNT(*) FROM kp_match_detail WHERE Id = 'shot3'"));
    }

    public function testEachWriteIsJournalized(): void
    {
        $this->put(['action' => 'add', 'uid' => 'shot4', 'kind' => 'SHOT', 'period' => 'M1', 'tpsJeu' => '06:42', 'team' => 'A', 'number' => 7]);

        $line = $this->beginWrites()->fetchOne("SELECT Journal FROM kp_journal WHERE Actions = 'Scoring tir' ORDER BY Id DESC LIMIT 1");

        self::assertIsString($line);
        self::assertStringContainsString('add SHOT A #7 M1 06:42', $line);
    }

    public function testALockedGameRefusesShots(): void
    {
        $this->beginWrites()->executeStatement("UPDATE kp_match SET Validation = 'O' WHERE Id = ?", [self::MATCH]);

        $this->put(['action' => 'add', 'uid' => 'shot5', 'kind' => 'SHOT', 'team' => 'A'], 400);
    }
}
