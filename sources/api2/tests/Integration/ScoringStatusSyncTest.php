<?php

namespace App\Tests\Integration;

use App\Service\ScoringLiveService;

/**
 * The match status changed from the games list (PATCH /admin/games/{id}/statut) must reach
 * the live state of a match already scored at the console — otherwise the console, which
 * reads scoring_live_state first, keeps showing the old status (PAGE_SCORING.md §0.2).
 */
final class ScoringStatusSyncTest extends ApiTestCase
{
    private const ADMIN = 'TADMIN';
    private const MATCH = 9404;

    private function prepare(string $kpStatus, bool $withLiveState, string $liveStatus = 'ON'): void
    {
        $connection = $this->beginWrites();
        $connection->executeStatement('DELETE FROM scoring_live_state WHERE id_match = ?', [self::MATCH]);
        $connection->executeStatement(
            "UPDATE kp_match SET Statut = ?, Validation = '' WHERE Id = ?",
            [$kpStatus, self::MATCH]
        );
        if ($withLiveState) {
            /** @var ScoringLiveService $live */
            $live = static::getContainer()->get(ScoringLiveService::class);
            $live->ensureState(self::MATCH);
            $connection->executeStatement(
                'UPDATE scoring_live_state SET statut = ? WHERE id_match = ?',
                [$liveStatus, self::MATCH]
            );
        }
    }

    private function liveStatus(): ?string
    {
        $value = $this->beginWrites()->fetchOne('SELECT statut FROM scoring_live_state WHERE id_match = ?', [self::MATCH]);

        return $value === false ? null : $value;
    }

    public function testResettingToPendingFromTheGamesListReachesTheLiveState(): void
    {
        $this->prepare('END', true, 'END');

        $res = $this->requestAs(self::ADMIN, 'PATCH', '/admin/games/' . self::MATCH . '/statut');

        self::assertSame('ATT', $res['statut']);
        self::assertSame('ATT', $this->liveStatus());
    }

    public function testStartingFromTheGamesListReachesTheLiveState(): void
    {
        $this->prepare('ATT', true, 'ATT');

        $this->requestAs(self::ADMIN, 'PATCH', '/admin/games/' . self::MATCH . '/statut');

        self::assertSame('ON', $this->liveStatus());
    }

    public function testAMatchNeverScoredAtTheConsoleGetsNoLiveState(): void
    {
        $this->prepare('ATT', false);

        $this->requestAs(self::ADMIN, 'PATCH', '/admin/games/' . self::MATCH . '/statut');

        self::assertNull($this->liveStatus());
    }

    public function testEndingFromTheGamesListConsolidatesTheLiveFacts(): void
    {
        $this->prepare('ON', true, 'ON');
        $connection = $this->beginWrites();
        $connection->executeStatement('DELETE FROM scoring_live_event WHERE id_match = ?', [self::MATCH]);
        /** @var ScoringLiveService $live */
        $live = static::getContainer()->get(ScoringLiveService::class);
        $live->writeEvent(self::MATCH, 'add', [
            'uid' => 'syncgoal1', 'code' => 'B', 'period' => 'M1', 'temps' => '00:05:00', 'tpsJeu' => '05:00',
            'team' => 'A', 'player' => '9502', 'number' => '8', 'reason' => '',
        ]);

        $this->requestAs(self::ADMIN, 'PATCH', '/admin/games/' . self::MATCH . '/statut');

        self::assertSame('END', $this->liveStatus());
        self::assertSame(
            ['syncgoal1'],
            $connection->fetchFirstColumn('SELECT Id FROM kp_match_detail WHERE Id_match = ?', [self::MATCH])
        );
    }
}
