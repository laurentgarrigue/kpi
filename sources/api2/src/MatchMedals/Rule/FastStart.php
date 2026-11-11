<?php

namespace App\MatchMedals\Rule;

use App\MatchMedals\MatchMedalCalculator;
use App\MatchMedals\MatchTimeline;
use App\MatchMedals\Model\Award;
use App\MatchMedals\Model\DataLevel;
use App\MatchMedals\Model\TimedGoal;

/** Entrée express · Fast Start: scores in the first seconds of a half. */
final class FastStart implements MedalRule
{
    public function code(): string
    {
        return 'fast_start';
    }

    public function dataLevel(): DataLevel
    {
        return DataLevel::Timeline;
    }

    public function award(MatchTimeline $game): array
    {
        $fast = array_filter(
            $game->goals(),
            static fn (TimedGoal $g): bool => $game->isRegulationPeriod($g->event->period) && $g->secondsInPeriod() < $game->thresholds->fastStartSeconds,
        );

        return MatchMedalCalculator::present(array_map(fn (TimedGoal $g): ?Award => Award::scorer($this->code(), $g), array_values($fast)));
    }
}
