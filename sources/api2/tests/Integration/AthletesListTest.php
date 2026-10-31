<?php

namespace App\Tests\Integration;

use Doctrine\DBAL\Connection;

/**
 * Retrouver les joueurs à compléter (CATEGORIE_AGE.md stories 28-30, PAGE_ATHLETES.md § 2.6): GET /admin/athletes
 * filters « date de naissance inconnue », « joueur international » (Matric > 2000000) and « engagé cette saison »
 * (composition of a team of the active season, 2999 in the fixtures), combinable, with the total.
 * Every athlete below is named LISTE; the tests narrow the list with q=LISTE to ignore the other fixtures.
 */
final class AthletesListTest extends ApiTestCase
{
    private const INTERNATIONAL_UNKNOWN_ENGAGED = 2100001;
    private const INTERNATIONAL_CONVENTIONAL_PAST_SEASON = 2100002;
    private const INTERNATIONAL_KNOWN_ENGAGED = 2100003;
    private const FEDERAL_UNKNOWN_ENGAGED = 9601;
    private const FEDERAL_KNOWN = 9602;

    private const ACTIVE_SEASON_TEAM = 9481;
    private const PAST_SEASON_TEAM = 9482;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->beginWrites();
        $this->insertAthlete(self::INTERNATIONAL_UNKNOWN_ENGAGED, 'Alice', null);
        $this->insertAthlete(self::INTERNATIONAL_CONVENTIONAL_PAST_SEASON, 'Bruno', '1901-01-01');
        $this->insertAthlete(self::INTERNATIONAL_KNOWN_ENGAGED, 'Chloé', '2980-01-01');
        $this->insertAthlete(self::FEDERAL_UNKNOWN_ENGAGED, 'Denis', null);
        $this->insertAthlete(self::FEDERAL_KNOWN, 'Emma', '2981-01-01');

