<?php

namespace App\MatchMedals\Rule;

use App\MatchMedals\MatchTimeline;

/** Triplé · Hat-trick: N goals or more in the game. */
final class HatTrick extends PlayerGoalsInGame
{
    public function code(): string
    {
        return 'hat_trick';
    }

    protected function required(MatchTimeline $game): int
    {
        return $game->thresholds->hatTrickGoals;
    }
}
