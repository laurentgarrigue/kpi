<?php

namespace App\Tests\Integration;

/**
 * Nouveaux endpoints publics du site (DOC/specs/public/API_PUBLIC_RESULTS.md § 4 et § 5), sur le jeu de
 * fixtures « groupe TSTRES » (SQL/fixtures/README.md). Chaque test cite le critère qu'il couvre.
 */
final class PublicCompetitionEndpointsTest extends ApiTestCase
{
    // ---------------------------------------------------------------- /seasons

    public function testApi05SeasonsListsActiveSeasonAndSeasonsWithPublishedCompetitions(): void
    {
        $body = $this->getJson('/seasons');

        self::assertSame('2999', $body['active']);
        self::assertSame(['2999', '2998'], $body['seasons']);
    }

    // ---------------------------------------------------- /group/{s}/{c}/competitions

    /** Ordre défini (GroupOrder), puis ordre du tour (Code_tour croissant) : le même pour la liste et les pastilles. */
    public function testApi06GroupCompetitionsFollowGroupOrderThenRoundAndHideUnpublished(): void
    {
        $body = $this->getJson('/group/2999/TSTRES/competitions');

        self::assertSame(['RCH', 'RCP', 'RMU', 'RAT'], self::column($body['competitions'], 'code'));
    }

    public function testApi06CompactRankingUsesLevelRankForCupsAndHidesUnrankedTeams(): void
    {
        $competitions = $this->byCode($this->getJson('/group/2999/TSTRES/competitions')['competitions']);

        $cup = $competitions['RCP']['ranking'];
        self::assertSame([1, 2, 3, 4], self::column($cup, 'rank'));
        self::assertSame(['Equipe Echo', 'Equipe Golf', 'Equipe Foxtrot', 'Equipe Hotel'], array_column(array_column($cup, 'team'), 'label'));
        self::assertSame(['rank', 'team', 'points', 'played', 'medal'], array_keys($cup[0]));
        self::assertSame(['id', 'number', 'label', 'logo'], array_keys($cup[0]['team']));

        $championship = $competitions['RCH']['ranking'];
        self::assertSame(7, $championship[0]['points'], 'Pts_publi / 100');
        self::assertSame(3, $championship[0]['played']);
        self::assertSame([], $competitions['RAT']['ranking'], 'CHPT en attente : pas de classement');
    }

    public function testApi12MedalsOnlyForFinishedFinalRoundCompetitions(): void
    {
        $competitions = $this->byCode($this->getJson('/group/2999/TSTRES/competitions')['competitions']);

        self::assertSame([1, 2, 3, null], self::column($competitions['RCP']['ranking'], 'medal'), 'CP END, tour final');
        self::assertSame([1, 2, 3], self::column($competitions['RMU']['ranking'], 'medal'), 'MULTI END, tour final');
        self::assertSame([null, null, null, null], self::column($competitions['RCH']['ranking'], 'medal'), 'en cours, tour 1');
    }

    public function testApi13GroupCompetitionsListLinkedPublishedEventsWithTheirShare(): void
    {
        $events = $this->getJson('/group/2999/TSTRES/competitions')['events'];

        self::assertSame([77], self::column($events, 'id'), 'l\'événement 78 n\'est pas publié');
        self::assertSame(['id', 'libelle', 'place', 'start', 'end', 'logo', 'share'], array_keys($events[0]));
        self::assertEqualsWithDelta(4 / 6, $events[0]['share'], 0.001, '4 des 6 journées publiées du groupe');
    }

    public function testApi09UnknownGroupIsNotFound(): void
    {
        self::assertSame(['error' => 'not_found'], $this->getJson('/group/2999/NOPE/competitions', 404));
    }

    // ---------------------------------------------------- /competition/{s}/{c}

    public function testHeaderFieldsAndDisplayTitleRule(): void
    {
        $cup = $this->getJson('/competition/2999/RCP');

        self::assertSame([
            'code', 'season', 'group', 'libelle', 'soustitre', 'soustitre2', 'display_title', 'type', 'status', 'level',
            'banner', 'logo', 'web', 'qualified', 'eliminated', 'has_games', 'round', 'final', 'siblings', 'events',
        ], array_keys($cup));
        self::assertSame(['code' => 'TSTRES', 'libelle' => 'Groupe Résultats', 'libelle_en' => 'Results group'], $cup['group']);
        self::assertSame('Phase finale', $cup['display_title'], 'titre inactif → sous-titre');
        self::assertSame('logo/logo-rcp.png', $cup['logo']);
        self::assertNull($cup['banner']);
        self::assertSame(10, $cup['round']);
        self::assertTrue($cup['final']);
        self::assertTrue($cup['has_games']);

        $championship = $this->getJson('/competition/2999/RCH');
        self::assertSame('Championnat Résultats', $championship['display_title'], 'titre actif → libellé');
        self::assertSame('logo/bandeau-rch.png', $championship['banner']);
        self::assertSame('https://example.test', $championship['web']);
        self::assertFalse($championship['final']);
    }

