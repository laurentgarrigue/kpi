<?php

namespace App\ClubRanking\Model;

/** Manual addition or removal of points to a club for a rule (§ 7), always with its reason, author and date. */
final class Adjustment
{
    public function __construct(
        public readonly string $ruleCode,
        public readonly string $clubCode,
        public readonly int $points,
        public readonly string $reason,
        public readonly ?int $id = null,
        public readonly ?string $author = null,
        public readonly ?string $createdAt = null,
    ) {
    }
}
