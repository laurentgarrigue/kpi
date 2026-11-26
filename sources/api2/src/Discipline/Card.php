<?php

namespace App\Discipline;

/** Un carton de la timeline d'un match validé, porté sur une licence (S5). */
final class Card
{
    private const TOURNAMENT = 'CP';

    public function __construct(
        public readonly string $id,
        public readonly int $matric,
        public readonly CardColor $color,
        public readonly int $matchId,
        public readonly string $gamedayKey,
        public readonly int $section,
    ) {
    }

    /**
     * Journée sportive d'un carton (S6) : la journée, sauf les phases d'un tournoi (CP) de mêmes dates et lieu, qui
     * n'en font qu'une (comme SUI-07 des Désignations).
     */
    public static function gamedayKey(int $gamedayId, string $competition, ?string $rankingType, ?string $start, ?string $end, ?string $place): string
    {
        return $rankingType === self::TOURNAMENT
            ? sprintf('CP|%s|%s|%s|%s', $competition, $start, $end, $place)
            : 'J|' . $gamedayId;
    }
}
