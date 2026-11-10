<?php

namespace App\MatchMedals\Stats;

use App\MatchMedals\MedalThresholds;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * Stored medal data of a perimeter, loaded once and shared by the statistics (§ 6.2): eligible games (validated,
 * published, computed), their teams, medals, cards and designated referees, with the names to display.
 * No licence number leaves this class (MED-44).
 */
final class MedalStatsData
{
    /** @var array<int, array<string, mixed>> */
    private array $games = [];
    /** @var array<int, array{id: int, number: int|null, label: string, logo: string|null}> */
    private array $teams = [];
    /** @var list<array<string, mixed>> */
    private array $medals = [];
    /** @var list<array<string, mixed>> */
    private array $cards = [];
    /** @var list<array<string, mixed>> */
    private array $referees = [];
    /** @var array<string, array{first_name: string|null, last_name: string|null, number: int|null}> */
    private array $players = [];
    /** @var array<int, array{first_name: string|null, last_name: string|null}> */
    private array $refereeNames = [];
    /** @var array<string, int|null>|null */
    private ?array $goalFest = null;

    public function __construct(private readonly Connection $connection, public readonly MedalScope $scope, private readonly MedalThresholds $thresholds)
    {
        $this->loadGames();
        if ($this->games !== []) {
            $this->loadDetails(array_keys($this->games));
        }
    }

    /** @return array<int, array<string, mixed>> eligible games by id */
    public function games(bool $reliableOnly = false): array
    {
        return $reliableOnly ? array_filter($this->games, static fn (array $g): bool => $g['timeline_reliable']) : $this->games;
    }

    /** @return array{id: int, number: int|null, label: string, logo: string|null}|null */
    public function team(?int $id): ?array
    {
        return $id === null ? null : ($this->teams[$id] ?? null);
    }

    /** @return list<array<string, mixed>> */
    public function medals(): array
    {
        return $this->medals;
    }

    /** @return list<array<string, mixed>> */
    public function cards(): array
    {
        return $this->cards;
    }

    /** @return array<int, list<array<string, mixed>>> cards by game id */
    public function cardsByGame(): array
    {
        $byGame = [];
        foreach ($this->cards as $card) {
            $byGame[$card['match_id']][] = $card;
        }

        return $byGame;
    }

    /** @return list<array{match_id: int, matric: int, role: string}> */
    public function referees(): array
    {
        return $this->referees;
    }

    /** @return array{first_name: string|null, last_name: string|null, number: int|null} */
    public function player(int $teamId, int $matric): array
    {
        return $this->players["$teamId-$matric"] ?? ['first_name' => null, 'last_name' => null, 'number' => null];
    }

    /** @return array{first_name: string|null, last_name: string|null} name only: the licence number never leaves */
    public function referee(int $matric): array
    {
        return $this->refereeNames[$matric] ?? ['first_name' => null, 'last_name' => null];
    }

    /** Labels of a game: stored ones, plus Goal Fest decided on the whole competition (§ 3.3). */
    public function labels(int $gameId): array
    {
        $game = $this->games[$gameId] ?? null;
        if ($game === null) {
            return [];
        }
        $labels = $game['labels'];
        $threshold = $this->goalFestThresholds()[$game['competition']] ?? null;
        if ($threshold !== null && $game['score_a'] + $game['score_b'] >= $threshold) {
            $labels[] = GoalFest::CODE;
        }

        return $labels;
    }

    /** @return array<string, int|null> */
    private function goalFestThresholds(): array
    {
        if ($this->goalFest === null) {
            $totals = [];
            foreach ($this->games as $game) {
                $totals[$game['competition']][] = $game['score_a'] + $game['score_b'];
            }
            $this->goalFest = array_map(fn (array $t): ?int => GoalFest::threshold($t, $this->thresholds), $totals);
        }

        return $this->goalFest;
    }

