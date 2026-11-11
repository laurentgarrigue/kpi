<?php

namespace App\MatchMedals;

use App\MatchMedals\Catalog\MedalCatalog;
use App\MatchMedals\Catalog\MedalSurface;
use App\MatchMedals\Stats\MedalScope;
use App\MatchMedals\Stats\MedalStatsData;
use App\PublicResults\Scope\ResultsScope;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/** Read side of the stored medals for the public pages (§ 6.4): one game, the labels of a competition, a team. */
final class MatchMedalReader
{
    public function __construct(private readonly Connection $connection, private readonly MedalCatalog $catalog)
    {
    }

    /**
     * Medals of a game visible on the surface; empty arrays when the game is not validated (MED-01).
     *
     * @return array{reliable: bool|null, labels: list<string>, team: list<array<string, mixed>>, player: list<array<string, mixed>>, referee: list<array<string, mixed>>}
     */
    public function forGame(int $gameId, MedalSurface $surface): array
    {
        $empty = ['reliable' => null, 'labels' => [], 'team' => [], 'player' => [], 'referee' => []];
        $game = $this->connection->fetchAssociative(
            "SELECT j.Code_saison season, j.Code_competition competition FROM kp_match_insight i
            INNER JOIN kp_match m ON (m.Id = i.match_id) INNER JOIN kp_journee j ON (j.Id = m.Id_journee)
            WHERE i.match_id = ? AND i.is_eligible = 1 AND m.Validation = 'O'",
            [$gameId],
        );
        if ($game === false) {
            return $empty;
        }
        $data = new MedalStatsData($this->connection, new MedalScope($game['season'], [$game['competition']]), $this->catalog->thresholdsOf($game['season']));
        if (!isset($data->games()[$gameId])) {
            return $empty;
        }
        $visible = $this->catalog->visibleCodes($surface);
        $medals = array_values(array_filter($data->medals(), static fn (array $m): bool => $m['match_id'] === $gameId && in_array($m['code'], $visible, true)));
        $team = [];
        foreach (array_filter($medals, static fn (array $m): bool => $m['scope'] === 'team') as $m) {
            $key = $m['code'].$m['side'];
            $team[$key] ??= ['code' => $m['code'], 'side' => $m['side'], 'count' => 0, 'events' => []];
            ++$team[$key]['count'];
            if ($m['event_id'] !== null) {
                $team[$key]['events'][] = ['event_id' => $m['event_id'], 'seconds' => $m['seconds']];
            }
        }
        $referee = array_values(array_unique(array_column(array_filter($medals, static fn (array $m): bool => $m['scope'] === 'referee'), 'code')));

        return [
            'reliable' => $data->games()[$gameId]['timeline_reliable'],
            'labels' => array_values(array_intersect($data->labels($gameId), $visible)),
            'team' => array_values($team),
            'player' => array_values(array_map(static fn (array $m): array => [
                'code' => $m['code'],
                'side' => $m['side'],
                ...$data->player((int) $m['team_id'], (int) $m['matric']),
                'event_id' => $m['event_id'],
                'seconds' => $m['seconds'],
            ], array_filter($medals, static fn (array $m): bool => $m['scope'] === 'player' && $m['team_id'] !== null))),
            // common to both referees of the game (§ 9.1)
            'referee' => array_map(static fn (string $code): array => ['code' => $code], $referee),
        ];
    }

    /** @return array<int, list<string>> visible labels by game id, Goal Fest included */
    public function competitionLabels(string $season, string $competition, MedalSurface $surface): array
    {
        $data = new MedalStatsData($this->connection, new MedalScope($season, [$competition]), $this->catalog->thresholdsOf($season));
        $visible = $this->catalog->visibleCodes($surface, 'label');
        $labels = [];
        foreach (array_keys($data->games()) as $gameId) {
            $game = array_values(array_intersect($data->labels($gameId), $visible));
            if ($game !== []) {
                $labels[$gameId] = $game;
            }
        }

        return $labels;
    }

