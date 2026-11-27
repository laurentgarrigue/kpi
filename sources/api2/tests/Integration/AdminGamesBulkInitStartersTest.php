<?php

namespace App\Tests\Integration;

/**
 * « Init titulaires » depuis la page Matchs : copie la composition d'équipe dans les feuilles
 * d'un périmètre (équipe, journée ou compétition), matchs verrouillés exclus.
 *
 * Données : SQL/fixtures/ — match 9404 (événement 77), le match 9411 est dans une autre journée.
 */
final class AdminGamesBulkInitStartersTest extends ApiTestCase
{
    private const ADMIN = 'TADMIN';

    /** @return array{team:int,journee:int,competition:string,season:string} */
    private function seed(): array
    {
        $db = $this->beginWrites();
        $m = $db->fetchAssociative(
            'SELECT m.Id_equipeA team, m.Id_journee journee, j.Code_competition competition, j.Code_saison season
             FROM kp_match m JOIN kp_journee j ON j.Id = m.Id_journee WHERE m.Id = 9404'
        );
        $matric = (int) $db->fetchOne('SELECT Matric FROM kp_licence LIMIT 1');
        $db->executeStatement('UPDATE kp_match SET Validation = \'N\' WHERE Id = 9404');
        $db->executeStatement('DELETE FROM kp_competition_equipe_joueur WHERE Id_equipe = ?', [$m['team']]);
        $db->executeStatement(
            "INSERT INTO kp_competition_equipe_joueur (Id_equipe, Matric, Nom, Prenom, Sexe, Categ, Numero, Capitaine) VALUES (?, ?, 'N', 'P', 'M', 'S', 7, 'C')",
            [$m['team'], $matric]
        );
        $db->executeStatement('DELETE FROM kp_match_joueur WHERE Id_match = 9404');

        return ['team' => (int) $m['team'], 'journee' => (int) $m['journee'], 'competition' => $m['competition'], 'season' => $m['season']];
    }

    private function rosterSize(): int
    {
        return (int) static::getContainer()->get(\Doctrine\DBAL\Connection::class)
            ->fetchOne('SELECT COUNT(*) FROM kp_match_joueur WHERE Id_match = 9404 AND Equipe = \'A\'');
    }

    public function testTeamScopeFillsTheSheetsOfThatTeam(): void
    {
        $s = $this->seed();

        $res = $this->requestAs(self::ADMIN, 'POST', '/admin/games/bulk/init-starters', ['season' => $s['season'], 'scope' => 'team', 'value' => (string) $s['team']]);

        self::assertGreaterThanOrEqual(1, $res['matchCount']);
        self::assertSame(1, $this->rosterSize());
    }

    public function testJourneeAndCompetitionScopes(): void
    {
        $s = $this->seed();
        $this->requestAs(self::ADMIN, 'POST', '/admin/games/bulk/init-starters', ['season' => $s['season'], 'scope' => 'journee', 'value' => (string) $s['journee']]);
        self::assertSame(1, $this->rosterSize());

        static::getContainer()->get(\Doctrine\DBAL\Connection::class)->executeStatement('DELETE FROM kp_match_joueur WHERE Id_match = 9404');
        $this->requestAs(self::ADMIN, 'POST', '/admin/games/bulk/init-starters', ['season' => $s['season'], 'scope' => 'competition', 'value' => $s['competition']]);
        self::assertSame(1, $this->rosterSize());
    }

    public function testLockedGamesAreLeftUntouched(): void
    {
        $s = $this->seed();
        static::getContainer()->get(\Doctrine\DBAL\Connection::class)->executeStatement("UPDATE kp_match SET Validation = 'O' WHERE Id = 9404");

        $this->requestAs(self::ADMIN, 'POST', '/admin/games/bulk/init-starters', ['season' => $s['season'], 'scope' => 'team', 'value' => (string) $s['team']]);

        self::assertSame(0, $this->rosterSize());
    }

    public function testUnknownScopeIsRejected(): void
    {
        $this->requestAs(self::ADMIN, 'POST', '/admin/games/bulk/init-starters', ['season' => '2999', 'scope' => 'planet', 'value' => 'x'], 400);
    }
}
