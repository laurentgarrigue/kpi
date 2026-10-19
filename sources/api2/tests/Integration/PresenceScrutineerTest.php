<?php

namespace App\Tests\Integration;

use Doctrine\DBAL\Connection;

/**
 * Feuille de présence équipe gérée par le profil 10 « Contrôle équipement » (PAGE_SCRUTINEERING.md § 2 et § 6).
 * Fixtures : SCA (9381 Alpha C001, 9382 Bravo C002), SCB verrouillée (9384 Alpha Deux C001, même équipe de club).
 * Comptes : TSCRUT (profil 10, SCA + SCB, Limitation C002 ignorée), TSCRUTA (profil 10, SCA seule), TCLUB (profil 7,
 * club C002).
 */
final class PresenceScrutineerTest extends ApiTestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->beginWrites();
    }

    public function testAdmScr01TheScrutineerHasNoGeneralConsultation(): void
    {
        $this->requestAs('TSCRUT', 'GET', '/admin/competition-teams?season=2997', expectedStatus: 403);
        $this->requestAs('TSCRUT', 'GET', '/admin/competitions?season=2997', expectedStatus: 403);
        $this->requestAs('TSCRUT', 'GET', '/admin/matches/9401/players?teamCode=A', expectedStatus: 403);
    }

    public function testAdmScr05TheScrutineerReadsAndEditsEveryTeamOfItsCompetitions(): void
    {
        $sheet = $this->requestAs('TSCRUT', 'GET', '/admin/teams/9381/players');
        self::assertTrue($sheet['canEdit']);
        self::assertSame([9511, 9512, 9513, 9514], self::column($sheet['players'], 'matric'));

        $this->requestAs('TSCRUT', 'PATCH', '/admin/teams/9381/players/9512', ['numero' => 12]);
        self::assertSame(12, (int) $this->connection->fetchOne('SELECT Numero FROM kp_competition_equipe_joueur WHERE Id_equipe = 9381 AND Matric = 9512'));
    }

    public function testAdmScr05AddDeleteCreateAndSearchAreAllowed(): void
    {
        self::assertSame([9516], self::column($this->requestAs('TSCRUT', 'GET', '/admin/players/search?q=Rempla')['players'], 'matric'));
        self::assertContains(9516, self::column($this->requestAs('TSCRUT', 'GET', '/admin/operations/autocomplete/players?q=SCRUT'), 'matric'));

        $this->requestAs('TSCRUT', 'POST', '/admin/teams/9381/players/add', ['mode' => 'existing', 'matric' => 9516, 'capitaine' => '-'], 201);
        self::assertSame(
            ['Users' => 'TSCRUT', 'Saisons' => '2997', 'Competitions' => 'SCA', 'Journal' => 'Equipe 9381 - Joueur 9516'],
            $this->connection->fetchAssociative("SELECT Users, Saisons, Competitions, Journal FROM kp_journal WHERE Actions = 'Ajout titulaire'"),
        );
        $created = $this->requestAs('TSCRUT', 'POST', '/admin/teams/9381/players/add', ['mode' => 'create', 'nom' => 'NOUVEAU', 'prenom' => 'Joueur', 'capitaine' => '-'], 201);
        $this->requestAs('TSCRUT', 'DELETE', '/admin/teams/9381/players', ['matricIds' => [9516, $created['matric']]]);

        self::assertSame(4, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM kp_competition_equipe_joueur WHERE Id_equipe = 9381'));
    }

    public function testAdmScr05CopyFromAnotherCompetitionIsAllowed(): void
    {
        self::assertSame(['SCB'], self::column($this->requestAs('TSCRUT', 'GET', '/admin/competition-teams/9381/compositions')['compositions'], 'competitionCode'));

        $this->requestAs('TSCRUT', 'POST', '/admin/teams/9381/players/copy', ['sourceCompetition' => 'SCB']);
        self::assertSame([9515], array_map('intval', $this->connection->fetchFirstColumn('SELECT Matric FROM kp_competition_equipe_joueur WHERE Id_equipe = 9381')));
    }

    public function testTheLastUpdateOfThePresenceSheetIgnoresTheScrutineering(): void
    {
        $this->requestAs('TSCRUT', 'PUT', '/staff/80/team/9381/player/9512/kayak_status/1');

        self::assertNull($this->requestAs('TSCRUT', 'GET', '/admin/teams/9381/players')['lastUpdate']);
    }

    public function testAdmScr06ALockedCompetitionKeepsThePresenceSheetReadOnly(): void
    {
        self::assertFalse($this->requestAs('TSCRUT', 'GET', '/admin/teams/9384/players')['canEdit']);
        $this->requestAs('TSCRUT', 'PATCH', '/admin/teams/9384/players/9515', ['numero' => 4], 403);
    }

    public function testAdmScr04CompetitionsOutsideThePerimeterAreRefused(): void
    {
        $this->requestAs('TSCRUTA', 'GET', '/admin/teams/9384/players', expectedStatus: 403);
        $this->requestAs('TSCRUTA', 'POST', '/admin/teams/9384/players/add', ['mode' => 'existing', 'matric' => 9516], 403);
    }

    public function testTheClubLimitationStillAppliesToProfileSeven(): void
    {
        self::assertFalse($this->requestAs('TCLUB', 'GET', '/admin/teams/9381/players')['canEdit']);
        $this->requestAs('TCLUB', 'PATCH', '/admin/teams/9381/players/9512', ['numero' => 12], 403);
    }
}
