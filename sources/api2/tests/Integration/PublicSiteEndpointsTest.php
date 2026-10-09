<?php

namespace App\Tests\Integration;

use Psr\Cache\CacheItemPoolInterface;

/**
 * Endpoints publics transverses du site (DOC/specs/public/API_PUBLIC_TRANSVERSE.md), sur les fixtures « groupe
 * TSTRES » et « site public, phase 3 » (SQL/fixtures/README.md). Chaque test cite le critère qu'il couvre.
 */
final class PublicSiteEndpointsTest extends ApiTestCase
{
    /** Adresse publique de documentation (RFC 5737) : soumise à la limite de débit, contrairement à 127.0.0.1. */
    private const PUBLIC_IP = '203.0.113.7';

    // ------------------------------------------------------------------ /calendar

    public function testApi01CalendarListsPublishedGamedaysMergedByCompetitionDatesAndPlace(): void
    {
        $days = $this->getJson('/calendar?start=2999-04-01&end=2999-04-30&group=TSTRES');

        // 9204 (Pause) exclue ; 9213 fusionnée dans 9211 (même coupe, mêmes dates, même lieu).
        self::assertSame([9202, 9211, 9212], self::column($days, 'id'));
        self::assertSame(['id', 'competition', 'name', 'place', 'department', 'start', 'end', 'event'], array_keys($days[0]));
        self::assertSame(
            ['season' => '2999', 'code' => 'RCH', 'display_title' => 'Championnat Résultats', 'type' => 'CHPT', 'level' => 'NAT', 'group' => 'TSTRES'],
            $days[0]['competition'],
        );
        self::assertSame(['Rivecity', '64', '2999-04-01', '2999-04-02'], [$days[0]['place'], $days[0]['department'], $days[0]['start'], $days[0]['end']]);
        self::assertSame(['id' => 77, 'libelle' => 'Tournoi Résultats'], $days[0]['event']);
    }

    public function testApi01CalendarKeepsGamedaysOverlappingThePeriodAndHidesUnpublishedEvents(): void
    {
        // 9201 (1er-2 mars) chevauche le début de la période ; son événement 78 n'est pas publié.
        $days = $this->getJson('/calendar?start=2999-03-02&end=2999-03-31&group=TSTRES');

        self::assertSame([9201], self::column($days, 'id'));
        self::assertNull($days[0]['event']);
        self::assertSame([], $this->getJson('/calendar?start=2999-05-01&end=2999-05-31&group=TSTRES'), '9203 non publiée');
    }

    public function testApi01CalendarFiltersByLevel(): void
    {
        self::assertSame([], $this->getJson('/calendar?start=2999-04-01&end=2999-04-30&group=TSTRES&level=REG'));
        self::assertCount(3, $this->getJson('/calendar?start=2999-04-01&end=2999-04-30&group=TSTRES&level=NAT'));
    }

    public function testApi01CalendarRejectsInvalidPeriodsAndFilters(): void
    {
        foreach ([
            '/calendar',
            '/calendar?start=2999-04-30&end=2999-04-01',
            '/calendar?start=2999-02-30&end=2999-03-31',
            '/calendar?start=2998-01-01&end=2999-03-01',
            '/calendar?start=2999-04-01&end=2999-04-30&level=XYZ',
            '/calendar?start=2999-04-01&end=2999-04-30&group=bad%20group',
        ] as $uri) {
            self::assertSame(['error' => 'invalid_parameter'], $this->getJson($uri, 400), $uri);
        }
    }

    // ------------------------------------------------------------------ ICS

