<?php

namespace App\MatchMedals\Rule;

use App\MatchMedals\MatchTimeline;
use App\MatchMedals\Model\Award;
use App\MatchMedals\Model\DataLevel;

/** Marathon: the game reaches N overtimes or more (both teams). */
final class Marathon implements MedalRule
{
    public function code(): string
    {
        return 'marathon';
    }

    public function dataLevel(): DataLevel
    {
        return DataLevel::Score;
    }

    public function award(MatchTimeline $game): array
    {
        return $game->overtimes() >= $game->thresholds->marathonOvertimes
            ? array_map(fn (string $s): Award => Award::team($this->code(), $s), MatchTimeline::SIDES)
            : [];
    }
}
