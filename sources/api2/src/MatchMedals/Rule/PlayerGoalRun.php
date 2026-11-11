<?php

namespace App\MatchMedals\Rule;

use App\MatchMedals\MatchMedalCalculator;
use App\MatchMedals\MatchTimeline;
use App\MatchMedals\Model\Award;
use App\MatchMedals\Model\DataLevel;

/** N goals in a row of the game by the same player, no other goal between them; one medal per run. */
abstract class PlayerGoalRun implements MedalRule
{
    /** Goals needed in a row, from the thresholds of the game. */
    abstract protected function required(MatchTimeline $game): int;

    final public function dataLevel(): DataLevel
    {
        return DataLevel::Timeline;
    }

    final public function award(MatchTimeline $game): array
    {
        $awards = [];
        $scorer = null;
        $run = 0;
        foreach ($game->goals() as $goal) {
            $player = $goal->event->player;
            $current = $player !== null && !$player->isCoach ? $player->matric : null;
            $run = $current !== null && $current === $scorer ? $run + 1 : 1;
            $scorer = $current;
            if ($current !== null && $run === $this->required($game)) {
                $awards[] = Award::scorer($this->code(), $goal);
            }
        }

        return MatchMedalCalculator::present($awards);
    }
}
