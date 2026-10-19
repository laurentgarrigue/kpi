<?php

namespace App\Scrutineering;

/**
 * Nation of a team for the "same nation" navigation (PAGE_SCRUTINEERING.md § 1.2): a 4-character club code is a
 * French club, any other code starts with its nation (same rule as the nation logos, commun/MyTools.php).
 */
final class TeamNation
{
    private const FRENCH_CLUB_CODE_LENGTH = 4;
    private const FRANCE = 'FRA';
    private const NATION_CODE_LENGTH = 3;

    public static function of(string $clubCode): string
    {
        if (strlen($clubCode) === self::FRENCH_CLUB_CODE_LENGTH) {
            return self::FRANCE;
        }

        return substr($clubCode, 0, self::NATION_CODE_LENGTH);
    }
}
