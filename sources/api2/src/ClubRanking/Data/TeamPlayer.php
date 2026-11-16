<?php

namespace App\ClubRanking\Data;

/**
 * A licensee linked to a team entry: on its roster (composition) and/or on the sheet of at least one of its validated
 * games (`played`). Coaches never count (§ 5.5).
 */
final class TeamPlayer
{
    public function __construct(
        public readonly int $teamEntryId,
        public readonly int $matric,
        public readonly bool $played,
        public readonly bool $coach = false,
        public readonly bool $onRoster = true,
    ) {
    }
}
