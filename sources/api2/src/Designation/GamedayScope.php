<?php

namespace App\Designation;

use App\Competition\CompetitionLevel;

/** Ce qu'il faut savoir d'une journée pour lui appliquer les Désignations (D9) : saison, compétition, événements. */
final class GamedayScope
{
    /** @param list<int> $eventIds événements auxquels la journée est associée (kp_evenement_journee) */
    public function __construct(
        public readonly int $gamedayId,
        public readonly string $season,
        public readonly string $competitionCode,
        public readonly array $eventIds,
    ) {
    }

    public function isNational(): bool
    {
        return CompetitionLevel::isNational($this->competitionCode);
    }

    /** La Désignation porte-t-elle sur cette journée, à quelque niveau que ce soit ? */
    public function covers(Designation $designation): bool
    {
        if ($designation->season !== $this->season) {
            return false;
        }

        return match ($designation->scopeType) {
            ScopeType::Journee => $designation->scopeId === (string) $this->gamedayId,
            ScopeType::Competition => $designation->scopeId === $this->competitionCode,
            ScopeType::Evenement => in_array((int) $designation->scopeId, $this->eventIds, true),
            ScopeType::Niveau => $designation->scopeId === Designation::NATIONAL_LEVEL && $this->isNational(),
            ScopeType::Equipe => false,
        };
    }
}
