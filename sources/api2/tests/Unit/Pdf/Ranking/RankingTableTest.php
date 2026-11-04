<?php

namespace App\Tests\Unit\Pdf\Ranking;

use App\Pdf\Ranking\RankingSource;
use App\Pdf\Ranking\RankingTable;
use PHPUnit\Framework\TestCase;

/** Tables of the ranking documents (PDF_GOTENBERG.md § 7.4). */
final class RankingTableTest extends TestCase
{
    public function testPdfData01PublicDocumentsOnlyReadThePublishedColumns(): void
    {
        self::assertSame('Clt_publi', RankingSource::Published->column('Clt'));
        self::assertSame('Clt', RankingSource::Computed->column('Clt'));
        self::assertFalse(RankingSource::Published->isProvisional());
        self::assertTrue(RankingSource::Computed->isProvisional());
    }

    public function testRankingRowsFollowTheRankOfTheTypeAndLeaveUnrankedTeamsOut(): void
    {
        $teams = [self::team(9, 1, 2, 700), self::team(10, 2, 1, 500), self::team(11, 0, 0, 0)];

        $championship = RankingTable::rankingRows($teams, 'CHPT', 'END', true);
        self::assertSame([1, 2], array_map(static fn ($row) => $row->rank, $championship));
        self::assertSame(7, $championship[0]->points, 'points stored × 100');
        self::assertSame(1, $championship[0]->medal, 'finished final round');

        $cup = RankingTable::rankingRows($teams, 'CP', 'ON', false);
        self::assertSame([2, 1], array_map(static fn ($row) => $row->rank, $cup), 'level rank of a cup');
        self::assertNull($cup[0]->medal);
        self::assertTrue(RankingTable::hasUnrankedTeam($teams, 'CHPT'));
        self::assertNull(RankingTable::rankingRows($teams, 'MULTI', 'END', false)[0]->details);
    }

    public function testForfeitsColumnOnlyWithTheFourTwoOneZeroScale(): void
    {
        $labels = array_fill_keys(['ranking.rank', 'ranking.team', 'ranking.points', 'ranking.played', 'ranking.won', 'ranking.drawn', 'ranking.lost', 'ranking.forfeits', 'ranking.goals_for', 'ranking.goals_against', 'ranking.goal_diff'], 'x');

        self::assertContains('forfeits', array_column(RankingTable::columns($labels, true), 'key'));
        self::assertNotContains('forfeits', array_column(RankingTable::columns($labels, false), 'key'));
    }

    public function testGameRowShowsTheScoreOfAValidatedGameOnlyWithTheWinnerInBold(): void
    {
        $row = RankingTable::gameRow(self::game('3', '1', 'O'));
        self::assertSame(['home' => '3', 'away' => '1', 'winner' => 'A'], $row['score'], 'only the score of the winner in bold');
        self::assertSame(['home' => 'pdf-strong', 'away' => ''], $row['_cells'], 'the winner in bold');
        self::assertSame('B', RankingTable::gameRow(self::game('0', '2', 'O'))['score']['winner']);

        self::assertNull(RankingTable::gameRow(self::game('3', '1', ''))['score'], 'not validated');
        self::assertNull(RankingTable::gameRow(self::game('2', '2', 'O'))['score']['winner'], 'draw');
        self::assertSame(['home' => '', 'away' => ''], RankingTable::gameRow(self::game('2', '2', 'O'))['_cells'], 'draw');
        self::assertFalse(RankingTable::isPlayed(self::game('?', '1', 'O')));
        self::assertFalse(RankingTable::isPlayed(self::game(null, null, '')));
        self::assertSame('01/03/2026 - Lacville', RankingTable::gamedayLabel('2026-03-01', 'Lacville'));
    }

    public function testAPlayedGameNotValidatedYetHasItsScoreWithoutWinner(): void
    {
        self::assertSame(['home' => '3', 'away' => '1', 'winner' => null], RankingTable::score(self::game('3', '1', ''), false));
        self::assertNull(RankingTable::score(self::game('3', '1', ''), true));
        self::assertNull(RankingTable::score(self::game('', '', ''), false), 'not played');
    }

    /** @return array<string, mixed> */
    private static function team(int $id, int $rank, int $levelRank, int $points): array
    {
        return [
            'Id' => $id, 'Numero' => null, 'Libelle' => 'Team ' . $id, 'logo' => null, 'Clt' => $rank, 'CltNiveau' => $levelRank,
            'Pts' => $points, 'J' => 3, 'G' => 2, 'N' => 1, 'P' => 0, 'F' => 0, 'Plus' => 9, 'Moins' => 4, 'Diff' => 5,
        ];
    }

    /** @return array<string, mixed> */
    private static function game(?string $scoreA, ?string $scoreB, string $validation): array
    {
        return ['LibelleA' => 'A', 'LibelleB' => 'B', 'ScoreA' => $scoreA, 'ScoreB' => $scoreB, 'Validation' => $validation];
    }
}
