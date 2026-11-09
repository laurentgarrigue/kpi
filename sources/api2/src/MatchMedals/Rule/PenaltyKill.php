<?php

namespace App\MatchMedals\Rule;

use App\MatchMedals\MatchTimeline;
use App\MatchMedals\Model\Award;
use App\MatchMedals\Model\DataLevel;
use App\MatchMedals\Model\Exclusion;

/** Résistance · Penalty Kill: goes through N exclusions or more without conceding during them. */
final class PenaltyKill implements MedalRule
{
    public function code(): string
    {
        return 'penalty_kill';
    }

    public function dataLevel(): DataLevel
    {
        return DataLevel::Timeline;
    }

    public function award(MatchTimeline $game): array
    {
        $sides = array_filter(MatchTimeline::SIDES, static function (string $side) use ($game): bool {
            $exclusions = $game->exclusions($side);

            return count($exclusions) >= $game->thresholds->penaltyKillExclusions
                && array_filter($exclusions, static fn (Exclusion $x): bool => !$x->isKilled) === [];
        });

        return array_map(fn (string $s): Award => Award::team($this->code(), $s), array_values($sides));
    }
}
