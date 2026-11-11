<?php

namespace App\Tests\Integration;

use App\MatchMedals\MatchMedalService;
use Doctrine\DBAL\Connection;

/**
 * Stored medals and their recalculation (FEATURE_MATCH_MEDALS.md § 6.3), on the TSTMED fixtures (season 2998).
 * Every test writes inside a rolled-back transaction.
 */
final class MatchMedalsPersistenceTest extends ApiTestCase
{
    private Connection $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->beginWrites();
    }

    public function testMed01ANotValidatedGameHasNoStoredMedal(): void
    {
        $this->medals()->computeSeason('2998');

        self::assertSame(0, (int) $this->db->fetchOne('SELECT COUNT(*) FROM kp_match_medal WHERE match_id = 9464'));
        self::assertFalse($this->db->fetchOne('SELECT match_id FROM kp_match_insight WHERE match_id = 9464'));
    }

    public function testComputingASeasonStoresMedalsCardsRefereesAndInsights(): void
    {
        $result = $this->medals()->computeSeason('2998');

        self::assertSame(4, $result['computed']);
        $insight = $this->db->fetchAssociative('SELECT * FROM kp_match_insight WHERE match_id = 9461');
        self::assertSame(1, (int) $insight['timeline_reliable']);
        self::assertSame(['thriller'], json_decode($insight['labels'], true));
        self::assertSame(1, (int) $insight['powerplay_goals_a']);
        self::assertSame(
            [['opener', 'team', 'B', 9362, null]],
            $this->medalRows("code = 'opener' AND scope = 'team'"),
        );
        self::assertSame('J', $this->db->fetchOne("SELECT code FROM kp_match_card WHERE match_id = 9461"));
        self::assertSame([9921, 9922], $this->db->fetchFirstColumn('SELECT referee_matric FROM kp_match_referee WHERE match_id = 9461 ORDER BY 1'));
    }

    public function testMed03UnrecordedTimesKeepScoreMedalsOnly(): void
    {
        $this->medals()->computeSeason('2998');

        self::assertSame(0, (int) $this->db->fetchOne('SELECT timeline_reliable FROM kp_match_insight WHERE match_id = 9463'));
        self::assertSame(['clean_sheet', 'fair_play', 'fair_play', 'hat_trick'], $this->db->fetchFirstColumn('SELECT code FROM kp_match_medal WHERE match_id = 9463 ORDER BY code'));
    }

    public function testMed27ACoachGoalGivesTheTeamMedalOnly(): void
    {
        $this->medals()->computeSeason('2998');

        self::assertSame(0, (int) $this->db->fetchOne("SELECT COUNT(*) FROM kp_match_medal WHERE match_id = 9465 AND scope = 'player'"));
        self::assertSame(1, (int) $this->db->fetchOne("SELECT COUNT(*) FROM kp_match_medal WHERE match_id = 9465 AND scope = 'team' AND code = 'opener'"));
    }

    public function testMed50GamesWithoutDesignatedRefereesHaveNoRefereeRow(): void
    {
        $this->medals()->computeSeason('2998');

        self::assertSame(0, (int) $this->db->fetchOne('SELECT COUNT(*) FROM kp_match_referee WHERE match_id = 9463'));
        self::assertSame(0, (int) $this->db->fetchOne("SELECT COUNT(*) FROM kp_match_medal WHERE match_id = 9463 AND scope = 'referee'"));
        self::assertSame([9921], $this->db->fetchFirstColumn('SELECT referee_matric FROM kp_match_referee WHERE match_id = 9462'));
    }

    public function testMed33SyncComputesNewGamesThenRewritesNothingAndRemovesUnvalidatedGames(): void
    {
        $first = $this->medals()->sync();
        self::assertGreaterThanOrEqual(4, $first['computed'], 'every validated game of the fixtures, TSTMED included');
        self::assertSame(['computed' => 0, 'removed' => 0], $this->medals()->sync(), 'unchanged sources: nothing rewritten');

        $this->db->executeStatement("UPDATE kp_match SET Validation = 'N' WHERE Id = 9465");
        self::assertSame(['computed' => 0, 'removed' => 1], $this->medals()->sync());
        self::assertSame(0, (int) $this->db->fetchOne('SELECT COUNT(*) FROM kp_match_medal WHERE match_id = 9465'));
    }

    public function testMed57AChangedDesignationIsPickedUpBySync(): void
    {
        $this->medals()->sync();
        $this->db->executeStatement('UPDATE kp_match SET Matric_arbitre_secondaire = 9922 WHERE Id = 9462');

        self::assertSame(['computed' => 1, 'removed' => 0], $this->medals()->sync());
        self::assertSame([9921, 9922], $this->db->fetchFirstColumn('SELECT referee_matric FROM kp_match_referee WHERE match_id = 9462 ORDER BY 1'));
    }

    public function testMed32ASeasonNeverComputedFirstGetsACopyOfTheCurrentThresholds(): void
    {
        $this->medals()->refreshGame(9461);

        $copy = json_decode((string) $this->db->fetchOne("SELECT thresholds FROM kp_medal_season WHERE season = '2998'"), true);
        self::assertSame(30, $copy['bounceBackSeconds']);
    }

    public function testMed31AGameIsComputedWithTheThresholdsOfItsSeason(): void
    {
        $this->medals()->computeSeason('2998');
        // the season copy says Thriller needs 4 changes of leader: the game (3 changes) loses its label
        $copy = json_decode((string) $this->db->fetchOne("SELECT thresholds FROM kp_medal_season WHERE season = '2998'"), true);
        $this->db->update('kp_medal_season', ['thresholds' => json_encode(['thrillerLeadChanges' => 4] + $copy)], ['season' => '2998']);

        $this->medals()->refreshGame(9461);

        self::assertSame([], json_decode((string) $this->db->fetchOne('SELECT labels FROM kp_match_insight WHERE match_id = 9461'), true));
    }

    public function testMed30ComputingASeasonReplacesItsThresholdCopyOnly(): void
    {
        $this->db->insert('kp_medal_season', ['season' => '2999', 'thresholds' => '{"bounceBackSeconds":45}', 'rules_version' => 0, 'computed_at' => '2999-01-01 00:00:00']);

        $this->medals()->computeSeason('2998');

        self::assertSame('{"bounceBackSeconds":45}', $this->db->fetchOne("SELECT thresholds FROM kp_medal_season WHERE season = '2999'"));
        self::assertSame(4, (int) $this->db->fetchOne("SELECT games_count FROM kp_medal_season WHERE season = '2998'"));
        self::assertSame(3, (int) $this->db->fetchOne("SELECT reliable_games_count FROM kp_medal_season WHERE season = '2998'"));
    }

    public function testMed37RetiredElementsAreNotComputed(): void
    {
        $this->db->update('kp_medal_catalog', ['status' => 'retired'], ['code' => 'opener']);

        $this->medals()->computeSeason('2998');

        self::assertSame(0, (int) $this->db->fetchOne("SELECT COUNT(*) FROM kp_match_medal WHERE code = 'opener'"));
    }

    private function medals(): MatchMedalService
    {
        return static::getContainer()->get(MatchMedalService::class);
    }

    /** @return list<list<mixed>> [code, scope, side, team id, person] of match 9461 */
    private function medalRows(string $where): array
    {
        return array_map(
            static fn (array $r): array => [$r['code'], $r['scope'], $r['team_side'], $r['team_id'] === null ? null : (int) $r['team_id'], $r['person_matric']],
            $this->db->fetchAllAssociative("SELECT * FROM kp_match_medal WHERE match_id = 9461 AND $where ORDER BY id"),
        );
    }
}
