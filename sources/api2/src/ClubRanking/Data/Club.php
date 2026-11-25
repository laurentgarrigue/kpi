<?php

namespace App\ClubRanking\Data;

/**
 * A structure that may carry teams or licensees. Only an eligible club earns points (§ 5.3): not a regional or
 * departmental selection, not a foreign club.
 */
final class Club
{
    public function __construct(
        public readonly string $code,
        public readonly string $label,
        public readonly bool $eligible,
        public readonly string $department = '',
        public readonly string $region = '',
    ) {
    }
}