    public function testApi02CompetitionCalendarIsValidIcsWithOneEventPerPublishedGameday(): void
    {
        $ics = $this->getIcs('/competition/2999/RCH/calendar.ics');

        $events = self::parseIcs($ics);
        self::assertSame(['gameday-9201@kayak-polo.info', 'gameday-9202@kayak-polo.info'], array_column($events, 'UID'));
        self::assertSame('29990301', $events[0]['DTSTART;VALUE=DATE']);
        self::assertSame('29990303', $events[0]['DTEND;VALUE=DATE'], 'DTEND exclusif : lendemain du dernier jour');
        self::assertSame('Championnat Résultats — RCH J1', $events[0]['SUMMARY']);
        self::assertSame('Lacville (33)', $events[0]['LOCATION']);
        self::assertStringEndsWith('/competitions/2999/RCH/games?gameday=9201', $events[0]['URL']);
        self::assertStringContainsString("X-WR-CALNAME:Championnat Résultats (2999)\r\n", $ics);
    }

    public function testApi02GamedayIcsAndStableUid(): void
    {
        $events = self::parseIcs($this->getIcs('/gameday/9212.ics'));

        self::assertCount(1, $events);
        self::assertSame('gameday-9212@kayak-polo.info', $events[0]['UID']);
        self::assertStringEndsWith('/competitions/2999/RCP', $events[0]['URL'], 'coupe : page de la compétition');
    }

    public function testApi02IcsContainsNoPersonalData(): void
    {
        $ics = $this->getIcs('/competition/2999/RCH/calendar.ics');

        foreach (['RESP', 'DELEGUE', 'CHEF', 'ARBITRE', 'ALPHA'] as $person) {
            self::assertStringNotContainsString($person, $ics);
        }
    }

    public function testApi02UnpublishedGamedaysAndCompetitionsAreNotFound(): void
    {
        foreach (['/gameday/9203.ics', '/gameday/9204.ics', '/gameday/9231.ics', '/competition/2999/RNP/calendar.ics'] as $uri) {
            $this->client->request('GET', $uri);
            self::assertSame(404, $this->client->getResponse()->getStatusCode(), $uri);
        }
    }

    // ------------------------------------------------------------------ historique

    public function testApi03HistoryListsGroupsHavingFinishedFinalsBySection(): void
    {
        $body = $this->getJson('/history');

        self::assertSame([['section' => 2, 'label' => 'Competitions_Nationales', 'groups' => [
            ['code' => 'TSTRES', 'libelle' => 'Groupe Résultats', 'libelle_en' => 'Results group'],
        ]]], $body['sections']);
    }

    public function testApi03HistoryOfAGroupShowsFinishedFinalsSeasonBySeasonWithMedals(): void
    {
        $body = $this->getJson('/history/TSTRES');

        self::assertSame(['code' => 'TSTRES', 'libelle' => 'Groupe Résultats', 'libelle_en' => 'Results group'], $body['group']);
        self::assertSame(['2999', '2998'], self::column($body['seasons'], 'season'));
        self::assertSame(['RCP', 'RMU'], self::column($body['seasons'][0]['competitions'], 'code'), 'RCH en cours, RAT en attente : absentes');
        self::assertSame(['RCP'], self::column($body['seasons'][1]['competitions'], 'code'), 'RQL n\'est pas du tour final');

        $cup = $body['seasons'][0]['competitions'][0];
        self::assertSame(['code', 'display_title', 'soustitre2', 'type', 'podium'], array_keys($cup));
        self::assertSame([1, 2, 3, 4], self::column($cup['podium'], 'rank'));
        self::assertSame([1, 2, 3, null], self::column($cup['podium'], 'medal'));
        self::assertSame(['number' => 111, 'label' => 'Equipe Echo', 'logo' => null], $cup['podium'][0]['team']);
    }

    public function testApi03UnknownOrEmptyHistoryIsNotFound(): void
    {
        self::assertSame(['error' => 'not_found'], $this->getJson('/history/TSTGRP', 404), 'aucune finale terminée');
        self::assertSame(['error' => 'not_found'], $this->getJson('/history/NOPE', 404));
        self::assertSame(['error' => 'invalid_parameter'], $this->getJson('/history/bad%20code', 400));
    }

    // ------------------------------------------------------------------ équipes

