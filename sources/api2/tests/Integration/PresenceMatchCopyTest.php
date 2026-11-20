<?php

namespace App\Tests\Integration;

use Doctrine\DBAL\Connection;

/**
 * Copie de la composition d'un match vers les autres matchs de l'équipe (PAGE_PRESENCE.md § 1.2, mode match) : la
 * journée est ouverte aux profils ≤ 6, la compétition aux profils ≤ 4, comme le bouton d'app4.
 * Fixtures « copie de composition de match » : compétition PCM, source 9471 (J1, équipe 9395 en A : 9511, 9512),
 * cibles 9472 (J1, 9395 en B) et 9473 (J2, 9395 en A). Comptes TCOMP (4), TORGA (6), TTEAMP (7).
 */
final class PresenceMatchCopyTest extends ApiTestCase
{
    private const SOURCE_PLAYERS = [9511, 9512];
    private const COPY_TO_BOTH_MATCHES = ['teamCode' => 'A', 'matchIds' => [9472, 9473]];

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->beginWrites();
    }

    public function testProfileSixCopiesToTheMatchesOfTheDayOnly(): void
    {
        $result = $this->requestAs('TORGA', 'POST', '/admin/matches/9471/players/copy-to-day', self::COPY_TO_BOTH_MATCHES);

        self::assertSame(1, $result['copied']);
        self::assertSame(self::SOURCE_PLAYERS, $this->matchRoster(9472, 'B'));
        self::assertSame([], $this->matchRoster(9473, 'A'), 'a match of another day is not a day copy target');
    }

    public function testProfileSixCannotCopyToTheCompetition(): void
    {
        $this->requestAs('TORGA', 'POST', '/admin/matches/9471/players/copy-to-competition', self::COPY_TO_BOTH_MATCHES, 403);

        self::assertSame([], $this->matchRoster(9473, 'A'));
    }

    public function testProfileFourCopiesToTheMatchesOfTheCompetition(): void
    {
        $result = $this->requestAs('TCOMP', 'POST', '/admin/matches/9471/players/copy-to-competition', self::COPY_TO_BOTH_MATCHES);

        self::assertSame(2, $result['copied']);
        self::assertSame(self::SOURCE_PLAYERS, $this->matchRoster(9472, 'B'));
        self::assertSame(self::SOURCE_PLAYERS, $this->matchRoster(9473, 'A'));
    }

    public function testProfileSevenCannotCopyToOtherMatches(): void
    {
        $this->requestAs('TTEAMP', 'POST', '/admin/matches/9471/players/copy-to-day', self::COPY_TO_BOTH_MATCHES, 403);
        $this->requestAs('TTEAMP', 'POST', '/admin/matches/9471/players/copy-to-competition', self::COPY_TO_BOTH_MATCHES, 403);

        self::assertSame([], $this->matchRoster(9472, 'B'));
    }

    /** @return list<int> */
    private function matchRoster(int $matchId, string $teamCode): array
    {
        return array_map('intval', $this->connection->fetchFirstColumn(
            'SELECT Matric FROM kp_match_joueur WHERE Id_match = ? AND Equipe = ? ORDER BY Matric',
            [$matchId, $teamCode],
        ));
    }
}
