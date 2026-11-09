<?php

namespace App\MatchMedals\Stats;

/** Répartition entre équipes · Card Balance: average gap per game between the cards of the two teams. */
final class RefereeCardBalanceStat extends AbstractRefereeStat
{
    public function code(): string
    {
        return 'referee_card_balance';
    }

    public function columns(): array
    {
        return [['key' => 'games', 'type' => 'integer'], ['key' => 'gap', 'type' => 'decimal']];
    }

    public function compute(MedalStatQuery $q): array
    {
        $gap = function (array $game) use ($q): int {
            $sides = array_count_values(array_column(self::cardsOf($q, $game['id']), 'side'));

            return abs(($sides['A'] ?? 0) - ($sides['B'] ?? 0));
        };
        $games = self::refereedGames($q);

        return [
            'rows' => $this->refereeRows($q, static fn (array $officiated): array => [
                'gap' => self::average(array_sum(array_map(static fn (array $g): int => $gap($g['game']), $officiated)), count($officiated)),
            ]),
            'summary' => ['average' => self::average(array_sum(array_map($gap, $games)), count($games))],
        ];
    }
}
