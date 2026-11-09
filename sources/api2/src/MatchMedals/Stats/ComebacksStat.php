<?php

namespace App\MatchMedals\Stats;

/** Remontées · Comebacks: games won after trailing by the comeback deficit or more; biggest first. */
final class ComebacksStat extends AbstractMedalStat
{
    public function code(): string
    {
        return 'comebacks';
    }

    public function columns(): array
    {
        return [['key' => 'deficit', 'type' => 'integer']];
    }

    public function compute(MedalStatQuery $q): array
    {
        $rows = [];
        foreach ($q->data->games(true) as $game) {
            $winner = $game['winner'];
            if ($winner !== null && $game['max_deficit'][$winner] >= $q->thresholds->comebackDeficit) {
                $rows[] = ['game' => $q->data->gameSummary($game['id']), 'team' => self::teamOf($q, $game, $winner), 'deficit' => $game['max_deficit'][$winner]];
            }
        }
        usort($rows, static fn (array $a, array $b): int => $b['deficit'] <=> $a['deficit']);

        return ['rows' => array_slice($rows, 0, $q->limit), 'summary' => ['count' => count($rows), 'biggest' => $rows[0]['deficit'] ?? null]];
    }
}
