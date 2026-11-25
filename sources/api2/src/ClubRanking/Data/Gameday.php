<?php

namespace App\ClubRanking\Data;

/** A gameday (kp_journee): organiser, dates and place to group events (§ 5.7), delegate and chief referee (7.3.1). */
final class Gameday
{
    public function __construct(
        public readonly int $id,
        public readonly string $competition,
        public readonly ?string $start,
        public readonly ?string $end,
        public readonly string $place,
        public readonly string $organiserCode,
        public readonly string $organiserLabel = '',
        public readonly string $delegate = '',
        public readonly string $chiefReferee = '',
    ) {
    }

    public function lastDay(): ?string
    {
        return $this->end ?? $this->start;
    }
}
