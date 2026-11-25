<?php

namespace App\Designation;

/** Ce qui compte d'un titulaire pour l'état d'une case (D26) : email connu (compte), club d'une équipe engagée. */
final class CoverageHolder
{
    public const WARNING_NO_EMAIL = 'no_email';
    public const WARNING_SAME_CLUB = 'same_club';

    public function __construct(
        public readonly bool $hasEmail,
        public readonly bool $sameClubAsEngagedTeam,
    ) {
    }

    /** @return list<string> */
    public function warnings(): array
    {
        return array_values(array_filter([
            $this->hasEmail ? null : self::WARNING_NO_EMAIL,
            $this->sameClubAsEngagedTeam ? self::WARNING_SAME_CLUB : null,
        ]));
    }
}
