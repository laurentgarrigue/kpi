<?php

namespace App\MatchMedals\Rule;

use App\MatchMedals\MatchTimeline;
use App\MatchMedals\Model\Award;
use App\MatchMedals\Model\DataLevel;

/** De bout en bout · Wire to Wire: opens the score, is never caught up, and wins. */
final class WireToWire implements MedalRule
{
    public function code(): string
    {
        return 'wire_to_wire';
    }

    public function dataLevel(): DataLevel
    {
        return DataLevel::Timeline;
    }

    public function award(MatchTimeline $game): array
    {
        $winner = $game->winner();
        if ($winner === null || $game->firstGoal()?->side() !== $winner) {
            return [];
        }
        $loser = MatchTimeline::opponent($winner);
        foreach ($game->goals() as $goal) {
            if ($goal->scoreAfter[$winner] <= $goal->scoreAfter[$loser]) {
                return [];
            }
        }

        return [Award::team($this->code(), $winner)];
    }
}
