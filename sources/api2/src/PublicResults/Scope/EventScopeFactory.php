<?php

namespace App\PublicResults\Scope;

/**
 * Le même identifiant d'« événement » d'app2 désigne deux choses selon sa valeur (contrat historique) :
 * en dessous de 3000 un tournoi (kp_evenement), au-delà une journée de championnat (kp_journee).
 */
final class EventScopeFactory
{
    public const FIRST_GAMEDAY_ID = 3000;

    public static function fromEventId(int $eventId): ResultsScope
    {
        return $eventId < self::FIRST_GAMEDAY_ID ? new TournamentScope($eventId) : new GamedayScope($eventId);
    }
}
