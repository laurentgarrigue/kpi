<?php

namespace App\MatchMedals\Stats;

/** Matchs à revoir · Highlights: games labelled Thriller, Seesaw or Goal Fest (visible labels only), by date. */
final class HighlightsStat extends AbstractMedalStat
{
    private const LABELS = ['thriller', 'seesaw', GoalFest::CODE];

    public function code(): string
    {
        return 'highlights';
    }

    public function compute(MedalStatQuery $q): array
    {
        $rows = [];
        foreach ($q->data->games() as $game) {
            $labels = array_values(array_filter($q->data->labels($game['id']), static fn (string $l): bool => in_array($l, self::LABELS, true) && $q->isVisible($l)));
            if ($labels !== []) {
                $rows[] = ['game' => $q->data->gameSummary($game['id']), 'labels' => $labels];
            }
        }

        return ['rows' => array_slice($rows, 0, $q->limit)];
    }
}
