<?php

namespace App\MatchMedals\Stats;

/** Matchs arbitrés · Games Officiated: games, as first or second referee, knockouts, overtimes. */
final class RefereeGamesStat extends AbstractRefereeStat
{
    public function code(): string
    {
        return 'referee_games';
    }

    public function columns(): array
    {
        return [['key' => 'games', 'type' => 'integer'], ['key' => 'principal', 'type' => 'integer'], ['key' => 'secondary', 'type' => 'integer'], ['key' => 'knockouts', 'type' => 'integer'], ['key' => 'overtimes', 'type' => 'integer']];
    }

    public function compute(MedalStatQuery $q): array
    {
        $rows = [];
        foreach (self::byReferee($q) as $entry) {
            $games = $entry['games'];
            $rows[] = [
                'referee' => $entry['referee'],
                'games' => count($games),
                'principal' => count(array_filter($games, static fn (array $g): bool => $g['role'] === 'principal')),
                'secondary' => count(array_filter($games, static fn (array $g): bool => $g['role'] === 'secondaire')),
                'knockouts' => count(array_filter($games, static fn (array $g): bool => $g['game']['type'] === 'E')),
                'overtimes' => count(array_filter($games, static fn (array $g): bool => $g['game']['overtimes'] > 0)),
            ];
        }

        return [
            'rows' => self::ranked($rows, 'games', static fn (array $r): string => self::name($r['referee']), $q->limit),
            'summary' => ['undesignated_games' => count($q->data->games()) - count(self::refereedGames($q))],
        ];
    }
}
