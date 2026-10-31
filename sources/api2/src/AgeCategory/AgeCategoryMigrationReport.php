<?php

namespace App\AgeCategory;

/** Outcome of the age category migration, planned (dry run) or applied. */
final class AgeCategoryMigrationReport
{
    public function __construct(
        public readonly int $convertedBirthDates,
        public readonly AgeCategoryChanges $categoryChanges,
    ) {
    }
}
