<?php

namespace App\ClubRanking\Data;

/** Referee qualification of a licensee (kp_arbitre: `arbitre` Reg, Nat, Int, OTM, JO and `niveau` A, B, C, S). */
final class Referee
{
    public function __construct(
        public readonly int $matric,
        public readonly string $qualification,
        public readonly string $level,
        public readonly int $nationalGames = 0,
    ) {
    }
}
