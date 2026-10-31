<?php

namespace App\Tests\Integration;

use Doctrine\DBAL\Connection;

/**
 * Age category of a player added to or copied into a composition (CATEGORIE_AGE.md stories 17 and 18): derived from
 * the birth date and the season of the destination team, none when the birth date is unknown.
 * Fixtures: 9381 (SCA 2997, club C001, numero 101: 9511 to 9514 stored 'U21'/'SEN'), 9516 (no birth date),
 * 9518 (born 2980-03-15). Here: 9511 born in 2979 (JUN in 2997, SEN in 2998) and team 9387, SCA 2998 of the same club.
 */
final class CompositionPlayerAgeCategoryTest extends ApiTestCase
{
    private const SOURCE_TEAM = 9381;
    private const NEXT_SEASON_TEAM = 9387;
    private const CLUB_TEAM = 101;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->beginWrites();
        $this->connection->executeStatement("UPDATE kp_licence SET Naissance = '2979-05-01' WHERE Matric = 9511");
        $this->connection->insert('kp_competition', [
            'Code' => 'SCA', 'Code_saison' => '2998', 'Code_niveau' => 'INT', 'Libelle' => 'Scrutineering Hommes',
            'Code_ref' => 'TSTSCR', 'Code_typeclt' => 'CHPT', 'Statut' => 'ON', 'Publication' => 'N', 'Verrou' => 'N',
        ]);
    }

    public function testStory17AnAddedPlayerGetsTheAgeCategoryOfTheSeasonOfTheTeam(): void
    {
        $this->requestAs('TADMIN', 'POST', '/admin/teams/9381/players/add', ['mode' => 'existing', 'matric' => 9518, 'capitaine' => '-', 'forceAdd' => true], 201);

        self::assertSame('JUN', $this->categoryOf(self::SOURCE_TEAM, 9518));
    }

    public function testStory17APlayerWithAnUnknownBirthDateIsAddedWithoutAgeCategory(): void
    {
        $this->requestAs('TADMIN', 'POST', '/admin/teams/9381/players/add', ['mode' => 'existing', 'matric' => 9516, 'capitaine' => '-', 'forceAdd' => true], 201);

        self::assertNull($this->categoryOf(self::SOURCE_TEAM, 9516));
    }

    public function testStory18CopyingACompositionToTheNextSeasonRecomputesEachAgeCategoryForThatSeason(): void
    {
        $this->connection->insert('kp_competition_equipe', [
            'Id' => self::NEXT_SEASON_TEAM, 'Code_compet' => 'SCA', 'Code_saison' => '2998', 'Libelle' => 'Scrut Alpha',
            'Code_club' => 'C001', 'Numero' => self::CLUB_TEAM, 'Poule' => '', 'Tirage' => 0,
        ]);

        $this->requestAs('TADMIN', 'POST', '/admin/teams/' . self::NEXT_SEASON_TEAM . '/players/copy', ['sourceCompetition' => 'SCA', 'sourceSeason' => '2997']);

        $this->assertCategoriesOfTheNextSeason(self::NEXT_SEASON_TEAM);
    }

    public function testStory18DuplicatingTheTeamsWithTheirPlayersToTheNextSeasonRecomputesEachAgeCategory(): void
    {
        $this->requestAs('TADMIN', 'POST', '/admin/competition-teams/duplicate', [
            'season' => '2998', 'targetCompetition' => 'SCA', 'sourceSeason' => '2997', 'sourceCompetition' => 'SCA', 'copyPlayers' => true,
        ]);

        $this->assertCategoriesOfTheNextSeason($this->nextSeasonTeamId());
    }

    public function testStory18EngagingATeamWithTheCompositionOfAPreviousSeasonRecomputesEachAgeCategory(): void
    {
        $this->requestAs('TADMIN', 'POST', '/admin/competition-teams', [
            'season' => '2998', 'competition' => 'SCA', 'mode' => 'history', 'teamNumbers' => [self::CLUB_TEAM],
            'copyComposition' => ['season' => '2997', 'competition' => 'SCA'],
        ], 201);

        $this->assertCategoriesOfTheNextSeason($this->nextSeasonTeamId());
    }

    /** 9511 born 2979 is SEN in 2998 (stored 'U21' in the source); 9512 has no birth date (stored 'U21'). */
    private function assertCategoriesOfTheNextSeason(int $teamId): void
    {
        self::assertSame('SEN', $this->categoryOf($teamId, 9511));
        self::assertNull($this->categoryOf($teamId, 9512), 'unknown birth date: no category, not the source one');
    }

    private function nextSeasonTeamId(): int
    {
        return (int) $this->connection->fetchOne(
            "SELECT Id FROM kp_competition_equipe WHERE Code_compet = 'SCA' AND Code_saison = '2998' AND Numero = ?",
            [self::CLUB_TEAM]
        );
    }

    private function categoryOf(int $teamId, int $matric): ?string
    {
        $categ = $this->connection->fetchOne('SELECT Categ FROM kp_competition_equipe_joueur WHERE Id_equipe = ? AND Matric = ?', [$teamId, $matric]);
        self::assertNotFalse($categ, "player $matric is not in team $teamId");

        return $categ === null ? null : (string) $categ;
    }
}
