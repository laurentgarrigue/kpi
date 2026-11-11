<?php

namespace App\MatchMedals\Rule;

use App\MatchMedals\MatchMedalCalculator;
use App\MatchMedals\MatchTimeline;
use App\MatchMedals\Model\Award;
use App\MatchMedals\Model\DataLevel;

/**
 * But de la délivrance · Tiebreaker: the last goal of the game, scored on a tie, for a one-goal win (a golden goal
 * among others). It is the decisive goal (§ 5.5) when nothing is scored after it.
 */
final class Tiebreaker implements MedalRule
{
    public function code(): string
    {
        return 'tiebreaker';
    }

    public function dataLevel(): DataLevel
    {
        return DataLevel::Timeline;
    }

    public function award(MatchTimeline $game): array
    {
        $goals = $game->goals();
        $decisive = $game->decisiveGoal();
        if ($decisive === null || $decisive !== end($goals)) {
            return [];
        }

        return MatchMedalCalculator::present([Award::scorer($this->code(), $decisive)]);
    }
}
