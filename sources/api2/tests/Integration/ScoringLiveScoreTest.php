<?php

namespace App\Tests\Integration;

use App\Service\ScoringLiveService;

/**
 * Le score live suit les buts saisis.
 *
 * Pendant le match, l'état canonique est `scoring_live_state` : c'est lui que relisent la
 * console (chargement, synchro Mercure entre terminaux), les incrustations et la
 * consolidation vers `kp_match` (Statut → END). Un but n'étant enregistré que comme fait
 * (`scoring_live_event`), le score doit être recompté dans la même transaction — sinon la
 * console retombe à 0 – 0 au premier rafraîchissement alors que la chronologie contient
 * les buts.
 *
 * Données : SQL/fixtures/ — match 9404 (en cours), feuille vidée en début de test.
 */
final class ScoringLiveScoreTest extends ApiTestCase
{
    private const MATCH = 9404;

    private ScoringLiveService $live;

    protected function setUp(): void
    {
        parent::setUp();
        $connection = $this->beginWrites();
        $this->live = static::getContainer()->get(ScoringLiveService::class);

        // Start from an empty sheet: the live state seeds itself from the legacy detail
        // table once (fixture-goal-06 is a goal of 9404), which would offset every count.
        $this->live->ensureState(self::MATCH);
        $connection->executeStatement('DELETE FROM scoring_live_event WHERE id_match = ?', [self::MATCH]);
    }

    /** @param array<string, mixed> $override */
    private function goal(string $uid, string $team, array $override = []): array
    {
        return array_merge([
            'uid' => $uid,
            'code' => 'B',
            'period' => 'M1',
            'temps' => '00:05:00',
            'tpsJeu' => '05:00',
            'team' => $team,
            'player' => '9502',
            'number' => '8',
            'reason' => '',
        ], $override);
    }

    /** @return array{0:int,1:int} */
    private function score(): array
    {
        $state = $this->live->getState(self::MATCH);

        return [$state['scoreA'], $state['scoreB']];
    }

    public function testAddingAGoalIncrementsTheLiveScore(): void
    {
        $this->live->writeEvent(self::MATCH, 'add', $this->goal('tstgoal1', 'A'));
        $this->live->writeEvent(self::MATCH, 'add', $this->goal('tstgoal2', 'B'));
        $this->live->writeEvent(self::MATCH, 'add', $this->goal('tstgoal3', 'A'));

        self::assertSame([2, 1], $this->score());
    }

    public function testRemovingAGoalDecrementsTheLiveScore(): void
    {
        $this->live->writeEvent(self::MATCH, 'add', $this->goal('tstgoal1', 'A'));
        $this->live->writeEvent(self::MATCH, 'add', $this->goal('tstgoal2', 'A'));
        $this->live->writeEvent(self::MATCH, 'remove', ['uid' => 'tstgoal1']);

        self::assertSame([1, 0], $this->score());
    }

    public function testACardLeavesTheScoreUnchanged(): void
    {
        $this->live->writeEvent(self::MATCH, 'add', $this->goal('tstgoal1', 'B'));
        $this->live->writeEvent(self::MATCH, 'add', $this->goal('tstcard1', 'A', ['code' => 'J']));

        self::assertSame([0, 1], $this->score());
    }

    /** Correction post-match : un but réattribué à l'autre équipe déplace le point. */
    public function testMovingAGoalToTheOtherTeamMovesThePoint(): void
    {
        $this->live->writeEvent(self::MATCH, 'add', $this->goal('tstgoal1', 'A'));
        $this->live->writeEvent(self::MATCH, 'update', $this->goal('tstgoal1', 'B'));

        self::assertSame([0, 1], $this->score());
    }
}
