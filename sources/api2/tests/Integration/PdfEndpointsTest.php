<?php

namespace App\Tests\Integration;

use App\Tests\Support\FakeGotenbergClient;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Response;

/**
 * PDF routes (DOC/specs/PDF_GOTENBERG.md § 6, § 11) on the fixtures: Gotenberg is the fake of the test environment,
 * the assertions bear on the HTML it receives and on the HTTP answers.
 */
final class PdfEndpointsTest extends ApiTestCase
{
    private const TEAMS_GROUPS_SCA = '/admin/pdf/teams-groups?season=2997&competition=SCA';
    private const RANKING_RCH = '/pdf/ranking?season=2999&competition=RCH';

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        // The admin PDFs write the journal (§ 6.4): rolled back at the end of each test.
        $this->connection = $this->beginWrites();
    }

    // ------------------------------------------------------------------ access (§ 6.4)

    public function testPdfAuth01AdminPdfRequiresAJwt(): void
    {
        $this->client->request('GET', self::TEAMS_GROUPS_SCA);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    public function testPdfAuth02ProfileBelowTheDocumentRoleIsRefused(): void
    {
        // Profile 9 (ROLE_SCORER) does not inherit ROLE_VIEWER: no list of teams (§ 7.2).
        $this->pdfAs('TSCORE', self::TEAMS_GROUPS_SCA, Response::HTTP_FORBIDDEN);
        // Profile 10: presence and control sheets only.
        $this->pdfAs('TSCRUTA', self::TEAMS_GROUPS_SCA, Response::HTTP_FORBIDDEN);
    }

    public function testPdfAuth03PerimeterOfTheActiveMandate(): void
    {
        $this->pdfAs('TDIV', self::TEAMS_GROUPS_SCA, Response::HTTP_OK);
        $this->pdfAs('TDIV', '/admin/pdf/teams-groups?season=2997&competition=SCC', Response::HTTP_FORBIDDEN);
        $this->pdfAs('TDIV', '/admin/pdf/teams-groups?season=2999&competition=RCH', Response::HTTP_FORBIDDEN, 'season out of the perimeter');

        // Mandate 9701 of TDIV: SCC only.
        $this->pdfAs('TDIV', '/admin/pdf/teams-groups?season=2997&competition=SCC', Response::HTTP_OK, mandate: 9701);
        $this->pdfAs('TDIV', self::TEAMS_GROUPS_SCA, Response::HTTP_FORBIDDEN, mandate: 9701);
    }

    public function testAdminTeamsGroupsInTheAdminCharter(): void
    {
        $response = $this->pdfAs('TADMIN', self::TEAMS_GROUPS_SCA, Response::HTTP_OK);

        self::assertSame('application/pdf', $response->headers->get('Content-Type'));
        self::assertSame(FakeGotenbergClient::PDF, $response->getContent());
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'), 'admin PDFs are never cached');
        self::assertStringContainsString('inline; filename="teams-groups_SCA_2997_fr.pdf"', (string) $response->headers->get('Content-Disposition'));

        $html = (string) $this->gotenberg()->html;
        self::assertStringContainsString('class="theme-admin"', $html, 'PDF-DESIGN-01');
        self::assertStringContainsString('tokens-admin.css', $html);
        self::assertStringContainsString('<span class="pdf-header__season">2997</span>', $html, 'the season at the right of the title');
        self::assertStringNotContainsString('Saison', $html, 'no « Season » word, no competition code');
        self::assertStringNotContainsString('pdf-columns', $html, 'a league: one list, not pools side by side');
        self::assertStringNotContainsString('pdf-section__title', $html, 'no « No pool » title');
        foreach (['Scrut Alpha', 'Scrut Bravo', 'Germany Men'] as $team) {
            self::assertStringContainsString($team, $html);
        }
        self::assertArrayHasKey('logo-kpi.png', $this->gotenberg()->files, 'PDF-DESIGN-04: admin PDFs carry the KPI logo');
        self::assertArrayNotHasKey('logo-ffck.png', $this->gotenberg()->files);
        self::assertArrayHasKey('raleway-latin.woff2', $this->gotenberg()->files, 'PDF-DOC-04: fonts sent with the page');
        self::assertStringNotContainsString('http://', $html, 'PDF-DOC-04: nothing to fetch');
        self::assertStringNotContainsString('https://', $html);
    }

    public function testAdminPdfIsRecordedInTheJournal(): void
    {
        $this->pdfAs('TADMIN', self::TEAMS_GROUPS_SCA . '&lang=en', Response::HTTP_OK);

        self::assertSame(
            ['Users' => 'TADMIN', 'Saisons' => '2997', 'Competitions' => 'SCA', 'Journal' => 'lang=en'],
            $this->connection->fetchAssociative("SELECT Users, Saisons, Competitions, Journal FROM kp_journal WHERE Actions = 'PDF teams-groups'"),
        );
    }

    public function testPublicPdfIsNotRecordedInTheJournal(): void
    {
        $this->client->request('GET', self::RANKING_RCH);

        self::assertFalse($this->connection->fetchOne("SELECT Id FROM kp_journal WHERE Actions LIKE 'PDF %'"));
    }

    public function testPdfDataLanguage(): void
    {
        $this->pdfAs('TADMIN', self::TEAMS_GROUPS_SCA . '&lang=en', Response::HTTP_OK);

        self::assertStringContainsString('<html lang="en">', (string) $this->gotenberg()->html);
        self::assertStringContainsString('Teams', (string) $this->gotenberg()->html);
        self::assertStringContainsString('Printed on', (string) $this->gotenberg()->footer);
    }

    // ------------------------------------------------------------------ request (§ 6.1)

    public function testPdfSess02InvalidRequests(): void
    {
        $this->pdfAs('TADMIN', '/admin/pdf/teams-groups?competition=SCA', Response::HTTP_BAD_REQUEST, 'season missing');
        $this->pdfAs('TADMIN', self::TEAMS_GROUPS_SCA . '&Compet=SCA', Response::HTTP_BAD_REQUEST, 'unknown parameter');
        $this->pdfAs('TADMIN', '/admin/pdf/teams-groups?season=2997', Response::HTTP_BAD_REQUEST, 'competition required');
        $this->pdfAs('TADMIN', '/admin/pdf/teams-groups?season=2997&competition=NONE', Response::HTTP_NOT_FOUND, 'nothing to print');
        $this->pdfAs('TADMIN', '/admin/pdf/unknown-doc?season=2997', Response::HTTP_NOT_FOUND);
    }

    public function testPdfInfra03GotenbergUnavailable(): void
    {
        $this->client->disableReboot();
        $this->gotenberg()->unavailable = true;

        $response = $this->pdfAs('TADMIN', self::TEAMS_GROUPS_SCA, Response::HTTP_SERVICE_UNAVAILABLE);

        self::assertNotNull($response->headers->get('Retry-After'));
        self::assertStringNotContainsString('Exception', (string) $response->getContent());
    }

    // ------------------------------------------------------------------ public (§ 7.4)

    public function testPdfAuth06PublicRankingWithoutAuthenticationInThePublicCharter(): void
    {
        $this->client->request('GET', self::RANKING_RCH);
        $response = $this->client->getResponse();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('application/pdf', $response->headers->get('Content-Type'));
        self::assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
        self::assertNotNull($response->getEtag());

        $html = (string) $this->gotenberg()->html;
        self::assertStringContainsString('class="theme-public"', $html, 'PDF-DESIGN-01');
        foreach (['logo-ffck.png', 'logo-cna.png'] as $logo) {
            self::assertArrayHasKey($logo, $this->gotenberg()->files, 'PDF-DESIGN-04');
        }
        self::assertArrayHasKey('qrcode.svg', $this->gotenberg()->files, 'PDF-DESIGN-08');
        self::assertStringContainsString('/competitions/2999/RCH/ranking', (string) $this->gotenberg()->footer);
    }

    public function testPdfData01PublicRankingIsThePublishedOne(): void
    {
        $published = $this->getJson('/competition/2999/RCH/ranking');
        $this->client->request('GET', self::RANKING_RCH);

        $html = (string) $this->gotenberg()->html;
        foreach ($published['rows'] as $row) {
            self::assertStringContainsString(htmlspecialchars($row['team']['label']), $html);
        }

        $this->client->request('GET', '/pdf/ranking?season=2999&competition=RNP');
        self::assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode(), 'unpublished competition');
    }

    public function testPdfPerf03SamePublicDocumentAnswers304(): void
    {
        $this->client->request('GET', self::RANKING_RCH);
        $etag = (string) $this->client->getResponse()->getEtag();

        $this->client->request('GET', self::RANKING_RCH, server: ['HTTP_IF_NONE_MATCH' => $etag]);

        self::assertSame(Response::HTTP_NOT_MODIFIED, $this->client->getResponse()->getStatusCode());
    }

    public function testAdminOnlyDocumentsAreNotPublic(): void
    {
        $this->client->request('GET', '/pdf/teams-groups?season=2997&competition=SCA');
        self::assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());

        $this->client->request('GET', '/pdf/scoresheet?season=2999&games=1');
        self::assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode(), '§ 7.3.2: no public scoresheet');
    }

    // ------------------------------------------------------------------ signed links (§ 6.3-2)

    public function testPdfAuth07SignedLinkWorksWithoutJwtAndOnlyAsSigned(): void
    {
        $link = $this->requestAs('TDIV', 'POST', '/admin/pdf/teams-groups/link', ['season' => '2997', 'competition' => 'SCA']);
        self::assertSame(300, $link['expires_in']);
        $url = (string) parse_url($link['url'], PHP_URL_PATH) . '?' . parse_url($link['url'], PHP_URL_QUERY);
        $url = (string) preg_replace('#^/api2#', '', $url);

        $this->client->request('GET', $url);
        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode(), (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', str_replace('competition=SCA', 'competition=SCB', $url));
        self::assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode(), 'altered link');
    }

    public function testSignedLinkIsRefusedOutOfThePerimeter(): void
    {
        $this->requestAs('TDIV', 'POST', '/admin/pdf/teams-groups/link', ['season' => '2997', 'competition' => 'SCC'], Response::HTTP_FORBIDDEN);
    }

    // ------------------------------------------------------------------ legacy pages (phase 0)

    public function testPhase0LegacyLinkFollowsTheRoleOfTheEquivalentDocument(): void
    {
        $link = $this->requestAs('TSCORE', 'POST', '/admin/pdf/legacy-link', ['page' => 'FeuilleMatchMulti.php', 'query' => ['listMatch' => '1,2']]);
        self::assertSame('/admin/FeuilleMatchMulti.php', $link['path'], 'I4: the scorer prints the scoresheets');
        self::assertSame('1,2', $link['query']['listMatch']);
        self::assertArrayHasKey('signature', $link['query']);
        self::assertArrayHasKey('expires', $link['query']);

        $this->requestAs('TSCORE', 'POST', '/admin/pdf/legacy-link', ['page' => 'FeuilleGroups.php', 'query' => ['S' => '2997', 'Compet' => 'SCA']], Response::HTTP_FORBIDDEN);
        $this->requestAs('TCLUB', 'POST', '/admin/pdf/legacy-link', ['page' => 'FeuillePresenceVisa.php', 'query' => ['season' => '2997', 'compet' => 'SCA']], Response::HTTP_FORBIDDEN);
        $this->requestAs('TADMIN', 'POST', '/admin/pdf/legacy-link', ['page' => 'Unknown.php', 'query' => []], Response::HTTP_NOT_FOUND);
    }

    public function testPdfAuth04LegacyPresenceOfATeamOfAnotherClubIsRefused(): void
    {
        // TCLUB: profile 7 limited to the club C002 — 9382 is C002, 9381 is C001.
        $this->requestAs('TCLUB', 'POST', '/admin/pdf/legacy-link', ['page' => 'FeuillePresence.php', 'query' => ['team' => '9382', 'compet' => 'SCA', 'season' => '2997']]);
        $this->requestAs('TCLUB', 'POST', '/admin/pdf/legacy-link', ['page' => 'FeuillePresence.php', 'query' => ['team' => '9381', 'compet' => 'SCA', 'season' => '2997']], Response::HTTP_FORBIDDEN);
        // PDF-AUTH-05: the scrutineer gets the presence and control sheets of every team of its competitions.
        $this->requestAs('TSCRUTA', 'POST', '/admin/pdf/legacy-link', ['page' => 'FeuilleControle.php', 'query' => ['team' => '9381', 'compet' => 'SCA', 'season' => '2997']]);
        $this->requestAs('TSCRUTA', 'POST', '/admin/pdf/legacy-link', ['page' => 'FeuilleCards.php', 'query' => ['Compet' => 'SCA', 'S' => '2997']], Response::HTTP_FORBIDDEN);
    }

    // ------------------------------------------------------------------ personal data (phase 2, § 7.2)

    public function testPresenceSheetOfEveryTeamWithPlayersInTheirOrder(): void
    {
        $this->pdfAs('TADMIN', '/admin/pdf/presence?season=2997&competition=SCA', Response::HTTP_OK);

        $html = (string) $this->gotenberg()->html;
        foreach (['Scrut Alpha', 'Scrut Bravo', 'Capitaine', 'Coach', 'Bravo', 'Document interne'] as $text) {
            self::assertStringContainsString($text, $html . $this->gotenberg()->footer);
        }
        self::assertLessThan(strpos($html, 'Coach'), strpos($html, 'Joueur'), 'players before the staff');
        self::assertStringContainsString('Staff</td>', $html, 'the role by its label');
        self::assertLessThan(strpos($html, '>Arbitre</td>'), strpos($html, 'Non joueurs'), 'referees and inactive members apart');
        self::assertStringContainsString('Pagaie', $html, 'national competition: paddle and certificate checked');
        self::assertStringNotContainsString('Germany Men', $html, 'a team without players has no page');
    }

    public function testPdfAuth04PresenceIsLimitedToTheClubsOfTheUser(): void
    {
        // TCLUB: profile 7 limited to the club C002 — 9382 is C002, 9381 is C001.
        $this->pdfAs('TCLUB', '/admin/pdf/presence?season=2997&competition=SCA', Response::HTTP_OK);
        $html = (string) $this->gotenberg()->html;
        self::assertStringContainsString('Scrut Bravo', $html);
        self::assertStringNotContainsString('Scrut Alpha', $html);

        $this->pdfAs('TCLUB', '/admin/pdf/presence?season=2997&competition=SCA&team=9382', Response::HTTP_OK);
        $this->pdfAs('TCLUB', '/admin/pdf/presence?season=2997&competition=SCA&team=9381', Response::HTTP_FORBIDDEN);
    }

    public function testPdfAuth09PresenceOptionsHaveTheirOwnRole(): void
    {
        $this->pdfAs('TCLUB', '/admin/pdf/presence?season=2997&competition=SCA&visa=1', Response::HTTP_FORBIDDEN, 'I2');
        $this->pdfAs('TDIV', '/admin/pdf/presence?season=2997&competition=SCA&visa=1', Response::HTTP_OK);
        self::assertStringContainsString('Visa', (string) $this->gotenberg()->html);

        $this->pdfAs('TDIV', '/admin/pdf/presence?season=2997&competition=SCA&view=birthyear', Response::HTTP_FORBIDDEN);
        $this->pdfAs('TADMIN', '/admin/pdf/presence?season=2997&competition=SCA&view=birthyear', Response::HTTP_OK);
        self::assertStringContainsString('2980', (string) $this->gotenberg()->html);

        $this->pdfAs('TCLUB', '/admin/pdf/presence?season=2997&competition=SCA&view=category&category=U21', Response::HTTP_FORBIDDEN);
        $this->pdfAs('TDIV', '/admin/pdf/presence?season=2997&competition=SCA&view=category&category=U21', Response::HTTP_OK);
        $html = (string) $this->gotenberg()->html;
        self::assertStringContainsString('15/03/2980', $html, 'born in 2980: under 21 in 2997, with the birth date');
        self::assertStringNotContainsString('Capitaine', $html, 'no birth date: not listed');

        $this->pdfAs('TADMIN', '/admin/pdf/presence?season=2997&competition=SCA&view=unknown', Response::HTTP_BAD_REQUEST);
    }

    public function testPdfAuth05ScrutineerGetsTheControlSheetOfItsCompetitions(): void
    {
        $this->pdfAs('TSCRUTA', '/admin/pdf/scrutineering-control?season=2997&competition=SCA', Response::HTTP_OK);
        $html = (string) $this->gotenberg()->html;
        self::assertStringContainsString('Feuille de contrôle', $html);
        self::assertStringContainsString('Sécurité', $html, 'status 3 of the player 9512');
        self::assertStringContainsString('Encadrement', $html, 'staff after the players');
        self::assertStringContainsString('Scrut Bravo', $html, 'every team, whatever the club');

        $this->pdfAs('TSCRUTA', '/admin/pdf/presence?season=2997&competition=SCA&team=9381', Response::HTTP_OK);
        $this->pdfAs('TSCRUTA', '/admin/pdf/scrutineering-control?season=2997&competition=SCB', Response::HTTP_FORBIDDEN, 'out of its competitions');
        $this->pdfAs('TDIV', '/admin/pdf/scrutineering-control?season=2997&competition=SCA', Response::HTTP_FORBIDDEN, 'I1');
        $this->pdfAs('TSCRUTA', '/admin/pdf/instances?season=2997&competition=SCA&gameday=9271', Response::HTTP_FORBIDDEN);
    }

    public function testRefereesPoolIsForAdministratorsOnly(): void
    {
        $this->pdfAs('TADMIN', '/admin/pdf/referees-pool?season=2997&lang=en', Response::HTTP_OK);
        $html = (string) $this->gotenberg()->html;
        self::assertStringContainsString('Referees pool', $html);
        self::assertStringContainsString('France', $html);
        self::assertStringContainsString('NAT-B', $html);

        $this->pdfAs('TDIV', '/admin/pdf/referees-pool?season=2997', Response::HTTP_FORBIDDEN);
    }

    public function testInstancesOfAGamedayInThePerimeterOfTheMandate(): void
    {
        $this->pdfAs('TDIV', '/admin/pdf/instances?season=2997&competition=SCA&gameday=9271', Response::HTTP_OK);
        $html = (string) $this->gotenberg()->html;
        self::assertStringContainsString('DELEGUE Test', $html);
        self::assertStringNotContainsString('9514', $html, 'licence numbers are never printed');
        self::assertStringContainsString('01/05/2997', $html);

        $this->pdfAs('TDIV', '/admin/pdf/instances?season=2997&competition=SCA', Response::HTTP_BAD_REQUEST, 'gameday required');
        $this->pdfAs('TDIV', '/admin/pdf/instances?season=2997&competition=SCA&gameday=9272', Response::HTTP_NOT_FOUND, 'gameday of another competition');
        // Mandate 9702 of TDIV: gameday 9272 only.
        $this->pdfAs('TDIV', '/admin/pdf/instances?season=2997&competition=SCA&gameday=9271', Response::HTTP_FORBIDDEN, mandate: 9702);
        $this->pdfAs('TCLUB', '/admin/pdf/instances?season=2997&competition=SCA&gameday=9271', Response::HTTP_OK, 'ROLE_VIEWER');
    }

    // ------------------------------------------------------------------ rankings (phase 3, § 7.4)

    public function testPublicRankingTeamDetailShowsThePublishedGamesOfEachTeam(): void
    {
        $this->client->request('GET', '/pdf/ranking-team-detail?season=2999&competition=RCH');
        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode(), (string) $this->client->getResponse()->getContent());

        $html = (string) $this->gotenberg()->html;
        self::assertStringContainsString('class="theme-public"', $html);
        self::assertStringContainsString('1. Equipe Alpha', $html, 'published ranking');
        self::assertStringContainsString('01/03/2999 - Lacville', $html);
        self::assertStringContainsString('3 - 1', strip_tags($html));
        self::assertStringContainsString('<span class="pdf-strong">3</span> - <span class="">1</span>', $html, 'only the score of the winner in bold');
        self::assertStringNotContainsString('5 - 0', strip_tags($html), 'game 9403 is not published');
        self::assertStringNotContainsString('pdf-watermark', $html);
    }

    public function testPublicRankingByGamedayAndByPhase(): void
    {
        $this->client->request('GET', '/pdf/ranking-gameday-detail?season=2999&competition=RCH');
        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        self::assertStringContainsString('Journée 1', (string) $this->gotenberg()->html);

        $this->client->request('GET', '/pdf/ranking-phases?season=2999&competition=RCP&lang=en');
        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $html = (string) $this->gotenberg()->html;
        self::assertStringContainsString('Poule A</h2>', $html, 'pool: its ranking');
        self::assertStringNotContainsString('Poule B</h2>', $html, 'pool without ranking');
        self::assertStringContainsString('2 - 1', strip_tags($html), 'elimination phase: its games');
        self::assertLessThan(strpos($html, 'Finale</h2>'), strpos($html, 'Poule A</h2>'), 'pools first');
        self::assertStringNotContainsString('Rivecity', $html, 'a cup: no date nor place for each phase');

        $this->client->request('GET', '/pdf/ranking-team-detail?season=2999&competition=RCP');
        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $html = (string) $this->gotenberg()->html;
        self::assertStringContainsString('<td colspan="3">Finale</td>', $html, 'a cup: games by phase');
        self::assertStringNotContainsString('Rivecity', $html, 'not by date and place');

        $this->client->request('GET', '/pdf/ranking?season=2999&competition=RCP');
        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        self::assertStringNotContainsString('Pts</th>', (string) $this->gotenberg()->html, 'a cup: final ranking only, no points');

        $this->client->request('GET', '/pdf/ranking-team-detail?season=2999&competition=RNP');
        self::assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode(), 'competition not published');
    }

    public function testPdfData02AdminRankingIsTheComputedOneMarkedProvisional(): void
    {
        $this->pdfAs('TADMIN', '/admin/pdf/ranking?season=2999&competition=RCH', Response::HTTP_OK);

        $html = (string) $this->gotenberg()->html;
        self::assertStringContainsString('class="theme-admin"', $html);
        self::assertStringContainsString('Provisoire', $html);
        self::assertLessThan(strpos($html, 'Equipe Alpha'), strpos($html, 'Equipe Delta'), 'computed: Delta first');

        $this->pdfAs('TADMIN', '/admin/pdf/ranking-team-detail?season=2999&competition=RCH', Response::HTTP_OK);
        self::assertStringContainsString('1. Equipe Delta', (string) $this->gotenberg()->html);
        self::assertStringContainsString('5 - 0', strip_tags((string) $this->gotenberg()->html), 'admin: games not published yet included');

        $this->pdfAs('TCLUB', '/admin/pdf/ranking?season=2997&competition=SCA', Response::HTTP_FORBIDDEN, 'I5: computed ranking for profiles 1–6');
    }

    public function testTiebreakJustificationIsRenderedByGotenberg(): void
    {
        $this->client->request('GET', '/admin/rankings/justification?season=2999&competition=RCH&format=pdf&locale=en', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->tokenFor('TADMIN'),
        ]);
        $response = $this->client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('application/pdf', $response->headers->get('Content-Type'));

        $html = (string) $this->gotenberg()->html;
        self::assertStringContainsString('Tie-break justification', $html);
        self::assertStringContainsString('Equipe Bravo', $html, 'Bravo and Charlie are level on points');
    }

    // ------------------------------------------------------------------ games (phase 4, § 7.3)

    public function testPdfData03PublicGameListOnlyHasPublishedGames(): void
    {
        $this->client->request('GET', '/pdf/games-list?season=2999&competition=RCH');
        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode(), (string) $this->client->getResponse()->getContent());

        $html = (string) $this->gotenberg()->html;
        self::assertStringContainsString('3 - 1', strip_tags($html));
        self::assertStringNotContainsString('5 - 0', strip_tags($html), 'game 9403 is not published');
        self::assertStringContainsString('ARBITRE Un', $html);
        self::assertStringNotContainsString('(C001)', $html, 'what is stored between parentheses is not published');
        self::assertStringContainsString('Arbitres</th>', $html, 'one column for both referees');
        self::assertStringNotContainsString('Compét.', $html, 'one competition: no competition column');

        $this->client->request('GET', '/pdf/games-list?season=2999&competition=RCH,RCP');
        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $html = (string) $this->gotenberg()->html;
        self::assertStringContainsString('Equipe Echo', $html, 'several competitions');
        self::assertStringContainsString('Compét.', $html);
        self::assertStringContainsString('Poule A, Finale', $html, 'header: the labels of the competitions, not their codes');

        $this->client->request('GET', '/pdf/games-list?season=2999&event=77');
        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $this->client->request('GET', '/pdf/games-list?season=2999&event=78');
        self::assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode(), 'event not published');
        $this->client->request('GET', '/pdf/games-list?season=2999');
        self::assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode(), 'a scope is required');
    }

    public function testPdfAuth10AdminGameListFollowsTheVisibilityOfTheGamesPage(): void
    {
        $this->pdfAs('TADMIN', '/admin/pdf/games-list?season=2999&competition=RCH', Response::HTTP_OK);
        self::assertStringContainsString('5 - 0', strip_tags((string) $this->gotenberg()->html), 'admin: games not published included');

        $this->pdfAs('TTEAM', '/admin/pdf/games-list?season=2999&competition=RCH', Response::HTTP_OK);
        $html = (string) $this->gotenberg()->html;
        self::assertStringContainsString('3 - 1', strip_tags($html));
        self::assertStringNotContainsString('5 - 0', strip_tags($html), 'profile 7: published games only');

        $this->pdfAs('TTEAM', '/admin/pdf/games-list?season=2999&competition=RCH,RCP', Response::HTTP_FORBIDDEN, 'RCP out of the perimeter');
    }

    public function testPdfData05PitchGridHasOneColumnPerPitchUsed(): void
    {
        $this->client->request('GET', '/pdf/games-grid?season=2999&competition=RCH');
        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode(), (string) $this->client->getResponse()->getContent());

        $html = (string) $this->gotenberg()->html;
        self::assertStringContainsString('Terrain 1</th>', $html);
        self::assertStringContainsString('Terrain 2</th>', $html);
        self::assertStringNotContainsString('Terrain 3</th>', $html);
        self::assertStringContainsString('Equipe Alpha - Equipe Bravo', $html, 'view teams by default');

        $this->client->request('GET', '/pdf/games-grid?season=2999&competition=RCH&view=phases');
        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        self::assertStringContainsString('Journée 1', (string) $this->gotenberg()->html);

        $this->client->request('GET', '/pdf/games-grid?season=2999&competition=RCH&view=pitches');
        self::assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode(), 'PDF-DATA-07');

        $this->pdfAs('TADMIN', '/admin/pdf/games-grid?season=2999&competition=RCH', Response::HTTP_NOT_FOUND, 'public version only');
    }

    public function testScoresheetForTheScoringTable(): void
    {
        $this->pdfAs('TSCORE', '/admin/pdf/scoresheet?season=2999&games=9401', Response::HTTP_OK);

        $html = (string) $this->gotenberg()->html;
        self::assertStringContainsString('Feuille de marque', $html);
        self::assertStringContainsString('ALPHA (Capitaine)', $html, 'team roster when the game has none; roles by their label');
        self::assertStringContainsString('>Staff</td>', $html, '« Staff » in place of the number');
        self::assertStringContainsString('pdf-sheet__card--yellow', $html, 'card columns on a pastel background');
        self::assertStringNotContainsString('Inactif', $html, 'inactive players are not on the sheet');
        self::assertStringContainsString('ID #9401', $html);
        self::assertStringContainsString('pdf-sheet__logos', $html, 'no visual of the competition: FFCK + KPI');
        self::assertArrayHasKey('logo-ffck.png', $this->gotenberg()->files);

        $this->pdfAs('TCLUB', '/admin/pdf/scoresheet?season=2999&games=9401', Response::HTTP_FORBIDDEN, 'game out of the perimeter');
        $this->pdfAs('TSCORE', '/admin/pdf/scoresheet?season=2999', Response::HTTP_BAD_REQUEST, 'a scope is required');
    }

    // ------------------------------------------------------------------ stats, cards, QR codes (phase 5, § 7.5)

    public function testSchemaOfACupAndOfALeague(): void
    {
        $this->pdfAs('TADMIN', '/admin/pdf/schema?season=2999&competition=RCP', Response::HTTP_OK);
        $html = (string) $this->gotenberg()->html;
        self::assertStringContainsString('Schéma de compétition', $html);
        self::assertStringContainsString('pdf-schema__stages', $html, 'a cup: stage columns');
        self::assertStringContainsString('pdf-schema__bracket-team--winner', $html, 'elimination phase: the winner first');
        self::assertStringContainsString('Poule A', $html);

        $this->pdfAs('TADMIN', '/admin/pdf/schema?season=2999&competition=RCH&count=0', Response::HTTP_OK);
        $html = (string) $this->gotenberg()->html;
        self::assertStringContainsString('pdf-schema__gameday', $html, 'a league: its gamedays');
        self::assertStringNotContainsString('pdf-schema__total', $html, 'count=0 hides the number of games');

        $this->pdfAs('TSCORE', '/admin/pdf/schema?season=2999&competition=RCH', Response::HTTP_FORBIDDEN, 'ROLE_VIEWER, as the Schema page');
    }

    public function testCardFollowUpCountsTheCardsOfTheSeason(): void
    {
        $this->pdfAs('TADMIN', '/admin/pdf/cards?season=2999&competition=RCH', Response::HTTP_OK);

        $html = (string) $this->gotenberg()->html;
        self::assertStringContainsString('Suivi des cartons', $html);
        self::assertStringContainsString('8 - ALPHA Bob', $html);
        $this->pdfAs('TCLUB', '/admin/pdf/cards?season=2997&competition=SCA', Response::HTTP_FORBIDDEN, 'ROLE_ORGANIZER');
    }

    public function testQrCodesOfAPublishedEvent(): void
    {
        $this->client->request('GET', '/pdf/qrcodes?season=2999&event=77');
        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode(), (string) $this->client->getResponse()->getContent());
        self::assertStringContainsString('/events/77/games', (string) $this->gotenberg()->html);
        self::assertArrayHasKey('qrcode-2.svg', $this->gotenberg()->files, 'games and progress');

        $this->client->request('GET', '/pdf/qrcode-app?season=2999&event=77');
        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        self::assertStringContainsString('/event/77', (string) $this->gotenberg()->html);

        $this->client->request('GET', '/pdf/qrcodes?season=2999&event=78');
        self::assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode(), 'event not published');
    }

    public function testStatsExportIsRenderedByGotenberg(): void
    {
        $this->client->request('GET', '/admin/stats/export/pdf?season=2999&type=Buteurs&competitions[]=RCH&title=Scorers&locale=en', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->tokenFor('TADMIN'),
        ]);
        $response = $this->client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('application/pdf', $response->headers->get('Content-Type'));
        self::assertStringContainsString('class="theme-admin"', (string) $this->gotenberg()->html);
        self::assertStringContainsString('Scorers', (string) $this->gotenberg()->html);
        self::assertStringContainsString('Poule A', (string) $this->gotenberg()->html, 'the competition by its label');
        self::assertStringNotContainsString('>RCH</td>', (string) $this->gotenberg()->html, 'never by its code');
    }

    // ------------------------------------------------------------------ helpers

    private function pdfAs(string $userCode, string $uri, int $expectedStatus, string $message = '', ?int $mandate = null): Response
    {
        $server = ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->tokenFor($userCode)];
        if ($mandate !== null) {
            $server['HTTP_X_ACTIVE_MANDATE'] = (string) $mandate;
        }
        $this->client->request('GET', $uri, server: $server);
        $response = $this->client->getResponse();
        self::assertSame($expectedStatus, $response->getStatusCode(), trim($message . ' ' . $uri . ' ' . substr((string) $response->getContent(), 0, 300)));

        return $response;
    }

    private function gotenberg(): FakeGotenbergClient
    {
        return static::getContainer()->get(FakeGotenbergClient::class);
    }
}
