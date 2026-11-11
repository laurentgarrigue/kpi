<?php

namespace App\MatchMedals\Model;

/** Numerical inferiority opened by a card (§ 5.3): [start, end[ on the game axis, end = PHP_INT_MAX when permanent. */
final class Exclusion
{
    public bool $isKilled = true;

    public function __construct(public readonly int $start, public int $end, public readonly bool $isPermanent)
    {
    }

    public function isActiveAt(int $seconds): bool
    {
        return $this->start <= $seconds && $seconds < $this->end;
    }
}