    public function testSiblingsArePublishedCompetitionsOfTheGroupByGroupOrder(): void
    {
        $siblings = $this->getJson('/competition/2999/RCP')['siblings'];

        self::assertSame(['RCH', 'RCP', 'RMU', 'RAT'], self::column($siblings, 'code'));
        self::assertSame(['code', 'display_title', 'soustitre2'], array_keys($siblings[0]));
    }

    public function testApi13CompetitionEventsShare(): void
    {
        $cup = $this->getJson('/competition/2999/RCP')['events'];
        self::assertSame([77], self::column($cup, 'id'));
        self::assertEqualsWithDelta(1.0, $cup[0]['share'], 0.001, 'toutes les journées de RCP');

        $championship = $this->getJson('/competition/2999/RCH')['events'];
        self::assertSame([77], self::column($championship, 'id'), '78 non publié, Pause non comptée');
        self::assertEqualsWithDelta(0.5, $championship[0]['share'], 0.001);
    }

    public function testApi09UnknownOrUnpublishedCompetitionIsNotFoundAndInvalidParametersAreRejected(): void
    {
        self::assertSame(['error' => 'not_found'], $this->getJson('/competition/2999/RNP', 404));
        self::assertSame(['error' => 'not_found'], $this->getJson('/competition/2999/NOPE', 404));
        self::assertSame(['error' => 'invalid_parameter'], $this->getJson('/competition/29a9/RCH', 400));
        self::assertSame(['error' => 'invalid_parameter'], $this->getJson('/competition/2999/TOO-LONG-CODE', 400));
        self::assertSame(['error' => 'not_found'], $this->getJson('/competition/2999/RNP/games', 404));
    }

    // ---------------------------------------------------- games / charts

    public function testApi03CompetitionGamesAreTheGroupGamesOfThatCompetition(): void
    {
        $group = $this->getJson('/group/2999/TSTRES/games');
        $expected = array_values(array_filter($group, static fn (array $game): bool => $game['c_code'] === 'RCH'));

        self::assertNotEmpty($expected);
        self::assertSame($expected, $this->getJson('/competition/2999/RCH/games'));
    }

    public function testCompetitionChartsHaveTheEventFormatForThatCompetitionOnly(): void
    {
        $charts = $this->getJson('/competition/2999/RCP/charts');

        self::assertSame(['RCP'], self::column($charts, 'code'));
        $final = $charts[0]['rounds'][2]['phases']['98-Finale'];
        self::assertSame(9212, $final['d_id']);
        self::assertSame('(Winner game #11)', $final['games'][1]['t_a_label'], 'libellés d\'attente résolus');
    }

    // ---------------------------------------------------- ranking

    public function testApi07ChampionshipRankingColumnsAndQualifiedMarks(): void
    {
        $ranking = $this->getJson('/competition/2999/RCH/ranking');

        self::assertSame(['status' => 'ON', 'type' => 'CHPT', 'qualified' => 1, 'eliminated' => 1], array_slice($ranking, 0, 4));
        self::assertSame([
            'rank', 'team', 'points', 'played', 'won', 'drawn', 'lost', 'forfeits', 'goals_for', 'goals_against', 'goal_diff', 'medal',
        ], array_keys($ranking['rows'][0]));
        self::assertSame([1, 2, 3, 4], self::column($ranking['rows'], 'rank'));
        self::assertSame(5, $ranking['rows'][0]['goal_diff']);
    }

    public function testApi07CupRankingUsesLevelRankAndMultiHasReducedColumns(): void
    {
        $cup = $this->getJson('/competition/2999/RCP/ranking');
        self::assertSame(['Equipe Echo', 'Equipe Golf', 'Equipe Foxtrot', 'Equipe Hotel'], array_column(array_column($cup['rows'], 'team'), 'label'));
        self::assertSame([1, 2, 3, null], self::column($cup['rows'], 'medal'));

        $multi = $this->getJson('/competition/2999/RMU/ranking');
        self::assertSame(['rank', 'team', 'points', 'played', 'medal'], array_keys($multi['rows'][0]));
        self::assertSame(30, $multi['rows'][0]['points']);
    }

    public function testApi07WaitingChampionshipHasNoRanking(): void
    {
        self::assertSame([], $this->getJson('/competition/2999/RAT/ranking')['rows']);
    }

