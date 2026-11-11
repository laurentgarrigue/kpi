<?php

namespace App\MatchMedals\Rule;

use App\MatchMedals\MatchTimeline;

/** Triplé consécutif · Natural Hat-trick: N goals in a row by the same player. */
final class NaturalHatTrick extends PlayerGoalRun
{
    public function code(): string
    {
        return 'natural_hat_trick';
    }

    protected function required(MatchTimeline $game): int
    {
        return $game->thresholds->hatTrickGoals;
    }
}
