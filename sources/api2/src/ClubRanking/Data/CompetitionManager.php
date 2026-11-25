<?php

namespace App\ClubRanking\Data;

/** A competition manager (RC) of the season (kp_rc). */
final class CompetitionManager
{
    public function __construct(
        public readonly int $matric,
        public readonly ?string $competition,
    ) {
    }
}
