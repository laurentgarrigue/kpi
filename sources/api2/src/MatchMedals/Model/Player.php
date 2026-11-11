<?php

namespace App\MatchMedals\Model;

/** The player credited with a fact. A coach (Capitaine = 'E') never gets a player medal. */
final readonly class Player
{
    public function __construct(public int $matric, public bool $isCoach = false)
    {
    }
}
