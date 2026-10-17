<?php

namespace App\PublicResults\Scope;

/** Tournoi (kp_evenement) : ses journées sont listées dans kp_evenement_journee. */
final class TournamentScope implements ResultsScope
{
    public function __construct(private readonly int $eventId)
    {
    }

    public function join(): string
    {
        return 'INNER JOIN kp_evenement_journee ej ON (j.Id = ej.Id_journee)';
    }

    public function condition(): string
    {
        return 'ej.Id_evenement = ?';
    }

    public function parameters(): array
    {
        return [$this->eventId];
    }

    public function isSingleGameday(): bool
    {
        return false;
    }
}