    public function testApi04TeamSearchNeedsTwoCharactersAndReturnsTwentyAtMost(): void
    {
        self::assertSame(['error' => 'invalid_parameter'], $this->getJson('/teams?q=a', 400));

        $teams = $this->getJson('/teams?q=alpha');
        self::assertSame([105, 101], self::column($teams, 'number'), 'tri par nom');
        self::assertSame(['number' => 101, 'label' => 'Equipe Alpha', 'club' => ['code' => 'C001', 'label' => 'Club Alpha Lacville']], $teams[1]);

        self::assertSame([105, 101], self::column($this->getJson('/teams?q=C001'), 'number'), 'code club');
        self::assertLessThanOrEqual(20, count($this->getJson('/teams?q=equipe')));
    }

    public function testApi05TeamSheetGivesClubHonoursAndSeasons(): void
    {
        $team = $this->getJson('/team/101');

        self::assertSame(['number', 'label', 'club', 'logo', 'colors', 'photo', 'honours', 'seasons'], array_keys($team));
        self::assertSame(['code' => 'C001', 'label' => 'Club Alpha Lacville'], $team['club']);
        self::assertNull($team['colors'], 'aucun fichier de couleurs dans l\'environnement de test');
        self::assertSame([
            ['season' => '2998', 'competition' => ['code' => 'RCP', 'display_title' => 'Coupe Résultats', 'group' => 'TSTRES'], 'rank' => 1, 'medal' => 1],
            ['season' => '2998', 'competition' => ['code' => 'RQL', 'display_title' => 'Qualification Résultats', 'group' => 'TSTRES'], 'rank' => 2, 'medal' => null],
        ], $team['honours'], 'RCH 2999 en cours : pas au palmarès ; RQL hors tour final : pas de médaille');
        self::assertSame(['2999', '2998'], self::column($team['seasons'], 'season'));
        self::assertSame([['code' => 'RCH', 'display_title' => 'Championnat Résultats']], $team['seasons'][0]['competitions']);
    }

    public function testApi05UnknownTeamIsNotFound(): void
    {
        self::assertSame(['error' => 'not_found'], $this->getJson('/team/9999', 404));
    }

    public function testApi06RosterExposesNoPersonalDataAndCountsOnlyValidatedPublishedGames(): void
    {
        $players = $this->getJson('/team/101/roster/2999/RCH')['players'];

        self::assertSame(['Ann', 'Bob', 'Coach'], self::column($players, 'first_name'), 'X exclu ; entraîneur après les joueurs');
        self::assertSame(
            ['first_name', 'last_name', 'number', 'category', 'role', 'goals', 'green', 'yellow', 'red', 'red_final'],
            array_keys($players[0]),
        );
        self::assertSame(['captain', null, 'coach'], self::column($players, 'role'));
        self::assertSame([2, 0, 0], self::column($players, 'goals'), 'buts de Bob : match non validé / non publié');
        self::assertSame([0, 1, 0], self::column($players, 'green'));

        $raw = (string) $this->client->getResponse()->getContent();
        foreach (['9501', 'Matric', 'Sexe', '"F"', '"M"'] as $personal) {
            self::assertStringNotContainsString($personal, $raw);
        }
    }

    public function testApi06RosterOfATeamNotEngagedIsNotFound(): void
    {
        self::assertSame(['error' => 'not_found'], $this->getJson('/team/101/roster/2999/RCP', 404));
        self::assertSame(['error' => 'not_found'], $this->getJson('/team/101/roster/2999/RNP', 404));
        self::assertSame(['error' => 'invalid_parameter'], $this->getJson('/team/101/roster/29/RCH', 400));
    }

    // ------------------------------------------------------------------ clubs

    public function testApi07ClubsListOnlyClubsHavingTeamsWithPositionAndLogo(): void
    {
        $clubs = $this->getJson('/clubs');

        self::assertNotContains('C099', self::column($clubs, 'code'), 'club sans équipe');
        $byCode = array_column($clubs, null, 'code');
        self::assertSame(['code', 'label', 'department', 'logo', 'position'], array_keys($byCode['C001']));
        self::assertSame(['lat' => 44.84, 'lng' => -0.58], $byCode['C001']['position']);
        self::assertSame(['code' => 'CDT33', 'label' => 'Comité Départemental Test 33'], $byCode['C001']['department']);
        self::assertNull($byCode['C002']['position']);
        self::assertNull($byCode['C004']['position'], 'position illisible');
    }

