<?php

namespace App\MatchMedals\Rule;

use App\MatchMedals\MatchTimeline;
use App\MatchMedals\Model\DataLevel;

/** Referee medal — Match couperet · Knockout: a knockout game (type E). */
final class RefKnockout extends RefereeRule
{
    public function code(): string
    {
        return 'ref_knockout';
    }

    public function dataLevel(): DataLevel
    {
        return DataLevel::Score;
    }

    protected function holds(MatchTimeline $game): bool
    {
        return $game->isKnockout();
    }
}
