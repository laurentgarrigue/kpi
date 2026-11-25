<?php

namespace App\ClubRanking\Data;

/** A line typed by hand for a manual rule (§ 7): a licensee (his club is read) or directly a club, with a detail. */
final class ManualEntry
{
    public function __construct(
        public readonly int $id,
        public readonly string $ruleCode,
        public readonly ?int $matric,
        public readonly ?string $clubCode,
        public readonly string $detail = '',
    ) {
    }
}