    public function testApi07ClubSearchMatchesNameOrCode(): void
    {
        self::assertSame(['C001'], self::column($this->getJson('/clubs?q=lacville'), 'code'));
        self::assertSame(['C003'], self::column($this->getJson('/clubs?q=saint%20malo'), 'code'), 'espaces → tirets');
        self::assertSame(['error' => 'invalid_parameter'], $this->getJson('/clubs?q=x', 400));
    }

    public function testApi07ClubSheetAndNotFound(): void
    {
        $club = $this->getJson('/club/C001');

        self::assertSame(['code', 'label', 'department', 'region', 'www', 'email', 'postal', 'position', 'logo', 'teams'], array_keys($club));
        self::assertSame(['code' => 'CRT', 'label' => 'Comité Régional Test'], $club['region']);
        self::assertSame('contact@alpha.example.test', $club['email']);
        self::assertSame([['number' => 105, 'label' => 'Alpha Deux'], ['number' => 101, 'label' => 'Equipe Alpha']], $club['teams']);
        self::assertNull($this->getJson('/club/C002')['www'], 'chaîne vide → null');

        self::assertSame(['error' => 'not_found'], $this->getJson('/club/C099', 404), 'club sans équipe');
        self::assertSame(['error' => 'not_found'], $this->getJson('/club/NOPE', 404));
        self::assertSame(['error' => 'invalid_parameter'], $this->getJson('/club/TOOLONG1', 400));
    }

    // ------------------------------------------------------------------ recherche globale

    public function testApi08SearchIgnoresAccentsAndCaseAndPutsActiveSeasonFirst(): void
    {
        $results = $this->getJson('/search?q=RESULTATS');

        self::assertSame(['competitions', 'events', 'teams', 'clubs'], array_keys($results));
        self::assertSame(
            ['2999/RCH', '2999/RCP', '2999/RMU', '2998/RQL', '2998/RCP'],
            array_map(static fn (array $c): string => $c['season'] . '/' . $c['code'], $results['competitions']),
            'RNP non publiée ; saison active d\'abord',
        );
        self::assertSame(['season', 'code', 'display_title', 'soustitre2', 'group'], array_keys($results['competitions'][0]));
        self::assertSame([77], self::column($results['events'], 'id'), '78 non publié');
        self::assertSame(['id', 'libelle', 'place', 'start', 'end'], array_keys($results['events'][0]));
    }

    public function testApi08SearchCapsEachCategoryAndNeverReturnsPeople(): void
    {
        self::assertCount(8, $this->getJson('/search?q=equipe')['teams']);

        $this->getJson('/search?q=alpha');
        $raw = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Club Alpha Lacville', $raw);
        self::assertStringNotContainsString('Ann', $raw, 'les joueurs ne sont pas indexés');
        self::assertStringNotContainsString('Club Sans Equipe', $this->rawGet('/search?q=sans'));
    }

    public function testApi08SearchRejectsTooShortOrTooLongQueries(): void
    {
        self::assertSame(['error' => 'invalid_parameter'], $this->getJson('/search?q=a', 400));
        self::assertSame(['error' => 'invalid_parameter'], $this->getJson('/search?q=' . str_repeat('x', 51), 400));
    }

