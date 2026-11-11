<?php

namespace App\MatchMedals\Rule;

use App\MatchMedals\MatchMedalCalculator;
use App\MatchMedals\MatchTimeline;
use App\MatchMedals\Model\Award;
use App\MatchMedals\Model\DataLevel;

/** Premier but · Opener: the team and the player who open the score. */
final class Opener implements MedalRule
{
    public function code(): string
    {
        return 'opener';
    }

    public function dataLevel(): DataLevel
    {
        return DataLevel::Timeline;
    }

    public function award(MatchTimeline $game): array
    {
        $goal = $game->firstGoal();

        return $goal === null ? [] : MatchMedalCalculator::present([Award::team($this->code(), $goal->side(), $goal), Award::scorer($this->code(), $goal)]);
    }
}
