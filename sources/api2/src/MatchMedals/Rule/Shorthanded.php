<?php

namespace App\MatchMedals\Rule;

use App\MatchMedals\MatchMedalCalculator;
use App\MatchMedals\MatchTimeline;
use App\MatchMedals\Model\Award;
use App\MatchMedals\Model\DataLevel;

/** Infériorité · Shorthanded: scores while the own team has more players excluded (one medal per goal). */
final class Shorthanded implements MedalRule
{
    public function code(): string
    {
        return 'shorthanded';
    }

    public function dataLevel(): DataLevel
    {
        return DataLevel::Timeline;
    }

    public function award(MatchTimeline $game): array
    {
        $awards = [];
        foreach ($game->goals() as $goal) {
            if ($goal->isShorthanded) {
                array_push($awards, Award::team($this->code(), $goal->side(), $goal), Award::scorer($this->code(), $goal));
            }
        }

        return MatchMedalCalculator::present($awards);
    }
}
