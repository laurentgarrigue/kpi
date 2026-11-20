<?php

namespace App\Tests\Integration;

use Doctrine\DBAL\Connection;

/**
 * « Copier depuis une autre compétition » ouvert aux profils ≤ 7 et au profil 10 (PAGE_PRESENCE.md § 1.2, issue #399).
 * Fixtures : SCA (9381 Alpha C001, 9382 Bravo C002), SCB verrouillée (9384 Alpha Deux C001, même équipe de club que
 * 9381, joueur 9515), SCC (9386 Charlie C001, autre numéro d'équipe).
 * Comptes : TCLUBA (profil 7, SCA + SCB, club C001), TCLUB (profil 7, SCA + SCB, club C002), TVIEW (profil 8).
 */
final class PresenceCompositionCopyTest extends ApiTestCase
{
    private const COPY_FROM_SCB = ['sourceCompetition' => 'SCB'];

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->beginWrites();
    }

    public function testProfileSevenCopiesTheCompositionOfATeamOfItsClub(): void
    {
        self::assertTrue($this->requestAs('TCLUBA', 'GET', '/admin/teams/9381/players')['canEdit']);

        $result = $this->requestAs('TCLUBA', 'POST', '/admin/teams/9381/players/copy', self::COPY_FROM_SCB);

        self::assertSame(1, $result['copied']);
        self::assertSame([9515], $this->rosterOf(9381));
        self::assertSame(
            ['Users' => 'TCLUBA', 'Competitions' => 'SCA'],
            $this->connection->fetchAssociative("SELECT Users, Competitions FROM kp_journal WHERE Actions = 'Copie composition'"),
        );
    }

    public function testTheProposedSourcesKeepTheSameClubAndTeamNumber(): void
    {
        $sources = $this->requestAs('TCLUBA', 'GET', '/admin/competition-teams/9381/compositions')['compositions'];

        self::assertSame(['SCB'], self::column($sources, 'competitionCode'));
    }

    public function testATeamOutsideTheClubsOfProfileSevenIsRefused(): void
    {
        $this->requestAs('TCLUB', 'POST', '/admin/teams/9381/players/copy', self::COPY_FROM_SCB, 403);

        self::assertSame([9511, 9512, 9513, 9514], $this->rosterOf(9381));
    }

    public function testACompetitionOutsideThePerimeterIsRefused(): void
    {
        $this->requestAs('TCLUBA', 'POST', '/admin/teams/9386/players/copy', ['sourceCompetition' => 'SCA'], 403);
    }

    public function testALockedCompetitionIsRefused(): void
    {
        $this->requestAs('TCLUBA', 'POST', '/admin/teams/9384/players/copy', ['sourceCompetition' => 'SCA'], 403);

        self::assertSame([9515], $this->rosterOf(9384));
    }

    public function testProfileEightIsRefused(): void
    {
        $this->requestAs('TVIEW', 'POST', '/admin/teams/9381/players/copy', self::COPY_FROM_SCB, 403);

        self::assertSame([9511, 9512, 9513, 9514], $this->rosterOf(9381));
    }

    /** @return list<int> */
    private function rosterOf(int $teamId): array
    {
        return array_map('intval', $this->connection->fetchFirstColumn(
            'SELECT Matric FROM kp_competition_equipe_joueur WHERE Id_equipe = ? ORDER BY Matric',
            [$teamId],
        ));
    }
}
