<?php

namespace App\MatchMedals\Rule;

use App\MatchMedals\MatchTimeline;
use App\MatchMedals\Model\Award;
use App\MatchMedals\Model\DataLevel;

/** Chassé-croisé · Seesaw (game label): N equalisers or more. */
final class Seesaw implements MedalRule
{
    public function code(): string
    {
        return 'seesaw';
    }

    public function dataLevel(): DataLevel
    {
        return DataLevel::Timeline;
    }

    public function award(MatchTimeline $game): array
    {
        return $game->equalizers() >= $game->thresholds->seesawEqualizers ? [Award::label($this->code())] : [];
    }
}
