<?php

namespace App\PublicSite;

/**
 * Public des rendez-vous de l'accueil (API_PUBLIC_TRANSVERSE.md § 3.7) : le site (app3) retient les événements
 * publiés, app2 ceux qu'il sait ouvrir (`app = 'O'`) et les groupes de ses sections publiques.
 */
enum FixtureScope: string
{
    case Site = 'site';
    case App = 'app';

    /** Colonne de kp_evenement qui retient un événement (valeur 'O'). */
    public function eventFlagColumn(): string
    {
        return match ($this) {
            self::Site => 'Publication',
            self::App => 'app',
        };
    }

    /** Section maximale (exclue) des groupes retenus, `null` sans limite. */
    public function maxSection(): ?int
    {
        return match ($this) {
            self::Site => null,
            self::App => GroupSections::PUBLIC_MAX,
        };
    }
}
