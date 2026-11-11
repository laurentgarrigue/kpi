<?php

namespace App\MatchMedals\Rule;

use App\MatchMedals\MatchMedalCalculator;
use App\MatchMedals\MatchTimeline;
use App\MatchMedals\Model\Award;
use App\MatchMedals\Model\DataLevel;

/** But de la victoire · Game Winner: the scorer of the decisive goal (§ 5.5). */
final class GameWinner implements MedalRule
{
    public function code(): string
    {
        return 'game_winner';
    }

    public function dataLevel(): DataLevel
    {
        return DataLevel::Timeline;
    }

    public function award(MatchTimeline $game): array
    {
        $goal = $game->decisiveGoal();

        return $goal === null ? [] : MatchMedalCalculator::present([Award::scorer($this->code(), $goal)]);
    }
}
