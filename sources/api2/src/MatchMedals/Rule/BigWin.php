<?php

namespace App\MatchMedals\Rule;

use App\MatchMedals\MatchTimeline;
use App\MatchMedals\Model\Award;
use App\MatchMedals\Model\DataLevel;

/** Large victoire · Big Win: wins by N goals or more. */
final class BigWin implements MedalRule
{
    public function code(): string
    {
        return 'big_win';
    }

    public function dataLevel(): DataLevel
    {
        return DataLevel::Score;
    }

    public function award(MatchTimeline $game): array
    {
        $winner = $game->winner();
        if ($winner === null || $game->score($winner) - $game->score(MatchTimeline::opponent($winner)) < $game->thresholds->bigWinMargin) {
            return [];
        }

        return [Award::team($this->code(), $winner)];
    }
}
