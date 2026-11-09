<?php

namespace App\MatchMedals;

use App\MatchMedals\Catalog\MedalCatalog;
use App\MatchMedals\Catalog\MedalSurface;
use App\MatchMedals\Stats\MedalScope;
use App\MatchMedals\Stats\MedalStatsData;
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
        $teamCodes = array_column(array_filter($this->catalog->all(), static fn (array $r): bool => str_contains((string) $r['scopes'], 'T')), 'code');
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
}
