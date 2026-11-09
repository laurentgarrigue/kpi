<?php

namespace App\MatchMedals\Rule;

use App\MatchMedals\MatchMedalCalculator;
use App\MatchMedals\MatchTimeline;
use App\MatchMedals\Model\Award;
use App\MatchMedals\Model\DataLevel;

/** Réplique · Bounce Back: scores less than N seconds after conceding (the previous goal of the game). */
final class BounceBack implements MedalRule
{
    public function code(): string
    {
        return 'bounce_back';
    }

    public function dataLevel(): DataLevel
    {
        return DataLevel::Timeline;
    }

    public function award(MatchTimeline $game): array
    {
        $awards = [];
        $previous = null;
        foreach ($game->goals() as $goal) {
            if ($previous !== null && $previous->side() !== $goal->side() && $goal->seconds - $previous->seconds < $game->thresholds->bounceBackSeconds) {
                $awards[] = Award::team($this->code(), $goal->side(), $goal);
                $awards[] = Award::scorer($this->code(), $goal);
            }
            $previous = $goal;
        }

        return MatchMedalCalculator::present($awards);
    }
}
