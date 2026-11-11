<?php

namespace App\MatchMedals\Rule;

use App\MatchMedals\MatchTimeline;
use App\MatchMedals\Model\DataLevel;

/** Referee medal — Prolongation · Extra Time: the game goes to overtime. */
final class RefExtraTime extends RefereeRule
{
    public function code(): string
    {
        return 'ref_extra_time';
    }

    public function dataLevel(): DataLevel
    {
        return DataLevel::Score;
    }

    protected function holds(MatchTimeline $game): bool
    {
        return $game->isOvertime();
    }
}
