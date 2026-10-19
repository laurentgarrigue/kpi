<?php

namespace App\Tests\Integration;

use Doctrine\DBAL\Connection;

/**
 * Scrutineering d'app4 sur les routes /staff en JWT (PAGE_SCRUTINEERING.md § 5). Fixtures : événement 80, SCA
 * (9381 Alpha C001, 9382 Bravo C002, 9383 Germany GER), SCB verrouillée (9384 Alpha Deux C001, 9385 GER-W).
 * Comptes : TSCRUT (profil 10, SCA + SCB, club C002 ignoré), TSCRUTA (profil 10, SCA seule), TDIV (profil 3),
 * TADMIN (profil 2).
 */
final class StaffScrutineeringTest extends ApiTestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->beginWrites();
    }

    public function testAdmScr02OnlyProfiles1To2AndTheScrutineerReachTheRoutes(): void
    {
        $this->requestAs('TDIV', 'GET', '/staff/80/teams', expectedStatus: 403);
        self::assertCount(5, $this->requestAs('TADMIN', 'GET', '/staff/80/teams'));
        self::assertCount(5, $this->requestAs('TSCRUT', 'GET', '/staff/80/teams'));
    }

    public function testAdmScr03TheApp2TokenIsNoLongerAccepted(): void
    {
        $this->client->request('GET', '/staff/80/teams', server: ['HTTP_X_AUTH_TOKEN' => 'app2-token']);
        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    public function testAdmScr04EventAndCompetitionPerimeter(): void
    {
        // TSCRUT is limited to event 80
        $this->requestAs('TSCRUT', 'GET', '/staff/77/teams', expectedStatus: 403);
        // TSCRUTA only sees SCA, and gets a 404 on a SCB team
        self::assertSame([9381, 9382, 9383], self::column($this->requestAs('TSCRUTA', 'GET', '/staff/80/teams'), 'team_id'));
        $this->requestAs('TSCRUTA', 'GET', '/staff/80/team/9384/players', expectedStatus: 404);
        $this->requestAs('TSCRUTA', 'PUT', '/staff/80/team/9384/player/9515/kayak_status/1', expectedStatus: 404);
        // a team that is not part of the event
        $this->requestAs('TSCRUT', 'GET', '/staff/80/team/9301/players', expectedStatus: 404);
    }

    public function testAdmScr15And16TeamsCarryClubAndNationForTheNavigation(): void
    {
        $teams = array_column($this->requestAs('TSCRUT', 'GET', '/staff/80/teams'), null, 'team_id');

        self::assertSame(['SCA', 'Scrutineering Hommes', 'C001', 'Club Alpha Lacville', 'FRA'], [
            $teams[9381]['competition_code'], $teams[9381]['competition_label'], $teams[9381]['club'],
            $teams[9381]['club_label'], $teams[9381]['nation'],
        ]);
        self::assertSame('C001', $teams[9384]['club']);
        self::assertSame(['GER', 'GER'], [$teams[9383]['nation'], $teams[9385]['nation']]);
    }

    public function testAdmScr10PlayersHideRefereesAndInactivesAndCarryTheTeamContext(): void
    {
        $data = $this->requestAs('TSCRUT', 'GET', '/staff/80/team/9381/players');

        self::assertSame([9511, 9512, 9513], self::column($data['players'], 'player_id'));
        self::assertSame(['E', 'RAS'], [$data['players'][2]['cap'], $data['players'][0]['comment']]);
        self::assertSame(
            ['team_id' => 9381, 'label' => 'Scrut Alpha', 'club' => 'C001', 'club_label' => 'Club Alpha Lacville', 'nation' => 'FRA',
                'logo' => null, 'competition_code' => 'SCA', 'competition_label' => 'Scrutineering Hommes', 'season' => '2997', 'locked' => false],
            $data['team'],
        );
    }

    public function testAdmScr08EquipmentIsPersistedAndBounded(): void
    {
        self::assertSame(['value' => 4], $this->requestAs('TSCRUT', 'PUT', '/staff/80/team/9381/player/9512/kayak_status/4'));
        self::assertSame(['value' => 6], $this->requestAs('TSCRUT', 'PUT', '/staff/80/team/9381/player/9512/paddle_count/6'));
        self::assertSame(
            ['kayak_status' => 4, 'paddle_count' => 6],
            array_map('intval', $this->connection->fetchAssociative('SELECT kayak_status, paddle_count FROM kp_scrutineering WHERE id_equipe = 9381 AND matric = 9512')),
        );

        $this->requestAs('TSCRUT', 'PUT', '/staff/80/team/9381/player/9512/kayak_status/5', expectedStatus: 400);
        $this->requestAs('TSCRUT', 'PUT', '/staff/80/team/9381/player/9512/paddle_count/7', expectedStatus: 400);
        // not a player of the composition
        $this->requestAs('TSCRUT', 'PUT', '/staff/80/team/9381/player/9515/kayak_status/1', expectedStatus: 404);
    }

    public function testAdmScr06EquipmentStaysEditableWhenTheCompetitionIsLocked(): void
    {
        $this->requestAs('TSCRUT', 'PUT', '/staff/80/team/9384/player/9515/vest_status/1');
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT vest_status FROM kp_scrutineering WHERE id_equipe = 9384 AND matric = 9515'));
    }

    public function testAdmScr11CommentIsStoredAsTextAndCappedAt255Characters(): void
    {
        $comment = "Pagaie à revoir, l'embout est fendu " . str_repeat('x', 300);
        $data = $this->requestAs('TSCRUT', 'PUT', '/staff/80/team/9381/player/9512/comment', ['comment' => $comment]);

        self::assertSame(mb_substr($comment, 0, 255), $data['comment']);
        self::assertSame($data['comment'], $this->connection->fetchOne('SELECT comment FROM kp_scrutineering WHERE id_equipe = 9381 AND matric = 9512'));
    }

    public function testAdmScr12ResetClearsEveryCheckOfTheTeam(): void
    {
        self::assertSame(['reset' => true, 'deleted' => 2], $this->requestAs('TSCRUT', 'DELETE', '/staff/80/team/9381/reset'));
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM kp_scrutineering WHERE id_equipe = 9381'));
    }

    public function testAdmScr13EveryActionIsJournalisedWithEventSeasonAndCompetition(): void
    {
        $this->requestAs('TSCRUT', 'PUT', '/staff/80/team/9381/player/9512/helmet_status/2');
        $this->requestAs('TSCRUT', 'PUT', '/staff/80/team/9381/player/9512/comment', ['comment' => 'Casque fendu']);
        $this->requestAs('TSCRUT', 'DELETE', '/staff/80/team/9381/reset');

        $rows = $this->connection->fetchAllAssociative(
            "SELECT Users, Actions, Evenements, Saisons, Competitions, Journal FROM kp_journal WHERE Actions LIKE 'Scrut%' ORDER BY Id"
        );
        self::assertSame([
            ['Users' => 'TSCRUT', 'Actions' => 'Scrut équipement', 'Evenements' => '80', 'Saisons' => '2997', 'Competitions' => 'SCA', 'Journal' => 'Equipe 9381 - Joueur 9512 - casque=2'],
            ['Users' => 'TSCRUT', 'Actions' => 'Scrut commentaire', 'Evenements' => '80', 'Saisons' => '2997', 'Competitions' => 'SCA', 'Journal' => 'Equipe 9381 - Joueur 9512'],
            ['Users' => 'TSCRUT', 'Actions' => 'Scrut réinitialisation', 'Evenements' => '80', 'Saisons' => '2997', 'Competitions' => 'SCA', 'Journal' => 'Equipe 9381 - réinitialisation complète'],
        ], array_map(static fn (array $row): array => array_map('strval', $row), $rows));
    }

    public function testAdmScr14OverviewStatusesWithinTheAllowedCompetitions(): void
    {
        $overview = $this->requestAs('TSCRUT', 'GET', '/staff/80/overview');
        $statuses = array_column($overview['teams'], 'status', 'team_id');

        self::assertSame(['U21 F', 'U21 H'], $overview['categories']);
        // 9381: one complete player and one partial → partial; 9382: a row reset to 0 → none
        self::assertSame(['partial', 'none', 'none'], [$statuses[9381], $statuses[9382], $statuses[9383]]);

        $restricted = $this->requestAs('TSCRUTA', 'GET', '/staff/80/overview');
        self::assertSame([9381, 9382, 9383], self::column($restricted['teams'], 'team_id'));
    }

    public function testAppTwoCompositionRoutesAreGone(): void
    {
        $this->requestAs('TSCRUT', 'PUT', '/staff/80/team/9381/composition/9512', ['numero' => 5], 404);
        $this->requestAs('TSCRUT', 'POST', '/staff/80/team/9381/player', ['matric' => 9515], 404);
        $this->requestAs('TSCRUT', 'GET', '/staff/80/team/9381/players/search?q=scr', expectedStatus: 404);
    }
}
