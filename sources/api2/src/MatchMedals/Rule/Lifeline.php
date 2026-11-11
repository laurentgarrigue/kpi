<?php

namespace App\MatchMedals\Rule;

use App\MatchMedals\MatchMedalCalculator;
use App\MatchMedals\MatchTimeline;
use App\MatchMedals\Model\Award;
use App\MatchMedals\Model\DataLevel;

/** Égalisation salvatrice · Lifeline: equalises in the last seconds of regulation time and forces overtime. */
final class Lifeline implements MedalRule
{
    public function code(): string
    {
        return 'lifeline';
    }

    public function dataLevel(): DataLevel
    {
        return DataLevel::Timeline;
    }

    public function award(MatchTimeline $game): array
    {
        $goal = $game->lastRegulationGoal();
        if ($goal === null || !$game->isOvertime() || !$game->isLate($goal) || $goal->scoreAfter['A'] !== $goal->scoreAfter['B']) {
            return [];
        }

        return MatchMedalCalculator::present([Award::team($this->code(), $goal->side(), $goal), Award::scorer($this->code(), $goal)]);
    }
}
