<?php

namespace App\Discipline;

/** Quand une règle se redéclenche une fois son seuil atteint (§ 2). */
enum Repeat: string
{
    /** Au seuil, puis à chaque multiple du seuil (8e, 16e… carton vert). */
    case EveryMultiple = 'EVERY_MULTIPLE';
    /** Au seuil, puis à chaque carton en plus. */
    case EveryCardAfter = 'EVERY_CARD_AFTER';

    public function fires(int $tally, int $threshold): bool
    {
        return match ($this) {
            self::EveryMultiple => $tally % $threshold === 0,
            self::EveryCardAfter => $tally >= $threshold,
        };
    }
}
