<?php

namespace App\MatchMedals\Rule;

use App\MatchMedals\MatchMedalCalculator;
use App\MatchMedals\MatchTimeline;
use App\MatchMedals\Model\Award;
use App\MatchMedals\Model\DataLevel;

/** Dernières secondes · Late Winner: the decisive goal, in the last seconds of regulation time. */
final class LateWinner implements MedalRule
{
    public function code(): string
    {
        return 'late_winner';
    }

    public function dataLevel(): DataLevel
    {
        return DataLevel::Timeline;
    }

    public function award(MatchTimeline $game): array
    {
        $goal = $game->decisiveGoal();
        if ($goal === null || !$game->isLate($goal)) {
            return [];
        }

        return MatchMedalCalculator::present([Award::team($this->code(), $goal->side(), $goal), Award::scorer($this->code(), $goal)]);
    }
}
