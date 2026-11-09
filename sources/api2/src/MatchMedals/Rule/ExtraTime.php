<?php

namespace App\MatchMedals\Rule;

use App\MatchMedals\MatchTimeline;
use App\MatchMedals\Model\Award;
use App\MatchMedals\Model\DataLevel;

/** Prolongation · Extra Time: the game goes to overtime (both teams). */
final class ExtraTime implements MedalRule
{
    public function code(): string
    {
        return 'extra_time';
    }

    public function dataLevel(): DataLevel
    {
        return DataLevel::Score;
    }

    public function award(MatchTimeline $game): array
    {
        return $game->isOvertime() ? array_map(fn (string $s): Award => Award::team($this->code(), $s), MatchTimeline::SIDES) : [];
    }
}
