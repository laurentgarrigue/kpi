<?php

namespace App\Tests\Integration;

use App\MatchMedals\Catalog\MedalCatalog;
use App\MatchMedals\MatchMedalService;
use Doctrine\DBAL\Connection;
use Symfony\Contracts\Service\ResetInterface;

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

    public function testMed63And64TheMedalsOfATeamInAGroupAreThoseOfApp2WithItsPlayersAndNoLicence(): void
    {
        $body = $this->getJson('/group/2998/TSTMED/team/9361/medals');

        $teamCodes = array_column($body['team'], 'count', 'code');
        self::assertArrayHasKey('hat_trick', $teamCodes, 'a player medal without a team version counts for the team');
        self::assertArrayNotHasKey('fair_play', $teamCodes, 'published on app3 only');
        $players = array_column($body['players'], null, 'number');
        self::assertSame('Ben', $players[2]['first_name']);
        self::assertContains('game_winner', array_column($players[2]['medals'], 'code'));
        self::assertSame(['code' => 'hat_trick', 'count' => 1], array_values(array_filter($players[1]['medals'], static fn (array $m): bool => $m['code'] === 'hat_trick'))[0]);
        foreach (['9531', '9532', 'matric', 'licence'] as $private) {
            self::assertStringNotContainsString($private, json_encode($body), "the response exposes $private");
        }
    }

    public function testMed64TheMedalsOfATeamInAnEventOrAGamedayOnlyCountTheGamesOfThatPerimeter(): void
    {
        $event = $this->getJson('/event/81/team/9361/medals');
        $gameday = $this->getJson('/event/9291/team/9361/medals');
        self::assertSame($event, $gameday, 'an id from 3000 is a gameday, the same games here');
        self::assertNotSame([], $event['team']);

        // another gameday holds none of the games: nothing to show, but the team exists
        self::assertSame(['team' => [], 'players' => []], $this->getJson('/event/80/team/9361/medals'));
    }

    public function testMed64TheMedalsOfATeamInACompetitionAreThoseOfTheGroupWhenTheGroupHasOnlyThatCompetition(): void
    {
        self::assertSame($this->getJson('/group/2998/TSTMED/team/9361/medals'), $this->getJson('/competition/2998/MED/team/9361/medals'));
    }

    public function testMed63AnUnknownTeamIs404(): void
    {
        $this->getJson('/group/2998/TSTMED/team/1/medals', 404);
        $this->getJson('/event/81/team/1/medals', 404);
        $this->getJson('/competition/2998/MED/team/1/medals', 404);
    }

    public function testThrillerLabelOfTheCompetitionGames(): void
    {
        self::assertSame(['9461' => ['thriller']], $this->getJson('/competition/2998/MED/labels')['labels']);
    }

    public function testTheGameSheetTellsWhichGroupAndPublishedEventsItBelongsTo(): void
    {
        self::assertSame(['season' => '2998', 'group' => 'TSTMED', 'events' => []], $this->getJson('/game-sheet/9461')['context'], 'event 81 is not published');

        $this->db->update('kp_evenement', ['Publication' => 'O'], ['Id' => 81]);

        self::assertSame([81], $this->getJson('/game-sheet/9461')['context']['events']);
    }

    public function testTheCatalogueIsReadAgainOnEachRequestOfAWorker(): void
    {
        $catalog = static::getContainer()->get(MedalCatalog::class);
        self::assertInstanceOf(ResetInterface::class, $catalog, 'a FrankenPHP worker resets it between requests');
        $before = count($catalog->all());

        $this->db->executeStatement("DELETE FROM kp_medal_catalog WHERE code = 'quadruple'");
        $catalog->reset();

        self::assertSame($before - 1, count($catalog->all()), 'a change made by another worker or a migration shows up');
    }

    public function testMed40AdminEndpointsAreForProfilesOneAndTwo(): void
    {
        $this->requestAs('TDIV', 'GET', '/admin/medals/catalog', expectedStatus: 403);
        self::assertCount(53, $this->requestAs('TSUPER', 'GET', '/admin/medals/catalog?season=2998')['items']);
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

    public function testAPlayerOnTheGameSheetButNoLongerOnTheTeamRosterKeepsANameAndANumber(): void
    {
        $this->db->executeStatement('DELETE FROM kp_competition_equipe_joueur WHERE Id_equipe = 9361 AND Matric = 9532');
        $this->db->executeStatement("INSERT INTO kp_match_joueur (Id_match, Matric, Numero, Equipe, Capitaine) VALUES (9461, 9532, 7, 'A', '-')");

        $rows = $this->getJson('/competition/2998/MED/stats/medals_players')['rows'];
        $ben = array_values(array_filter($rows, static fn (array $r): bool => $r['first_name'] === 'Ben'));

        self::assertCount(1, $ben, 'the player is named from his licence');
        self::assertSame(['MEDAL', 7, 'Medal Oscar'], [$ben[0]['last_name'], $ben[0]['number'], $ben[0]['team']['label']]);
    }

    public function testAdminSeasonsShowTheFrozenThresholds(): void
    {
        $seasons = $this->requestAs('TSUPER', 'GET', '/admin/medals/seasons');

        self::assertSame('2998', $seasons['seasons'][0]['season']);
        self::assertFalse($seasons['seasons'][0]['outdated']);
        self::assertSame(4, $seasons['seasons'][0]['games_count']);
    }
}
