<?php

namespace App\MatchMedals\Rule;

use App\MatchMedals\MatchTimeline;
use App\MatchMedals\Model\DataLevel;

/** Referee medal — But en or · Golden Goal: the game is decided by a golden goal. */
final class RefGoldenGoal extends RefereeRule
{
    public function code(): string
    {
        return 'ref_golden_goal';
    }

    public function dataLevel(): DataLevel
    {
        return DataLevel::Score;
    }

    protected function holds(MatchTimeline $game): bool
    {
        return $game->isOvertime() && $game->winner() !== null;
    }
}