    public function testApi08SearchIsRateLimitedPerPublicIp(): void
    {
        /** @var CacheItemPoolInterface $cache */
        $cache = static::getContainer()->get('cache.app');
        $cache->clear();

        for ($i = 1; $i <= 30; ++$i) {
            $this->client->request('GET', '/search?q=alpha', server: ['REMOTE_ADDR' => self::PUBLIC_IP]);
            self::assertSame(200, $this->client->getResponse()->getStatusCode(), "requête $i");
        }
        $this->client->request('GET', '/search?q=alpha', server: ['REMOTE_ADDR' => self::PUBLIC_IP]);
        $response = $this->client->getResponse();
        self::assertSame(429, $response->getStatusCode());
        self::assertSame(['error' => 'too_many_requests'], json_decode((string) $response->getContent(), true));
        self::assertGreaterThan(0, (int) $response->headers->get('Retry-After'));

        // L'adresse privée (rendu serveur d'app3) n'est pas limitée ; le visiteur qu'elle relaie l'est.
        $this->client->request('GET', '/search?q=alpha');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        $this->client->request('GET', '/search?q=alpha', server: ['REMOTE_ADDR' => '172.18.0.5', 'HTTP_X_FORWARDED_FOR' => self::PUBLIC_IP]);
        self::assertSame(429, $this->client->getResponse()->getStatusCode());
    }

    // ------------------------------------------------------------------ cache

    public function testApi09EveryResponseCarriesItsCacheControl(): void
    {
        foreach ([
            '/calendar?start=2999-04-01&end=2999-04-30' => 300,
            '/teams?q=alpha' => 300,
            '/team/101' => 300,
            '/team/101/roster/2999/RCH' => 300,
            '/search?q=alpha' => 300,
            '/history' => 3600,
            '/history/TSTRES' => 3600,
            '/clubs' => 3600,
            '/club/C001' => 3600,
            '/competition/2999/RCH/calendar.ics' => 3600,
            '/gameday/9201.ics' => 3600,
        ] as $uri => $maxAge) {
            $this->client->request('GET', $uri);
            $cacheControl = (string) $this->client->getResponse()->headers->get('Cache-Control');
            self::assertStringContainsString('public', $cacheControl, $uri);
            self::assertStringContainsString('max-age=' . $maxAge, $cacheControl, $uri);
        }
    }

    // ------------------------------------------------------------------ outils

    private function getIcs(string $uri): string
    {
        $this->client->request('GET', $uri);
        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode(), $uri);
        self::assertSame('text/calendar; charset=utf-8', $response->headers->get('Content-Type'));

        return (string) $response->getContent();
    }

    private function rawGet(string $uri): string
    {
        $this->client->request('GET', $uri);

        return (string) $this->client->getResponse()->getContent();
    }

    /**
     * Analyseur iCalendar minimal (RFC 5545) : vérifie les fins de ligne CRLF, le repli à 75 octets, l'équilibre
     * BEGIN/END et les propriétés obligatoires, puis renvoie les propriétés de chaque VEVENT.
     *
     * @return list<array<string, string>>
     */
    private static function parseIcs(string $ics): array
    {
        self::assertStringEndsWith("\r\n", $ics);
        self::assertDoesNotMatchRegularExpression('/(?<!\r)\n/', $ics, 'toutes les lignes en CRLF');
        foreach (explode("\r\n", rtrim($ics, "\r\n")) as $physical) {
            self::assertLessThanOrEqual(75, strlen($physical), 'ligne repliée à 75 octets : ' . $physical);
        }

        $lines = explode("\r\n", rtrim(preg_replace("/\r\n /", '', $ics) ?? '', "\r\n"));
        self::assertSame('BEGIN:VCALENDAR', $lines[0]);
        self::assertSame('END:VCALENDAR', end($lines));
        self::assertContains('VERSION:2.0', $lines);
        self::assertNotEmpty(array_filter($lines, static fn (string $line): bool => str_starts_with($line, 'PRODID:')));

        $events = [];
        $current = null;
        foreach ($lines as $line) {
            if ($line === 'BEGIN:VEVENT') {
                self::assertNull($current, 'VEVENT imbriqué');
                $current = [];
            } elseif ($line === 'END:VEVENT') {
                self::assertIsArray($current);
                foreach (['UID', 'DTSTAMP', 'DTSTART;VALUE=DATE', 'SUMMARY'] as $required) {
                    self::assertArrayHasKey($required, $current);
                }
                $events[] = $current;
                $current = null;
            } elseif ($current !== null) {
                [$name, $value] = explode(':', $line, 2);
                $current[$name] = $value;
            }
        }
        self::assertNull($current, 'VEVENT non fermé');

        return $events;
    }
}
