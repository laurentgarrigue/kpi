<?php

namespace App\ClubRanking\Data;

/**
 * A team engaged in one competition (kp_competition_equipe). The same team (`teamNumber`, kp_equipe) is engaged in
 * several competitions of a category (preliminary rounds, phases): it counts once per category (§ 5.4).
 */
final class TeamEntry
{
    public function __construct(
        public readonly int $id,
        public readonly string $competition,
        public readonly int $teamNumber,
        public readonly string $clubCode,
        public readonly string $label,
        public readonly int $rank = 0,
        public readonly int $levelRank = 0,
        public readonly int $validatedGames = 0,
    ) {
    }

    /** Published place: championship ranking, or level ranking of a cup / tournament (§ 5.6). */
    public function place(Competition $competition): int
    {
        return $competition->isChampionship() ? $this->rank : $this->levelRank;
    }
}
