<?php

namespace App\Tests\Integration;

use App\Tests\Support\FakeGotenbergClient;
use Symfony\Component\HttpFoundation\Response;

/**
 * PDF routes (DOC/specs/PDF_GOTENBERG.md § 6, § 11) on the fixtures: Gotenberg is the fake of the test environment,
 * the assertions bear on the HTML it receives and on the HTTP answers.
 */
final class PdfEndpointsTest extends ApiTestCase
{
    private const TEAMS_GROUPS_SCA = '/admin/pdf/teams-groups?season=2997&competition=SCA';
    private const RANKING_RCH = '/pdf/ranking?season=2999&competition=RCH';

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
        foreach (['Scrut Alpha', 'Scrut Bravo', 'Germany Men'] as $team) {
            self::assertStringContainsString($team, $html);
        }
        self::assertArrayHasKey('logo-kpi.png', $this->gotenberg()->files, 'PDF-DESIGN-04: admin PDFs carry the KPI logo');
        self::assertArrayNotHasKey('logo-ffck.png', $this->gotenberg()->files);
        self::assertArrayHasKey('raleway-latin.woff2', $this->gotenberg()->files, 'PDF-DOC-04: fonts sent with the page');
        self::assertStringNotContainsString('http://', $html, 'PDF-DOC-04: nothing to fetch');
        self::assertStringNotContainsString('https://', $html);
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
