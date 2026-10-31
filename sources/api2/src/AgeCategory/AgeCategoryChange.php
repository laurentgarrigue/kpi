<?php

namespace App\AgeCategory;

/** One composition row whose cached Categ differs from the age category derived for the season of its team. */
final class AgeCategoryChange
{
    public function __construct(
        public readonly int $teamId,
        public readonly int $matric,
        public readonly string $season,
        public readonly ?string $before,
        public readonly ?string $after,
    ) {
    }
}
