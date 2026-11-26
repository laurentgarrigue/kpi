<?php

namespace App\Designation;

/**
 * État d'une case du suivi de couverture (FEATURE_DESIGNATIONS.md D26) : rouge = rôle requis non pourvu ; orange =
 * pourvu par une personne sans email ; vert = pourvu (y compris par une personne du club d'une équipe engagée, que
 * l'alerte signale sans changer la couleur) ; vide = facultatif non pourvu.
 */
enum CoverageStatus: string
{
    case Red = 'red';
    case Orange = 'orange';
    case Green = 'green';
    case Empty = 'empty';
    case NotApplicable = 'not_applicable';

    /** @param list<CoverageHolder> $holders */
    public static function of(Requirement $requirement, array $holders): self
    {
        if ($requirement === Requirement::NotApplicable) {
            return self::NotApplicable;
        }
        if ($holders === []) {
            return $requirement === Requirement::Required ? self::Red : self::Empty;
        }
        foreach ($holders as $holder) {
            if (!$holder->hasEmail) {
                return self::Orange;
            }
        }

        return self::Green;
    }
}