    /** Game summary for lists (highlights, comebacks…). */
    public function gameSummary(int $gameId): array
    {
        $g = $this->games[$gameId];

        return [
            'id' => $gameId,
            'competition' => $g['competition'],
            'number' => $g['number'],
            'date' => $g['date'],
            'team_a' => $this->team($g['team_a']),
            'team_b' => $this->team($g['team_b']),
            'score_a' => $g['score_a'],
            'score_b' => $g['score_b'],
        ];
    }

    private function loadGames(): void
    {
        if ($this->scope->competitions === []) {
            return;
        }
        $rows = $this->connection->fetchAllAssociative(
            "SELECT m.Id id, j.Code_competition competition, m.Numero_ordre number, m.Date_match date, m.Type type,
                m.ScoreA score_a, m.ScoreB score_b, m.Id_equipeA team_a, m.Id_equipeB team_b, i.*
            FROM kp_match_insight i
            INNER JOIN kp_match m ON (m.Id = i.match_id)
            INNER JOIN kp_journee j ON (j.Id = m.Id_journee)
            WHERE i.is_eligible = 1 AND m.Validation = 'O' AND m.Publication = 'O' AND j.Publication = 'O'
            AND j.Code_saison = ? AND j.Code_competition IN (?)
            ORDER BY m.Date_match, m.Heure_match, m.Id",
            [$this->scope->season, $this->scope->competitions],
            [\PDO::PARAM_STR, ArrayParameterType::STRING],
        );
        foreach ($rows as $r) {
            $this->games[(int) $r['id']] = [
                'id' => (int) $r['id'],
                'competition' => (string) $r['competition'],
                'number' => $r['number'] === null ? null : (int) $r['number'],
                'date' => $r['date'],
                'type' => (string) $r['type'],
                'score_a' => (int) $r['score_a'],
                'score_b' => (int) $r['score_b'],
                'team_a' => $r['team_a'] === null ? null : (int) $r['team_a'],
                'team_b' => $r['team_b'] === null ? null : (int) $r['team_b'],
                'timeline_reliable' => (bool) $r['timeline_reliable'],
                'labels' => json_decode((string) $r['labels'], true) ?: [],
                'winner' => $r['winner_side'],
                'overtimes' => (int) $r['overtimes'],
                'first_goal_side' => $r['first_goal_side'],
                'first_goal_seconds' => $r['first_goal_seconds'] === null ? null : (int) $r['first_goal_seconds'],
                'max_deficit' => ['A' => (int) $r['max_deficit_a'], 'B' => (int) $r['max_deficit_b']],
                'exclusions' => ['A' => (int) $r['exclusions_a'], 'B' => (int) $r['exclusions_b']],
                'powerplay_goals' => ['A' => (int) $r['powerplay_goals_a'], 'B' => (int) $r['powerplay_goals_b']],
                'penalty_killed' => ['A' => (int) $r['penalty_killed_a'], 'B' => (int) $r['penalty_killed_b']],
                'goal_seconds' => json_decode((string) $r['goal_seconds'], true) ?: [],
            ];
        }
    }

