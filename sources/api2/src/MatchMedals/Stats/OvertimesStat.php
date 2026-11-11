<?php

namespace App\MatchMedals\Stats;

/** Prolongations · Extra Time: games that went to overtime, most overtimes, golden goals. */
final class OvertimesStat extends AbstractMedalStat
{
    public function needsReliableTimeline(): bool
    {
        return false;
    }

    public function code(): string
    {
        return 'overtimes';
    }

    public function columns(): array
    {
        return [['key' => 'overtimes', 'type' => 'integer']];
    }

    public function compute(MedalStatQuery $q): array
    {
        $rows = [];
        foreach ($q->data->games() as $game) {
            if ($game['overtimes'] > 0) {
                $rows[] = ['game' => $q->data->gameSummary($game['id']), 'overtimes' => $game['overtimes'], 'golden_goal' => $game['winner'] !== null];
            }
        }
        usort($rows, static fn (array $a, array $b): int => $b['overtimes'] <=> $a['overtimes']);

        return [
            'rows' => array_slice($rows, 0, $q->limit),
            'summary' => ['games' => count($rows), 'max_overtimes' => $rows[0]['overtimes'] ?? 0, 'golden_goals' => count(array_filter($rows, static fn (array $r): bool => $r['golden_goal']))],
        ];
    }
}
