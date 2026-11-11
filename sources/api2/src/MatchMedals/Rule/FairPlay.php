<?php

namespace App\MatchMedals\Rule;

use App\MatchMedals\MatchTimeline;
use App\MatchMedals\Model\Award;
use App\MatchMedals\Model\DataLevel;
use App\MatchMedals\Model\GameEvent;

/** Fair-play · Fair Play: no card in the game. */
final class FairPlay implements MedalRule
{
    public function code(): string
    {
        return 'fair_play';
    }

    public function dataLevel(): DataLevel
    {
        return DataLevel::Score;
    }

    public function award(MatchTimeline $game): array
    {
        $carded = array_map(static fn (GameEvent $e): string => $e->side, $game->cardEvents());
        $sides = array_diff(MatchTimeline::SIDES, $carded);

        return array_map(fn (string $s): Award => Award::team($this->code(), $s), array_values($sides));
    }
}
