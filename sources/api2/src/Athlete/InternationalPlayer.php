<?php

namespace App\Athlete;

/**
 * « Joueur international » (GLOSSARY.md, PAGE_ATHLETES.md Q7): no FFCK licence, i.e. a licence number from the
 * non-federal range. Only these athletes are edited by hand, FFCK licences come from the PCE file.
 */
final class InternationalPlayer
{
    public const FIRST_MATRIC = 2000000;

    public static function is(int $matric): bool
    {
        return $matric >= self::FIRST_MATRIC;
    }
}
