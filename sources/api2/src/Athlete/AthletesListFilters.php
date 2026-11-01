<?php

namespace App\Athlete;

/** Combinable filters of the athletes list (PAGE_ATHLETES.md § 6.8), all off by default. */
final class AthletesListFilters
{
    public function __construct(
        public readonly string $search = '',
        public readonly bool $unknownBirthDate = false,
        public readonly bool $international = false,
        public readonly bool $engagedThisSeason = false,
    ) {
    }
}
