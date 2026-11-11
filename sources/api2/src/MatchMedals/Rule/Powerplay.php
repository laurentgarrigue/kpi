<?php

namespace App\MatchMedals\Rule;

use App\MatchMedals\MatchMedalCalculator;
use App\MatchMedals\MatchTimeline;
use App\MatchMedals\Model\Award;
use App\MatchMedals\Model\DataLevel;

/** Supériorité · Powerplay: scores while the opponents have more players excluded (one medal per goal). */
final class Powerplay implements MedalRule
{
    public function code(): string
    {
        return 'powerplay';
    }

    public function dataLevel(): DataLevel
    {
        return DataLevel::Timeline;
    }

    public function award(MatchTimeline $game): array
    {
        $awards = [];
        foreach ($game->goals() as $goal) {
            if ($goal->isPowerplay) {
                array_push($awards, Award::team($this->code(), $goal->side(), $goal), Award::scorer($this->code(), $goal));
            }
        }

        return MatchMedalCalculator::present($awards);
    }
}
