<?php

namespace App\MatchMedals\Stats;

/** Supériorité % · Powerplay %: power-play goals compared with the exclusions of the opponents, per team. */
final class PowerplayRateStat extends AbstractMedalStat
{
    public function code(): string
    {
        return 'powerplay_rate';
    }

    public function columns(): array
    {
        return [['key' => 'powerplay_goals', 'type' => 'integer'], ['key' => 'opponent_exclusions', 'type' => 'integer'], ['key' => 'percent', 'type' => 'percent']];
    }

    public function compute(MedalStatQuery $q): array
    {
        $rows = [];
        foreach (self::byTeam($q, true) as $entry) {
            $goals = $exclusions = 0;
            foreach ($entry['games'] as ['game' => $game, 'side' => $side]) {
                $goals += $game['powerplay_goals'][$side];
                $exclusions += $game['exclusions'][self::other($side)];
            }
            $rows[] = ['team' => $entry['team'], 'powerplay_goals' => $goals, 'opponent_exclusions' => $exclusions, 'percent' => self::percent($goals, $exclusions)];
        }

        return ['rows' => self::ranked($rows, 'percent', static fn (array $r): string => (string) $r['team']['label'], $q->limit)];
    }
}
