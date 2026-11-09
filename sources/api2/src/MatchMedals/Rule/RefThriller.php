<?php

namespace App\MatchMedals\Rule;

use App\MatchMedals\MatchTimeline;
use App\MatchMedals\Model\DataLevel;

/** Referee medal — Suspense · Thriller: the game is labelled Thriller. */
final class RefThriller extends RefereeRule
{
    public function code(): string
    {
        return 'ref_thriller';
    }

    public function dataLevel(): DataLevel
    {
        return DataLevel::Timeline;
    }

    protected function holds(MatchTimeline $game): bool
    {
        return $game->isThriller();
    }
}
