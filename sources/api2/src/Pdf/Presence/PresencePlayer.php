<?php

namespace App\Pdf\Presence;

/**
 * Formatting of a player row on the presence and control sheets, as the legacy pages did
 * (admin/FeuillePresence*.php, controle_pagaie() of commun/MyTools.php). Pure functions, unit-tested.
 */
final class PresencePlayer
{
    /** Licence numbers from this value are provisional: the ICF number (Reserve) stands for them. */
    public const PROVISIONAL_LICENCE_FROM = 2000000;

    private const PADDLE_COLOURS = ['PAGR' => 'Rouge', 'PAGN' => 'Noire', 'PAGBL' => 'Bleue', 'PAGV' => 'Verte'];
    private const PADDLE_BELOW_GREEN = ['PAGJ' => 'Jaune', 'PAGB' => 'Blanche'];

    /** Equipment control statuses of kp_scrutineering (PAGE_SCRUTINEERING.md). */
    public const CONTROL_STATUSES = [1 => 'ok', 2 => 'cosmetic', 3 => 'safety', 4 => 'technical'];

    /**
     * Paddle level: the kayak-polo (ECA) level from green up, else an equivalent level of whitewater (EVI) or sea
     * (MER) kayaking, between parentheses; a yellow or white kayak-polo paddle is shown when nothing better exists.
     */
    public static function paddle(?string $polo, ?string $whitewater, ?string $sea): string
    {
        if (isset(self::PADDLE_COLOURS[(string) $polo])) {
            return self::PADDLE_COLOURS[(string) $polo];
        }
        // Same precedence as the legacy pages: a sea level overrides a whitewater one.
        foreach ([$whitewater, $sea] as $other) {
            if (isset(self::PADDLE_COLOURS[(string) $other])) {
                $equivalent = self::PADDLE_COLOURS[(string) $other];
            }
        }

        return isset($equivalent) ? '(' . $equivalent . ')' : (self::PADDLE_BELOW_GREEN[(string) $polo] ?? '');
    }

    /**
     * Label of the role (kp_competition_equipe_joueur.Capitaine): C captain, E staff, A referee, X inactive; empty for
     * a player (- or nothing).
     *
     * @param array<string, string> $labels labels of the language (`role.C`, …)
     */
    public static function role(?string $captain, array $labels): string
    {
        $code = (string) $captain;

        return $code === '' || $code === '-' ? '' : ($labels['role.' . $code] ?? $code);
    }

    /** Licence number shown: the ICF number for a provisional licence, with the season of origin when it is not the current one. */
    public static function licence(int $matric, ?string $icf, ?string $origin, string $season): string
    {
        $number = $matric >= self::PROVISIONAL_LICENCE_FROM ? (($icf ?? '0') === '0' ? '' : (string) $icf) : (string) $matric;
        if ($origin !== null && $origin !== '' && $origin !== $season) {
            $number .= ' (' . $origin . ')';
        }

        return trim($number);
    }

    /** Referee qualification with its level, e.g. « N-B ». */
    public static function referee(?string $qualification, ?string $level): string
    {
        $qualification = (string) $qualification;

        return $level !== null && $level !== '' && $qualification !== '' ? $qualification . '-' . $level : $qualification;
    }

    public static function lastName(?string $name): string
    {
        return mb_strtoupper((string) $name);
    }

    public static function firstName(?string $name): string
    {
        return mb_convert_case(mb_strtolower((string) $name), MB_CASE_TITLE, 'UTF-8');
    }

    /** Players are under 21 when born after the year « season − 22 » (admin/FeuillePresenceU21.php). */
    public static function isUnder21(?string $birthDate, string $season): bool
    {
        $year = (int) substr((string) $birthDate, 0, 4);

        return $year > 0 && $year > (int) $season - 22;
    }
}
