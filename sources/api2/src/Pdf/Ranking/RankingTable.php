<?php

namespace App\Pdf\Ranking;

use App\PublicResults\CompetitionRules;
use App\PublicResults\Dto\RankingRow;
use App\PublicResults\Dto\TeamRef;

/**
 * Tables of the ranking documents (PDF_GOTENBERG.md § 7.4): columns and rows of a ranking, rows of the games.
 * Pure functions on the rows of RankingRepository, whose statistics are aliased without suffix (Clt, Pts…).
 */
final class RankingTable
{
    /** A score that is not a number yet: the game is not played (legacy « ? »). */
    private const UNPLAYED_SCORES = ['', '?'];

    /** Type of a cup: its points come from the phases, it only shows its final ranking. */
    public const CUP = 'CP';

    /**
     * @param array<string, string> $labels
     *
     * @return list<array<string, string>>
     */
    public static function columns(array $labels, bool $forfeits): array
    {
        $columns = [
            ['key' => 'rank', 'label' => $labels['ranking.rank'], 'class' => 'num'],
            ['key' => 'team', 'label' => $labels['ranking.team']],
            ['key' => 'points', 'label' => $labels['ranking.points'], 'class' => 'num'],
            ['key' => 'played', 'label' => $labels['ranking.played'], 'class' => 'num'],
            ['key' => 'won', 'label' => $labels['ranking.won'], 'class' => 'num'],
            ['key' => 'drawn', 'label' => $labels['ranking.drawn'], 'class' => 'num'],
            ['key' => 'lost', 'label' => $labels['ranking.lost'], 'class' => 'num'],
        ];
        if ($forfeits) {
            $columns[] = ['key' => 'forfeits', 'label' => $labels['ranking.forfeits'], 'class' => 'num'];
        }

        return [
            ...$columns,
            ['key' => 'goals_for', 'label' => $labels['ranking.goals_for'], 'class' => 'num'],
            ['key' => 'goals_against', 'label' => $labels['ranking.goals_against'], 'class' => 'num'],
            ['key' => 'goal_diff', 'label' => $labels['ranking.goal_diff'], 'class' => 'num'],
        ];
    }

    /**
     * Row of a ranking table (general or of a gameday).
     *
     * @param array<string, mixed> $team
     *
     * @return array<string, int|float|string>
     */
    public static function row(array $team, int $rank): array
    {
        return [
            'rank' => $rank,
            'team' => (string) $team['Libelle'],
            'points' => CompetitionRules::points((int) $team['Pts']),
            'played' => (int) $team['J'],
            'won' => (int) $team['G'],
            'drawn' => (int) $team['N'],
            'lost' => (int) $team['P'],
            'forfeits' => (int) $team['F'],
            'goals_for' => (int) $team['Plus'],
            'goals_against' => (int) $team['Moins'],
            'goal_diff' => (int) $team['Diff'],
        ];
    }

    /**
     * General ranking as the public website builds it (PublicCompetitionService): rank of the type, teams ranked 0
     * left out, medals of a finished final round.
     *
     * @param list<array<string, mixed>> $teams
     *
     * @return list<RankingRow>
     */
    public static function rankingRows(array $teams, string $type, string $status, bool $final): array
    {
        $rows = [];
        foreach ($teams as $team) {
            $rank = CompetitionRules::rank($type, (int) $team['Clt'], (int) $team['CltNiveau']);
            if ($rank === 0) {
                continue;
            }
            $row = self::row($team, $rank);
            $rows[] = new RankingRow(
                rank: $rank,
                team: new TeamRef((int) $team['Id'], $team['Numero'] === null ? null : (int) $team['Numero'], (string) $team['Libelle'], $team['logo']),
                points: $row['points'],
                played: (int) $team['J'],
                medal: CompetitionRules::medal($status, $final, $rank),
                details: $type === 'MULTI' ? null : [
                    'won' => $row['won'],
                    'drawn' => $row['drawn'],
                    'lost' => $row['lost'],
                    'forfeits' => $row['forfeits'],
                    'goals_for' => $row['goals_for'],
                    'goals_against' => $row['goals_against'],
                    'goal_diff' => $row['goal_diff'],
                ],
            );
        }

        return $rows;
    }

    /** A team ranked 0 makes the qualified / eliminated marks misleading (legacy rule, PublicCompetitionService). */
    public static function hasUnrankedTeam(array $teams, string $type): bool
    {
        foreach ($teams as $team) {
            if (CompetitionRules::rank($type, (int) $team['Clt'], (int) $team['CltNiveau']) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, string> $labels
     *
     * @return list<array<string, string>>
     */
    public static function gameColumns(array $labels): array
    {
        return [
            ['key' => 'home', 'label' => $labels['games.home'], 'class' => 'pdf-col--home'],
            ['key' => 'score', 'label' => $labels['games.score'], 'class' => 'pdf-col--score', 'kind' => 'score', 'empty' => '-'],
            ['key' => 'away', 'label' => $labels['games.away']],
        ];
    }

    public static function isPlayed(array $game): bool
    {
        return !in_array((string) $game['ScoreA'], self::UNPLAYED_SCORES, true)
            && !in_array((string) $game['ScoreB'], self::UNPLAYED_SCORES, true);
    }

    /**
     * Score of a game for a `score` column: both scores and the winner, whose score alone is in bold (the winner of a
     * validated game only). Null when the game is not played, or not validated when `$validatedOnly`.
     *
     * @param array<string, mixed> $game
     *
     * @return array{home: string, away: string, winner: ?string}|null
     */
    public static function score(array $game, bool $validatedOnly): ?array
    {
        $validated = $game['Validation'] === 'O' && self::isPlayed($game);
        if (!self::isPlayed($game) || ($validatedOnly && !$validated)) {
            return null;
        }
        $scoreA = (int) $game['ScoreA'];
        $scoreB = (int) $game['ScoreB'];

        return [
            'home' => (string) $game['ScoreA'],
            'away' => (string) $game['ScoreB'],
            'winner' => !$validated || $scoreA === $scoreB ? null : ($scoreA > $scoreB ? 'A' : 'B'),
        ];
    }

    /**
     * Row of a game: the score of a validated game only; the winner and its score in bold.
     *
     * @param array<string, mixed> $game
     *
     * @return array<string, mixed>
     */
    public static function gameRow(array $game): array
    {
        $score = self::score($game, true);

        return [
            'home' => (string) $game['LibelleA'],
            'score' => $score,
            'away' => (string) $game['LibelleB'],
            '_cells' => [
                'home' => ($score['winner'] ?? null) === 'A' ? 'pdf-strong' : '',
                'away' => ($score['winner'] ?? null) === 'B' ? 'pdf-strong' : '',
            ],
        ];
    }

    /** « 01/05/2026 - Lacville »: separator of the games of a gameday. */
    public static function gamedayLabel(?string $date, ?string $place): string
    {
        $parsed = $date === null ? false : \DateTimeImmutable::createFromFormat('!Y-m-d', substr($date, 0, 10));

        return trim(($parsed === false ? '' : $parsed->format('d/m/Y')) . ' - ' . $place, ' -');
    }
}