    /**
     * Medals of a team (kp_equipe number) over a season, by code, with the games concerned (§ 9.2, MED-49). A player
     * medal without a team version counts for the team; one with a team version counts once.
     *
     * @return list<array{code: string, count: int, games: list<array<string, mixed>>}>
     */
    public function teamSeason(int $teamNumber, string $season, MedalSurface $surface): array
    {
        $competitions = $this->connection->fetchFirstColumn(
            "SELECT DISTINCT ce.Code_compet FROM kp_competition_equipe ce INNER JOIN kp_competition c ON (c.Code = ce.Code_compet AND c.Code_saison = ce.Code_saison)
            WHERE ce.Numero = ? AND ce.Code_saison = ? AND c.Publication = 'O'",
            [$teamNumber, $season],
        );
        $data = new MedalStatsData($this->connection, new MedalScope($season, $competitions), $this->catalog->thresholdsOf($season));
        $visible = $this->catalog->visibleCodes($surface, 'medal');
        $teamCodes = $this->codesWithATeamVersion();
        $byCode = [];
        foreach ($data->medals() as $m) {
            $team = $data->team($m['team_id']);
            if ($team === null || $team['number'] !== $teamNumber || !in_array($m['code'], $visible, true)
                || $m['scope'] === 'referee' || ($m['scope'] === 'player' && in_array($m['code'], $teamCodes, true))) {
                continue;
            }
            $byCode[$m['code']] ??= ['code' => $m['code'], 'count' => 0, 'games' => []];
            ++$byCode[$m['code']]['count'];
            $byCode[$m['code']]['games'][$m['match_id']] = ['side' => $m['side'], ...$data->gameSummary($m['match_id'])];
        }
        $order = array_flip($visible);
        uasort($byCode, static fn (array $a, array $b): int => [$b['count'], $order[$a['code']]] <=> [$a['count'], $order[$b['code']]]);

        return array_values(array_map(static fn (array $c): array => [...$c, 'games' => array_values($c['games'])], $byCode));
    }

    /**
     * Medals of a team over a perimeter (an event, a gameday or a group), for app2's team page: the team's own, and the
     * players' (§ 9.1, MED-63, MED-64). The team is identified by a `kp_competition_equipe` Id and found again in the
     * other competitions of the perimeter by its number. No licence number: a player is named and numbered.
     *
     * @return array{team: list<array{code: string, count: int}>, players: list<array<string, mixed>>}|null null for an unknown team
     */
    public function teamInScope(ResultsScope $scope, int $teamId, MedalSurface $surface): ?array
    {
        $number = $this->connection->fetchOne('SELECT Numero FROM kp_competition_equipe WHERE Id = ?', [$teamId]);
        if ($number === false || $number === null) {
            return null;
        }
        $journees = $this->connection->fetchAllAssociative(
            "SELECT j.Id, j.Code_saison season, j.Code_competition competition FROM kp_journee j
            INNER JOIN kp_competition c ON (c.Code = j.Code_competition AND c.Code_saison = j.Code_saison) {$scope->join()}
            WHERE j.Publication = 'O' AND c.Publication = 'O' AND {$scope->condition()}",
            $scope->parameters(),
        );
        $matchIds = $journees === [] ? [] : array_map('intval', $this->connection->fetchFirstColumn(
            'SELECT Id FROM kp_match WHERE Id_journee IN (?)',
            [array_column($journees, 'Id')],
            [ArrayParameterType::INTEGER],
        ));
        $competitionsBySeason = [];
        foreach ($journees as $journee) {
            $competitionsBySeason[$journee['season']][$journee['competition']] = $journee['competition'];
        }
        $visible = $this->catalog->visibleCodes($surface, 'medal');
        $teamVersion = $this->codesWithATeamVersion();
        $team = [];
        $players = [];
        foreach ($competitionsBySeason as $season => $competitions) {
            $data = new MedalStatsData($this->connection, new MedalScope((string) $season, array_values($competitions)), $this->catalog->thresholdsOf((string) $season));
            foreach ($data->medals() as $m) {
                $owner = $data->team($m['team_id']);
                if ($owner === null || $owner['number'] !== (int) $number || !in_array($m['code'], $visible, true)
                    || $m['scope'] === 'referee' || !in_array($m['match_id'], $matchIds, true)) {
                    continue;
                }
                if ($m['scope'] === 'team' || !in_array($m['code'], $teamVersion, true)) {
                    $team[$m['code']] = ($team[$m['code']] ?? 0) + 1;
                }
                if ($m['scope'] === 'player' && $m['matric'] !== null) {
                    $key = (string) $m['matric'];
                    $players[$key] ??= [...$data->player((int) $m['team_id'], (int) $m['matric']), 'medals' => []];
                    $players[$key]['medals'][$m['code']] = ($players[$key]['medals'][$m['code']] ?? 0) + 1;
                }
            }
        }
        $order = array_flip($visible);
        $ranked = static function (array $counts) use ($order): array {
            uksort($counts, static fn (string $a, string $b): int => [$counts[$b], $order[$a]] <=> [$counts[$a], $order[$b]]);

            return array_map(static fn (string $code, int $count): array => ['code' => $code, 'count' => $count], array_keys($counts), $counts);
        };
        $players = array_map(static fn (array $p): array => [...$p, 'medals' => $ranked($p['medals'])], array_values($players));
        usort($players, static fn (array $a, array $b): int => [$a['number'] ?? PHP_INT_MAX, $a['last_name'], $a['first_name']] <=> [$b['number'] ?? PHP_INT_MAX, $b['last_name'], $b['first_name']]);

        return ['team' => $ranked($team), 'players' => $players];
    }

    /** @return list<string> codes of the medals that also exist for a team: their player version is not added to the team's count */
    private function codesWithATeamVersion(): array
    {
        return array_column(array_filter($this->catalog->all(), static fn (array $r): bool => str_contains((string) $r['scopes'], 'T')), 'code');
    }
}
