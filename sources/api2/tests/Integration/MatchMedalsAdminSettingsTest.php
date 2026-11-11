<?php

namespace App\Tests\Integration;

use App\MatchMedals\MatchMedalService;
use App\MatchMedals\MedalSettings;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Thresholds, recalculation and deletion of a season from the admin, profile 1 only (FEATURE_MATCH_MEDALS.md § 5.4,
 * § 6.3, § 9.3, MED-66 to MED-69), on the TSTMED fixtures (season 2998).
 */
final class MatchMedalsAdminSettingsTest extends ApiTestCase
{
    private Connection $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->beginWrites();
        static::getContainer()->get(MatchMedalService::class)->computeSeason('2998');
    }

    public function testMed66OnlyProfileOneChangesTheThresholdsAndItIsJournalled(): void
    {
        $before = $this->requestAs('TSUPER', 'GET', '/admin/medals/seasons');
        self::assertSame(30, $before['thresholds']['bounceBackSeconds']);

        $this->requestAs('TADMIN', 'PUT', '/admin/medals/thresholds', ['changes' => ['bounceBackSeconds' => 45]], 403);
        $changed = $this->requestAs('TSUPER', 'PUT', '/admin/medals/thresholds', ['changes' => ['bounceBackSeconds' => 45]]);

        self::assertSame(45, $changed['thresholds']['bounceBackSeconds']);
        self::assertSame(['bounceBackSeconds' => 45], $changed['overrides']);
        $seasons = $this->requestAs('TADMIN', 'GET', '/admin/medals/seasons');
        self::assertSame(45, $seasons['thresholds']['bounceBackSeconds'], 'profile 2 reads the current thresholds');
        self::assertSame(30, $seasons['default_thresholds']['bounceBackSeconds'], 'the values of the file are kept');
        self::assertSame(30, $seasons['seasons'][0]['thresholds']['bounceBackSeconds'], 'a computed season keeps its own');
        self::assertTrue($seasons['seasons'][0]['differs']);
        self::assertStringContainsString('bounceBackSeconds', (string) $this->db->fetchOne("SELECT Journal FROM kp_journal WHERE Actions = 'Seuils médailles' ORDER BY Id DESC LIMIT 1"));
    }

    #[DataProvider('invalidChanges')]
    public function testMed67AnInvalidThresholdIsRefusedAndNothingIsSaved(array $changes): void
    {
        $this->requestAs('TSUPER', 'PUT', '/admin/medals/thresholds', ['changes' => $changes], 400);

        self::assertSame(0, (int) $this->db->fetchOne("SELECT COUNT(*) FROM kp_medal_setting WHERE name = 'thresholds'"));
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidChanges(): iterable
    {
        yield 'unknown key' => [['nope' => 3]];
        yield 'zero' => [['hatTrickGoals' => 0]];
        yield 'too large' => [['bounceBackSeconds' => 99999]];
        yield 'not a number' => [['comebackDeficit' => 'abc']];
        yield 'unknown card' => [['permanentCards' => ['X']]];
        yield 'one good one bad' => [['bounceBackSeconds' => 40, 'hatTrickGoals' => -1]];
    }

    public function testMed67ResettingGoesBackToTheValuesOfTheFile(): void
    {
        $this->requestAs('TSUPER', 'PUT', '/admin/medals/thresholds', ['changes' => ['bounceBackSeconds' => 45, 'permanentCards' => ['D', 'R']]]);
        $reset = $this->requestAs('TSUPER', 'DELETE', '/admin/medals/thresholds');

        self::assertSame(30, $reset['thresholds']['bounceBackSeconds']);
        self::assertSame(['D'], $reset['thresholds']['permanentCards']);
        self::assertSame([], $reset['overrides']);
        $this->requestAs('TADMIN', 'DELETE', '/admin/medals/thresholds', expectedStatus: 403);
    }

    public function testMed67AValueEqualToTheFileIsNotStoredAsAnOverride(): void
    {
        $saved = $this->requestAs('TSUPER', 'PUT', '/admin/medals/thresholds', ['changes' => ['bounceBackSeconds' => 30, 'hatTrickGoals' => 4]]);

        self::assertSame(['hatTrickGoals' => 4], $saved['overrides']);
    }

    public function testMed68RecomputingASeasonFreezesTheCurrentThresholdsAndLeavesTheOthersAlone(): void
    {
        $this->db->insert('kp_medal_season', ['season' => '2997', 'thresholds' => json_encode(['bounceBackSeconds' => 20]), 'rules_version' => 1, 'computed_at' => '2026-01-01 00:00:00']);
        $this->requestAs('TSUPER', 'PUT', '/admin/medals/thresholds', ['changes' => ['bounceBackSeconds' => 45]]);

        $this->requestAs('TADMIN', 'POST', '/admin/medals/seasons/2998/compute', expectedStatus: 403);
        $started = $this->requestAs('TSUPER', 'POST', '/admin/medals/seasons/2998/compute', expectedStatus: 202);

        self::assertSame('2998', $started['job']['season']);
        // the tests run the job in the request; in production it runs in a background process (the page polls /seasons)
        $job = $this->requestAs('TSUPER', 'GET', '/admin/medals/seasons')['job'];
        self::assertSame(['season' => '2998', 'status' => 'done', 'computed' => 4], array_intersect_key($job, array_flip(['season', 'status', 'computed'])));
        self::assertSame('TSUPER', $this->db->fetchOne("SELECT Users FROM kp_journal WHERE Journal LIKE 'Saison 2998 recalculée%' ORDER BY Id DESC LIMIT 1"), 'the journal names the user');
        self::assertSame(45, json_decode((string) $this->db->fetchOne("SELECT thresholds FROM kp_medal_season WHERE season = '2998'"), true)['bounceBackSeconds']);
        self::assertSame(20, json_decode((string) $this->db->fetchOne("SELECT thresholds FROM kp_medal_season WHERE season = '2997'"), true)['bounceBackSeconds']);
        $this->requestAs('TSUPER', 'POST', '/admin/medals/seasons/12/compute', expectedStatus: 404);
    }

    public function testMed73AComputationAlreadyRunningRefusesAnotherOne(): void
    {
        $settings = static::getContainer()->get(MedalSettings::class);
        $settings->saveJob(['season' => '2997', 'status' => 'running', 'user' => 'TSUPER', 'started_at' => date('Y-m-d H:i:s')]);

        $this->requestAs('TSUPER', 'POST', '/admin/medals/seasons/2998/compute', expectedStatus: 409);

        $settings->saveJob(['season' => '2997', 'status' => 'running', 'user' => 'TSUPER', 'started_at' => date('Y-m-d H:i:s', time() - 7200)]);
        self::assertSame('error', $this->requestAs('TSUPER', 'GET', '/admin/medals/seasons')['job']['status'], 'a job that never ended is reported as failed');
        $this->requestAs('TSUPER', 'POST', '/admin/medals/seasons/2998/compute', expectedStatus: 202);
    }

    public function testMed69DeletingASeasonRemovesItsCalculationsForGoodUntilItIsComputedAgain(): void
    {
        $this->requestAs('TADMIN', 'DELETE', '/admin/medals/seasons/2998', expectedStatus: 403);
        $deleted = $this->requestAs('TSUPER', 'DELETE', '/admin/medals/seasons/2998');

        self::assertSame(4, $deleted['removed']);
        foreach (['kp_match_medal', 'kp_match_card', 'kp_match_referee', 'kp_match_insight'] as $table) {
            self::assertSame(0, (int) $this->db->fetchOne("SELECT COUNT(*) FROM $table WHERE match_id IN (9461, 9462, 9463, 9465)"), $table);
        }
        self::assertFalse($this->db->fetchOne("SELECT season FROM kp_medal_season WHERE season = '2998'"));
        self::assertContains('2998', $this->requestAs('TSUPER', 'GET', '/admin/medals/seasons')['uncomputed']);
        self::assertSame([], $this->getJson('/team/161/medals?season=2998')['medals']);

        $service = static::getContainer()->get(MatchMedalService::class);
        $service->sync();
        self::assertSame(0, (int) $this->db->fetchOne("SELECT COUNT(*) FROM kp_match_insight WHERE season = '2998'"), 'the cron does not bring it back');
        $service->refreshGame(9461);
        self::assertFalse($this->db->fetchOne('SELECT match_id FROM kp_match_insight WHERE match_id = 9461'), 'nor does a validation');

        $this->requestAs('TSUPER', 'POST', '/admin/medals/seasons/2998/compute', expectedStatus: 202);
        self::assertSame(4, (int) $this->db->fetchOne('SELECT COUNT(*) FROM kp_match_insight WHERE match_id IN (9461, 9462, 9463, 9465)'));
        self::assertNotContains('2998', $this->requestAs('TSUPER', 'GET', '/admin/medals/seasons')['uncomputed']);
        $this->requestAs('TSUPER', 'DELETE', '/admin/medals/seasons/1234', expectedStatus: 404);
    }
}
