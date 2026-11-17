<?php

namespace App\Tests\Integration;

use Doctrine\DBAL\Connection;

/**
 * Feuille de présence match (PAGE_PRESENCE.md § 1.2 et § 3.2) : éditée par les profils ≤ 7 et la table de marque
 * (profil 9), en lecture seule pour le profil 8 ; on n'y ajoute qu'un joueur de la feuille de présence de l'équipe.
 * Fixtures « copie de composition de match » : 9472 (J1, 9388 en A), 9474 (validé). Feuille de présence de 9388 :
 * 9513 (E), 9516 (C) et 9518 (-) ajoutables, 9514 (A), 9517 (X) non ajoutables ; 9511 n'y figure pas.
 * Comptes TTEAMP (7), TVIEWP (8), TSCOREP (9), TSCRUTA (10).
 */
final class PresenceMatchSheetTest extends ApiTestCase
{
    private const TEAM_A = ['teamCode' => 'A'];

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->beginWrites();
    }

    public function testTheScoreTableReadsTheMatchSheetButNotTheTeamSheet(): void
    {
        self::assertSame([], $this->requestAs('TSCOREP', 'GET', '/admin/matches/9472/players?teamCode=A')['players']);
        $this->requestAs('TSCOREP', 'GET', '/admin/teams/9388/players', expectedStatus: 403);
        $this->requestAs('TSCRUTA', 'GET', '/admin/matches/9472/players?teamCode=A', expectedStatus: 403);
    }

    public function testOnlyThePlayersOfThePresenceSheetCanBeAdded(): void
    {
        $available = $this->requestAs('TSCOREP', 'GET', '/admin/matches/9472/available-players?teamCode=A')['players'];

        $matrics = self::column($available, 'matric');
        sort($matrics);
        self::assertSame([9513, 9516, 9518], $matrics, 'coaches included, referees and inactive players excluded');
    }

    public function testTheScoreTableEditsTheMatchComposition(): void
    {
        $this->requestAs('TSCOREP', 'POST', '/admin/matches/9472/players/initialize', self::TEAM_A);
        self::assertSame([9513, 9516, 9518], $this->matchRoster(9472), 'the initialization keeps the coaches (E)');

        $this->requestAs('TSCOREP', 'PATCH', '/admin/matches/9472/players/9518', self::TEAM_A + ['numero' => 12]);
        self::assertSame(12, (int) $this->connection->fetchOne('SELECT Numero FROM kp_match_joueur WHERE Id_match = 9472 AND Matric = 9518'));

        $this->requestAs('TSCOREP', 'DELETE', '/admin/matches/9472/players', self::TEAM_A + ['matricIds' => [9513, 9518]]);
        $this->requestAs('TSCOREP', 'POST', '/admin/matches/9472/players/add', self::TEAM_A + ['matric' => 9518], 201);
        $this->requestAs('TSCOREP', 'POST', '/admin/matches/9472/players/add', self::TEAM_A + ['matric' => 9513, 'capitaine' => 'E'], 201);
        self::assertSame([9513, 9516, 9518], $this->matchRoster(9472));

        $this->requestAs('TSCOREP', 'DELETE', '/admin/matches/9472/players/clear', self::TEAM_A);
        self::assertSame([], $this->matchRoster(9472));
    }

    public function testAPlayerOutsideThePresenceSheetIsRefused(): void
    {
        $this->requestAs('TTEAMP', 'POST', '/admin/matches/9472/players/add', self::TEAM_A + ['matric' => 9511], 422);
        $this->requestAs('TTEAMP', 'POST', '/admin/matches/9472/players/add', self::TEAM_A + ['matric' => 9517], 422);
        $this->requestAs('TTEAMP', 'POST', '/admin/matches/9472/players/add', self::TEAM_A + ['matric' => 9514], 422);

        self::assertSame([], $this->matchRoster(9472));
    }

    public function testTheScoreTableDoesNotCopyToOtherMatches(): void
    {
        $copy = ['teamCode' => 'A', 'matchIds' => [9472]];
        $this->requestAs('TSCOREP', 'POST', '/admin/matches/9471/players/copy-to-day', $copy, 403);
        $this->requestAs('TSCOREP', 'POST', '/admin/matches/9471/players/copy-to-competition', $copy, 403);
    }

    public function testConsultationAndValidatedMatchesStayReadOnly(): void
    {
        $this->requestAs('TVIEWP', 'GET', '/admin/matches/9472/players?teamCode=A');
        $this->requestAs('TVIEWP', 'POST', '/admin/matches/9472/players/add', self::TEAM_A + ['matric' => 9516], 403);
        $this->requestAs('TSCOREP', 'POST', '/admin/matches/9474/players/initialize', self::TEAM_A, 403);
    }

    /** @return list<int> */
    private function matchRoster(int $matchId): array
    {
        return array_map('intval', $this->connection->fetchFirstColumn(
            "SELECT Matric FROM kp_match_joueur WHERE Id_match = ? AND Equipe = 'A' ORDER BY Matric",
            [$matchId],
        ));
    }
}
