<?php

namespace App\MatchMedals\Rule;

use App\MatchMedals\MatchTimeline;
use App\MatchMedals\Model\DataLevel;

/** Referee medal — Calme plat · Dead Calm: neither a goal nor a card in the game. */
final class RefCalmGame extends RefereeRule
{
    public function code(): string
    {
        return 'ref_calm_game';
    }

    public function dataLevel(): DataLevel
    {
        return DataLevel::Score;
    }

    protected function holds(MatchTimeline $game): bool
    {
        return $game->score(MatchTimeline::SIDES[0]) === 0 && $game->score(MatchTimeline::SIDES[1]) === 0 && $game->cardEvents() === [];
    }
}
