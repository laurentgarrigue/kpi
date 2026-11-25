<?php

namespace App\ClubRanking\Calculator;

use App\ClubRanking\Model\Alert;
use App\ClubRanking\Model\Line;

/** Lines and alerts produced by the calculation of one rule (or of the whole scale). */
final class CalculationResult
{
    /**
     * @param list<Line>  $lines
     * @param list<Alert> $alerts
     */
    public function __construct(
        public readonly array $lines = [],
        public readonly array $alerts = [],
    ) {
    }
}
