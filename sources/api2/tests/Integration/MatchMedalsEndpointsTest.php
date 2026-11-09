<?php

namespace App\Tests\Integration;

use App\MatchMedals\MatchMedalService;
use Doctrine\DBAL\Connection;

/**
 * Public and admin endpoints of the match medals (FEATURE_MATCH_MEDALS.md § 6.4), on the TSTMED fixtures. Medals
 * are computed in a rolled-back transaction first.
 */
final class MatchMedalsEndpointsTest extends ApiTestCase
{
    private Connection $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->beginWrites();
        static::getContainer()->get(MatchMedalService::class)->computeSeason('2998');
    }

    public function testMed35And37KindsDependOnTheSurfaceAndAnUnpublishedKindIs404(): void
    {
        $app3 = $this->getJson('/competition/2998/MED/stats')['kinds'];
        self::assertSame(['scorers', 'medals_teams', 'medals_players', 'opener_wins', 'powerplay_rate', 'referee_games', 'medals_referees'], $app3);
        self::assertSame(['scorers', 'medals_players'], $this->getJson('/competition/2998/MED/stats?surface=app2')['kinds']);

        $this->getJson('/competition/2998/MED/stats/powerplay_rate?surface=app2', 404);
        $this->getJson('/competition/2998/MED/stats/goal_timing', 404);
        $this->getJson('/competition/2998/MED/stats?surface=admin', 400);
    }

    public function testMed45AStatisticSaysOnHowManyGamesItIsComputed(): void
    {
        $stat = $this->getJson('/competition/2998/MED/stats/powerplay_rate');

        self::assertSame('powerplay_rate', $stat['kind']);
        self::assertSame(4, $stat['games_count']);
        self::assertSame(3, $stat['reliable_games_count']);
        $alpha = array_values(array_filter($stat['rows'], static fn (array $r): bool => $r['team']['label'] === 'Medal Oscar'))[0];
        self::assertSame([1, 1, 100], [$alpha['powerplay_goals'], $alpha['opponent_exclusions'], $alpha['percent']]);
    }

    public function testMed51And55RefereeStatisticsCountBothRefereesAndSortByGames(): void
    {
        $rows = $this->getJson('/competition/2998/MED/stats/referee_games')['rows'];

        self::assertSame([['REFEREE', 'One', 2, 1], ['REFEREE', 'Two', 1, 0]], array_map(
            static fn (array $r): array => [$r['referee']['last_name'], $r['referee']['first_name'], $r['games'], $r['knockouts']],
            $rows,
        ));
    }

    public function testMed44And59NoLicenceNumberInPublicResponses(): void
    {
        foreach (['medals_players', 'referee_games', 'medals_referees', 'medals_teams'] as $kind) {
            $body = json_encode($this->getJson("/competition/2998/MED/stats/$kind"));
            foreach (['9531', '9532', '9921', '9922'] as $licence) {
                self::assertStringNotContainsString($licence, $body, "$kind exposes a licence number");
            }
        }
        $sheet = $this->getJson('/game-sheet/9461')['medals'];
        self::assertStringNotContainsString('9531', json_encode($sheet));
    }

    public function testMed43And38TheGameSheetShowsTheMedalsPublishedOnApp2(): void
    {
        $medals = $this->getJson('/game-sheet/9461')['medals'];

        self::assertTrue($medals['reliable']);
        self::assertSame(['thriller'], $medals['labels']);
        self::assertContains(['code' => 'powerplay', 'side' => 'A'], array_map(static fn (array $t): array => ['code' => $t['code'], 'side' => $t['side']], $medals['team']));
        self::assertNotContains('fair_play', array_column($medals['team'], 'code'), 'published on app3 only');
        self::assertSame(['ref_thriller'], array_column($medals['referee'], 'code'));
        $scorers = array_map(static fn (array $p): string => $p['code'].' '.$p['first_name'], $medals['player']);
        self::assertContains('game_winner Ben', $scorers);
    }

    public function testMed01AndMed44TheGameSheetOfAGameNotValidatedHasNoMedal(): void
    {
        $medals = $this->getJson('/game-sheet/9464')['medals'];
        self::assertSame([[], [], [], []], [$medals['labels'], $medals['team'], $medals['player'], $medals['referee']]);
        self::assertFalse($this->getJson('/game-sheet/9463')['medals']['reliable'], 'MED-46: untimed sheet');
    }

    public function testMed48TheCatalogListsPublishedElementsWithExplanationsAndPictograms(): void
    {
        $catalog = $this->getJson('/medals/catalog');
        $codes = array_column($catalog['items'], 'code');

        self::assertContains('fair_play', $codes, 'app3 only, still explained');
        self::assertContains('game_winner', $codes, 'app2 only, still explained');
        self::assertNotContains('wire_to_wire', $codes, 'not published');
        $hatTrick = array_values(array_filter($catalog['items'], static fn (array $i): bool => $i['code'] === 'hat_trick'))[0];
        self::assertSame('Marque au moins 3 buts dans le match.', $hatTrick['explanation_fr']);
        self::assertSame('medals/icons/hat_trick.svg', $hatTrick['icon']);
        self::assertStringContainsString('<path', $hatTrick['icon_svg']);
        self::assertSame(['2998'], $catalog['seasons']);
    }

    public function testMed41ExplanationsUseTheThresholdsOfTheSeason(): void
    {
        $copy = json_decode((string) $this->db->fetchOne("SELECT thresholds FROM kp_medal_season WHERE season = '2998'"), true);
        $this->db->update('kp_medal_season', ['thresholds' => json_encode(['hatTrickGoals' => 4] + $copy)], ['season' => '2998']);

        $items = $this->getJson('/medals/catalog?season=2998')['items'];
        $hatTrick = array_values(array_filter($items, static fn (array $i): bool => $i['code'] === 'hat_trick'))[0];

        self::assertSame('Marque au moins 4 buts dans le match.', $hatTrick['explanation_fr']);
    }

    public function testMed47TheTeamMedalsOfTheSeasonLeadToTheirGames(): void
    {
        $medals = $this->getJson('/team/161/medals?season=2998')['medals'];
        $byCode = array_column($medals, null, 'code');

        self::assertArrayHasKey('hat_trick', $byCode, 'a player medal counts for the team');
        self::assertSame([9463], array_column($byCode['hat_trick']['games'], 'id'));
        self::assertArrayNotHasKey('game_winner', $byCode, 'published on app2 only');
        $this->getJson('/team/161/medals', 400);
    }

    public function testThrillerLabelOfTheCompetitionGames(): void
    {
        self::assertSame(['9461' => ['thriller']], $this->getJson('/competition/2998/MED/labels')['labels']);
    }

    public function testMed40AdminEndpointsAreForProfilesOneAndTwo(): void
    {
        $this->requestAs('TDIV', 'GET', '/admin/medals/catalog', expectedStatus: 403);
        self::assertCount(50, $this->requestAs('TSUPER', 'GET', '/admin/medals/catalog?season=2998')['items']);
    }

    public function testMed36PublishingAnElementShowsItsHistoryWithoutRecomputing(): void
    {
        $this->getJson('/competition/2998/MED/stats/goal_timing', 404);

        $this->requestAs('TSUPER', 'PATCH', '/admin/medals/catalog/goal_timing', ['published_app3' => true]);

        $rows = $this->getJson('/competition/2998/MED/stats/goal_timing')['rows'];
        self::assertSame(11, array_sum(array_column($rows, 'goals')), 'timed goals of 9461 (7), 9462 (3) and 9465 (1)');
    }

    public function testMed43CatalogChangesAreJournalled(): void
    {
        $this->requestAs('TSUPER', 'PATCH', '/admin/medals/catalog/opener', ['name_fr' => 'Ouverture du score']);

        $journal = $this->db->fetchAssociative("SELECT * FROM kp_journal WHERE Actions = 'Catalogue médailles' ORDER BY Id DESC LIMIT 1");
        self::assertSame('TSUPER', $journal['Users']);
        self::assertStringContainsString('Premier but', $journal['Journal']);
        self::assertStringContainsString('Ouverture du score', $journal['Journal']);
        $this->requestAs('TSUPER', 'PATCH', '/admin/medals/catalog/opener', ['status' => 'hidden'], 400);
    }

    public function testAdminStatisticsCoverEveryActiveKindWithTheMinimumGamesRule(): void
    {
        $kinds = array_column($this->requestAs('TSUPER', 'GET', '/admin/medals/stats')['kinds'], 'code');
        self::assertCount(20, $kinds);

        foreach ($kinds as $kind) {
            $stat = $this->requestAs('TSUPER', 'GET', "/admin/medals/stats/$kind?season=2998&competitions=MED");
            self::assertSame($kind, $stat['kind']);
        }
        $cards = $this->requestAs('TSUPER', 'GET', '/admin/medals/stats/referee_cards?season=2998&competitions=MED');
        self::assertNull($cards['rows'][0]['per_game'] ?? null, 'MED-54: under 5 games, no indicator');
        $this->requestAs('TSUPER', 'GET', '/admin/medals/stats/referee_cards?season=2998', expectedStatus: 400);
    }

    public function testAdminSeasonsShowTheFrozenThresholds(): void
    {
        $seasons = $this->requestAs('TSUPER', 'GET', '/admin/medals/seasons');

        self::assertSame('2998', $seasons['seasons'][0]['season']);
        self::assertFalse($seasons['seasons'][0]['outdated']);
        self::assertSame(4, $seasons['seasons'][0]['games_count']);
    }
}
