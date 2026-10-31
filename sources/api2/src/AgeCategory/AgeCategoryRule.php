<?php

namespace App\AgeCategory;

/**
 * The single age category rule of api2 (CATEGORIE_AGE.md, ADR 0001, GLOSSARY.md « Catégorie d'âge »).
 *
 * The category is derived from the birth year and the season of the competition (never the calendar year nor the
 * season of origin of the licence). Stateless: the category table comes from the injected AgeCategoryTable.
 */
final class AgeCategoryRule
{
    /** Dates written by the old code for « unknown »: they are not birth dates (GLOSSARY.md). */
    public const CONVENTIONAL_BIRTH_DATES = ['1901-01-01', '0000-00-00'];

    /** A birth date giving an age outside these bounds for the season is implausible (story 14). */
    public const MIN_PLAUSIBLE_AGE = 5;
    public const MAX_PLAUSIBLE_AGE = 100;

    public function __construct(private readonly AgeCategoryTable $table)
    {
    }

    /** Category of a player for a season, null when the birth date is unknown or out of every category. */
    public function categoryFor(?string $birthDate, string $season): ?AgeCategory
    {
        if (self::isUnknownBirthDate($birthDate)) {
            return null;
        }
        $age = self::ageIn($birthDate, $season);
        foreach ($this->table->all() as $category) {
            if ($category->includes($age)) {
                return $category;
            }
        }

        return null;
    }

    /** True for an absent birth date and for the conventional dates standing for « unknown ». */
    public static function isUnknownBirthDate(?string $birthDate): bool
    {
        return $birthDate === null || $birthDate === '' || in_array($birthDate, self::CONVENTIONAL_BIRTH_DATES, true);
    }

    /** False for a birth date giving less than 5 or more than 100 years old in the season, and for the conventional dates. */
    public static function isPlausibleBirthDate(string $birthDate, string $season): bool
    {
        if (self::isUnknownBirthDate($birthDate)) {
            return false;
        }
        $age = self::ageIn($birthDate, $season);

        return $age >= self::MIN_PLAUSIBLE_AGE && $age <= self::MAX_PLAUSIBLE_AGE;
    }

    /** Age reached during the season: season minus birth year. */
    private static function ageIn(string $birthDate, string $season): int
    {
        return (int) $season - (int) substr($birthDate, 0, 4);
    }
}