    // ---------------------------------------------------- stats

    public function testApi08StatsListsAvailableKinds(): void
    {
        self::assertSame(['kinds' => ['scorers']], $this->getJson('/competition/2999/RCH/stats'));
    }

    public function testApi08ScorersCountValidatedPublishedGoalsWithSharedRanks(): void
    {
        $scorers = $this->getJson('/competition/2999/RCH/stats/scorers');

        self::assertSame('scorers', $scorers['kind']);
        self::assertSame([['key' => 'goals', 'type' => 'integer']], $scorers['columns']);
        self::assertSame(['ALPHA', 'CHARLIE', 'BRAVO'], self::column($scorers['rows'], 'last_name'));
        self::assertSame([1, 1, 3], self::column($scorers['rows'], 'rank'));
        self::assertSame([2, 2, 1], self::column($scorers['rows'], 'goals'));
        self::assertSame(['rank', 'first_name', 'last_name', 'number', 'team', 'goals'], array_keys($scorers['rows'][0]));
        self::assertSame(['id', 'number', 'label'], array_keys($scorers['rows'][0]['team']));
        self::assertStringNotContainsString('950', (string) json_encode($scorers), 'aucun numéro de licence (Matric 95xx)');
    }

    public function testApi08ScorersLimitIsBoundedAndUnknownKindIsNotFound(): void
    {
        self::assertCount(1, $this->getJson('/competition/2999/RCH/stats/scorers?limit=1')['rows']);
        self::assertSame(['error' => 'invalid_parameter'], $this->getJson('/competition/2999/RCH/stats/scorers?limit=0', 400));
        self::assertSame(['error' => 'invalid_parameter'], $this->getJson('/competition/2999/RCH/stats/scorers?limit=101', 400));
        self::assertSame(['error' => 'not_found'], $this->getJson('/competition/2999/RCH/stats/attack', 404));
    }

    // ---------------------------------------------------- info

    public function testInfoListsPublishedGamedaysWithOfficialsWithoutLicenceNumbers(): void
    {
        $info = $this->getJson('/competition/2999/RCH/info');

        self::assertSame([9201, 9202], self::column($info['gamedays'], 'id'), 'non publiée et Pause exclues');
        $first = $info['gamedays'][0];
        self::assertSame(['id', 'name', 'phase', 'start', 'end', 'place', 'department', 'organizer', 'officials'], array_keys($first));
        self::assertSame(['rc' => 'RESP Insc', 'r1' => 'RESP Rone', 'delegate' => 'DELEGUE Del', 'chief_referee' => 'CHEF Arb'], $first['officials']);
        self::assertSame([['pool' => '', 'teams' => ['Equipe Alpha', 'Equipe Bravo', 'Equipe Charlie', 'Equipe Delta']]], array_map(
            static fn (array $pool): array => ['pool' => $pool['pool'], 'teams' => array_column($pool['teams'], 'label')],
            $info['teams_by_pool'],
        ));
        self::assertNull($info['schema']);
    }

    public function testInfoHidesTeamsBeforeTheCompetitionStarts(): void
    {
        self::assertSame([], $this->getJson('/competition/2999/RAT/info')['teams_by_pool']);
    }

    // ---------------------------------------------------- /event/{id}/competitions

    public function testEventCompetitionsGiveTheEventHeaderAndItsPublishedCompetitions(): void
    {
        $body = $this->getJson('/event/77/competitions');

        self::assertSame([
            'id' => 77, 'libelle' => 'Tournoi Résultats', 'place' => 'Rivecity',
            'logo' => 'logo/resultats.png', 'start' => '2999-04-01', 'end' => '2999-04-02',
        ], $body['event']);
        self::assertSame(['RCH', 'RCP'], self::column($body['competitions'], 'code'));
        self::assertSame(['code', 'season', 'display_title', 'soustitre2'], array_keys($body['competitions'][0]));
    }

    public function testUnpublishedEventIsNotFound(): void
    {
        self::assertSame(['error' => 'not_found'], $this->getJson('/event/78/competitions', 404));
    }

    // ---------------------------------------------------- cache

    public function testResponsesArePubliclyCacheable(): void
    {
        $this->client->request('GET', '/competition/2999/RCH/ranking');
        self::assertSame('max-age=60, public', $this->client->getResponse()->headers->get('Cache-Control'));

        $this->client->request('GET', '/seasons');
        self::assertSame('max-age=300, public', $this->client->getResponse()->headers->get('Cache-Control'));
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return array<string, array<string, mixed>>
     */
    private function byCode(array $rows): array
    {
        return array_column($rows, null, 'code');
    }
}
