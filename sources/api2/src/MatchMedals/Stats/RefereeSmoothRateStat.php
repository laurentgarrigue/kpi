<?php

namespace App\MatchMedals\Stats;

/** Matchs fluides · Smooth Games: share of the games officiated without any card. */
final class RefereeSmoothRateStat extends AbstractRefereeStat
{
    public function code(): string
    {
        return 'referee_smooth_rate';
    }

    public function columns(): array
    {
        return [['key' => 'games', 'type' => 'integer'], ['key' => 'percent', 'type' => 'percent']];
    }

    public function compute(MedalStatQuery $q): array
    {
        $isSmooth = fn (array $game): bool => self::cardsOf($q, $game['id']) === [];
        $games = self::refereedGames($q);

        return [
            'rows' => $this->refereeRows($q, static fn (array $officiated): array => [
                'percent' => self::percent(count(array_filter($officiated, static fn (array $g): bool => $isSmooth($g['game']))), count($officiated)),
            ]),
            'summary' => ['average' => self::percent(count(array_filter($games, $isSmooth)), count($games))],
        ];
    }
}
