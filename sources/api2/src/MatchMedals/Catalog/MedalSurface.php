<?php

namespace App\MatchMedals\Catalog;

/** Where an element of the catalogue is shown (§ 3.1): app2, app3, either of them (explanation page), or the admin. */
enum MedalSurface: string
{
    case App2 = 'app2';
    case App3 = 'app3';
    /** Published on app2 OR app3: the explanation page must explain everything a visitor can see. */
    case Public = 'public';
    /** Every active element, published or not (admin, profiles 1 and 2). */
    case Admin = 'admin';

    /** Public query parameter; unknown → null (400 for the caller). */
    public static function fromPublicParameter(?string $value, self $default = self::App3): ?self
    {
        if ($value === null || $value === '') {
            return $default;
        }
        $surface = self::tryFrom($value);

        return $surface === self::Admin ? null : $surface;
    }
}