    /** @param list<int> $gameIds */
    private function loadDetails(array $gameIds): void
    {
        $ids = [$gameIds];
        $types = [ArrayParameterType::INTEGER];
        $teamIds = array_values(array_unique(array_filter(array_merge(array_column($this->games, 'team_a'), array_column($this->games, 'team_b')))));
        foreach ($this->connection->fetchAllAssociative('SELECT Id, Numero, Libelle, logo FROM kp_competition_equipe WHERE Id IN (?)', [$teamIds], $types) as $t) {
            $this->teams[(int) $t['Id']] = ['id' => (int) $t['Id'], 'number' => $t['Numero'] === null ? null : (int) $t['Numero'], 'label' => (string) $t['Libelle'], 'logo' => $t['logo']];
        }
        foreach ($this->connection->fetchAllAssociative('SELECT Id_equipe, Matric, Nom, Prenom, Numero FROM kp_competition_equipe_joueur WHERE Id_equipe IN (?)', [$teamIds], $types) as $p) {
            $this->players[$p['Id_equipe'].'-'.$p['Matric']] = ['first_name' => $p['Prenom'], 'last_name' => $p['Nom'], 'number' => $p['Numero'] === null ? null : (int) $p['Numero']];
        }
        $this->medals = array_map(static fn (array $m): array => [
            'match_id' => (int) $m['match_id'], 'code' => $m['code'], 'scope' => $m['scope'], 'side' => $m['team_side'],
            'team_id' => $m['team_id'] === null ? null : (int) $m['team_id'], 'matric' => $m['person_matric'] === null ? null : (int) $m['person_matric'],
            'event_id' => $m['event_detail_id'], 'seconds' => $m['game_seconds'] === null ? null : (int) $m['game_seconds'],
        ], $this->connection->fetchAllAssociative('SELECT * FROM kp_match_medal WHERE match_id IN (?) ORDER BY id', $ids, $types));
        $this->cards = array_map(static fn (array $c): array => [
            'match_id' => (int) $c['match_id'], 'side' => $c['team_side'], 'code' => $c['code'], 'motif' => $c['motif'],
            'seconds' => $c['game_seconds'] === null ? null : (int) $c['game_seconds'],
        ], $this->connection->fetchAllAssociative('SELECT * FROM kp_match_card WHERE match_id IN (?)', $ids, $types));
        $this->referees = array_map(static fn (array $r): array => [
            'match_id' => (int) $r['match_id'], 'matric' => (int) $r['referee_matric'], 'role' => $r['role'],
        ], $this->connection->fetchAllAssociative('SELECT * FROM kp_match_referee WHERE match_id IN (?) ORDER BY match_id, role', $ids, $types));
        $matrics = array_values(array_unique(array_merge(
            array_column($this->referees, 'matric'),
            array_column(array_filter($this->medals, static fn (array $m): bool => $m['scope'] === 'referee'), 'matric'),
        )));
        if ($matrics !== []) {
            foreach ($this->connection->fetchAllAssociative('SELECT Matric, Nom, Prenom FROM kp_licence WHERE Matric IN (?)', [$matrics], $types) as $l) {
                $this->refereeNames[(int) $l['Matric']] = ['first_name' => $l['Prenom'], 'last_name' => $l['Nom']];
            }
        }
        $this->loadOffRosterPlayers();
    }

    /**
     * A player can be on the game sheets without being (any more) on the team roster: named from the licence, numbered
     * from the last game sheet of a medal that gives one.
     */
    private function loadOffRosterPlayers(): void
    {
        $missing = array_values(array_filter($this->medals, fn (array $m): bool => $m['scope'] === 'player' && $m['team_id'] !== null
            && $m['matric'] !== null && !isset($this->players[$m['team_id'].'-'.$m['matric']])));
        if ($missing === []) {
            return;
        }
        $types = [ArrayParameterType::INTEGER, ArrayParameterType::INTEGER];
        $params = [array_values(array_unique(array_column($missing, 'match_id'))), array_values(array_unique(array_column($missing, 'matric')))];
        $names = $this->connection->fetchAllAssociativeIndexed('SELECT Matric, Nom, Prenom FROM kp_licence WHERE Matric IN (?)', [$params[1]], [$types[1]]);
        $numbers = [];
        foreach ($this->connection->fetchAllAssociative('SELECT Id_match, Matric, Numero FROM kp_match_joueur WHERE Id_match IN (?) AND Matric IN (?)', $params, $types) as $row) {
            $numbers[$row['Id_match'].'-'.$row['Matric']] = $row['Numero'] === null ? null : (int) $row['Numero'];
        }
        usort($missing, static fn (array $a, array $b): int => $a['match_id'] <=> $b['match_id']);
        foreach ($missing as $m) {
            $key = $m['team_id'].'-'.$m['matric'];
            $name = $names[$m['matric']] ?? ['Nom' => null, 'Prenom' => null];
            $this->players[$key] = [
                'first_name' => $name['Prenom'], 'last_name' => $name['Nom'],
                'number' => $numbers[$m['match_id'].'-'.$m['matric']] ?? $this->players[$key]['number'] ?? null,
            ];
        }
    }
}
