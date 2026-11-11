<?php

namespace App\MatchMedals\Stats;

/** Délai du premier but · First Goal Time: average and median time before the first goal; fastest first goals. */
final class FirstGoalTimeStat extends AbstractMedalStat
{
    public function code(): string
    {
        return 'first_goal_time';
    }

    public function columns(): array
    {
        return [['key' => 'seconds', 'type' => 'seconds']];
    }

    public function compute(MedalStatQuery $q): array
    {
        $times = [];
        foreach ($q->data->games(true) as $game) {
            if ($game['first_goal_seconds'] !== null) {
                $times[] = ['game' => $q->data->gameSummary($game['id']), 'team' => self::teamOf($q, $game, (string) $game['first_goal_side']), 'seconds' => $game['first_goal_seconds']];
            }
        }
        usort($times, static fn (array $a, array $b): int => $a['seconds'] <=> $b['seconds']);
        $values = array_column($times, 'seconds');
        $count = count($values);
        $median = $count === 0 ? null : ($count % 2 ? $values[intdiv($count, 2)] : intdiv($values[$count / 2 - 1] + $values[$count / 2], 2));

        return [
            'rows' => array_slice($times, 0, $q->limit),
            'summary' => ['average' => self::average(array_sum($values), $count, 0), 'median' => $median],
        ];
    }
}
