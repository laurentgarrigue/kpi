<?php

namespace App\MatchMedals\Rule;

use App\MatchMedals\MatchTimeline;

/** Quadruplé consécutif · Natural Four-goal Haul: N goals in a row by the same player. */
final class NaturalQuadruple extends PlayerGoalRun
{
    public function code(): string
    {
        return 'natural_quadruple';
    }

    protected function required(MatchTimeline $game): int
    {
        return $game->thresholds->quadrupleGoals;
    }
}
