<?php

namespace App\MatchMedals\Stats;

use App\MatchMedals\MedalThresholds;

/** What a statistic is computed on: the data of a perimeter, the medal codes visible on the surface, the limit. */
final readonly class MedalStatQuery
{
    /** @param list<string> $visibleCodes medals and labels the surface may show */
    public function __construct(
        public MedalStatsData $data,
        public array $visibleCodes,
        public MedalThresholds $thresholds,
        public int $limit,
    ) {
    }

    public function isVisible(string $code): bool
    {
        return in_array($code, $this->visibleCodes, true);
    }

    /** A referee indicator is only shown from N games officiated (§ 3.5). */
    public function hasEnoughGames(int $games): bool
    {
        return $games >= $this->thresholds->refereeMinGames;
    }
}
