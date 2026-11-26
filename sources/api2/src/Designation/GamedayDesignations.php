<?php

namespace App\Designation;

/**
 * Une journée créée, dupliquée ou (dés)associée à un événement hérite autrement des Désignations (D9) : on recopie
 * les colonnes legacy (DES-10, DES-11) et on recalcule les mandats qui la couvrent (D10, MAN-06).
 */
final class GamedayDesignations
{
    public function __construct(
        private readonly DesignationRepository $repository,
        private readonly LegacyColumnsSync $legacyColumns,
        private readonly GeneratedMandates $mandates,
    ) {
    }

    public function afterGamedayChange(int $gamedayId, ?int $leftEventId = null): void
    {
        $this->legacyColumns->afterGamedayChange($gamedayId, $leftEventId);
        $gameday = $this->repository->gameday($gamedayId);
        if ($gameday !== null) {
            $this->mandates->afterGamedayChange($gameday, $leftEventId);
        }
    }
}
