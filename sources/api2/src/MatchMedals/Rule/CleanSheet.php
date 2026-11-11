<?php

namespace App\MatchMedals\Rule;

use App\MatchMedals\MatchTimeline;
use App\MatchMedals\Model\Award;
use App\MatchMedals\Model\DataLevel;

/** Cage inviolée · Clean Sheet: concedes no goal. */
final class CleanSheet implements MedalRule
{
    public function code(): string
    {
        return 'clean_sheet';
    }

    public function dataLevel(): DataLevel
    {
        return DataLevel::Score;
    }

    public function award(MatchTimeline $game): array
    {
        $sides = array_filter(MatchTimeline::SIDES, static fn (string $s): bool => $game->score(MatchTimeline::opponent($s)) === 0);

        return array_map(fn (string $s): Award => Award::team($this->code(), $s), array_values($sides));
    }
}
