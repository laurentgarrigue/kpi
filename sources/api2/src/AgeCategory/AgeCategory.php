<?php

namespace App\AgeCategory;

/** One row of the category table (kp_categorie): ages are counted on the season, bounds included. */
final class AgeCategory
{
    public function __construct(
        public readonly string $code,
        public readonly string $label,
        public readonly int $minAge,
        public readonly int $maxAge,
    ) {
    }

    public function includes(int $age): bool
    {
        return $age >= $this->minAge && $age <= $this->maxAge;
    }
}
