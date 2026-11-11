<?php

namespace App\MatchMedals\Stats;

/** Perimeter of a statistic: one season, one or several competitions (a competition page, or the admin context). */
final readonly class MedalScope
{
    /** @param list<string> $competitions */
    public function __construct(public string $season, public array $competitions)
    {
    }
}
