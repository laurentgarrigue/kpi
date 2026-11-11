<?php

namespace App\MatchMedals\Stats;

/** Impact des exclusions · Powerplay Impact: share of the goals of the games officiated scored on the power play. */
final class RefereePowerplayStat extends AbstractRefereeStat
{
    public function needsReliableTimeline(): bool
    {
        return true;
    }

    public function code(): string
    {
        return 'referee_powerplay';
    }

    public function columns(): array
    {
        return [['key' => 'games', 'type' => 'integer'], ['key' => 'percent', 'type' => 'percent']];
    }

    public function compute(MedalStatQuery $q): array
    {
        $share = static function (array $games): ?int {
            $goals = array_sum(array_map(static fn (array $g): int => $g['score_a'] + $g['score_b'], $games));
            $powerplay = array_sum(array_map(static fn (array $g): int => array_sum($g['powerplay_goals']), $games));

            return self::percent($powerplay, $goals);
        };

        return [
            'rows' => $this->refereeRows($q, static fn (array $officiated): array => ['percent' => $share(array_column($officiated, 'game'))], true),
            'summary' => ['average' => $share(self::refereedGames($q, true))],
        ];
    }
}
