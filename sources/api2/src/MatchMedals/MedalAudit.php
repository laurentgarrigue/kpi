<?php

namespace App\MatchMedals;

use App\MatchMedals\Model\TimelineIssue;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

/**
 * Lets the admin check the games behind the figures (FEATURE_MATCH_MEDALS.md § 9.3, MED-79): those that received an
 * element of the catalogue, those whose timeline was set aside and why. Admin only: licence numbers are not hidden.
 */
final class MedalAudit
{
    public const MAX_GAMES = 200;

    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return array{total: int, games: list<array<string, mixed>>} games of a season with a medal, most recent first */
    public function gamesWithMedal(string $code, string $season): array
    {
        $result = $this->games('i.match_id IN (SELECT match_id FROM kp_match_medal WHERE code = ?)', [$season, $code]);
        $awards = $this->awards($code, array_column($result['games'], 'id'));
        $result['games'] = array_map(static fn (array $game): array => [...$game, 'awards' => $awards[$game['id']] ?? []], $result['games']);

        return $result;
    }

    /** @return array{total: int, games: list<array<string, mixed>>} games of a season with a label, most recent first */
    public function gamesWithLabel(string $code, string $season): array
    {
        return $this->games('JSON_CONTAINS(i.labels, ?)', [$season, json_encode($code, JSON_THROW_ON_ERROR)]);
    }

    /** @return array{total: int, games: list<array<string, mixed>>} eligible games of a season whose timeline is set aside */
    public function unreliableGames(string $season, ?TimelineIssue $issue): array
    {
        return $issue === null
            ? $this->games('i.is_eligible = 1 AND i.timeline_reliable = 0', [$season])
            : $this->games('i.is_eligible = 1 AND i.timeline_reliable = 0 AND i.timeline_issue = ?', [$season, $issue->value]);
    }

    /** @return array<string, array<string, int>> number of games set aside, by season then by issue (`unknown`: computed before the issue was stored) */
    public function issueCounts(): array
    {
        $counts = [];
        foreach ($this->connection->fetchAllAssociative(
            'SELECT season, timeline_issue, COUNT(*) n FROM kp_match_insight WHERE is_eligible = 1 AND timeline_reliable = 0 GROUP BY season, timeline_issue',
        ) as $row) {
            $counts[$row['season']][$row['timeline_issue'] ?? 'unknown'] = (int) $row['n'];
        }

        return $counts;
    }

    /**
     * @param list<mixed> $params the season, then the parameters of $condition
     *
     * @return array{total: int, games: list<array<string, mixed>>}
     */
    private function games(string $condition, array $params): array
    {
        $where = "WHERE i.season = ? AND $condition";
        $rows = $this->connection->fetchAllAssociative(
            "SELECT m.Id id, m.Date_match date, m.Heure_match time, j.Code_competition competition, m.Numero_ordre number,
                ea.Libelle team_a, eb.Libelle team_b, m.ScoreA score_a, m.ScoreB score_b, i.timeline_issue
            FROM kp_match_insight i
            INNER JOIN kp_match m ON (m.Id = i.match_id)
            INNER JOIN kp_journee j ON (j.Id = m.Id_journee)
            LEFT JOIN kp_competition_equipe ea ON (ea.Id = m.Id_equipeA)
            LEFT JOIN kp_competition_equipe eb ON (eb.Id = m.Id_equipeB)
            $where
            ORDER BY m.Date_match DESC, m.Heure_match DESC, m.Id DESC
            LIMIT ".self::MAX_GAMES,
            $params,
        );

        return [
            'total' => (int) $this->connection->fetchOne("SELECT COUNT(*) FROM kp_match_insight i $where", $params),
            'games' => array_map(static fn (array $r): array => [
                ...$r,
                'id' => (int) $r['id'],
                'number' => $r['number'] === null ? null : (int) $r['number'],
            ], $rows),
        ];
    }

    /**
     * @param list<int> $gameIds
     *
     * @return array<int, list<array<string, mixed>>> awards of a medal by game, with the player and the time of the fact
     */
    private function awards(string $code, array $gameIds): array
    {
        if ($gameIds === []) {
            return [];
        }
        $awards = [];
        // the fact is joined through its game: event_detail_id and kp_match_detail.Id differ in charset, so the primary
        // key alone cannot be used and the join would scan every fact of the base for each award (timeout)
        foreach ($this->connection->fetchAllAssociative(
            'SELECT md.match_id, md.scope, md.team_side, md.person_matric, l.Prenom first_name, l.Nom last_name, d.Periode period, d.Temps remaining
            FROM kp_match_medal md
            LEFT JOIN kp_licence l ON (l.Matric = md.person_matric)
            LEFT JOIN kp_match_detail d ON (d.Id_match = md.match_id AND d.Id = md.event_detail_id)
            WHERE md.code = ? AND md.match_id IN (?)
            ORDER BY md.match_id, md.id',
            [$code, $gameIds],
            [ParameterType::STRING, ArrayParameterType::INTEGER],
        ) as $a) {
            $awards[(int) $a['match_id']][] = [
                'scope' => $a['scope'],
                'side' => $a['team_side'],
                'matric' => $a['person_matric'] === null ? null : (int) $a['person_matric'],
                'first_name' => $a['first_name'],
                'last_name' => $a['last_name'],
                'period' => $a['period'],
                'remaining' => MatchMedalRepository::remainingSeconds($a['remaining']),
            ];
        }

        return $awards;
    }
}
