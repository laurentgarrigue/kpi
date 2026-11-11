<?php

namespace App\MatchMedals\Stats;

/** Cartons par match · Cards per Game: cards of the games officiated per game, next to the average. */
final class RefereeCardsStat extends AbstractRefereeStat
{
    public function code(): string
    {
        return 'referee_cards';
    }

    public function columns(): array
    {
        return [['key' => 'games', 'type' => 'integer'], ['key' => 'per_game', 'type' => 'decimal']];
    }

    public function compute(MedalStatQuery $q): array
    {
        $count = fn (array $game): int => count(self::cardsOf($q, $game['id']));
        $games = self::refereedGames($q);

        return [
            'rows' => $this->refereeRows($q, static fn (array $officiated): array => [
                'per_game' => self::average(array_sum(array_map(static fn (array $g): int => $count($g['game']), $officiated)), count($officiated)),
            ]),
            'summary' => ['average' => self::average(array_sum(array_map($count, $games)), count($games))],
        ];
    }
}
