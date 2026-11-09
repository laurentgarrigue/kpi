<?php

namespace App\MatchMedals\Rule;

use App\MatchMedals\MatchTimeline;
use App\MatchMedals\Model\DataLevel;

/** Referee medal — Match fluide · Smooth Game: no card in the game. */
final class RefSmoothGame extends RefereeRule
{
    public function code(): string
    {
        return 'ref_smooth_game';
    }

    public function dataLevel(): DataLevel
    {
        return DataLevel::Score;
    }

    protected function holds(MatchTimeline $game): bool
    {
        return $game->cardEvents() === [];
    }
}
