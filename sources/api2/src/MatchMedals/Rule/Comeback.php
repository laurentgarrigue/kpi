<?php

namespace App\MatchMedals\Rule;

use App\MatchMedals\MatchTimeline;
use App\MatchMedals\Model\Award;
use App\MatchMedals\Model\DataLevel;

/** Remontée · Comeback: trailed by N goals or more, and wins. */
final class Comeback implements MedalRule
{
    public function code(): string
    {
        return 'comeback';
    }

    public function dataLevel(): DataLevel
    {
        return DataLevel::Timeline;
    }

    public function award(MatchTimeline $game): array
    {
        $winner = $game->winner();

        return $winner !== null && $game->maxDeficit($winner) >= $game->thresholds->comebackDeficit ? [Award::team($this->code(), $winner)] : [];
    }
}
