<?php

namespace App\AgeCategory;

use App\Service\ActiveSeason;

/**
 * Validation of a birth date entered on the athlete sheet (CATEGORIE_AGE.md story 14): a real YYYY-MM-DD date giving
 * an age between AgeCategoryRule::MIN_PLAUSIBLE_AGE and MAX_PLAUSIBLE_AGE in the active season.
 */
final class BirthDatePlausibility
{
    public function __construct(private readonly ActiveSeason $activeSeason)
    {
    }

    public function isPlausible(string $birthDate): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $birthDate);

        return $date !== false
            && $date->format('Y-m-d') === $birthDate
            && AgeCategoryRule::isPlausibleBirthDate($birthDate, $this->activeSeason->code());
    }

    /** Explicit message of the refusal. */
    public static function refusalMessage(): string
    {
        return sprintf(
            "Date de naissance invraisemblable : l'athlète doit avoir entre %d et %d ans pour la saison en cours",
            AgeCategoryRule::MIN_PLAUSIBLE_AGE,
            AgeCategoryRule::MAX_PLAUSIBLE_AGE
        );
    }
}
