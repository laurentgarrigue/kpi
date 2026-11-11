<?php

namespace App\MatchMedals\Rule;

use App\MatchMedals\MatchTimeline;

/** Quadruplé · Four-goal Haul: N goals or more in the game. */
final class Quadruple extends PlayerGoalsInGame
{
    public function code(): string
    {
        return 'quadruple';
    }

    protected function required(MatchTimeline $game): int
    {
        return $game->thresholds->quadrupleGoals;
    }
}
