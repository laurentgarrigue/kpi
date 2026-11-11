<?php

namespace App\MatchMedals\Rule;

use App\MatchMedals\MatchTimeline;
use App\MatchMedals\Model\Award;
use App\MatchMedals\Model\DataLevel;

/** Série · Scoring Run: N goals in a row without conceding; one medal per run. */
final class ScoringRun implements MedalRule
{
    public function code(): string
    {
        return 'scoring_run';
    }

    public function dataLevel(): DataLevel
    {
        return DataLevel::Timeline;
    }

    public function award(MatchTimeline $game): array
    {
        $awards = [];
        $side = null;
        $run = 0;
        foreach ($game->goals() as $goal) {
            $run = $goal->side() === $side ? $run + 1 : 1;
            $side = $goal->side();
            if ($run === $game->thresholds->scoringRunGoals) {
                $awards[] = Award::team($this->code(), $side, $goal);
            }
        }

        return $awards;
    }
}
