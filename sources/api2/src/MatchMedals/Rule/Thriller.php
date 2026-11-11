<?php

namespace App\MatchMedals\Rule;

use App\MatchMedals\MatchTimeline;
use App\MatchMedals\Model\Award;
use App\MatchMedals\Model\DataLevel;

/** Suspense · Thriller (game label): N changes of leader or more. */
final class Thriller implements MedalRule
{
    public function code(): string
    {
        return 'thriller';
    }

    public function dataLevel(): DataLevel
    {
        return DataLevel::Timeline;
    }

    public function award(MatchTimeline $game): array
    {
        return $game->isThriller() ? [Award::label($this->code())] : [];
    }
}