        $this->insertTeam(self::ACTIVE_SEASON_TEAM, '2999');
        $this->insertTeam(self::PAST_SEASON_TEAM, '2998');
        $this->engage(self::ACTIVE_SEASON_TEAM, self::INTERNATIONAL_UNKNOWN_ENGAGED);
        $this->engage(self::ACTIVE_SEASON_TEAM, self::INTERNATIONAL_KNOWN_ENGAGED);
        $this->engage(self::ACTIVE_SEASON_TEAM, self::FEDERAL_UNKNOWN_ENGAGED);
        $this->engage(self::PAST_SEASON_TEAM, self::INTERNATIONAL_CONVENTIONAL_PAST_SEASON);
    }

    public function testListWithoutFilterGivesEveryAthleteSortedByNameWithTheTotal(): void
    {
        $list = $this->list('');

        self::assertSame(5, $list['total']);
        self::assertSame([
            self::INTERNATIONAL_UNKNOWN_ENGAGED,
            self::INTERNATIONAL_CONVENTIONAL_PAST_SEASON,
            self::INTERNATIONAL_KNOWN_ENGAGED,
            self::FEDERAL_UNKNOWN_ENGAGED,
            self::FEDERAL_KNOWN,
        ], self::column($list['items'], 'matric'));
    }

    public function testManagerFiltersOnUnknownBirthDateIncludingConventionalDates(): void
    {
        $list = $this->list('&unknown_birthdate=1');

        self::assertSame(3, $list['total']);
        self::assertSame([
            self::INTERNATIONAL_UNKNOWN_ENGAGED,
            self::INTERNATIONAL_CONVENTIONAL_PAST_SEASON,
            self::FEDERAL_UNKNOWN_ENGAGED,
        ], self::column($list['items'], 'matric'));
        self::assertSame([null, null, null], self::column($list['items'], 'naissance'), 'a conventional date is shown as unknown');
    }

    public function testManagerFiltersOnInternationalPlayers(): void
    {
        $list = $this->list('&international=1');

        self::assertSame(3, $list['total']);
        self::assertSame([
            self::INTERNATIONAL_UNKNOWN_ENGAGED,
            self::INTERNATIONAL_CONVENTIONAL_PAST_SEASON,
            self::INTERNATIONAL_KNOWN_ENGAGED,
        ], self::column($list['items'], 'matric'));
    }

    public function testManagerFiltersOnPlayersEngagedThisSeason(): void
    {
        $list = $this->list('&engaged_this_season=1');

        self::assertSame(3, $list['total']);
        self::assertSame([
            self::INTERNATIONAL_UNKNOWN_ENGAGED,
            self::INTERNATIONAL_KNOWN_ENGAGED,
            self::FEDERAL_UNKNOWN_ENGAGED,
        ], self::column($list['items'], 'matric'));
    }

    public function testManagerCombinesTheThreeFiltersToTreatCurrentInternationalsFirst(): void
    {
        $list = $this->list('&unknown_birthdate=1&international=1&engaged_this_season=1');

        self::assertSame(1, $list['total']);
        self::assertSame([self::INTERNATIONAL_UNKNOWN_ENGAGED], self::column($list['items'], 'matric'));
    }

    public function testTotalCountsEveryMatchingAthleteAcrossPages(): void
    {
        $firstPage = $this->list('&unknown_birthdate=1&limit=2&page=1');
        $secondPage = $this->list('&unknown_birthdate=1&limit=2&page=2');

        self::assertSame(3, $firstPage['total']);
        self::assertSame(3, $secondPage['total']);
        self::assertSame([self::INTERNATIONAL_UNKNOWN_ENGAGED, self::INTERNATIONAL_CONVENTIONAL_PAST_SEASON], self::column($firstPage['items'], 'matric'));
        self::assertSame([self::FEDERAL_UNKNOWN_ENGAGED], self::column($secondPage['items'], 'matric'));
        self::assertSame(2, $secondPage['page']);
        self::assertSame(2, $secondPage['limit']);
    }

    public function testPlayerLeavesTheUnknownBirthDateFilterOnceHisDateIsCorrected(): void
    {
        $this->requestAs('TADMIN', 'PUT', '/admin/athletes/' . self::INTERNATIONAL_UNKNOWN_ENGAGED, [
            'nom' => 'LISTE',
            'prenom' => 'Alice',
            'sexe' => 'F',
            'naissance' => '2980-06-15',
            'origine' => '2999',
        ]);

        $list = $this->list('&unknown_birthdate=1');

        self::assertSame(2, $list['total']);
        self::assertNotContains(self::INTERNATIONAL_UNKNOWN_ENGAGED, self::column($list['items'], 'matric'));
    }

    /** @return array{items: list<array<string, mixed>>, total: int, page: int, limit: int} */
    private function list(string $filters): array
    {
        return $this->requestAs('TADMIN', 'GET', '/admin/athletes?q=LISTE' . $filters);
    }

    private function insertAthlete(int $matric, string $prenom, ?string $naissance): void
    {
        $this->connection->insert('kp_licence', [
            'Matric' => $matric, 'Origine' => '2999', 'Nom' => 'LISTE', 'Prenom' => $prenom, 'Sexe' => 'F',
            'Numero_club' => 'C001', 'Etat' => 'Actif', 'Naissance' => $naissance,
        ]);
    }

    private function insertTeam(int $teamId, string $season): void
    {
        $this->connection->insert('kp_competition_equipe', [
            'Id' => $teamId, 'Code_compet' => 'TSTCH', 'Code_saison' => $season, 'Libelle' => 'Liste ' . $season,
            'Code_club' => 'C001', 'Numero' => 181,
        ]);
    }

    private function engage(int $teamId, int $matric): void
    {
        $this->connection->insert('kp_competition_equipe_joueur', [
            'Id_equipe' => $teamId, 'Matric' => $matric, 'Nom' => 'LISTE', 'Prenom' => '', 'Sexe' => 'F', 'Numero' => 1, 'Capitaine' => '-',
        ]);
    }
}
