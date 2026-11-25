<?php

namespace App\ClubRanking;

/** One club of the ranking: its total, its points by rule and its rank. */
final class StandingRow
{
    /** @param array<string, int> $byRule */
    public function __construct(
        public readonly string $clubCode,
        public readonly string $label,
        public readonly int $total,
        public readonly array $byRule,
        public readonly int $rank,
    ) {
    }
}
