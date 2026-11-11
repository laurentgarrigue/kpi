<?php

namespace App\MatchMedals\Stats;

/** Minutes des buts · Goal Timing: goals by slice of playing time; overtime goals in a last row. */
final class GoalTimingStat extends AbstractMedalStat
{
    public function code(): string
    {
        return 'goal_timing';
    }

    public function columns(): array
    {
        return [['key' => 'goals', 'type' => 'integer']];
    }

    public function compute(MedalStatQuery $q): array
    {
        $slice = $q->thresholds->goalTimingSliceSeconds;
        $regulation = 2 * $q->thresholds->regularPeriodSeconds;
        $slices = array_fill(0, (int) ceil($regulation / $slice), 0);
        $overtime = 0;
        foreach ($q->data->games(true) as $game) {
            foreach ($game['goal_seconds'] as ['seconds' => $seconds]) {
                if ($seconds >= $regulation) {
                    ++$overtime;
                } else {
                    ++$slices[intdiv($seconds, $slice)];
                }
            }
        }
        $rows = array_map(static fn (int $goals, int $i): array => ['from' => $i * $slice, 'to' => ($i + 1) * $slice, 'goals' => $goals], $slices, array_keys($slices));
        $rows[] = ['from' => $regulation, 'to' => null, 'goals' => $overtime];

        return ['rows' => $rows];
    }
}
